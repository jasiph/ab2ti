<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Support\Editorial\EditorialContent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlockTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = BlockTemplate::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($blocks) use ($search) {
                $blocks->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('Admin/BlockTemplates/Index', [
            'blockTemplates' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/BlockTemplates/Form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'html_content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        BlockTemplate::create([
            'name' => $request->name,
            'description' => $request->description,
            'html_content' => app(EditorialContent::class)->normalize($request->html_content),
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('admin.block-templates.index')->with('success', 'Block Template created.');
    }

    public function edit(BlockTemplate $blockTemplate)
    {
        return Inertia::render('Admin/BlockTemplates/Form', [
            'blockTemplate' => $blockTemplate,
        ]);
    }

    public function update(Request $request, BlockTemplate $blockTemplate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'html_content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $blockTemplate->update([
            'name' => $request->name,
            'description' => $request->description,
            'html_content' => app(EditorialContent::class)->normalize($request->html_content),
            'is_active' => $request->is_active,
        ]);

        return redirect()->route('admin.block-templates.index')->with('success', 'Block Template updated.');
    }

    public function destroy(BlockTemplate $blockTemplate)
    {
        $blockTemplate->delete();

        return redirect()->back()->with('success', 'Block Template deleted.');
    }

    public function toggle(BlockTemplate $blockTemplate)
    {
        $blockTemplate->update([
            'is_active' => ! $blockTemplate->is_active,
        ]);

        return back()->with('success', 'Status Block Template updated.');
    }

    public function getActiveBlocks()
    {
        $pipeline = app(EditorialContent::class);

        $blocks = BlockTemplate::query()
            ->where('is_active', true)
            ->latest()
            ->get(['id', 'name', 'description', 'html_content'])
            ->map(fn (BlockTemplate $block) => [
                'id' => $block->id,
                'name' => $block->name,
                'description' => $block->description,
                'html_content' => $pipeline->normalize($block->html_content),
            ]);

        return response()->json($blocks);
    }
}
