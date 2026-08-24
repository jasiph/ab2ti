<?php

namespace App\Support\Lapak;

use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Support\PublicDelivery\PublicContentSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PublicLapakSearch
{
    public const PER_PAGE = 12;

    /**
     * Default public directory/storefront order: latest published, then id.
     *
     * @return array{term: string, category: ?string}
     */
    public static function filters(Request $request): array
    {
        $category = trim($request->string('kategori')->toString());

        if (mb_strlen($category) > 100) {
            $category = mb_substr($category, 0, 100);
        }

        return [
            'term' => PublicContentSearch::fromRequest($request),
            'category' => $category !== '' ? $category : null,
        ];
    }

    public static function listings(Request $request): LengthAwarePaginator
    {
        $filters = self::filters($request);
        $query = self::listingCardQuery();

        if ($filters['category'] !== null) {
            $category = LapakCategory::query()
                ->where('is_active', true)
                ->where('slug', $filters['category'])
                ->first();

            if ($category === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('category_id', $category->id);
            }
        }

        if ($filters['term'] !== '') {
            $like = '%'.addcslashes($filters['term'], '%_\\').'%';

            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('lapak', function ($lapak) use ($like) {
                        $lapak->where('name', 'like', $like)
                            ->orWhere('location_text', 'like', $like);
                    })
                    ->orWhereHas('category', function ($category) use ($like) {
                        $category->where('name', 'like', $like);
                    });
            });
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public static function storefrontListings(Lapak $lapak): LengthAwarePaginator
    {
        return self::listingCardQuery()
            ->where('lapak_id', $lapak->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return Collection<int, LapakListing>
     */
    public static function latest(int $limit = 3): Collection
    {
        return self::listingCardQuery()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Public-eligible listings for cards: no description/moderation/member payload.
     */
    public static function listingCardQuery(): Builder
    {
        return LapakListing::query()
            ->publicEligible()
            ->select([
                'id',
                'lapak_id',
                'category_id',
                'title',
                'slug',
                'summary',
                'price_mode',
                'price_amount',
                'price_unit',
                'availability',
                'moderation_status',
                'published_at',
            ])
            ->with([
                'lapak' => fn ($lapak) => $lapak
                    ->select(['id', 'name', 'slug', 'location_text', 'moderation_status'])
                    ->with('media'),
                'category:id,name,slug,is_active',
                'media',
            ]);
    }
}
