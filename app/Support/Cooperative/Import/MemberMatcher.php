<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeIdentityClass;
use App\Enums\CooperativeMatchMethod;
use App\Models\Member;
use Illuminate\Support\Collection;

final class MemberMatcher
{
    public function __construct(
        private readonly PhoneNormalizer $phones = new PhoneNormalizer,
    ) {}

    /**
     * Conservative match. Never auto-binds by name alone. Never writes NIK.
     *
     * @param  array{
     *     identity_class?: ?string,
     *     identity_value?: ?string,
     *     email?: ?string,
     *     phone_normalized?: ?string,
     *     name?: ?string
     * }  $normalized
     * @return array{
     *     member_id: ?int,
     *     method: CooperativeMatchMethod,
     *     candidate_ids: list<int>
     * }
     */
    public function match(array $normalized): array
    {
        $identifierHits = $this->identifierHits($normalized['identity_class'] ?? null, $normalized['identity_value'] ?? null);
        $emailHits = $this->emailHits($normalized['email'] ?? null);
        $phoneHits = $this->phoneHits($normalized['phone_normalized'] ?? null);
        $nameHits = $this->nameHits($normalized['name'] ?? null);

        $ranked = [
            [CooperativeMatchMethod::MemberIdentifierExact, $identifierHits],
            [CooperativeMatchMethod::EmailExact, $emailHits],
            [CooperativeMatchMethod::PhoneExact, $phoneHits],
        ];

        $uniqueBindings = [];

        foreach ($ranked as [$method, $hits]) {
            if ($hits->count() === 1) {
                $uniqueBindings[$method->value] = (int) $hits->first();
            }

            if ($hits->count() > 1) {
                return [
                    'member_id' => null,
                    'method' => CooperativeMatchMethod::Ambiguous,
                    'candidate_ids' => $hits->values()->all(),
                ];
            }
        }

        $uniqueMemberIds = array_values(array_unique(array_values($uniqueBindings)));

        if (count($uniqueMemberIds) > 1) {
            return [
                'member_id' => null,
                'method' => CooperativeMatchMethod::Ambiguous,
                'candidate_ids' => $uniqueMemberIds,
            ];
        }

        if (count($uniqueMemberIds) === 1) {
            $boundId = $uniqueMemberIds[0];

            if ($nameHits->isNotEmpty() && ! $nameHits->contains($boundId)) {
                return [
                    'member_id' => null,
                    'method' => CooperativeMatchMethod::Ambiguous,
                    'candidate_ids' => array_values(array_unique([...$uniqueMemberIds, ...$nameHits->all()])),
                ];
            }

            $method = CooperativeMatchMethod::from(array_key_first($uniqueBindings));

            return [
                'member_id' => $boundId,
                'method' => $method,
                'candidate_ids' => [$boundId],
            ];
        }

        if ($nameHits->count() === 1 && $this->nameHasSupportingMatch(
            (int) $nameHits->first(),
            $identifierHits,
            $emailHits,
            $phoneHits,
        )) {
            return [
                'member_id' => (int) $nameHits->first(),
                'method' => CooperativeMatchMethod::NameWithSupport,
                'candidate_ids' => [(int) $nameHits->first()],
            ];
        }

        if ($nameHits->count() > 1) {
            return [
                'member_id' => null,
                'method' => CooperativeMatchMethod::Ambiguous,
                'candidate_ids' => $nameHits->values()->all(),
            ];
        }

        if ($nameHits->count() === 1) {
            return [
                'member_id' => null,
                'method' => CooperativeMatchMethod::Unmatched,
                'candidate_ids' => [(int) $nameHits->first()],
            ];
        }

        return [
            'member_id' => null,
            'method' => CooperativeMatchMethod::Unmatched,
            'candidate_ids' => [],
        ];
    }

    /**
     * @param  Collection<int, int>  $identifierHits
     * @param  Collection<int, int>  $emailHits
     * @param  Collection<int, int>  $phoneHits
     */
    private function nameHasSupportingMatch(
        int $memberId,
        Collection $identifierHits,
        Collection $emailHits,
        Collection $phoneHits,
    ): bool {
        return $identifierHits->contains($memberId)
            || $emailHits->contains($memberId)
            || $phoneHits->contains($memberId);
    }

    /**
     * @return Collection<int, int>
     */
    private function identifierHits(?string $class, ?string $value): Collection
    {
        if ($class !== CooperativeIdentityClass::Ab2tiIdentifier->value || $value === null || $value === '') {
            return collect();
        }

        return Member::query()
            ->whereRaw('lower(membership_number) = ?', [mb_strtolower($value)])
            ->pluck('id');
    }

    /**
     * @return Collection<int, int>
     */
    private function emailHits(?string $email): Collection
    {
        if ($email === null || $email === '') {
            return collect();
        }

        return Member::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->pluck('id');
    }

    /**
     * @return Collection<int, int>
     */
    private function phoneHits(?string $normalized): Collection
    {
        if ($normalized === null || $normalized === '') {
            return collect();
        }

        return Member::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->filter(function (Member $member) use ($normalized): bool {
                $parsed = $this->phones->normalize($member->phone);

                return $parsed['parseable'] && $parsed['normalized'] === $normalized;
            })
            ->pluck('id')
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function nameHits(?string $name): Collection
    {
        if ($name === null || trim($name) === '') {
            return collect();
        }

        return Member::query()
            ->whereRaw('lower(legal_name) = ?', [mb_strtolower(trim($name))])
            ->pluck('id');
    }
}
