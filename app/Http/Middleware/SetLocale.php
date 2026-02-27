<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // dd('1');
        (Session::get('locale'));
        // 1. Cek apakah ada session 'locale'
        if (Session::has('locale')) {
            $locale = Session::get('locale');
            App::setLocale($locale);
        } else {
            // (Opsional) Jika tidak ada session, kita bisa deteksi browser atau default config
            // Untuk starter ini, kita pakai default dari config/app.php
            App::setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
