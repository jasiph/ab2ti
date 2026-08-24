<?php

namespace App\Support\PublicDelivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class PublicContentSearch
{
    public const MAX_LENGTH = 100;

    public static function fromRequest(Request $request): string
    {
        $term = trim($request->string('q')->toString());

        if ($term === '') {
            return '';
        }

        if (mb_strlen($term) > self::MAX_LENGTH) {
            return mb_substr($term, 0, self::MAX_LENGTH);
        }

        return $term;
    }

    public static function constrain(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like) {
            $inner->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like);
        });
    }
}
