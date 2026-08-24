<?php

namespace App\Http\Controllers\Account;

use App\Actions\Membership\ActivateMemberSiteAccess;
use App\Actions\Membership\LinkUserToMember;
use App\Exceptions\MemberAccountLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\ActivateMemberSiteAccessRequest;
use App\Models\Member;
use App\Models\SecureCapabilityGrant;
use App\Support\Auth\PostAuthenticationHome;
use App\Support\PublicDelivery\PublicMetadataResolver;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MemberAccountLinkController extends Controller
{
    public function show(
        Request $request,
        string $secret,
        SecureCapabilityAccess $access,
        PublicMetadataResolver $metadata,
    ): Response {
        $grant = $this->liveGrant($access, $secret);
        $member = Member::query()->findOrFail($grant->target_id);

        if ($member->isLinkedToAccount()) {
            abort(404);
        }

        $user = $request->user();
        $payload = is_array($grant->payload) ? $grant->payload : [];
        $intendedEmail = $this->intendedEmail($payload['intended_email'] ?? null);
        $existingAccount = ($payload['existing_account'] ?? false) === true;

        if ($user === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return Inertia::render('Account/MemberLink/Activate', [
                'secret' => $secret,
                'member' => [
                    'legal_name' => $member->legal_name,
                    'membership_number' => $member->membership_number,
                ],
                'mode' => $existingAccount ? 'login' : 'activate',
                'intended_email' => $intendedEmail,
                'existing_account' => $existingAccount,
                'suggested_name' => $member->legal_name,
                'login_url' => route('login'),
                'seo' => $this->seo($request, $metadata),
            ]);
        }

        return Inertia::render('Account/MemberLink/Confirm', [
            'secret' => $secret,
            'member' => [
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
            ],
            'account_name' => $user->name,
            'account_email' => $user->email,
            'staff_blocked' => $user->isPlatformStaff(),
            'already_linked_elsewhere' => $user->linkedMember() !== null,
            'seo' => $this->seo($request, $metadata),
        ]);
    }

    public function confirm(
        Request $request,
        string $secret,
        SecureCapabilityAccess $access,
        LinkUserToMember $link,
    ): RedirectResponse {
        $grant = $this->liveGrant($access, $secret);

        try {
            $link->handle($request->user(), $grant);
        } catch (MemberAccountLinkException $exception) {
            if ($exception->getMessage() === MemberAccountLinkException::invitationUnavailable()->getMessage()) {
                abort(404);
            }

            return back()->with('error', $exception->getMessage());
        }

        $user = $request->user()->fresh();

        return redirect()
            ->to(PostAuthenticationHome::url($user))
            ->with('success', 'Akun terhubung ke keanggotaan AB2TI.');
    }

    public function activate(
        ActivateMemberSiteAccessRequest $request,
        string $secret,
        SecureCapabilityAccess $access,
        ActivateMemberSiteAccess $activate,
    ): RedirectResponse {
        $grant = $this->liveGrant($access, $secret);
        $member = Member::query()->findOrFail($grant->target_id);

        if ($member->isLinkedToAccount()) {
            abort(404);
        }

        try {
            $user = $activate->handle($secret, $request->safe()->only(['name', 'email', 'password']));
        } catch (MemberAccountLinkException $exception) {
            if (in_array($exception->getMessage(), [
                MemberAccountLinkException::alreadyLinked()->getMessage(),
                MemberAccountLinkException::invitationUnavailable()->getMessage(),
            ], true)) {
                abort(404);
            }

            return back()->with('error', $exception->getMessage());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->to(PostAuthenticationHome::url($user))
            ->with('success', 'Akses situs anggota AB2TI sudah aktif.');
    }

    private function liveGrant(SecureCapabilityAccess $access, string $secret): SecureCapabilityGrant
    {
        $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');
        $grant = $access->findLiveBySecret($secret);

        if (
            $grant === null
            || ! $access->assertUsable($grant, $purpose, 'member', (int) $grant->target_id)
        ) {
            abort(404);
        }

        return $grant;
    }

    private function intendedEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = Str::lower(trim($value));

        return $email !== '' ? $email : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function seo(Request $request, PublicMetadataResolver $metadata): array
    {
        return $metadata->forTransactionalPage(
            $request->path(),
            'Akses situs anggota AB2TI',
            'Halaman undangan akses anggota. Tidak untuk diindeks.',
        )->toArray();
    }
}
