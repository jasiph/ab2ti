<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class RequestMembershipApplicationRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestRevision', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revision_reason' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'revision_reason' => 'instruksi revisi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'revision_reason.required' => 'Tuliskan instruksi revisi yang dapat dipahami pemohon.',
            'revision_reason.min' => 'Instruksi revisi terlalu singkat. Jelaskan perbaikan yang diminta.',
        ];
    }
}
