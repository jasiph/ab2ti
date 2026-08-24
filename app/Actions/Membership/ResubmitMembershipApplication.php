<?php

namespace App\Actions\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Enums\MembershipDocumentType;
use App\Models\MembershipApplication;
use App\Models\SecureCapabilityGrant;
use App\Support\Membership\MembershipApplicationLifecycle;
use App\Support\Membership\MembershipDocumentWriter;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResubmitMembershipApplication
{
    public function __construct(
        private MembershipApplicationLifecycle $lifecycle,
        private MembershipDocumentWriter $documents,
        private SecureCapabilityAccess $capabilities,
    ) {}

    /**
     * @param  array{legal_name: string, email: string, phone: string, address: string, applicant_notes?: ?string}  $payload
     * @param  array<string, UploadedFile|null>  $files
     */
    public function handle(
        MembershipApplication $application,
        SecureCapabilityGrant $grant,
        array $payload,
        array $files = [],
    ): MembershipApplication {
        $disk = (string) config('membership.documents_disk');
        $storedKeys = [];

        try {
            return DB::transaction(function () use ($application, $grant, $payload, $files, &$storedKeys) {
                /** @var MembershipApplication $application */
                $application = MembershipApplication::query()
                    ->lockForUpdate()
                    ->findOrFail($application->id);

                $application->fill([
                    'legal_name' => $payload['legal_name'],
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'address' => $payload['address'],
                    'applicant_notes' => $payload['applicant_notes'] ?? null,
                ]);

                $this->lifecycle->transition(
                    $application,
                    MembershipApplicationStatus::Submitted,
                    null,
                    'applicant',
                    null,
                    function (MembershipApplication $application): void {
                        $application->resubmitted_at = now();
                    },
                );

                foreach (MembershipDocumentType::cases() as $type) {
                    $file = $files[$type->value] ?? null;

                    if ($file instanceof UploadedFile) {
                        $this->documents->replaceCurrent($application, $type, $file, 'applicant', $storedKeys);
                    }
                }

                $this->capabilities->consume($grant);

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
