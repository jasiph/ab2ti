<?php

namespace App\Http\Controllers\Membership;

use App\Actions\Membership\SubmitMembershipApplication;
use App\Enums\MembershipDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\StorePublicMembershipApplicationRequest;
use App\Support\Membership\MembershipIntakeNonce;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicMembershipController extends Controller
{
    public function index(PublicMetadataResolver $metadata): Response
    {
        return Inertia::render('Frontend/Membership/Index', [
            'applyUrl' => route('membership.apply'),
            'seo' => $metadata->forPublicInformationalPage(
                'keanggotaan',
                __('Membership'),
                __('Membership landing intro'),
            )->toArray(),
        ]);
    }

    public function create(
        Request $request,
        PublicMetadataResolver $metadata,
        MembershipIntakeNonce $nonce,
    ): Response {
        return Inertia::render('Frontend/Membership/Apply', [
            'intakeToken' => $nonce->ensure($request),
            'documentTypes' => array_map(
                fn (MembershipDocumentType $type) => $type->value,
                MembershipDocumentType::cases(),
            ),
            'documentConstraints' => [
                'maxKilobytes' => (int) config('membership.max_document_kilobytes', 5120),
                'mimes' => config('membership.document_mimes', []),
            ],
            'seo' => $metadata->forTransactionalPage(
                'keanggotaan/daftar',
                __('Membership application'),
                __('Fill the form to apply. Documents are optional.'),
            )->toArray(),
        ]);
    }

    public function store(
        StorePublicMembershipApplicationRequest $request,
        SubmitMembershipApplication $submit,
        MembershipIntakeNonce $nonce,
    ): RedirectResponse {
        $token = (string) $request->validated('intake_token');
        $nonce->assertMatches($request, $token);

        if (! $nonce->acquireLock($token)) {
            if (is_string($request->session()->get('membership_reference_code'))) {
                return redirect()->route('membership.apply.submitted');
            }

            throw ValidationException::withMessages([
                'intake_token' => 'Permohonan Anda sedang diproses. Tunggu sebentar, jangan kirim ulang.',
            ]);
        }

        try {
            $files = [];

            foreach (MembershipDocumentType::cases() as $type) {
                $files[$type->value] = $request->file($type->value);
            }

            $application = $submit->handle($request->applicantPayload(), $files);
        } catch (\Throwable $exception) {
            $nonce->releaseLock($token);
            throw $exception;
        }

        $nonce->consume($request);
        $request->session()->put('membership_reference_code', $application->reference_code);

        return redirect()
            ->route('membership.apply.submitted')
            ->with('success', 'Permohonan keanggotaan berhasil dikirim.');
    }

    public function submitted(Request $request, PublicMetadataResolver $metadata): Response
    {
        $referenceCode = $request->session()->get('membership_reference_code');

        return Inertia::render('Frontend/Membership/Submitted', [
            'referenceCode' => is_string($referenceCode) && $referenceCode !== '' ? $referenceCode : null,
            'seo' => $metadata->forTransactionalPage(
                'keanggotaan/daftar/terkirim',
                __('Application submitted'),
                __('We received your membership application. Staff will review it.'),
            )->toArray(),
        ]);
    }
}
