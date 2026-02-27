<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LetterTemplateController extends Controller
{
    public function index()
    {
        $templates = LetterTemplate::query()
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/LetterTemplate/Index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/LetterTemplate/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:letter_templates,code',
            'content_template' => 'required|string',
            'data_schema' => 'nullable|array',
        ]);

        LetterTemplate::create($validated);

        return redirect()->route('admin.letter-templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function edit(LetterTemplate $letterTemplate)
    {
        return Inertia::render('Admin/LetterTemplate/Form', [
            'letterTemplate' => $letterTemplate,
        ]);
    }

    public function update(Request $request, LetterTemplate $letterTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:letter_templates,code,' . $letterTemplate->id,
            'content_template' => 'required|string',
            'data_schema' => 'nullable|array',
        ]);

        $letterTemplate->update($validated);

        return redirect()->route('admin.letter-templates.index')
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(LetterTemplate $letterTemplate)
    {
        $letterTemplate->delete();

        return redirect()->route('admin.letter-templates.index')
            ->with('success', 'Template deleted successfully.');
    }
}
