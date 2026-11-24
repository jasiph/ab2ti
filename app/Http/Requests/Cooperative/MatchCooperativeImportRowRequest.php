<?php

namespace App\Http\Requests\Cooperative;

use App\Models\CooperativeImportBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatchCooperativeImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof CooperativeImportBatch
            && ($this->user()?->can('update', $batch) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
