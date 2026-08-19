<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Kirim locale saat ini ke frontend
            'locale' => app()->getLocale(),
            // Definisi Flash Message Global
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            // TAMBAHKAN INI: Auth User Permissions & Roles
            'auth.user.roles' => fn () => $request->user()
                ? $request->user()->getRoleNames()
                : [],

            'auth.user.permissions' => fn () => $request->user()
                ? $request->user()->getAllPermissions()->pluck('name')
                : [],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'canRegister' => fn () => \Illuminate\Support\Facades\Route::has('register'),
        ];
    }
}
