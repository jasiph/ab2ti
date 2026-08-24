<?php

namespace App\Http\Requests\Lapak;

use App\Models\LapakCategory;
use App\Support\Lapak\LapakCategorySlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertLapakCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        if ($category instanceof LapakCategory) {
            return $this->user()?->can('update', $category) ?? false;
        }

        return $this->user()?->can('create', LapakCategory::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $category = $this->route('category');
        $ignoreId = $category instanceof LapakCategory ? $category->id : null;
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = LapakCategorySlug::fromName((string) $this->input('name', ''), $ignoreId);
        } else {
            $slug = LapakCategorySlug::unique($slug, $ignoreId);
        }

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $ignoreId = $category instanceof LapakCategory ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('lapak_categories', 'slug')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
