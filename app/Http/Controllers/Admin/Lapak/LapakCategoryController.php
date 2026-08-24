<?php

namespace App\Http\Controllers\Admin\Lapak;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lapak\UpsertLapakCategoryRequest;
use App\Models\LapakCategory;
use App\Support\Lapak\LapakViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LapakCategoryController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', LapakCategory::class);

        $categories = LapakCategory::query()
            ->withCount('listings')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LapakCategory $category) => LapakViews::categoryListItem($category));

        return Inertia::render('Admin/Lapak/Categories/Index', [
            'categories' => $categories,
            'canManage' => $request->user()->can('create', LapakCategory::class),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Kategori Lapak',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function store(UpsertLapakCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        LapakCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('admin.lapak.categories.index')
            ->with('success', 'Kategori ditambahkan.');
    }

    public function update(UpsertLapakCategoryRequest $request, LapakCategory $category): RedirectResponse
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $category->is_active,
        ]);

        return redirect()
            ->route('admin.lapak.categories.index')
            ->with('success', 'Kategori diperbarui.');
    }

    public function toggle(Request $request, LapakCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        $label = $category->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', 'Kategori '.$label.'. Produk yang memakai kategori ini tetap tersimpan.');
    }
}
