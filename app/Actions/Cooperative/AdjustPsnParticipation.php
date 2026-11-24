<?php

namespace App\Actions\Cooperative;

use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\PsnAdjustmentReason;
use App\Exceptions\Cooperative\PsnAdjustmentRejectedException;
use App\Exceptions\Cooperative\StalePsnConfirmationException;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeMembership;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\FinancialInvariants;
use App\Support\Cooperative\PsnAdjustmentEligibility;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdjustPsnParticipation
{
    /**
     * Translate a desired total into an auditable ledger adjustment.
     * Never overwrites opening or prior entries. Frontend current/delta/value are ignored.
     *
     * @param  array{
     *     desired_units: int,
     *     expected_current_units: int,
     *     confirmation_token: string,
     *     effective_date: string,
     *     reason: string,
     *     note?: ?string,
     *     share_reference?: ?string,
     *     correction_of_entry_id?: ?int,
     *     membership_id?: ?int,
     *     participation_id?: ?int,
     *     member_id?: ?int
     * }  $payload
     */
    public function handle(
        CooperativeMembership $membership,
        User $operator,
        array $payload,
    ): ParticipationEntry {
        return DB::transaction(function () use ($membership, $operator, $payload) {
            $membership = CooperativeMembership::query()
                ->whereKey($membership->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanonicalResources($membership, $payload);

            if (! $membership->isActive()) {
                throw PsnAdjustmentRejectedException::inactiveMembership();
            }

            if (PsnAdjustmentEligibility::hasUnresolvedPsn($membership)) {
                throw PsnAdjustmentRejectedException::unresolvedPsn();
            }

            $token = $this->requireConfirmationToken($payload);
            $existing = $this->existingEntryForToken($token);
            if ($existing !== null) {
                return $this->replayOrReject($existing, $membership, $payload);
            }

            $desired = $this->requireNonNegativeInteger($payload['desired_units'] ?? null, 'Desired units');
            $expectedCurrent = $this->requireNonNegativeInteger($payload['expected_current_units'] ?? null, 'Expected current units');

            $reason = PsnAdjustmentReason::tryFrom((string) ($payload['reason'] ?? ''));
            if ($reason === null) {
                throw new InvalidArgumentException('A valid adjustment reason is required.');
            }

            $note = isset($payload['note']) ? trim((string) $payload['note']) : '';
            if ($reason === PsnAdjustmentReason::Other && $note === '') {
                throw new InvalidArgumentException('Other reason requires an administrative note.');
            }

            $effectiveDate = $this->validatedEffectiveDate((string) ($payload['effective_date'] ?? ''));

            $business = CooperativeBusiness::psn();
            $participation = $this->lockPsnParticipation($membership, $business);

            $this->assertOptionalParticipationId($participation, $payload);

            $currentUnits = $participation->ledgerUnitsSum();

            if ($currentUnits !== $expectedCurrent) {
                throw new StalePsnConfirmationException($expectedCurrent, $currentUnits);
            }

            $delta = $desired - $currentUnits;

            if ($delta === 0) {
                throw PsnAdjustmentRejectedException::noChange();
            }

            if ($delta < 0 && $note === '') {
                throw new InvalidArgumentException('Share reductions require an administrative note.');
            }

            if (in_array($reason, [
                PsnAdjustmentReason::AdministrativeCorrection,
                PsnAdjustmentReason::CorrectionFromDocument,
            ], true) && $note === '') {
                throw new InvalidArgumentException('Correction adjustments require an administrative note.');
            }

            $correctionOfId = $this->validatedCorrectionTarget($participation, $payload['correction_of_entry_id'] ?? null);
            $origin = $this->origin($reason, $correctionOfId);

            $unitValue = (int) $business->current_unit_value;
            $amount = $delta * $unitValue;
            $reasonText = $reason->value.($note !== '' ? ': '.$note : '');

            FinancialInvariants::assertAdjustment($delta, $unitValue, $amount, $reasonText);
            FinancialInvariants::assertNonNegativeResultingUnits($currentUnits, $delta);

            $shareReference = isset($payload['share_reference'])
                ? trim((string) $payload['share_reference'])
                : null;

            $resultingUnits = $currentUnits + $delta;

            try {
                $entry = ParticipationEntry::query()->create([
                    'business_participation_id' => $participation->id,
                    'entry_type' => ParticipationEntryType::Adjustment,
                    'units' => $delta,
                    'unit_value' => $unitValue,
                    'amount' => $amount,
                    'effective_date_start' => $effectiveDate,
                    'effective_date_end' => null,
                    'share_reference' => $shareReference !== '' ? $shareReference : $participation->latestShareReference(),
                    'units_origin' => null,
                    'source_type' => CooperativeSourceType::Staff,
                    'import_batch_id' => null,
                    'legacy_source_row_id' => null,
                    'reverses_entry_id' => null,
                    'notes' => $reasonText,
                    'confirmation_token' => $token,
                    'provenance' => [
                        'adjustment' => 'administrative',
                        'origin' => $origin,
                        'reason_code' => $reason->value,
                        'administrative_note' => $note !== '' ? $note : null,
                        'previous_units' => $currentUnits,
                        'desired_units' => $desired,
                        'delta_units' => $delta,
                        'resulting_units' => $resultingUnits,
                        'unit_value' => $unitValue,
                        'operator_id' => $operator->id,
                        'adjusted_at' => now()->toIso8601String(),
                        'effective_date' => $effectiveDate,
                        'membership_id' => $membership->id,
                        'member_id' => $membership->member_id,
                        'expected_current_units' => $expectedCurrent,
                        'confirmation_token' => $token,
                        'correction_of' => $correctionOfId,
                    ],
                ]);
            } catch (UniqueConstraintViolationException) {
                $replayed = $this->existingEntryForToken($token);
                if ($replayed === null) {
                    throw PsnAdjustmentRejectedException::tokenReused();
                }

                return $this->replayOrReject($replayed, $membership, $payload);
            }

            $verifiedUnits = $participation->ledgerUnitsSum();
            if ($verifiedUnits !== $resultingUnits || $verifiedUnits < 0) {
                throw new InvalidArgumentException('Resulting PSN ledger position could not be verified.');
            }

            return $entry;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCanonicalResources(CooperativeMembership $membership, array $payload): void
    {
        if (isset($payload['membership_id']) && (int) $payload['membership_id'] !== (int) $membership->id) {
            throw PsnAdjustmentRejectedException::resourceMismatch();
        }

        if (isset($payload['member_id']) && (int) $payload['member_id'] !== (int) $membership->member_id) {
            throw PsnAdjustmentRejectedException::resourceMismatch();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertOptionalParticipationId(BusinessParticipation $participation, array $payload): void
    {
        if (isset($payload['participation_id']) && (int) $payload['participation_id'] !== (int) $participation->id) {
            throw PsnAdjustmentRejectedException::resourceMismatch();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireConfirmationToken(array $payload): string
    {
        $token = trim((string) ($payload['confirmation_token'] ?? ''));
        if ($token === '' || preg_match('/^[0-9a-fA-F-]{36}$/', $token) !== 1) {
            throw new InvalidArgumentException('A confirmation token is required.');
        }

        return strtolower($token);
    }

    private function existingEntryForToken(string $token): ?ParticipationEntry
    {
        return ParticipationEntry::query()
            ->where('confirmation_token', $token)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replayOrReject(
        ParticipationEntry $existing,
        CooperativeMembership $membership,
        array $payload,
    ): ParticipationEntry {
        $existingMembershipId = (int) ($existing->provenance['membership_id'] ?? 0);
        $existingDesired = (int) ($existing->provenance['desired_units'] ?? -1);
        $desired = $this->requireNonNegativeInteger($payload['desired_units'] ?? null, 'Desired units');

        if (
            $existingMembershipId !== (int) $membership->id
            || $existingDesired !== $desired
        ) {
            throw PsnAdjustmentRejectedException::tokenReused();
        }

        return $existing;
    }

    private function requireNonNegativeInteger(mixed $value, string $label): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException($label.' must be a non-negative integer.');
    }

    private function validatedEffectiveDate(string $effectiveDate): string
    {
        if ($effectiveDate === '') {
            throw new InvalidArgumentException('Effective date is required.');
        }

        $today = now('Asia/Jakarta')->toDateString();
        if ($effectiveDate > $today) {
            throw PsnAdjustmentRejectedException::futureEffectiveDate();
        }

        return $effectiveDate;
    }

    private function lockPsnParticipation(
        CooperativeMembership $membership,
        CooperativeBusiness $business,
    ): BusinessParticipation {
        $participation = BusinessParticipation::query()
            ->where('cooperative_membership_id', $membership->id)
            ->where('cooperative_business_id', $business->id)
            ->lockForUpdate()
            ->first();

        if ($participation !== null) {
            return $participation;
        }

        try {
            $participation = BusinessParticipation::query()->create([
                'cooperative_membership_id' => $membership->id,
                'cooperative_business_id' => $business->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return BusinessParticipation::query()
                ->where('cooperative_membership_id', $membership->id)
                ->where('cooperative_business_id', $business->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return BusinessParticipation::query()
            ->whereKey($participation->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function validatedCorrectionTarget(BusinessParticipation $participation, mixed $correctionOf): ?int
    {
        if ($correctionOf === null || $correctionOf === '') {
            return null;
        }

        $id = (int) $correctionOf;
        $target = ParticipationEntry::query()
            ->whereKey($id)
            ->where('business_participation_id', $participation->id)
            ->first();

        if ($target === null) {
            throw PsnAdjustmentRejectedException::invalidCorrectionTarget();
        }

        return $id;
    }

    private function origin(PsnAdjustmentReason $reason, ?int $correctionOfId): string
    {
        if (
            $correctionOfId !== null
            || $reason === PsnAdjustmentReason::AdministrativeCorrection
            || $reason === PsnAdjustmentReason::CorrectionFromDocument
        ) {
            return 'correction';
        }

        if ($reason === PsnAdjustmentReason::ReconciliationFollowup) {
            return 'reconciliation';
        }

        return 'administrative_adjustment';
    }
}
