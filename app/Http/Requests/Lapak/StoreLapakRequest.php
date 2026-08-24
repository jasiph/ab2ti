<?php

namespace App\Http\Requests\Lapak;

use App\Models\Lapak;
use App\Support\Lapak\LapakSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLapakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lapak::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = LapakSlug::fromName((string) $this->input('name', ''));
        } else {
            $slug = LapakSlug::unique($slug);
        }

        $this->merge(['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                'exists:members,id',
                Rule::unique('lapaks', 'member_id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:lapaks,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'existing_cover_asset_id' => ['nullable', 'integer', 'exists:files,id'],
            'new_cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_id.unique' => 'Anggota ini sudah memiliki Lapak. Satu anggota hanya boleh punya satu Lapak.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'member_id',
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
