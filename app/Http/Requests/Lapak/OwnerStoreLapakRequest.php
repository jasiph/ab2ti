<?php

namespace App\Http\Requests\Lapak;

use App\Support\Lapak\LapakSlug;
use Illuminate\Foundation\Http\FormRequest;

class OwnerStoreLapakRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->isPlatformStaff()
            && $user->linkedMember() !== null
            && $user->linkedMember()->lapak === null;
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:lapaks,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'new_cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $member = $this->user()->linkedMember();

        return [
            'member_id' => $member->id,
            ...$this->safe()->only([
                'name',
                'slug',
                'summary',
                'description',
                'location_text',
                'contact_phone',
                'contact_whatsapp',
                'contact_email',
            ]),
        ];
    }
}
