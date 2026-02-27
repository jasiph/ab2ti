<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\SongCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class SongController extends Controller
{
    public function index(Request $request)
    {
        $query = Song::query()->with('categories');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('lyrics', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('song_categories.id', $request->category);
            });
        }

        $songs = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Admin/Song/Index', [
            'songs' => $songs,
            'categories' => SongCategory::all(),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Song/Form', [
            'categories' => SongCategory::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'alternative_title' => 'nullable|string|max:255',
            'key' => 'nullable|string|max:10',
            'tempo' => 'nullable|integer',
            'time_signature' => 'nullable|string|max:10',
            'lyric_author' => 'nullable|string|max:255',
            'music_author' => 'nullable|string|max:255',
            'copyright' => 'nullable|string|max:255',
            'youtube_link' => 'nullable|url',
            'content' => 'nullable|array', // JSON Structure: [{type: 'Verse 1', body: '...'}]
            'lyrics' => 'nullable|string', // Plain text for search optimization
            'categories' => 'nullable|array',
            'categories.*' => 'exists:song_categories,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        // Auto-generate plain lyrics from content if not provided
        if (empty($validated['lyrics']) && !empty($validated['content'])) {
            $plainText = collect($validated['content'])
                ->pluck('body')
                ->join("\n\n");
            $validated['lyrics'] = $plainText;
        }

        $song = Song::create($validated);

        if (!empty($validated['categories'])) {
            $song->categories()->sync($validated['categories']);
        }

        return redirect()->route('admin.songs.index')->with('success', 'Song created successfully.');
    }

    public function edit(Song $song)
    {
        return Inertia::render('Admin/Song/Form', [
            'song' => $song->load('categories'),
            'categories' => SongCategory::all(),
        ]);
    }

    public function update(Request $request, Song $song)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'alternative_title' => 'nullable|string|max:255',
            'key' => 'nullable|string|max:10',
            'tempo' => 'nullable|integer',
            'time_signature' => 'nullable|string|max:10',
            'lyric_author' => 'nullable|string|max:255',
            'music_author' => 'nullable|string|max:255',
            'copyright' => 'nullable|string|max:255',
            'youtube_link' => 'nullable|url',
            'content' => 'nullable|array',
            'lyrics' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:song_categories,id',
        ]);

        if ($validated['title'] !== $song->title) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Auto-update plain lyrics
        if (!empty($validated['content'])) {
            $plainText = collect($validated['content'])
                ->pluck('body')
                ->join("\n\n");
            $validated['lyrics'] = $plainText;
        }

        $song->update($validated);

        if (isset($validated['categories'])) {
            $song->categories()->sync($validated['categories']);
        }

        return redirect()->route('admin.songs.index')->with('success', 'Song updated successfully.');
    }

    public function show(Song $song)
    {
        return Inertia::render('Admin/Song/Show', [
            'song' => $song->load('categories'),
        ]);
    }

    public function destroy(Song $song)
    {
        $song->delete();
        return back()->with('success', 'Song deleted.');
    }
}
