<?php

namespace Tests\Feature\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Models\MembershipApplication;
use App\Models\SecureCapabilityGrant;
use App\Models\User;
use App\Support\SecureCapability\CapabilitySecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class MembershipRevisionWorkflowTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('membership_documents');
    }

    public function test_authorized_staff_can_request_revision_and_secret_is_not_persisted(): void
    {
        $staff = $this->revisionStaff();
        $application = MembershipApplication::factory()->underReview()->create([
            'staff_notes' => 'Catatan internal staf',
        ]);

        $response = $this->actingAs($staff)
            ->post(route('admin.membership.applications.revision', $application), [
                'revision_reason' => 'Lengkapi alamat lengkap dan unggah foto yang lebih jelas.',
            ]);

        $response->assertRedirect(route('admin.membership.applications.show', $application));
        $response->assertSessionHas('revision_capability_url');

        $url = (string) $response->getSession()->get('revision_capability_url');
        $secret = basename(parse_url($url, PHP_URL_PATH) ?: '');

        $application->refresh();
        $this->assertSame(MembershipApplicationStatus::RevisionRequested, $application->status);
        $this->assertSame(
            'Lengkapi alamat lengkap dan unggah foto yang lebih jelas.',
            $application->revision_reason,
        );
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'from_status' => 'under_review',
            'to_status' => 'revision_requested',
            'actor_user_id' => $staff->id,
        ]);
        $this->assertDatabaseCount('secure_capability_grants', 1);
        $this->assertDatabaseMissing('secure_capability_grants', [
            'secret_digest' => $secret,
        ]);
        $this->assertTrue(
            CapabilitySecret::matches($secret, (string) SecureCapabilityGrant::query()->value('secret_digest')),
        );
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_request_revision_requires_permission_and_under_review_state(): void
    {
        $viewer = $this->userWithPermissions(['membership-applications.view']);
        $staff = $this->revisionStaff();
        $submitted = MembershipApplication::factory()->create();
        $underReview = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($viewer)
            ->post(route('admin.membership.applications.revision', $underReview), [
                'revision_reason' => 'Lengkapi alamat lengkap dan unggah foto.',
            ])
            ->assertForbidden();

        $this->from(route('admin.membership.applications.show', $submitted))
            ->actingAs($staff)
            ->post(route('admin.membership.applications.revision', $submitted), [
                'revision_reason' => 'Lengkapi alamat lengkap dan unggah foto.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('submitted', $submitted->fresh()->status->value);
        $this->assertDatabaseCount('secure_capability_grants', 0);
    }

    public function test_valid_bearer_opens_only_the_target_without_staff_notes(): void
    {
        [$application, $secret] = $this->issueRevision();

        $this->get(route('capability.redeem', ['secret' => $secret]))
            ->assertRedirect(route('capability.membership.revision.show'));

        $this->get(route('capability.membership.revision.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Membership/Revise')
                ->where('revisionInstruction', 'Lengkapi alamat lengkap dan unggah foto yang lebih jelas.')
                ->where('application.legal_name', $application->legal_name)
                ->missing('application.staff_notes')
                ->missing('staff_notes')
                ->missing('available_actions'));
    }

    public function test_invalid_expired_revoked_and_wrong_secrets_are_generic_not_found(): void
    {
        [, $secret] = $this->issueRevision();

        $this->get(route('capability.redeem', ['secret' => str_repeat('a', 32)]))
            ->assertNotFound();

        $grant = SecureCapabilityGrant::query()->firstOrFail();
        $grant->expires_at = now()->subMinute();
        $grant->save();
        $this->get(route('capability.redeem', ['secret' => $secret]))->assertNotFound();

        $grant->expires_at = now()->addDay();
        $grant->revoked_at = now();
        $grant->save();
        $this->get(route('capability.redeem', ['secret' => $secret]))->assertNotFound();

        $this->get(route('capability.membership.revision.show'))->assertNotFound();
    }

    public function test_reference_code_alone_does_not_open_revision(): void
    {
        [$application] = $this->issueRevision();

        $this->get('/akses/'.$application->reference_code)->assertNotFound();
        $this->get(route('capability.membership.revision.show'))->assertNotFound();
    }

    public function test_applicant_resubmits_to_submitted_without_user_or_member(): void
    {
        [$application, $secret] = $this->issueRevision();

        $this->get(route('capability.redeem', ['secret' => $secret]))->assertRedirect();

        $this->post(route('capability.membership.revision.update'), [
            'legal_name' => 'Sari Petani Revisi',
            'email' => 'sari.revisi@example.com',
            'phone' => '081298765432',
            'address' => 'Alamat baru yang lebih lengkap',
            'applicant_notes' => 'Sudah dilengkapi.',
            'applicant_photo' => UploadedFile::fake()->image('baru.jpg', 200, 200),
        ])->assertRedirect(route('capability.membership.revision.submitted'));

        $application->refresh();
        $this->assertSame(MembershipApplicationStatus::Submitted, $application->status);
        $this->assertSame('Sari Petani Revisi', $application->legal_name);
        $this->assertSame($application->id, MembershipApplication::query()->value('id'));
        $this->assertNotNull($application->resubmitted_at);
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'from_status' => 'revision_requested',
            'to_status' => 'submitted',
            'actor_context' => 'applicant',
        ]);
        $this->assertNotNull(SecureCapabilityGrant::query()->value('consumed_at'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('members', 0);
        $this->assertFalse(Role::query()->where('name', 'member')->exists());

        $this->get(route('capability.redeem', ['secret' => $secret]))->assertNotFound();
        $this->get(route('capability.membership.revision.show'))->assertNotFound();
    }

    public function test_invalid_revision_fields_are_rejected_without_status_change(): void
    {
        [$application, $secret] = $this->issueRevision();

        $this->get(route('capability.redeem', ['secret' => $secret]))->assertRedirect();

        $this->post(route('capability.membership.revision.update'), [
            'legal_name' => '',
            'email' => 'bukan-email',
            'phone' => 'abc',
            'address' => '',
        ])->assertSessionHasErrors(['legal_name', 'email', 'phone', 'address']);

        $this->assertSame('revision_requested', $application->fresh()->status->value);
        $this->assertNull($application->fresh()->resubmitted_at);
        $this->assertNull(SecureCapabilityGrant::query()->value('consumed_at'));
    }

    public function test_staff_must_start_review_again_after_resubmit(): void
    {
        $staff = $this->revisionStaff();
        [, $secret] = $this->issueRevision($staff);

        $this->get(route('capability.redeem', ['secret' => $secret]))->assertRedirect();
        $this->post(route('capability.membership.revision.update'), [
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'address' => 'Alamat lengkap hasil revisi',
        ])->assertRedirect();

        $application = MembershipApplication::query()->firstOrFail();
        $this->assertSame('submitted', $application->status->value);

        $this->from(route('admin.membership.applications.show', $application))
            ->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('members', 0);

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.review', $application))
            ->assertRedirect();

        $this->assertSame('under_review', $application->fresh()->status->value);
    }

    public function test_stale_approve_after_revision_request_is_rejected(): void
    {
        $staff = $this->revisionStaff();
        $application = MembershipApplication::factory()->underReview()->create();

        $this->actingAs($staff)
            ->post(route('admin.membership.applications.revision', $application), [
                'revision_reason' => 'Lengkapi alamat lengkap dan unggah foto yang lebih jelas.',
            ])
            ->assertRedirect();

        $this->from(route('admin.membership.applications.show', $application))
            ->actingAs($staff)
            ->post(route('admin.membership.applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('revision_requested', $application->fresh()->status->value);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_reissue_revokes_the_previous_grant(): void
    {
        $staff = $this->revisionStaff();
        [$application, $firstSecret] = $this->issueRevision($staff);

        $response = $this->actingAs($staff)
            ->post(route('admin.membership.applications.revision', $application), [
                'revision_reason' => 'Tambahan: perbarui nomor telepon yang dapat dihubungi.',
            ]);

        $url = (string) $response->getSession()->get('revision_capability_url');
        $secondSecret = basename(parse_url($url, PHP_URL_PATH) ?: '');

        $this->assertDatabaseCount('secure_capability_grants', 2);
        $this->assertNotNull(SecureCapabilityGrant::query()->orderBy('id')->value('revoked_at'));

        $this->logoutTestUser();
        $this->get(route('capability.redeem', ['secret' => $firstSecret]))->assertNotFound();
        $this->get(route('capability.redeem', ['secret' => $secondSecret]))
            ->assertRedirect(route('capability.membership.revision.show'));
    }

    public function test_revision_workspace_cannot_approve_or_see_another_application(): void
    {
        [, $secret] = $this->issueRevision();
        $other = MembershipApplication::factory()->underReview()->create();

        $this->logoutTestUser();
        $this->get(route('capability.redeem', ['secret' => $secret]))->assertRedirect();

        $this->post(route('admin.membership.applications.approve', $other))->assertRedirect(route('login'));
        $this->get(route('admin.membership.applications.show', $other))->assertRedirect(route('login'));
        $this->assertDatabaseCount('members', 0);
    }

    /**
     * @return array{0: MembershipApplication, 1: string}
     */
    private function issueRevision(?User $staff = null): array
    {
        $staff ??= $this->revisionStaff();
        $application = MembershipApplication::factory()->underReview()->create([
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'staff_notes' => 'Jangan tampilkan ini ke pemohon',
        ]);

        $response = $this->actingAs($staff)
            ->post(route('admin.membership.applications.revision', $application), [
                'revision_reason' => 'Lengkapi alamat lengkap dan unggah foto yang lebih jelas.',
            ]);

        $url = (string) $response->getSession()->get('revision_capability_url');
        $secret = basename(parse_url($url, PHP_URL_PATH) ?: '');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return [$application->fresh(), $secret];
    }

    private function logoutTestUser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    private function revisionStaff(): User
    {
        return $this->userWithPermissions([
            'membership-applications.view',
            'membership-applications.review',
            'membership-applications.approve',
            'membership-applications.reject',
            'membership-applications.request-revision',
        ]);
    }
}
