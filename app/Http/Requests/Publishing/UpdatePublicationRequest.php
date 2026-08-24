<?php

namespace App\Http\Requests\Publishing;

use App\Enums\PublicationType;
use App\Models\Publication;
use App\Support\Publishing\PublicationSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $publication = $this->route('publication');

        return $publication instanceof Publication
            && ($this->user()?->can('update', $publication) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $publication = $this->route('publication');
        $ignoreId = $publication instanceof Publication ? $publication->id : null;
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = PublicationSlug::fromTitle((string) $this->input('title', ''), $ignoreId);
        } else {
            $slug = PublicationSlug::unique($slug, $ignoreId);
        }

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $publication = $this->route('publication');
        $ignoreId = $publication instanceof Publication ? $publication->id : null;

        return [
            'type' => ['required', Rule::enum(PublicationType::class)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('publications', 'slug')->ignore($ignoreId),
            ],
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
