<?php

namespace App\Http\Controllers\Membership;

use App\Actions\Membership\ResubmitMembershipApplication;
use App\Enums\MembershipDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\ResubmitPublicMembershipApplicationRequest;
use App\Models\MembershipApplication;
use App\Models\SecureCapabilityGrant;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipRevisionController extends Controller
{
    public function show(Request $request, PublicMetadataResolver $metadata): Response
    {
        $application = $this->application($request);

        return Inertia::render('Frontend/Membership/Revise', [
            'revisionInstruction' => $application->revision_reason,
            'application' => [
                'legal_name' => $application->legal_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
                'applicant_notes' => $application->applicant_notes,
                'documents' => $application->currentDocuments
                    ->map(fn ($document) => [
                        'document_type' => $document->document_type->value,
                        'original_filename' => $document->original_filename,
                    ])
                    ->values()
                    ->all(),
            ],
            'documentConstraints' => [
                'maxKilobytes' => (int) config('membership.max_document_kilobytes', 5120),
                'mimes' => config('membership.document_mimes', []),
            ],
            'seo' => $metadata->forTransactionalPage(
                'akses/revisi',
                'Revisi permohonan keanggotaan AB2TI',
                'Halaman revisi permohonan keanggotaan. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function update(
        ResubmitPublicMembershipApplicationRequest $request,
        ResubmitMembershipApplication $resubmit,
    ): RedirectResponse {
        $application = $this->application($request);
        $grant = $this->grant($request);
        $files = [];

        foreach (MembershipDocumentType::cases() as $type) {
            $files[$type->value] = $request->file($type->value);
        }

        $updated = $resubmit->handle($application, $grant, $request->applicantPayload(), $files);

        $request->session()->forget((string) config('secure_capability.session_key', 'capability_grant_id'));
        $request->session()->regenerate();
        $request->session()->put('membership_reference_code', $updated->reference_code);

        return redirect()
            ->route('capability.membership.revision.submitted')
            ->with('success', 'Permohonan yang diperbarui sudah dikirim ulang.');
    }

    public function submitted(Request $request, PublicMetadataResolver $metadata): Response
    {
        $referenceCode = $request->session()->get('membership_reference_code');

        return Inertia::render('Frontend/Membership/RevisionSubmitted', [
            'referenceCode' => is_string($referenceCode) && $referenceCode !== '' ? $referenceCode : null,
            'seo' => $metadata->forTransactionalPage(
                'akses/revisi/terkirim',
                'Revisi permohonan terkirim',
                'Konfirmasi pengiriman ulang permohonan keanggotaan AB2TI.',
            )->toArray(),
        ]);
    }

    private function application(Request $request): MembershipApplication
    {
        $application = $request->attributes->get('capability_application');

        abort_unless($application instanceof MembershipApplication, 404);

        $application->loadMissing('currentDocuments');

        return $application;
    }

    private function grant(Request $request): SecureCapabilityGrant
    {
        $grant = $request->attributes->get('capability_grant');

        abort_unless($grant instanceof SecureCapabilityGrant, 404);

        return $grant;
    }
}
