<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WelcomeSection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WelcomeSectionController extends Controller
{
    public function edit()
    {
        $welcome = WelcomeSection::query()->latest()->first();

        return Inertia::render('Admin/WelcomeSection/Form', [
            'welcomeSection' => $welcome ? [
                'id' => $welcome->id,
                'title' => $welcome->title,
                'content' => $welcome->content,
                'secondary_content' => $welcome->secondary_content,
                'image_url' => $welcome->image_url,
                'cta_label' => $welcome->cta_label,
                'cta_url' => $welcome->cta_url,
                'is_active' => $welcome->is_active,
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'secondary_content' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);

        $welcome = WelcomeSection::query()->latest()->first();
        if ($welcome) {
            $welcome->update($validated);
        } else {
            WelcomeSection::query()->create($validated);
        }

        return back()->with('success', 'Welcome section updated.');
    }
}
