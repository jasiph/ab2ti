<?php

namespace App\Http\Controllers;

use App\Support\PublicDelivery\CanonicalUrl;
use App\Support\PublicDelivery\PublicSitemap;
use Symfony\Component\HttpFoundation\Response;

class PublicDiscoveryController extends Controller
{
    /**
     * @var list<string>
     */
    private const DISALLOW = [
        '/admin',
        '/akun',
        '/akses',
        '/dashboard',
        '/login',
        '/logout',
        '/register',
        '/user',
        '/forgot-password',
        '/reset-password',
        '/two-factor-challenge',
        '/email',
        '/sanctum',
        '/api',
        '/language',
        '/up',
        '/keanggotaan/daftar',
    ];

    public function robots(CanonicalUrl $canonicalUrl): Response
    {
        $lines = ['User-agent: *'];

        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$canonicalUrl->origin().'/sitemap.xml';
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(PublicSitemap $sitemap): Response
    {
        return response($sitemap->toXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
