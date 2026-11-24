<?php

namespace App\Http\Requests\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\CooperativeMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativeMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $membership = $this->route('membership');

        return $membership instanceof CooperativeMembership
            && ($this->user()?->can('update', $membership) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CooperativeMembershipStatus::class)],
            'joined_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'status',
            'joined_at' => 'tanggal bergabung',
            'ended_at' => 'tanggal berakhir',
            'notes' => 'catatan',
        ];
    }

    /**
     * @return array{
     *     status: CooperativeMembershipStatus,
     *     joined_at: ?string,
     *     ended_at: ?string,
     *     notes: ?string
     * }
     */
    public function membershipPayload(): array
    {
        return [
            'status' => CooperativeMembershipStatus::from($this->validated('status')),
            'joined_at' => $this->validated('joined_at'),
            'ended_at' => $this->validated('ended_at'),
            'notes' => $this->validated('notes'),
        ];
    }
}
