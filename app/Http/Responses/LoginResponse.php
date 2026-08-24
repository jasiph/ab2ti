<?php

namespace App\Http\Responses;

use App\Support\Auth\PostAuthenticationHome;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->to(self::destination($request));
    }

    public static function destination($request): string
    {
        $user = $request->user();
        $home = PostAuthenticationHome::url($user);

        if ($user !== null && ! $user->isPlatformStaff()) {
            $intended = $request->session()->pull('url.intended');

            if (is_string($intended)
                && $intended !== ''
                && ! PostAuthenticationHome::isAdministrativePath($intended)) {
                return $intended;
            }

            return $home;
        }

        $intended = $request->session()->pull('url.intended', $home);

        return is_string($intended) && $intended !== '' ? $intended : $home;
    }
}
