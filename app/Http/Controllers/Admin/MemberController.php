<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Membership\IssueMemberAccountLink;
use App\Actions\Membership\RevokeMemberAccountLink;
use App\Actions\Membership\UpdateMemberContact;
use App\Exceptions\MemberAccountLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\IssueMemberSiteAccessRequest;
use App\Http\Requests\Membership\UpdateMemberRequest;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Support\Membership\MembershipViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', Member::class);

        $search = $request->string('search')->toString();

        $members = Member::query()
            ->with('cooperativeMembership')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('legal_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('membership_number', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('recognized_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Member $member) => MembershipViews::memberListItem($member));

        return Inertia::render('Admin/Membership/Members/Index', [
            'members' => $members,
            'filters' => [
                'search' => $search,
            ],
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Daftar anggota AB2TI',
                'Halaman staf keanggotaan AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function show(Request $request, Member $member, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('view', $member);

        return Inertia::render('Admin/Membership/Members/Show', [
            'member' => MembershipViews::memberSummary($member),
            'canUpdate' => $request->user()->can('update', $member),
            'canIssueAccountLink' => $request->user()->can('update', $member),
            'accountLinkInvitationUrl' => $request->session()->get('member_account_link_url'),
            'canViewSourceApplication' => $request->user()->can('membership-applications.view'),
            'canManageCooperative' => $request->user()->can('create', CooperativeMembership::class),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Detail anggota AB2TI',
                'Halaman staf keanggotaan AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function update(
        UpdateMemberRequest $request,
        Member $member,
        UpdateMemberContact $updateContact,
    ): RedirectResponse {
        $updateContact->handle($member, $request->contactPayload());

        return redirect()
            ->route('admin.membership.members.show', $member)
            ->with('success', 'Data kontak anggota diperbarui.');
    }

    public function issueAccountLink(
        IssueMemberSiteAccessRequest $request,
        Member $member,
        IssueMemberAccountLink $issue,
    ): RedirectResponse {
        $this->authorize('update', $member);

        try {
            $issued = $issue->handle(
                $member,
                $request->user(),
                (string) $request->validated('intended_email'),
            );
        } catch (MemberAccountLinkException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $url = route('account.member-link.show', ['secret' => $issued->plainSecret]);
        $existing = (bool) ($issued->grant->payload['existing_account'] ?? false);

        return redirect()
            ->route('admin.membership.members.show', $member)
            ->with('success', $existing
                ? 'Akun dengan email ini sudah ada. Salin undangan penghubungan sekarang; tautan tidak disimpan sebagai teks biasa.'
                : 'Undangan aktivasi akses situs dibuat. Salin tautan sekarang; tautan tidak disimpan sebagai teks biasa.')
            ->with('member_account_link_url', $url);
    }

    public function revokeAccountLink(Member $member, RevokeMemberAccountLink $revoke): RedirectResponse
    {
        $this->authorize('update', $member);

        $revoke->handle($member);

        return redirect()
            ->route('admin.membership.members.show', $member)
            ->with('success', 'Undangan akses situs dicabut.');
    }
}
