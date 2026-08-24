<?php

namespace App\Http\Controllers\SecureCapability;

use App\Enums\MembershipApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\MembershipApplication;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CapabilityRedeemController extends Controller
{
    public function __invoke(Request $request, string $secret, SecureCapabilityAccess $access): RedirectResponse
    {
        $grant = $access->findLiveBySecret($secret);
        $purpose = (string) config('membership.revision_capability_purpose', 'membership-application-revision');

        if (
            $grant === null
            || ! $access->assertUsable(
                $grant,
                $purpose,
                'membership_application',
                (int) $grant->target_id,
            )
        ) {
            abort(404);
        }

        $application = MembershipApplication::query()->find($grant->target_id);

        if (
            $application === null
            || $application->status !== MembershipApplicationStatus::RevisionRequested
        ) {
            abort(404);
        }

        $request->session()->regenerate();
        $request->session()->put(
            (string) config('secure_capability.session_key', 'capability_grant_id'),
            $grant->id,
        );
        $access->touchLastUsed($grant);

        return redirect()->route('capability.membership.revision.show');
    }
}
