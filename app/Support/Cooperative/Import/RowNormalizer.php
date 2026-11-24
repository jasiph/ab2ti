<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeIdentityClass;
use App\Enums\CooperativeImportComponentStatus;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMatchMethod;
use App\Enums\CooperativeMemberResolution;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportRow;
use App\Models\Member;

final class RowNormalizer
{
    public function __construct(
        private readonly MoneyParser $money = new MoneyParser,
        private readonly DateRangeParser $dates = new DateRangeParser,
        private readonly ShareReferenceParser $shares = new ShareReferenceParser,
        private readonly IdentityClassifier $identities = new IdentityClassifier,
        private readonly PhoneNormalizer $phones = new PhoneNormalizer,
        private readonly MemberMatcher $matcher = new MemberMatcher,
        private readonly SourceFingerprint $fingerprints = new SourceFingerprint,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *     normalized: array<string, mixed>,
     *     status: CooperativeImportRowStatus,
     *     errors: list<string>,
     *     matched_member_id: ?int,
     *     match_method: CooperativeMatchMethod
     * }
     */
    public function normalize(array $raw, CooperativeBusiness $business): array
    {
        $errors = [];
        $identityReview = [];
        $financialReview = [];
        $dateReview = [];
        $shareReview = [];
        $profileWarnings = [];

        $name = $this->stringOrNull($raw['name'] ?? null);
        $identity = $this->identities->classify($raw['identity'] ?? null);
        $email = $this->emailOrNull($raw['email'] ?? null);
        $phone = $this->phones->normalize($raw['phone'] ?? null);
        $principal = $this->money->parse($raw['principal'] ?? null);
        $voluntary = $this->money->parse($raw['voluntary'] ?? null);
        $total = $this->money->parse($raw['total'] ?? null);
        $share = $this->shares->parse($raw['share'] ?? null);
        $date = $this->dates->parse($raw['date'] ?? null);
        $legacyLabel = $this->stringOrNull($raw['legacy_label'] ?? null);
        $legacyNotes = $this->stringOrNull($raw['legacy_notes'] ?? null);
        $address = $this->stringOrNull($raw['address'] ?? null);

        if ($name === null) {
            $errors[] = 'missing_name';
        }

        if ($email === null && $this->stringOrNull($raw['email'] ?? null) !== null) {
            $profileWarnings[] = 'unparseable_email';
        } elseif ($email === null) {
            $profileWarnings[] = 'missing_email';
        }

        if (! $phone['parseable'] && $this->stringOrNull($raw['phone'] ?? null) !== null) {
            $profileWarnings[] = 'unparseable_phone';
        } elseif (($phone['normalized'] ?? null) === null) {
            $profileWarnings[] = 'missing_phone';
        }

        if ($address === null) {
            $profileWarnings[] = 'missing_address';
        }

        if (! $principal['parseable']) {
            $financialReview[] = 'malformed_principal';
        } elseif (($principal['amount'] ?? 0) < 0) {
            $financialReview[] = 'negative_principal';
        }

        if (! $voluntary['parseable']) {
            $financialReview[] = 'malformed_voluntary';
        } elseif (($voluntary['amount'] ?? 0) < 0) {
            $financialReview[] = 'negative_voluntary';
        }

        if (! $total['parseable']) {
            $financialReview[] = 'malformed_total';
        } elseif (($total['amount'] ?? 0) < 0) {
            $financialReview[] = 'negative_total';
        }

        if (! $share['parseable']) {
            $shareReview[] = 'unknown_share_format';
        }

        if ($date['uncertain']) {
            $dateReview[] = 'malformed_date';
        }

        $principalAmount = $principal['parseable'] ? (int) $principal['amount'] : null;
        $voluntaryAmount = $voluntary['parseable'] ? (int) $voluntary['amount'] : null;
        $sourceTotal = $total['parseable'] && ! $total['blank'] ? (int) $total['amount'] : null;
        $principalProvided = $principal['parseable'] && ! $principal['blank'];
        $voluntaryProvided = $voluntary['parseable'] && ! $voluntary['blank'];
        $computedTotal = ($principalProvided || $voluntaryProvided)
            ? (int) ($principal['amount'] ?? 0) + (int) ($voluntary['amount'] ?? 0)
            : null;

        $totalMatches = true;
        if ($sourceTotal !== null && $computedTotal !== null && $sourceTotal !== $computedTotal) {
            $totalMatches = false;
            $financialReview[] = 'total_mismatch';
        }

        $unitValue = (int) $business->current_unit_value;
        $reconciliationAmount = $sourceTotal ?? $computedTotal;
        $inferredUnits = null;
        $units = null;
        $unitsOrigin = ParticipationUnitsOrigin::None;
        $shareReconciled = true;

        if ($reconciliationAmount !== null && $unitValue > 0) {
            if ($reconciliationAmount % $unitValue !== 0) {
                $shareReconciled = false;
                $shareReview[] = 'amount_not_divisible_by_unit_value';
                if ($share['explicit_units'] === null && ($share['share_reference'] ?? null) !== null) {
                    $shareReview[] = 'non_integral_legacy_total';
                }
            } else {
                $inferredUnits = intdiv($reconciliationAmount, $unitValue);
            }
        }

        if ($share['explicit_units'] !== null) {
            $units = $share['explicit_units'];
            $unitsOrigin = ParticipationUnitsOrigin::Explicit;

            if ($inferredUnits !== null && $inferredUnits !== $units) {
                $shareReconciled = false;
                $shareReview[] = 'share_unit_mismatch';
            }
        } elseif ($inferredUnits !== null) {
            $units = $inferredUnits;
            $unitsOrigin = ParticipationUnitsOrigin::Inferred;
        }

        $match = $this->matcher->match([
            'identity_class' => $identity['class']->value,
            'identity_value' => $identity['value'],
            'email' => $email,
            'phone_normalized' => $phone['normalized'],
            'name' => $name,
        ]);

        $memberResolution = CooperativeMemberResolution::Invalid;
        $proposeCreateMember = false;
        $proposeCreateMembership = false;
        $matchMethod = $match['method'];

        if ($match['method'] === CooperativeMatchMethod::Ambiguous) {
            $identityReview[] = 'ambiguous_member';
            $memberResolution = CooperativeMemberResolution::Ambiguous;
        } elseif ($match['member_id'] !== null) {
            $memberResolution = CooperativeMemberResolution::Existing;
            $member = Member::query()->find($match['member_id']);
            $membership = $member?->cooperativeMembership;
            $proposeCreateMembership = $membership === null;
        } elseif ($match['candidate_ids'] !== []) {
            $identityReview[] = 'name_collision';
            $memberResolution = CooperativeMemberResolution::Ambiguous;
        } elseif ($name !== null) {
            $proposeCreateMember = true;
            $proposeCreateMembership = true;
            $memberResolution = CooperativeMemberResolution::ProposedLegacyCreate;
            $matchMethod = CooperativeMatchMethod::ProposedLegacyCreate;
        } else {
            $memberResolution = CooperativeMemberResolution::Invalid;
        }

        $existingPsn = false;
        $existingFunds = false;

        if ($match['member_id'] !== null) {
            $member = Member::query()->find($match['member_id']);
            $membership = $member?->cooperativeMembership;

            if ($membership !== null) {
                $existingPsn = BusinessParticipation::query()
                    ->where('cooperative_membership_id', $membership->id)
                    ->where('cooperative_business_id', $business->id)
                    ->whereHas('entries')
                    ->exists();

                $existingFunds = CooperativeFundEntry::query()
                    ->where('cooperative_membership_id', $membership->id)
                    ->notReversed()
                    ->exists();
            }
        }

        if ($existingPsn) {
            $shareReview[] = 'existing_psn_participation';
        }

        if ($existingFunds && ($principalAmount > 0 || $voluntaryAmount > 0)) {
            $financialReview[] = 'existing_fund_opening';
        }

        $emptyFinancial = ($principalAmount === 0 || $principalAmount === null)
            && ($voluntaryAmount === 0 || $voluntaryAmount === null)
            && ($sourceTotal === null || $sourceTotal === 0)
            && $share['blank'];

        $normalized = [
            'name' => $name,
            'identity_raw' => $identity['raw'],
            'identity_class' => $identity['class']->value,
            'identity_value' => $identity['value'],
            'email' => $email,
            'phone_raw' => $phone['raw'],
            'phone_normalized' => $phone['normalized'],
            'principal_amount' => $principalAmount,
            'principal_blank' => $principal['blank'],
            'voluntary_amount' => $voluntaryAmount,
            'voluntary_blank' => $voluntary['blank'],
            'source_total' => $sourceTotal,
            'source_total_blank' => $total['blank'],
            'computed_total' => $computedTotal,
            'total_matches' => $totalMatches,
            'share_raw' => $share['raw'],
            'share_reference' => $share['share_reference'],
            'explicit_units' => $share['explicit_units'],
            'inferred_units' => $inferredUnits,
            'units' => $units,
            'units_origin' => $unitsOrigin->value,
            'unit_value' => $unitValue,
            'share_reconciled' => $shareReconciled,
            'date_raw' => $date['raw'],
            'effective_date_start' => $date['start'],
            'effective_date_end' => $date['end'],
            'date_parseable' => $date['parseable'],
            'legacy_label' => $legacyLabel,
            'legacy_notes' => $legacyNotes,
            'address' => $address,
            'propose_create_member' => $proposeCreateMember,
            'propose_create_membership' => $proposeCreateMembership,
            'member_resolution' => $memberResolution->value,
            'candidate_ids' => $match['candidate_ids'],
            'nik_present_not_stored' => $identity['class'] === CooperativeIdentityClass::ValidNik,
            'nik_not_used_for_match' => $identity['class'] === CooperativeIdentityClass::ValidNik,
            'profile_warnings' => array_values(array_unique($profileWarnings)),
        ];

        $fingerprint = $this->fingerprints->fromNormalized($normalized);
        $normalized['source_fingerprint'] = $fingerprint;

        if (CooperativeImportRow::query()
            ->where('source_fingerprint', $fingerprint)
            ->whereNotNull('committed_at')
            ->exists()) {
            $identityReview[] = 'duplicate_committed_source';
        }

        $memberStatus = CooperativeImportComponentStatus::NeedsReview;
        if ($errors !== []) {
            $memberStatus = CooperativeImportComponentStatus::NeedsReview;
            $memberResolution = CooperativeMemberResolution::Invalid;
            $normalized['member_resolution'] = $memberResolution->value;
            $normalized['propose_create_member'] = false;
            $normalized['propose_create_membership'] = false;
        } elseif ($identityReview === [] && $name !== null) {
            $memberStatus = CooperativeImportComponentStatus::Ready;
        }

        $membershipStatus = $memberStatus === CooperativeImportComponentStatus::Ready
            ? CooperativeImportComponentStatus::Ready
            : CooperativeImportComponentStatus::NeedsReview;

        $fundsStatus = CooperativeImportComponentStatus::None;
        if ($financialReview !== []) {
            $fundsStatus = CooperativeImportComponentStatus::NeedsReview;
        } elseif (($principalAmount ?? 0) > 0 || ($voluntaryAmount ?? 0) > 0) {
            $fundsStatus = CooperativeImportComponentStatus::Ready;
        }

        $psnStatus = CooperativeImportComponentStatus::None;
        if ($shareReview !== [] || in_array('total_mismatch', $financialReview, true)) {
            $psnStatus = CooperativeImportComponentStatus::NeedsReview;
        } elseif ($units !== null && $units > 0 && $shareReconciled) {
            $psnStatus = CooperativeImportComponentStatus::Ready;
        }

        if ($emptyFinancial && $psnStatus === CooperativeImportComponentStatus::None) {
            $fundsStatus = CooperativeImportComponentStatus::None;
        }

        $normalized['components'] = [
            'member' => ['status' => $memberStatus->value],
            'cooperative_membership' => ['status' => $membershipStatus->value],
            'funds' => ['status' => $fundsStatus->value],
            'psn' => ['status' => $psnStatus->value],
        ];
        $normalized['review_categories'] = [
            'identity' => array_values(array_unique($identityReview)),
            'financial' => array_values(array_unique($financialReview)),
            'date' => array_values(array_unique($dateReview)),
            'share' => array_values(array_unique($shareReview)),
            'profile' => array_values(array_unique($profileWarnings)),
        ];

        $blocking = array_values(array_unique([
            ...$identityReview,
            ...$financialReview,
            ...$shareReview,
        ]));

        $status = CooperativeImportRowStatus::Ready;
        if ($errors !== []) {
            $status = CooperativeImportRowStatus::Invalid;
        } elseif ($blocking !== []) {
            $status = CooperativeImportRowStatus::NeedsReview;
        }

        return [
            'normalized' => $normalized,
            'status' => $status,
            'errors' => array_values(array_unique([...$errors, ...$blocking, ...$dateReview, ...$profileWarnings])),
            'matched_member_id' => $match['member_id'],
            'match_method' => $matchMethod,
            'source_fingerprint' => $normalized['source_fingerprint'],
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }

    private function emailOrNull(mixed $value): ?string
    {
        $text = $this->stringOrNull($value);

        if ($text === null) {
            return null;
        }

        $email = mb_strtolower($text);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
