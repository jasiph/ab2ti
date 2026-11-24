<?php

namespace App\Http\Requests\Cooperative;

use App\Enums\PsnAdjustmentReason;
use App\Models\CooperativeMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustPsnParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CooperativeMembership $membership */
        $membership = $this->route('membership');

        return $this->user()?->can('update', $membership) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = now('Asia/Jakarta')->toDateString();

        return [
            'desired_units' => ['required'],
            'expected_current_units' => ['required'],
            'confirmation_token' => ['required', 'uuid'],
            'effective_date' => ['required', 'date', 'before_or_equal:'.$today],
            'reason' => ['required', Rule::enum(PsnAdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'share_reference' => ['nullable', 'string', 'max:100'],
            'confirm' => ['required', 'accepted'],
            'acknowledge_reduction' => ['nullable', 'boolean'],
            'correction_of_entry_id' => ['nullable', 'integer', 'min:1'],
            'membership_id' => ['nullable', 'integer'],
            'participation_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->isNonNegativeInteger($this->input('desired_units'))) {
                $validator->errors()->add('desired_units', __('Desired PSN units must be a whole number of 0 or more.'));
            }

            if (! $this->isNonNegativeInteger($this->input('expected_current_units'))) {
                $validator->errors()->add('expected_current_units', __('Expected current units must be a whole number of 0 or more.'));
            }

            $note = trim((string) $this->input('note', ''));
            $reason = (string) $this->input('reason', '');

            if ($reason === PsnAdjustmentReason::Other->value && $note === '') {
                $validator->errors()->add('note', __('Other reason requires an administrative note.'));
            }

            if (in_array($reason, [
                PsnAdjustmentReason::AdministrativeCorrection->value,
                PsnAdjustmentReason::CorrectionFromDocument->value,
            ], true) && $note === '') {
                $validator->errors()->add('note', __('Correction adjustments require an administrative note.'));
            }

            if (
                $this->isNonNegativeInteger($this->input('desired_units'))
                && $this->isNonNegativeInteger($this->input('expected_current_units'))
            ) {
                $desired = (int) $this->input('desired_units');
                $expected = (int) $this->input('expected_current_units');

                if ($desired < $expected) {
                    if ($note === '') {
                        $validator->errors()->add('note', __('Share reductions require an administrative note.'));
                    }

                    if (! $this->boolean('acknowledge_reduction')) {
                        $validator->errors()->add(
                            'acknowledge_reduction',
                            __('Confirm that PSN units will decrease.'),
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array{
     *     desired_units: int,
     *     expected_current_units: int,
     *     confirmation_token: string,
     *     effective_date: string,
     *     reason: string,
     *     note: ?string,
     *     share_reference: ?string,
     *     correction_of_entry_id: ?int,
     *     membership_id: ?int,
     *     participation_id: ?int,
     *     member_id: ?int
     * }
     */
    public function adjustmentPayload(): array
    {
        $validated = $this->validated();

        return [
            'desired_units' => (int) $validated['desired_units'],
            'expected_current_units' => (int) $validated['expected_current_units'],
            'confirmation_token' => strtolower((string) $validated['confirmation_token']),
            'effective_date' => (string) $validated['effective_date'],
            'reason' => (string) $validated['reason'],
            'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
            'share_reference' => $validated['share_reference'] ?? null,
            'correction_of_entry_id' => isset($validated['correction_of_entry_id'])
                ? (int) $validated['correction_of_entry_id']
                : null,
            'membership_id' => isset($validated['membership_id']) ? (int) $validated['membership_id'] : null,
            'participation_id' => isset($validated['participation_id']) ? (int) $validated['participation_id'] : null,
            'member_id' => isset($validated['member_id']) ? (int) $validated['member_id'] : null,
        ];
    }

    private function isNonNegativeInteger(mixed $value): bool
    {
        if (is_int($value) && $value >= 0) {
            return true;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return true;
        }

        return false;
    }
}
