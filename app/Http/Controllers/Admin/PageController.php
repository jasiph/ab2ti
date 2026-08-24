<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Page;
use App\Support\CmsPath;
use App\Support\Editorial\EditorialContent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $query = Page::with('media');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        return Inertia::render('Admin/Pages/Index', [
            'pages' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Pages/Form');
    }

    public function store(Request $request)
    {
        $request->merge([
            'slug' => CmsPath::normalize((string) $request->input('slug', '')),
        ]);

        $rules = [
            'title' => 'required|string|max:255',
            'slug' => $this->slugRules(),
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'existing_cover_asset_id' => 'nullable|integer|exists:files,id',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];

        if ($request->hasFile('new_cover')) {
            $rules['new_cover'] = 'file|mimes:jpg,jpeg,png,gif,webp,avif,heic,heif,svg|max:10240';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $validated): void {
            $page = Page::create([
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'content' => app(EditorialContent::class)->normalize($validated['content'] ?? null),
                'meta_title' => $validated['meta_title'] ?? $validated['title'],
                'meta_description' => $validated['meta_description'] ?? null,
                'settings' => $validated['settings'] ?? [],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if ($request->hasFile('new_cover')) {
                $file = $request->file('new_cover');
                $asset = File::create([
                    'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'user_id' => auth()->id(),
                ]);
                $mediaItem = $asset->addMedia($file)->toMediaCollection('default');
                $mediaItem->copy($page, 'page-cover');
            }

            if (! empty($validated['existing_cover_asset_id'])) {
                $asset = File::with('media')->find($validated['existing_cover_asset_id']);
                if ($asset && $mediaItem = $asset->getFirstMedia('default')) {
                    $mediaItem->copy($page, 'page-cover');
                }
            }
        });

        return redirect()->route('admin.pages.index')->with('success', 'Page created successfully.');
    }

    public function edit(Page $page)
    {
        $page->load('media');
        $page->setAttribute('content', app(EditorialContent::class)->normalize($page->content));

        return Inertia::render('Admin/Pages/Form', [
            'page' => $page,
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $request->merge([
            'slug' => CmsPath::normalize((string) $request->input('slug', '')),
        ]);

        $rules = [
            'title' => 'required|string|max:255',
            'slug' => $this->slugRules($page),
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'existing_cover_asset_id' => 'nullable|integer|exists:files,id',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'remove_cover' => 'nullable|boolean',
        ];

        if ($request->hasFile('new_cover')) {
            $rules['new_cover'] = 'file|mimes:jpg,jpeg,png,gif,webp,avif,heic,heif,svg|max:10240';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $page, $validated): void {
            $page->update([
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'content' => app(EditorialContent::class)->normalize($validated['content'] ?? null),
                'meta_title' => $validated['meta_title'] ?? $validated['title'],
                'meta_description' => $validated['meta_description'] ?? null,
                'settings' => $validated['settings'] ?? [],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            if ($request->hasFile('new_cover')) {
                $file = $request->file('new_cover');
                $asset = File::create([
                    'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'user_id' => auth()->id(),
                ]);
                $mediaItem = $asset->addMedia($file)->toMediaCollection('default');
                $mediaItem->copy($page, 'page-cover');
            }

            if (! empty($validated['existing_cover_asset_id'])) {
                $asset = File::with('media')->find($validated['existing_cover_asset_id']);
                if ($asset && $mediaItem = $asset->getFirstMedia('default')) {
                    $mediaItem->copy($page, 'page-cover');
                }
            }

            if (! empty($validated['remove_cover'])) {
                $page->clearMediaCollection('page-cover');
            }
        });

        return redirect()->route('admin.pages.index')->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        $page->clearMediaCollection('page-cover');
        $page->delete();

        return redirect()->back()->with('success', 'Page deleted successfully.');
    }

    public function toggle(Page $page)
    {
        $page->update([
            'is_active' => ! $page->is_active,
        ]);

        return back()->with('success', 'Page status updated.');
    }

    /**
     * @return array<int, mixed>
     */
    private function slugRules(?Page $page = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            Rule::unique('pages', 'slug')->ignore($page),
            function (string $attribute, mixed $value, Closure $fail): void {
                $normalized = CmsPath::normalize((string) $value);

                if (! CmsPath::hasValidShape($normalized)) {
                    $fail('The slug may only contain lowercase letters, numbers, dashes, and slashes between segments.');

                    return;
                }

                if (CmsPath::isReserved($normalized)) {
                    $fail('This slug uses a reserved application path and cannot be used for a CMS page.');
                }
            },
        ];
    }
}
