<?php

namespace App\Http\Requests\Cooperative;

use App\Models\CooperativeImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class SelectCooperativeImportWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CooperativeImportBatch $batch */
        $batch = $this->route('batch');

        return $this->user()?->can('update', $batch) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'worksheet_name' => ['required', 'string', 'max:64'],
        ];
    }
}
