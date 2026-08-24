<?php

namespace App\Http\Requests\Lapak;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakPriceMode;
use App\Support\Lapak\LapakListingSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerStoreLapakListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $lapak = $user?->linkedMember()?->lapak;

        return $user !== null
            && $lapak !== null
            && $user->ownsLapak($lapak);
    }

    protected function prepareForValidation(): void
    {
        $lapakId = (int) ($this->user()?->linkedMember()?->lapak?->id ?? 0);
        $slug = $this->input('slug');

        if ($lapakId > 0) {
            if (! is_string($slug) || trim($slug) === '') {
                $slug = LapakListingSlug::fromTitle((string) $this->input('title', ''), $lapakId);
            } else {
                $slug = LapakListingSlug::unique($slug, $lapakId);
            }

            $this->merge(['slug' => $slug]);
        }

        $mode = LapakPriceMode::tryFrom((string) $this->input('price_mode', ''));
        if ($mode === LapakPriceMode::Hidden) {
            $this->merge(['price_amount' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lapakId = (int) ($this->user()?->linkedMember()?->lapak?->id ?? 0);
        $mode = LapakPriceMode::tryFrom((string) $this->input('price_mode', ''));

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('lapak_categories', 'id')->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('lapak_listings', 'slug')->where(fn ($query) => $query->where('lapak_id', $lapakId)),
            ],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_mode' => ['required', Rule::enum(LapakPriceMode::class)],
            'price_amount' => array_filter([
                $mode?->requiresAmount() ? 'required' : 'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ]),
            'price_unit' => ['nullable', 'string', 'max:32'],
            'availability' => ['required', Rule::enum(LapakListingAvailability::class)],
            'new_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'lapak_id' => $this->user()->linkedMember()->lapak->id,
            ...$this->safe()->only([
                'category_id',
                'title',
                'slug',
                'summary',
                'description',
                'price_mode',
                'price_amount',
                'price_unit',
                'availability',
            ]),
        ];
    }
}
