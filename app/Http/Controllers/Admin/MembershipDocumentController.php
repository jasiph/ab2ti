<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MembershipDocumentController extends Controller
{
    public function show(MembershipDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($document->replaced_at !== null, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->object_key), 404);

        return Storage::disk($document->disk)->download(
            $document->object_key,
            $this->safeDownloadName($document->original_filename),
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function safeDownloadName(?string $filename): string
    {
        $name = str_replace(["\0", "\r", "\n", '"', '\\'], '', (string) $filename);
        $name = basename(trim($name));

        return $name !== '' ? $name : 'dokumen';
    }
}
