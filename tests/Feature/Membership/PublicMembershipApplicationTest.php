<?php

namespace Tests\Feature\Membership;

use App\Actions\Membership\SubmitMembershipApplication;
use App\Models\MembershipApplication;
use App\Models\MembershipDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\SubmitsPublicMembershipApplication;
use Tests\TestCase;

class PublicMembershipApplicationTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsPublicMembershipApplication;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('membership_documents');
    }

    public function test_anonymous_visitor_can_access_intake(): void
    {
        $this->get(route('membership.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Membership/Index')
                ->where('applyUrl', route('membership.apply')));

        $this->get(route('membership.apply'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Membership/Apply')
                ->has('intakeToken')
                ->has('documentConstraints.maxKilobytes')
                ->missing('documentConstraints.disk')
                ->missing('status'));
    }

    public function test_valid_application_can_be_submitted_without_creating_a_user_or_member(): void
    {
        $response = $this->postPublicMembership($this->validPublicMembershipPayload());

        $response->assertRedirect(route('membership.apply.submitted'));

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('membership_applications', 1);
        $this->assertDatabaseHas('membership_applications', [
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'status' => 'submitted',
        ]);

        $application = MembershipApplication::query()->first();
        $this->assertNotNull($application?->reference_code);
        $this->assertNotSame((string) $application->id, $application->reference_code);
        $this->assertTrue(str_starts_with($application->reference_code, 'APP-'));
        $this->assertNotNull($application->submitted_at);
        $this->assertNull($application->approved_by);
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseHas('membership_application_transitions', [
            'membership_application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'actor_context' => 'applicant',
        ]);

        $this->get(route('membership.apply.submitted'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Membership/Submitted')
                ->where('referenceCode', $application->reference_code)
                ->missing('application')
                ->missing('staff_notes')
                ->missing('status'));
    }

    public function test_invalid_application_is_rejected(): void
    {
        $this->from(route('membership.apply'))
            ->postPublicMembership([
                'legal_name' => '',
                'email' => 'not-an-email',
                'phone' => '',
                'address' => '',
            ])
            ->assertRedirect(route('membership.apply'))
            ->assertSessionHasErrors(['legal_name', 'email', 'phone', 'address']);

        $this->assertDatabaseCount('membership_applications', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_phone_must_contain_digits_without_inventing_a_national_format(): void
    {
        $this->from(route('membership.apply'))
            ->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
                'phone' => 'abc',
            ]))
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('membership_applications', 0);

        $this->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
            'phone' => '+62 812-3456-7890',
        ]))->assertRedirect(route('membership.apply.submitted'));

        $this->assertDatabaseCount('membership_applications', 1);
    }

    public function test_client_cannot_set_protected_workflow_fields(): void
    {
        $this->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
            'status' => 'approved',
            'approved_by' => 99,
            'approved_at' => now()->toDateTimeString(),
            'reviewed_by' => 99,
            'reference_code' => 'HACK-CODE',
            'staff_notes' => 'should be ignored',
        ]))->assertRedirect(route('membership.apply.submitted'));

        $application = MembershipApplication::query()->first();

        $this->assertNotNull($application);
        $this->assertSame('submitted', $application->status->value);
        $this->assertNull($application->approved_by);
        $this->assertNull($application->approved_at);
        $this->assertNull($application->reviewed_by);
        $this->assertNull($application->staff_notes);
        $this->assertNotSame('HACK-CODE', $application->reference_code);
    }

    public function test_optional_document_is_stored_privately(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 200, 200);

        $this->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
            'applicant_photo' => $file,
        ]))->assertRedirect(route('membership.apply.submitted'));

        $application = MembershipApplication::query()->with('currentDocuments')->first();
        $document = $application?->currentDocuments->first();

        $this->assertNotNull($document);
        $this->assertSame('applicant_photo', $document->document_type->value);
        $this->assertStringNotContainsString('..', $document->object_key);
        Storage::disk('membership_documents')->assertExists($document->object_key);
        $this->assertFalse(str_contains($document->object_key, 'foto.jpg'));
    }

    public function test_rejected_document_does_not_create_an_application(): void
    {
        $this->from(route('membership.apply'))
            ->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
                'applicant_photo' => UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload'),
            ]))
            ->assertSessionHasErrors('applicant_photo');

        $this->assertDatabaseCount('membership_applications', 0);
        $this->assertDatabaseCount('membership_documents', 0);
        $this->assertSame([], Storage::disk('membership_documents')->allFiles());
    }

    public function test_oversized_document_is_rejected(): void
    {
        $this->from(route('membership.apply'))
            ->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
                'identity_document' => UploadedFile::fake()->create('ktp.pdf', 6000, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('identity_document');

        $this->assertDatabaseCount('membership_applications', 0);
    }

    public function test_failed_document_metadata_does_not_leave_orphan_files_or_partial_applications(): void
    {
        MembershipDocument::creating(function (): void {
            throw new RuntimeException('metadata write failed');
        });

        $file = UploadedFile::fake()->image('foto.jpg', 200, 200);

        try {
            app(SubmitMembershipApplication::class)->handle($this->validPublicMembershipPayload(), [
                'applicant_photo' => $file,
            ]);
            $this->fail('Expected document metadata failure');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('membership_applications', 0);
            $this->assertDatabaseCount('membership_documents', 0);
            $this->assertSame([], Storage::disk('membership_documents')->allFiles());
        } finally {
            MembershipDocument::getEventDispatcher()
                ->forget('eloquent.creating: '.MembershipDocument::class);
        }
    }

    public function test_repeat_submit_with_the_same_intake_token_does_not_create_a_second_application(): void
    {
        $token = (string) Str::uuid();

        $this->postPublicMembership($this->validPublicMembershipPayload(), $token)
            ->assertRedirect(route('membership.apply.submitted'));

        $this->postPublicMembership($this->validPublicMembershipPayload(), $token)
            ->assertRedirect(route('membership.apply.submitted'));

        $this->assertDatabaseCount('membership_applications', 1);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_a_new_form_token_allows_another_application_with_the_same_email(): void
    {
        $this->postPublicMembership($this->validPublicMembershipPayload())
            ->assertRedirect(route('membership.apply.submitted'));

        $this->postPublicMembership($this->validPublicMembershipPayload())
            ->assertRedirect(route('membership.apply.submitted'));

        $this->assertSame(2, MembershipApplication::query()->where('email', 'sari@example.com')->count());
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_intake_token_is_rejected(): void
    {
        $this->from(route('membership.apply'))
            ->post(route('membership.apply.store'), $this->validPublicMembershipPayload())
            ->assertSessionHasErrors('intake_token');

        $this->assertDatabaseCount('membership_applications', 0);
    }

    public function test_reference_code_query_parameter_does_not_authorize_or_reveal_an_application(): void
    {
        $this->postPublicMembership($this->validPublicMembershipPayload());
        $application = MembershipApplication::query()->firstOrFail();

        $this->flushSession();

        $this->get(route('membership.apply.submitted', [
            'reference_code' => $application->reference_code,
            'id' => $application->id,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Frontend/Membership/Submitted')
                ->where('referenceCode', null)
                ->missing('application'));

        $this->get('/keanggotaan/'.$application->reference_code)->assertNotFound();
        $this->get(route('admin.membership.applications.show', $application))
            ->assertRedirect(route('login'));
    }

    public function test_reference_code_does_not_authorize_private_document_download(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 200, 200);
        $this->postPublicMembership(array_merge($this->validPublicMembershipPayload(), [
            'applicant_photo' => $file,
        ]));

        $document = MembershipDocument::query()->firstOrFail();

        $this->flushSession();

        $this->get(route('admin.membership.documents.show', $document))
            ->assertRedirect(route('login'));
    }

    public function test_confirmation_refresh_does_not_create_another_application(): void
    {
        $this->postPublicMembership($this->validPublicMembershipPayload())
            ->assertRedirect(route('membership.apply.submitted'));

        $this->get(route('membership.apply.submitted'))->assertOk();
        $this->get(route('membership.apply.submitted'))->assertOk();

        $this->assertDatabaseCount('membership_applications', 1);
    }
}
