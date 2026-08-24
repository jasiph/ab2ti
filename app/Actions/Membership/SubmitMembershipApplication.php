<?php

namespace App\Actions\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Enums\MembershipDocumentType;
use App\Models\MembershipApplication;
use App\Support\Membership\MembershipApplicationLifecycle;
use App\Support\Membership\MembershipDocumentWriter;
use App\Support\Membership\MembershipReferenceCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SubmitMembershipApplication
{
    public function __construct(
        private MembershipApplicationLifecycle $lifecycle,
        private MembershipDocumentWriter $documents,
    ) {}

    /**
     * @param  array{legal_name: string, email: string, phone: string, address: string, applicant_notes?: ?string}  $payload
     * @param  array<string, UploadedFile|null>  $files
     */
    public function handle(array $payload, array $files = []): MembershipApplication
    {
        $disk = (string) config('membership.documents_disk');
        $storedKeys = [];

        try {
            return DB::transaction(function () use ($payload, $files, &$storedKeys) {
                $application = new MembershipApplication;
                $application->fill([
                    'legal_name' => $payload['legal_name'],
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'address' => $payload['address'],
                    'applicant_notes' => $payload['applicant_notes'] ?? null,
                ]);
                $application->reference_code = MembershipReferenceCode::generate();

                $this->lifecycle->initialize(
                    $application,
                    MembershipApplicationStatus::Submitted,
                    'applicant',
                    apply: function (MembershipApplication $application): void {
                        $application->submitted_at = now();
                    },
                );

                foreach (MembershipDocumentType::cases() as $type) {
                    $file = $files[$type->value] ?? null;

                    if ($file instanceof UploadedFile) {
                        $this->documents->store($application, $type, $file, 'applicant', $storedKeys);
                    }
                }

                return $application->fresh(['currentDocuments']) ?? $application;
            });
        } catch (Throwable $exception) {
            foreach ($storedKeys as $key) {
                Storage::disk($disk)->delete($key);
            }

            throw $exception;
        }
    }
}
