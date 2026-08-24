<?php

namespace App\Support\Http;

use App\Support\PublicDelivery\PublicMetadataInput;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class ApplicationErrorPage
{
    /**
     * @var list<int>
     */
    private const ALWAYS_BRANDED = [403, 404, 419];

    /**
     * @var list<int>
     */
    private const PRODUCTION_BRANDED = [500];

    public static function shouldRender(int $status, bool $debug): bool
    {
        if (in_array($status, self::ALWAYS_BRANDED, true)) {
            return true;
        }

        return ! $debug && in_array($status, self::PRODUCTION_BRANDED, true);
    }

    public static function toResponse(Request $request, int $status): Response
    {
        $copy = self::copy($status, $request);

        return Inertia::render('Errors/Show', [
            'status' => $status,
            'title' => $copy['title'],
            'message' => $copy['message'],
            'actions' => $copy['actions'],
            'seo' => app(PublicMetadataResolver::class)->resolve(new PublicMetadataInput(
                path: '/'.$request->path(),
                seoTitle: $copy['title'],
                seoDescription: $copy['message'],
                ogType: 'website',
                indexable: false,
            ))->toArray(),
        ])->toResponse($request)->setStatusCode($status);
    }

    /**
     * @return array{title: string, message: string, actions: list<array{label: string, href?: string, reload?: bool, back?: bool}>}
     */
    private static function copy(int $status, Request $request): array
    {
        $home = url('/');
        $authenticated = $request->user() !== null;

        return match ($status) {
            403 => [
                'title' => 'Akses tidak diizinkan',
                'message' => 'Anda tidak memiliki izin untuk membuka halaman ini.',
                'actions' => array_values(array_filter([
                    ['label' => 'Beranda', 'href' => $home],
                    $authenticated && $request->user()?->isPlatformStaff()
                        ? ['label' => 'Dasbor', 'href' => route('admin.dashboard')]
                        : null,
                    $authenticated
                        ? null
                        : ['label' => 'Masuk', 'href' => route('login')],
                ])),
            ],
            404 => [
                'title' => 'Halaman tidak ditemukan',
                'message' => 'Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.',
                'actions' => [
                    ['label' => 'Beranda', 'href' => $home],
                    ['label' => 'Halaman sebelumnya', 'back' => true],
                ],
            ],
            419 => [
                'title' => 'Sesi telah berakhir',
                'message' => 'Halaman ini sudah kedaluwarsa. Muat ulang, lalu coba lagi.',
                'actions' => array_values(array_filter([
                    ['label' => 'Muat ulang', 'reload' => true],
                    ['label' => 'Beranda', 'href' => $home],
                    $authenticated ? null : ['label' => 'Masuk', 'href' => route('login')],
                ])),
            ],
            default => [
                'title' => 'Terjadi gangguan',
                'message' => 'Layanan sedang mengalami gangguan. Silakan coba lagi beberapa saat.',
                'actions' => [
                    ['label' => 'Beranda', 'href' => $home],
                ],
            ],
        };
    }
}
