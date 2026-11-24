<?php

namespace App\Http\Requests\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\CooperativeMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativeMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CooperativeMembership::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id'),
                Rule::unique('cooperative_memberships', 'member_id'),
            ],
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
            'member_id' => 'anggota AB2TI',
            'status' => 'status',
            'joined_at' => 'tanggal bergabung',
            'ended_at' => 'tanggal berakhir',
            'notes' => 'catatan',
        ];
    }

    /**
     * @return array{
     *     member_id: int,
     *     status: CooperativeMembershipStatus,
     *     joined_at: ?string,
     *     ended_at: ?string,
     *     notes: ?string
     * }
     */
    public function membershipPayload(): array
    {
        return [
            'member_id' => (int) $this->validated('member_id'),
            'status' => CooperativeMembershipStatus::from($this->validated('status')),
            'joined_at' => $this->validated('joined_at'),
            'ended_at' => $this->validated('ended_at'),
            'notes' => $this->validated('notes'),
        ];
    }
}
