<?php

use App\Http\Middleware\SetLocale;
use App\Support\Auth\PostAuthenticationHome;
use App\Support\Http\ApplicationErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'platform-staff' => \App\Http\Middleware\EnsurePlatformStaff::class,
        ]);

        $middleware->redirectUsersTo(fn () => PostAuthenticationHome::url(auth()->user()));

        $middleware->web(append: [
            SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Trusted proxies are applied in AppServiceProvider::boot() after
        // configuration is loaded. config() is not available in this callback.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if (ApplicationErrorPage::shouldRender($status, (bool) config('app.debug'))) {
                return ApplicationErrorPage::toResponse($request, $status);
            }

            return $response;
        });
    })->create();
