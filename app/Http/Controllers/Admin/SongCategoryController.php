<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SongCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class SongCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = SongCategory::query();

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $categories = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Admin/SongCategory/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:song_categories,name',
            'type' => 'nullable|string|max:50',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        SongCategory::create($validated);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, SongCategory $songCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:song_categories,name,' . $songCategory->id,
            'type' => 'nullable|string|max:50',
        ]);

        if ($validated['name'] !== $songCategory->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $songCategory->update($validated);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroy(SongCategory $songCategory)
    {
        $songCategory->delete();
        return redirect()->back()->with('success', 'Category deleted.');
    }
}
