<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Hero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HeroController extends Controller
{
    public function index()
    {
        $heroes = Hero::with('media')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function (Hero $hero) {
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

    public function create()
    {
        return Inertia::render('Admin/Hero/Form');
    }

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

            $this->attachBackgrounds($hero, $request->input('file_ids', []));
        });

        return redirect()->route('admin.heroes.index')
            ->with('success', 'Hero created successfully.');
    }

    public function edit(Hero $hero)
    {
        $hero->load('media');

        return Inertia::render('Admin/Hero/Form', [
            'hero' => [
                'id' => $hero->id,
                'title' => $hero->title,
                'primary_text' => $hero->primary_text,
                'secondary_text' => $hero->secondary_text,
                'button_text' => $hero->button_text,
                'button_url' => $hero->button_url,
                'is_active' => $hero->is_active,
                'images' => $hero->getMedia('hero-backgrounds')->map(function (Media $media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'thumbnail_url' => $media->getUrl('thumb'),
                    ];
                }),
            ],
        ]);
    }

    public function update(Request $request, Hero $hero)
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

            $this->attachBackgrounds($hero, $request->input('file_ids', []));

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

    public function toggle(Hero $hero)
    {
        $hero->update([
            'is_active' => ! $hero->is_active,
        ]);

        return back()->with('success', 'Hero status updated.');
    }

    public function destroy(Hero $hero)
    {
        $hero->delete();

        return redirect()->route('admin.heroes.index')
            ->with('success', 'Hero deleted successfully.');
    }

    /**
     * @param  array<int, mixed>  $fileIds
     */
    private function attachBackgrounds(Hero $hero, array $fileIds): void
    {
        foreach ($fileIds as $fileId) {
            $file = File::query()->find($fileId);
            $media = $file?->getFirstMedia('default');

            if ($media) {
                $media->copy($hero, 'hero-backgrounds');
            }
        }
    }
}
