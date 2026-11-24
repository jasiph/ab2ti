<?php

namespace App\Http\Requests\Cooperative;

use App\Models\CooperativeImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class StoreCooperativeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CooperativeImportBatch::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('cooperative.imports_max_kilobytes', 10240),
                'extensions:csv,xlsx',
            ],
        ];
    }
}
