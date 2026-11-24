<?php

namespace App\Http\Requests\Cooperative;

use App\Enums\CooperativeImportReviewOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveImportPsnReviewRequest extends FormRequest
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
            'units' => ['nullable', 'integer', 'min:0'],
            'share_reference' => ['nullable', 'string', 'max:100'],
            'administrative_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
