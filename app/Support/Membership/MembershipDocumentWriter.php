<?php

namespace App\Support\Membership;

use App\Enums\MembershipDocumentType;
use App\Models\MembershipApplication;
use App\Models\MembershipDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MembershipDocumentWriter
{
    /**
     * Store a new current document. Callers must record the returned object
     * key so failed transactions can delete orphan bytes.
     *
     * @param  list<string>  $storedKeys
     */
    public function store(
        MembershipApplication $application,
        MembershipDocumentType $type,
        UploadedFile $file,
        string $uploadedByContext,
        array &$storedKeys,
    ): MembershipDocument {
        $disk = (string) config('membership.documents_disk');
        $extension = $this->safeExtension($file, $type);
        $objectKey = $application->id.'/'.$type->value.'/'.Str::uuid().'.'.$extension;

        Storage::disk($disk)->putFileAs(
            dirname($objectKey),
            $file,
            basename($objectKey),
        );
        $storedKeys[] = $objectKey;

        return $application->documents()->create([
            'document_type' => $type,
            'disk' => $disk,
            'object_key' => $objectKey,
            'original_filename' => $this->safeOriginalFilename($file),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'checksum' => is_string($file->getRealPath()) ? hash_file('sha256', $file->getRealPath()) : null,
            'uploaded_at' => now(),
            'uploaded_by_context' => $uploadedByContext,
            'uploaded_by_user_id' => null,
        ]);
    }

    /**
     * @param  list<string>  $storedKeys
     */
    public function replaceCurrent(
        MembershipApplication $application,
        MembershipDocumentType $type,
        UploadedFile $file,
        string $uploadedByContext,
        array &$storedKeys,
    ): MembershipDocument {
        $current = $application->currentDocument($type);

        $document = $this->store($application, $type, $file, $uploadedByContext, $storedKeys);

        if ($current !== null) {
            $current->replaced_at = now();
            $current->save();
        }

        return $document;
    }

    private function safeExtension(UploadedFile $file, MembershipDocumentType $type): string
    {
        $allowed = config('membership.document_mimes.'.$type->value, []);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (is_array($allowed) && in_array($extension, $allowed, true)) {
            return $extension;
        }

        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $name = str_replace(["\0", "\r", "\n", '"', '\\'], '', $file->getClientOriginalName());
        $name = basename($name);
        $name = preg_replace('/[^\P{C}]+/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'dokumen';
        }

        return Str::limit($name, 180, '');
    }
}
