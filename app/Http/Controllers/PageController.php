<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::with('media')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            abort(404);
        }

        return Inertia::render('Frontend/Page/Show', [
            'page' => $page,
        ]);
    }
}
