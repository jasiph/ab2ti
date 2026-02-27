<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = ArchivedDocument::query()
            ->when($request->search, function ($query, $search) {
            $query->search($search);
        })
            ->when($request->type, function ($query, $type) {
            if ($type !== 'all') {
                $query->type($type);
            }
        })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Document/Index', [
            'documents' => $documents,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Document/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'reference_number' => 'nullable|string',
            'source' => 'required|string',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'document_date' => 'nullable|date',
            'received_at' => 'required|date',
            'congregation_id' => 'nullable|exists:congregations,id',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        $path = $request->file('file')->store('documents', 'public');

        ArchivedDocument::create([
            'type' => $validated['type'],
            'reference_number' => $validated['reference_number'],
            'source' => $validated['source'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'document_date' => $validated['document_date'],
            'received_at' => $validated['received_at'],
            'congregation_id' => $validated['congregation_id'],
            'file_path' => '/storage/' . $path,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.documents.index')
            ->with('success', 'Document archived successfully.');
    }

    public function edit(ArchivedDocument $document)
    {
        $document->load('congregation');
        return Inertia::render('Admin/Document/Form', [
            'document' => $document
        ]);
    }

    public function update(Request $request, ArchivedDocument $document)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'reference_number' => 'nullable|string',
            'source' => 'required|string',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'document_date' => 'nullable|date',
            'received_at' => 'required|date',
            'congregation_id' => 'nullable|exists:congregations,id',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('file')) {
            // Delete old file
            if ($document->file_path) {
                $oldPath = str_replace('/storage/', '', $document->file_path);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('file')->store('documents', 'public');
            $validated['file_path'] = '/storage/' . $path;
        }

        $document->update($validated);

        return redirect()->route('admin.documents.index')
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(ArchivedDocument $document)
    {
        if ($document->file_path) {
            $oldPath = str_replace('/storage/', '', $document->file_path);
            Storage::disk('public')->delete($oldPath);
        }

        $document->delete();

        return redirect()->route('admin.documents.index')
            ->with('success', 'Document deleted successfully.');
    }
}
