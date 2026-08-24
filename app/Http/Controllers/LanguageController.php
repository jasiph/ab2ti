<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function __invoke(string $language): RedirectResponse
    {
        if (! in_array($language, ['en', 'id'], true)) {
            abort(400);
        }

        Session::put('locale', $language);

        return back()->with(
            'success',
            $language === 'id' ? 'beralih ke bahasa Indonesia' : 'switch to English',
        );
    }
}
