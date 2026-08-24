<?php

namespace App\Support\PublicDelivery;

final class CanonicalUrl
{
    public function origin(): string
    {
        $origin = (string) config('public_delivery.canonical_origin', config('app.url'));

        return rtrim($origin, '/');
    }

    public function forPath(string $path): string
    {
        $withoutQuery = strstr($path, '?', true);
        $path = $withoutQuery === false ? $path : $withoutQuery;

        $normalized = '/'.ltrim($path, '/');

        if ($normalized === '/') {
            return $this->origin().'/';
        }

        $normalized = rtrim($normalized, '/');

        return $this->origin().$normalized;
    }
}
