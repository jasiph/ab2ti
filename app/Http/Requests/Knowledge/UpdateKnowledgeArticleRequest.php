<?php

namespace App\Http\Requests\Knowledge;

use App\Models\KnowledgeArticle;
use App\Support\Knowledge\KnowledgeArticleSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof KnowledgeArticle
            && ($this->user()?->can('update', $article) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $article = $this->route('article');
        $ignoreId = $article instanceof KnowledgeArticle ? $article->id : null;
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = KnowledgeArticleSlug::fromTitle((string) $this->input('title', ''), $ignoreId);
        } else {
            $slug = KnowledgeArticleSlug::unique($slug, $ignoreId);
        }

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $article = $this->route('article');
        $ignoreId = $article instanceof KnowledgeArticle ? $article->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('knowledge_articles', 'slug')->ignore($ignoreId),
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
