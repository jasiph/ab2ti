<?php

namespace App\Actions\Membership;

use App\Enums\CooperativeIdentityClass;
use App\Enums\CooperativeMatchMethod;
use App\Enums\MemberSourceType;
use App\Enums\MemberStatus;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\Member;
use App\Models\User;
use App\Support\Cooperative\Import\MemberMatcher;
use App\Support\Membership\MembershipNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class BootstrapLegacyMember
{
    public function __construct(
        private readonly MemberMatcher $matcher = new MemberMatcher,
    ) {}

    /**
     * Create a historical AB2TI Member from an authorized Cooperative import.
     * Does not create a User or a MembershipApplication.
     * Does not invent placeholder contact values.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(
        CooperativeImportRow $row,
        CooperativeImportBatch $batch,
        User $actor,
        array $data,
    ): Member {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Legacy Member bootstrap requires a usable name.');
        }

        $email = $this->nullableEmail($data['email'] ?? null);
        $phone = $this->nullableText($data['phone_normalized'] ?? $data['phone_raw'] ?? null);
        $address = $this->nullableText($data['address'] ?? null);

        $lock = Cache::lock('cooperative-legacy-member:'.$this->lockKey($email, $data, $row), 10);

        try {
            $lock->block(8);

            $preserveDistinct = ($data['preserve_source_duplicate'] ?? false) === true;

            if (! $preserveDistinct) {
                $existing = $this->lockAndResolve($data, $email);

                if ($existing !== null) {
                    return $existing;
                }
            }

            try {
                $member = new Member;
                $member->membership_number = $this->membershipNumber($data, $preserveDistinct);
                $member->source_application_id = null;
                $member->source_type = MemberSourceType::CooperativeLegacyImport;
                $member->status = MemberStatus::Active;
                $member->legal_name = $name;
                $member->email = $email;
                $member->phone = $phone;
                $member->address = $address;
                $member->recognized_at = $data['effective_date_start'] ?? now();
                $member->card_issued = false;
                $member->created_by = $actor->id;
                $member->save();
            } catch (UniqueConstraintViolationException) {
                if ($preserveDistinct) {
                    $member = new Member;
                    $member->membership_number = MembershipNumberGenerator::generate();
                    $member->source_application_id = null;
                    $member->source_type = MemberSourceType::CooperativeLegacyImport;
                    $member->status = MemberStatus::Active;
                    $member->legal_name = $name;
                    $member->email = null;
                    $member->phone = $phone;
                    $member->address = $address;
                    $member->recognized_at = $data['effective_date_start'] ?? now();
                    $member->card_issued = false;
                    $member->created_by = $actor->id;
                    $member->save();
                } else {
                    $existing = $this->lockAndResolve($data, $email);

                    if ($existing === null) {
                        throw new RuntimeException('Legacy Member bootstrap conflicted with an existing identity.');
                    }

                    return $existing;
                }
            }

            Log::info('cooperative legacy member bootstrapped', [
                'member_id' => $member->id,
                'import_batch_id' => $batch->id,
                'legacy_source_row_id' => $row->id,
                'worksheet_name' => $batch->worksheet_name,
                'source_row_number' => $row->source_row_number,
                'operator_id' => $actor->id,
                'resolution' => 'proposed_legacy_create',
                'source_type' => MemberSourceType::CooperativeLegacyImport->value,
            ]);

            return $member;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockAndResolve(array $data, ?string $email): ?Member
    {
        if ($email !== null) {
            Member::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->lockForUpdate()
                ->get();
        }

        $identifier = $this->historicalMembershipNumber($data);
        if ($identifier !== null) {
            Member::query()
                ->whereRaw('lower(membership_number) = ?', [mb_strtolower($identifier)])
                ->lockForUpdate()
                ->get();
        }

        $match = $this->matcher->match([
            'identity_class' => $data['identity_class'] ?? null,
            'identity_value' => $data['identity_value'] ?? null,
            'email' => $email,
            'phone_normalized' => $data['phone_normalized'] ?? null,
            'name' => $data['name'] ?? null,
        ]);

        if ($match['method'] === CooperativeMatchMethod::Ambiguous || ($match['member_id'] === null && $match['candidate_ids'] !== [])) {
            throw new RuntimeException('Member identity is ambiguous at commit time.');
        }

        if ($match['member_id'] === null) {
            return null;
        }

        return Member::query()
            ->whereKey($match['member_id'])
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function membershipNumber(array $data, bool $preserveDistinct = false): string
    {
        $historical = $this->historicalMembershipNumber($data);
        if ($historical !== null) {
            $taken = Member::query()
                ->whereRaw('lower(membership_number) = ?', [mb_strtolower($historical)])
                ->exists();

            if ($taken) {
                if ($preserveDistinct) {
                    return MembershipNumberGenerator::generate();
                }

                throw new RuntimeException('Historical AB2TI identifier already belongs to another Member.');
            }

            return $historical;
        }

        return MembershipNumberGenerator::generate();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function historicalMembershipNumber(array $data): ?string
    {
        if (($data['identity_class'] ?? null) !== CooperativeIdentityClass::Ab2tiIdentifier->value) {
            return null;
        }

        $value = trim((string) ($data['identity_value'] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lockKey(?string $email, array $data, CooperativeImportRow $row): string
    {
        if ($email !== null) {
            return 'email:'.sha1($email);
        }

        $historical = $this->historicalMembershipNumber($data);
        if ($historical !== null) {
            return 'id:'.sha1(mb_strtolower($historical));
        }

        return 'row:'.$row->id;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $text = $this->nullableText($value);

        if ($text === null || ! filter_var($text, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return mb_strtolower($text);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }
}
