<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Inertia\Inertia;



class FileManagerController extends Controller
{
    public function index()
    {
        $files = File::with('media')->latest()->paginate(24);

        $files->getCollection()->transform(function ($file) {
            $media = $file->getFirstMedia('default');
            return [
                'id' => $file->id,
                'name' => $file->name ?? $media?->file_name,
                'file_name' => $media?->file_name,
                'url' => $media?->getUrl(),
                'thumbnail_url' => $media?->getUrl('thumb'),
                'mime_type' => $media?->mime_type,
                'size' => $media?->size,
                'created_at' => $file->created_at,
                'extension' => $media?->extension,
            ];
        });

        return Inertia::render('Admin/FileManager/Index', [
            'files' => $files,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/FileManager/Form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:20480',
        ]);

        foreach ($request->file('files') as $uploadedFile) {
            $file = File::create([
                'user_id' => auth()->id(),
                'name' => $uploadedFile->getClientOriginalName(),
            ]);

            $file->addMedia($uploadedFile)->toMediaCollection('default');
        }

        return redirect()->route('admin.files.index')->with('success', 'Files uploaded successfully.');
    }

    public function destroy(int $id)
    {
        $file = File::findOrFail($id);
        $file->delete(); // soft-delete
        return response()->json([
            'success' => true,
            'message' => 'File deleted.',
        ]);
    }

    /**
     * Store files and return JSON response (for AJAX uploads from other pages).
     */
    public function storeJson(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,svg|max:20480',
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $uploadedFile) {
            $file = File::create([
                'user_id' => auth()->id(),
                'name' => $uploadedFile->getClientOriginalName(),
            ]);

            $file->addMedia($uploadedFile)->toMediaCollection('default');

            $media = $file->getFirstMedia('default');
            $uploaded[] = [
                'id' => $file->id,
                'name' => $file->name,
                'file_name' => $media?->file_name,
                'url' => $media?->getUrl(),
                'thumbnail_url' => $media?->getUrl('thumb'),
                'mime_type' => $media?->mime_type,
                'size' => $media?->size,
            ];
        }

        return response()->json([
            'success' => true,
            'files' => $uploaded,
        ]);
    }

    public function getJson()
    {
        $files = File::with('media')->latest()->paginate(24);

        $files->getCollection()->transform(function ($file) {
            $media = $file->getFirstMedia('default');
            return [
                'id' => $file->id,
                'name' => $file->name ?? $media?->file_name,
                'file_name' => $media?->file_name,
                'url' => $media?->getUrl(),
                'thumbnail_url' => $media?->getUrl('thumb'),
                'mime_type' => $media?->mime_type,
                'size' => $media?->size,
                'created_at' => $file->created_at,
                'extension' => $media?->extension,
            ];
        });

        return response()->json($files);
    }
}
