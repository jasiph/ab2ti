<?php

namespace Tests\Feature\Membership;

use App\Actions\Membership\ApproveMembershipApplication;
use App\Actions\Membership\CreateAb2tiMemberFromApplication;
use App\Actions\Membership\StartMembershipApplicationReview;
use App\Actions\Membership\SubmitMembershipApplication;
use App\Enums\MembershipApplicationStatus;
use App\Enums\MemberStatus;
use App\Exceptions\InvalidMembershipApplicationTransition;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MembershipDomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_application_persists_with_expected_relationships(): void
    {
        $application = MembershipApplication::factory()->create([
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
        ]);

        $this->assertDatabaseHas('membership_applications', [
            'id' => $application->id,
            'legal_name' => 'Sari Petani',
            'status' => 'submitted',
        ]);
        $this->assertNotNull($application->reference_code);
        $this->assertSame(0, $application->documents()->count());
        $this->assertNull($application->member);
        $this->assertFalse(Schema::hasColumn('membership_applications', 'user_id'));
        $this->assertFalse(Schema::hasColumn('membership_applications', 'edit_token'));
        $this->assertFalse(Schema::hasColumn('membership_applications', 'nik'));
    }

    public function test_application_email_is_not_unique_so_reapply_is_a_new_record(): void
    {
        MembershipApplication::factory()->create(['email' => 'sari@example.com']);
        MembershipApplication::factory()->create(['email' => 'sari@example.com']);

        $this->assertSame(2, MembershipApplication::query()->where('email', 'sari@example.com')->count());
    }

    public function test_ab2ti_member_persists_without_auto_user_link_or_koperasi_columns(): void
    {
        $member = Member::factory()->create([
            'legal_name' => 'Budi Tani',
            'email' => 'budi@example.com',
        ]);

        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertNotNull($member->membership_number);
        $this->assertTrue($member->sourceApplication()->exists());
        $this->assertTrue(Schema::hasColumn('members', 'user_id'));
        $this->assertNull($member->user_id);
        $this->assertFalse(Schema::hasColumn('members', 'koperasi_status'));
        $this->assertFalse(Schema::hasColumn('members', 'koperasi_member_id'));
        $this->assertFalse(Schema::hasColumn('users', 'member_id'));
        $this->assertTrue(method_exists(User::class, 'member'));
        $this->assertTrue(method_exists(Member::class, 'account'));
        $this->assertFalse(method_exists(Member::class, 'user'));
        $this->assertFalse(User::query()->where('email', 'budi@example.com')->exists());
    }

    public function test_source_application_id_is_unique_on_members(): void
    {
        $application = MembershipApplication::factory()->approved()->create();
        Member::factory()->create(['source_application_id' => $application->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        Member::factory()->create(['source_application_id' => $application->id]);
    }

    public function test_membership_number_is_unique_and_is_not_the_numeric_id(): void
    {
        $first = Member::factory()->create();
        $second = Member::factory()->create();

        $this->assertNotSame((string) $first->id, $first->membership_number);
        $this->assertNotSame($first->membership_number, $second->membership_number);

        $this->expectException(UniqueConstraintViolationException::class);
        Member::factory()->create(['membership_number' => $first->membership_number]);
    }

    public function test_submit_does_not_create_a_user_or_member(): void
    {
        $application = app(SubmitMembershipApplication::class)->handle([
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'address' => 'Desa Contoh',
        ]);

        $this->assertSame(MembershipApplicationStatus::Submitted, $application->status);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('members', 0);
        $this->assertNull($application->member);
    }

    public function test_approve_creates_one_member_snapshot_without_a_user(): void
    {
        $staff = User::factory()->create();
        $application = MembershipApplication::factory()->underReview()->create([
            'legal_name' => 'Budi Tani',
            'email' => 'budi@example.com',
            'phone' => '081111111111',
            'address' => 'Alamat Budi',
        ]);

        $member = app(ApproveMembershipApplication::class)->handle($application, $staff);

        $this->assertSame($application->id, $member->source_application_id);
        $this->assertSame('Budi Tani', $member->legal_name);
        $this->assertSame('budi@example.com', $member->email);
        $this->assertSame('081111111111', $member->phone);
        $this->assertSame('Alamat Budi', $member->address);
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertFalse($member->card_issued);
        $this->assertSame($staff->id, $member->created_by);
        $this->assertSame(MembershipApplicationStatus::Approved, $application->fresh()->status);
        $this->assertFalse(User::query()->where('email', 'budi@example.com')->exists());
        $this->assertDatabaseCount('members', 1);
    }

    public function test_member_identity_fields_are_not_mass_assignable(): void
    {
        $member = new Member;
        $member->fill([
            'legal_name' => 'Nama',
            'email' => 'assign@example.com',
            'phone' => '080000000000',
            'address' => 'Alamat',
            'membership_number' => 'FORGED-NUMBER',
            'source_application_id' => 1,
            'status' => 'active',
            'recognized_at' => now(),
            'created_by' => 99,
            'card_issued' => true,
        ]);

        $this->assertSame('Nama', $member->legal_name);
        $this->assertArrayNotHasKey('card_issued', $member->getAttributes());
        $this->assertArrayNotHasKey('membership_number', $member->getAttributes());
        $this->assertArrayNotHasKey('source_application_id', $member->getAttributes());
        $this->assertArrayNotHasKey('status', $member->getAttributes());
        $this->assertArrayNotHasKey('recognized_at', $member->getAttributes());
        $this->assertArrayNotHasKey('created_by', $member->getAttributes());
    }

    public function test_start_review_does_not_treat_revision_requested_as_resubmit(): void
    {
        $staff = User::factory()->create();
        $application = MembershipApplication::factory()->revisionRequested()->create();

        try {
            app(StartMembershipApplicationReview::class)->handle($application, $staff);
            $this->fail('Expected InvalidMembershipApplicationTransition');
        } catch (InvalidMembershipApplicationTransition) {
            $this->assertSame(
                MembershipApplicationStatus::RevisionRequested,
                $application->fresh()->status,
            );
        }
    }

    public function test_create_member_action_does_not_create_a_user(): void
    {
        $staff = User::factory()->create();
        $application = MembershipApplication::factory()->underReview()->create([
            'email' => 'anggota@example.com',
        ]);

        $member = app(CreateAb2tiMemberFromApplication::class)->handle($application, $staff, now());

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame($application->id, $member->source_application_id);
        $this->assertFalse(User::query()->where('email', 'anggota@example.com')->exists());
        $this->assertSame(1, User::query()->count());
    }

    public function test_revision_capability_purpose_is_configured_and_is_not_an_acl_permission(): void
    {
        $purpose = (string) config('membership.revision_capability_purpose');

        $this->assertSame('membership-application-revision', $purpose);

        $permissions = collect(config('acl.permissions'))->flatten();
        $this->assertFalse($permissions->contains($purpose));
        $this->assertFalse($permissions->contains('membership-applications.revise'));
        $this->assertFalse($permissions->contains('membership.applications.approve'));
    }
}
