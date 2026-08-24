<?php

namespace App\Http\Requests\Publishing;

use App\Enums\PublicationType;
use App\Support\Publishing\PublicationSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Publication::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = PublicationSlug::fromTitle((string) $this->input('title', ''));
        } else {
            $slug = PublicationSlug::unique($slug);
        }

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PublicationType::class)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:publications,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'existing_cover_asset_id' => ['nullable', 'integer', 'exists:files,id'],
            'remove_cover' => ['sometimes', 'boolean'],
            'existing_social_asset_id' => ['nullable', 'integer', 'exists:files,id'],
            'remove_social_image' => ['sometimes', 'boolean'],
            'new_cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'new_social_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }
}
