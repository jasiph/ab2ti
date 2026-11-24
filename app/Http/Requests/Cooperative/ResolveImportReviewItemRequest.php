<?php

namespace App\Http\Requests\Cooperative;

use App\Enums\CooperativeImportReviewOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveImportReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $this->user()?->can('update', $batch) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(CooperativeImportReviewOutcome::class)],
            'administrative_note' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
