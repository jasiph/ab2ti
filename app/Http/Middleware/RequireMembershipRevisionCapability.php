<?php

namespace App\Http\Middleware;

use App\Enums\MembershipApplicationStatus;
use App\Models\MembershipApplication;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMembershipRevisionCapability
{
    public function __construct(
        private SecureCapabilityAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $grantId = $request->session()->get((string) config('secure_capability.session_key', 'capability_grant_id'));

        if (! is_numeric($grantId)) {
            abort(404);
        }

        $grant = $this->access->findLiveById((int) $grantId);

        if ($grant === null) {
            abort(404);
        }

        $purpose = (string) config('membership.revision_capability_purpose', 'membership-application-revision');

        if (! $this->access->assertUsable($grant, $purpose, 'membership_application', (int) $grant->target_id)) {
            abort(404);
        }

        $application = MembershipApplication::query()->find($grant->target_id);

        if (
            $application === null
            || $application->status !== MembershipApplicationStatus::RevisionRequested
        ) {
            abort(404);
        }

        $request->attributes->set('capability_grant', $grant);
        $request->attributes->set('capability_application', $application);

        return $next($request);
    }
}
