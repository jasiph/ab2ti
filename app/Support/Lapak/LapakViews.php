<?php

namespace App\Support\Lapak;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Enums\LapakPriceMode;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Models\User;

final class LapakViews
{
    /**
     * @return array<string, mixed>
     */
    public static function storefrontListItem(Lapak $lapak): array
    {
        $lapak->loadMissing('member');

        return [
            'id' => $lapak->id,
            'name' => $lapak->name,
            'slug' => $lapak->slug,
            'moderation_status' => $lapak->moderation_status->value,
            'moderation_label' => $lapak->moderation_status->label(),
            'public_eligible' => $lapak->isPublicEligible(),
            'member_name' => $lapak->member?->legal_name,
            'membership_number' => $lapak->member?->membership_number,
            'location_text' => $lapak->location_text,
            'updated_at' => $lapak->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function storefrontForm(Lapak $lapak, User $actor): array
    {
        $cover = $lapak->coverMedia();
        $lapak->loadMissing('member');

        return [
            'id' => $lapak->id,
            'member_id' => $lapak->member_id,
            'member_name' => $lapak->member?->legal_name,
            'membership_number' => $lapak->member?->membership_number,
            'name' => $lapak->name,
            'slug' => $lapak->slug,
            'summary' => $lapak->summary,
            'description' => $lapak->description,
            'location_text' => $lapak->location_text,
            'contact_phone' => $lapak->contact_phone,
            'contact_whatsapp' => $lapak->contact_whatsapp,
            'contact_email' => $lapak->contact_email,
            'moderation_status' => $lapak->moderation_status->value,
            'moderation_label' => $lapak->moderation_status->label(),
            'moderation_note' => $lapak->moderation_note,
            'public_eligible' => $lapak->isPublicEligible(),
            'published_at' => $lapak->published_at?->toIso8601String(),
            'cover_url' => $cover?->getUrl('thumb') ?: $cover?->getUrl(),
            'can_update' => $actor->can('update', $lapak),
            'can_moderate' => $actor->can('moderate', $lapak),
            'allowed_transitions' => array_map(
                fn (LapakModerationStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $lapak->moderation_status->allowedTargets(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listingListItem(LapakListing $listing): array
    {
        $listing->loadMissing(['lapak.member', 'category']);

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'lapak_id' => $listing->lapak_id,
            'lapak_name' => $listing->lapak?->name,
            'member_name' => $listing->lapak?->member?->legal_name,
            'category_name' => $listing->category?->name,
            'moderation_status' => $listing->moderation_status->value,
            'moderation_label' => $listing->moderation_status->label(),
            'availability' => $listing->availability->value,
            'availability_label' => $listing->availability->label(),
            'price_label' => self::priceLabel($listing),
            'public_eligible' => $listing->isPublicEligible(),
            'ineligible_reason' => self::ineligibleReason($listing),
            'updated_at' => $listing->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listingForm(LapakListing $listing, User $actor): array
    {
        $listing->loadMissing(['lapak.member', 'category']);

        return [
            'id' => $listing->id,
            'lapak_id' => $listing->lapak_id,
            'lapak_name' => $listing->lapak?->name,
            'member_name' => $listing->lapak?->member?->legal_name,
            'membership_number' => $listing->lapak?->member?->membership_number,
            'category_id' => $listing->category_id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'summary' => $listing->summary,
            'description' => $listing->description,
            'price_mode' => $listing->price_mode->value,
            'price_amount' => $listing->price_amount,
            'price_unit' => $listing->price_unit,
            'price_label' => self::priceLabel($listing),
            'availability' => $listing->availability->value,
            'availability_label' => $listing->availability->label(),
            'moderation_status' => $listing->moderation_status->value,
            'moderation_label' => $listing->moderation_status->label(),
            'moderation_note' => $listing->moderation_note,
            'public_eligible' => $listing->isPublicEligible(),
            'ineligible_reason' => self::ineligibleReason($listing),
            'images' => $listing->galleryMedia()->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail_url' => $media->getUrl('thumb') ?: $media->getUrl(),
            ])->values(),
            'can_update' => $actor->can('update', $listing),
            'can_moderate' => $actor->can('moderate', $listing),
            'allowed_transitions' => array_map(
                fn (LapakModerationStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $listing->moderation_status->allowedTargets(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function categoryListItem(LapakCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'listings_count' => $category->listings_count ?? $category->listings()->count(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function moderationOptions(): array
    {
        return array_map(
            fn (LapakModerationStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            LapakModerationStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function availabilityOptions(): array
    {
        return array_map(
            fn (LapakListingAvailability $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            LapakListingAvailability::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function priceModeOptions(): array
    {
        return array_map(
            fn (LapakPriceMode $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            LapakPriceMode::cases(),
        );
    }

    public static function priceLabel(LapakListing $listing): string
    {
        $unit = filled($listing->price_unit) ? ' / '.$listing->price_unit : '';

        return match ($listing->price_mode) {
            LapakPriceMode::Fixed => self::formatAmount($listing->price_amount).$unit,
            LapakPriceMode::From => 'Mulai '.self::formatAmount($listing->price_amount).$unit,
            LapakPriceMode::Negotiable => $listing->price_amount
                ? 'Nego ('.self::formatAmount($listing->price_amount).')'
                : 'Nego / hubungi penjual',
            LapakPriceMode::Hidden => 'Harga tidak ditampilkan',
        };
    }

    public static function ineligibleReason(LapakListing $listing): ?string
    {
        if ($listing->isPublicEligible()) {
            return null;
        }

        if (! $listing->moderation_status->isPublicEligible()) {
            return 'Belum tayang: status publikasi '.$listing->moderation_status->label().'.';
        }

        if ($listing->availability !== LapakListingAvailability::Available) {
            return 'Sudah disetujui, tetapi ditandai tidak tersedia.';
        }

        if ($listing->lapak && ! $listing->lapak->isPublicEligible()) {
            return 'Produk disetujui, tetapi Lapak pemilik belum tayang.';
        }

        if ($listing->category && ! $listing->category->is_active) {
            return 'Kategori produk sedang dinonaktifkan.';
        }

        return 'Belum memenuhi syarat tayang publik.';
    }

    /**
     * @return array<string, mixed>
     */
    public static function ownerStorefront(Lapak $lapak): array
    {
        $cover = $lapak->coverMedia();

        return [
            'id' => $lapak->id,
            'name' => $lapak->name,
            'slug' => $lapak->slug,
            'summary' => $lapak->summary,
            'description' => $lapak->description,
            'location_text' => $lapak->location_text,
            'contact_phone' => $lapak->contact_phone,
            'contact_whatsapp' => $lapak->contact_whatsapp,
            'contact_email' => $lapak->contact_email,
            'moderation_status' => $lapak->moderation_status->value,
            'moderation_label' => $lapak->moderation_status->ownerLabel(),
            'moderation_note' => $lapak->moderation_note,
            'public_visible' => $lapak->isPublicEligible(),
            'cover_url' => $cover?->getUrl('thumb') ?: $cover?->getUrl(),
            'can_edit' => $lapak->moderation_status->ownerMayEditContent(),
            'can_submit' => $lapak->moderation_status->ownerMaySubmitForReview(),
            'guidance' => self::ownerGuidance($lapak->moderation_status, $lapak->moderation_note, $lapak->isPublicEligible()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ownerListing(LapakListing $listing): array
    {
        $listing->loadMissing('category');

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'summary' => $listing->summary,
            'description' => $listing->description,
            'category_id' => $listing->category_id,
            'category_name' => $listing->category?->name,
            'price_mode' => $listing->price_mode->value,
            'price_amount' => $listing->price_amount,
            'price_unit' => $listing->price_unit,
            'price_label' => self::priceLabel($listing),
            'availability' => $listing->availability->value,
            'availability_label' => $listing->availability->label(),
            'moderation_status' => $listing->moderation_status->value,
            'moderation_label' => $listing->moderation_status->ownerLabel(),
            'moderation_note' => $listing->moderation_note,
            'public_visible' => $listing->isPublicEligible(),
            'images' => $listing->galleryMedia()->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail_url' => $media->getUrl('thumb') ?: $media->getUrl(),
            ])->values(),
            'can_edit' => $listing->moderation_status->ownerMayEditContent(),
            'can_submit' => $listing->moderation_status->ownerMaySubmitForReview(),
            'can_change_availability' => $listing->moderation_status->ownerMayChangeAvailability(),
            'guidance' => self::ownerGuidance(
                $listing->moderation_status,
                $listing->moderation_note,
                $listing->isPublicEligible(),
                self::ineligibleReason($listing),
            ),
        ];
    }

    /**
     * @return array{why: ?string, next: ?string}
     */
    public static function ownerGuidance(
        LapakModerationStatus $status,
        ?string $note,
        bool $publicVisible,
        ?string $ineligibleReason = null,
    ): array {
        return match ($status) {
            LapakModerationStatus::Draft => [
                'why' => 'Belum tayang karena masih draf.',
                'next' => 'Lengkapi data, lalu kirim untuk ditinjau staf.',
            ],
            LapakModerationStatus::PendingReview => [
                'why' => 'Belum tayang karena menunggu tinjauan staf.',
                'next' => 'Tunggu hasil tinjauan. Data tidak dapat diubah sementara ini.',
            ],
            LapakModerationStatus::Published => [
                'why' => $publicVisible ? null : $ineligibleReason,
                'next' => $publicVisible
                    ? 'Sudah tayang. Ubah ketersediaan kapan saja. Perubahan isi akan dikirim ulang untuk ditinjau.'
                    : ($ineligibleReason ?? 'Sudah disetujui, tetapi belum memenuhi syarat tayang.'),
            ],
            LapakModerationStatus::Rejected => [
                'why' => 'Perlu diperbaiki.'.($note ? ' Alasan staf: '.$note : ''),
                'next' => 'Perbaiki data, lalu kirim ulang untuk ditinjau.',
            ],
            LapakModerationStatus::Suspended => [
                'why' => 'Ditangguhkan staf.'.($note ? ' Alasan: '.$note : ''),
                'next' => 'Anda tidak dapat menayangkan ulang sendiri. Hubungi staf AB2TI.',
            ],
        };
    }

    public static function publicPriceLabel(LapakListing $listing): string
    {
        $unit = filled($listing->price_unit) ? ' / '.$listing->price_unit : '';

        return match ($listing->price_mode) {
            LapakPriceMode::Fixed => self::formatAmount($listing->price_amount).$unit,
            LapakPriceMode::From => 'Mulai '.self::formatAmount($listing->price_amount).$unit,
            LapakPriceMode::Negotiable => 'Harga dapat ditanyakan',
            LapakPriceMode::Hidden => 'Hubungi penjual untuk harga',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicListingCard(LapakListing $listing): array
    {
        $listing->loadMissing(['lapak', 'category']);
        $image = $listing->galleryMedia()->first();
        $cover = $listing->lapak?->coverMedia();

        return [
            'title' => $listing->title,
            'slug' => $listing->slug,
            'summary' => $listing->summary,
            'category_name' => $listing->category?->name,
            'price_label' => self::publicPriceLabel($listing),
            'image_url' => self::safeMediaUrl($image, preferThumb: true) ?: self::safeMediaUrl($cover, preferThumb: true),
            'image_alt' => $listing->title,
            'lapak_name' => $listing->lapak?->name,
            'lapak_slug' => $listing->lapak?->slug,
            'location_text' => $listing->lapak?->location_text,
            'url' => '/'.self::listingPublicPath($listing),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicStorefront(Lapak $lapak): array
    {
        $cover = $lapak->coverMedia();

        return [
            'name' => $lapak->name,
            'slug' => $lapak->slug,
            'summary' => $lapak->summary,
            'description' => $lapak->description,
            'location_text' => $lapak->location_text,
            'cover_url' => self::safeMediaUrl($cover),
            'cover_alt' => $lapak->name,
            'url' => '/lapak/'.$lapak->slug,
            'contact' => LapakPublicContact::channels(
                $lapak->contact_phone,
                $lapak->contact_whatsapp,
                $lapak->contact_email,
                'Halo, saya melihat Lapak "'.$lapak->name.'" di Lapak Anggota AB2TI.',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicListingDetail(LapakListing $listing): array
    {
        $listing->loadMissing(['lapak', 'category']);
        $images = $listing->galleryMedia()->map(function ($media) use ($listing) {
            $url = self::safeMediaUrl($media);

            if ($url === null) {
                return null;
            }

            return [
                'url' => $url,
                'thumbnail_url' => self::safeMediaUrl($media, preferThumb: true) ?? $url,
                'alt' => $listing->title,
            ];
        })->filter()->values();

        $prefill = self::listingPrefillMessage($listing);
        $storefront = $listing->lapak ? self::publicStorefront($listing->lapak) : null;

        if ($storefront !== null) {
            $storefront['contact'] = LapakPublicContact::channels(
                $listing->lapak->contact_phone,
                $listing->lapak->contact_whatsapp,
                $listing->lapak->contact_email,
                $prefill,
            );
        }

        return [
            'title' => $listing->title,
            'slug' => $listing->slug,
            'summary' => $listing->summary,
            'description' => $listing->description,
            'category_name' => $listing->category?->name,
            'price_label' => self::publicPriceLabel($listing),
            'images' => $images,
            'url' => '/'.self::listingPublicPath($listing),
            'lapak' => $storefront,
        ];
    }

    public static function listingPublicPath(LapakListing $listing): string
    {
        $listing->loadMissing('lapak');

        return 'lapak/'.$listing->lapak->slug.'/produk/'.$listing->slug;
    }

    public static function listingPrefillMessage(LapakListing $listing): string
    {
        return 'Halo, saya melihat produk "'.$listing->title.'" di Lapak Anggota AB2TI.';
    }

    private static function safeMediaUrl(mixed $media, bool $preferThumb = false): ?string
    {
        if ($media === null || ! method_exists($media, 'getUrl')) {
            return null;
        }

        try {
            if ($preferThumb && method_exists($media, 'hasGeneratedConversion') && $media->hasGeneratedConversion('thumb')) {
                return $media->getUrl('thumb');
            }

            return $media->getUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return '—';
        }

        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
