<?php

namespace App\Http\Middleware;

use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        $shared = parent::share($request);
        $auth = is_array($shared['auth'] ?? null) ? $shared['auth'] : [];
        $auth['linked_to_member'] = $request->user()?->linkedMember() !== null;
        $auth['can_access_admin'] = $request->user()?->isPlatformStaff() === true;

        return [
            ...$shared,
            'auth' => $auth,
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
            'canRegister' => fn () => Route::has('register'),
            'organization' => fn () => [
                'name' => config('organization.name'),
                'short_name' => config('organization.short_name'),
                'contact' => config('organization.contact'),
            ],
            'seo' => fn () => app(PublicMetadataResolver::class)
                ->forRequest($request)
                ->toArray(),
        ];
    }
}
