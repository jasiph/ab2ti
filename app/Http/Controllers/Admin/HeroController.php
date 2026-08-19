<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HeroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $heroes = Hero::with('media')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($hero) {
                return [
                    'id' => $hero->id,
                    'title' => $hero->title,
                    'is_active' => $hero->is_active,
                    'created_at' => $hero->created_at,
                    'background_image' => $hero->getFirstMediaUrl('hero-backgrounds', 'thumb'),
                ];
            });

        return Inertia::render('Admin/Hero/Index', [
            'heroes' => $heroes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Hero/Form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'primary_text' => 'nullable|string|max:255',
            'secondary_text' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:255',
            'button_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $hero = Hero::create([
                'title' => $validated['title'],
                'primary_text' => $validated['primary_text'] ?? null,
                'secondary_text' => $validated['secondary_text'] ?? null,
                'button_text' => $validated['button_text'] ?? null,
                'button_url' => $validated['button_url'] ?? null,
                'is_active' => $validated['is_active'] ?? false,
            ]);

            if (! empty($request->file_ids)) {
                foreach ($request->file_ids as $fileId) {
                    $file = \App\Models\File::find($fileId);
                    if ($file) {
                        $media = $file->getFirstMedia('default');
                        if ($media) {
                            $media->copy($hero, 'hero-backgrounds');
                        }
                    }
                }
            }
        });

        return redirect()->route('admin.heroes.index')
            ->with('success', 'Hero created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $hero = Hero::with('media')->findOrFail($id);

        return Inertia::render('Admin/Hero/Form', [
            'hero' => [
                'id' => $hero->id,
                'title' => $hero->title,
                'primary_text' => $hero->primary_text,
                'secondary_text' => $hero->secondary_text,
                'button_text' => $hero->button_text,
                'button_url' => $hero->button_url,
                'is_active' => $hero->is_active,
                'images' => $hero->getMedia('hero-backgrounds')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'thumbnail_url' => $media->getUrl('thumb'),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $hero = Hero::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'primary_text' => 'nullable|string|max:255',
            'secondary_text' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:255',
            'button_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'exists:files,id',
            'remove_media_ids' => 'nullable|array',
            'remove_media_ids.*' => 'exists:media,id',
        ]);

        DB::transaction(function () use ($hero, $validated, $request) {
            $hero->update([
                'title' => $validated['title'],
                'primary_text' => $validated['primary_text'] ?? null,
                'secondary_text' => $validated['secondary_text'] ?? null,
                'button_text' => $validated['button_text'] ?? null,
                'button_url' => $validated['button_url'] ?? null,
                'is_active' => $validated['is_active'] ?? false,
            ]);

            // Handle new files
            if (! empty($request->file_ids)) {
                foreach ($request->file_ids as $fileId) {
                    $file = \App\Models\File::find($fileId);
                    if ($file) {
                        $media = $file->getFirstMedia('default');
                        if ($media) {
                            $media->copy($hero, 'hero-backgrounds');
                        }
                    }
                }
            }

            if (! empty($request->remove_media_ids)) {
                $hero->media()
                    ->whereIn('id', $request->remove_media_ids)
                    ->get()
                    ->each(fn (Media $media) => $media->delete());
            }
        });

        return redirect()->route('admin.heroes.index')
            ->with('success', 'Hero updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $hero = Hero::findOrFail($id);
        $hero->delete();

        return redirect()->route('admin.heroes.index')
            ->with('success', 'Hero deleted successfully.');
    }
}
