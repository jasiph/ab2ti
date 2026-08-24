<?php

namespace App\Http\Requests\Lapak;

use App\Models\Lapak;
use App\Support\Lapak\LapakSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLapakRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lapak = $this->route('lapak');

        return $lapak instanceof Lapak
            && ($this->user()?->can('update', $lapak) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $lapak = $this->route('lapak');
        $ignoreId = $lapak instanceof Lapak ? $lapak->id : null;
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = LapakSlug::fromName((string) $this->input('name', ''), $ignoreId);
        } else {
            $slug = LapakSlug::unique($slug, $ignoreId);
        }

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lapak = $this->route('lapak');
        $ignoreId = $lapak instanceof Lapak ? $lapak->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('lapaks', 'slug')->ignore($ignoreId),
            ],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'existing_cover_asset_id' => ['nullable', 'integer', 'exists:files,id'],
            'remove_cover' => ['sometimes', 'boolean'],
            'new_cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'name',
            'slug',
            'summary',
            'description',
            'location_text',
            'contact_phone',
            'contact_whatsapp',
            'contact_email',
        ]);
    }
}
