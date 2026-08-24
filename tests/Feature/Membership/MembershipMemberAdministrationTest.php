<?php

namespace Tests\Feature\Membership;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class MembershipMemberAdministrationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_authorized_staff_can_view_the_member_registry(): void
    {
        $staff = $this->userWithPermissions(['membership-members.view']);
        $member = Member::factory()->create([
            'legal_name' => 'Budi Tani',
            'email' => 'budi@example.com',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.membership.members.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Membership/Members/Index')
                ->has('members.data', 1)
                ->where('members.data.0.legal_name', 'Budi Tani')
                ->where('members.data.0.membership_number', $member->membership_number)
                ->missing('members.data.0.phone')
                ->missing('members.data.0.address')
                ->missing('members.data.0.card_issued'));
    }

    public function test_unauthorized_user_cannot_view_members(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();
        $member = Member::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.membership.members.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.membership.members.show', $member))
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('admin.membership.members.update', $member), [
                'legal_name' => 'Tidak Boleh',
                'email' => 'no@example.com',
                'phone' => '081234567890',
                'address' => 'Alamat',
            ])
            ->assertForbidden();
    }

    public function test_member_detail_separates_source_application_and_does_not_expose_card_workflow(): void
    {
        $staff = $this->userWithPermissions([
            'membership-members.view',
            'membership-applications.view',
        ]);
        $application = MembershipApplication::factory()->approved()->create();
        $member = Member::factory()->create([
            'source_application_id' => $application->id,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.membership.members.show', $member))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Membership/Members/Show')
                ->where('member.membership_number', $member->membership_number)
                ->where('member.source_application.reference_code', $application->reference_code)
                ->where('canViewSourceApplication', true)
                ->missing('member.card_issued')
                ->missing('member.card_issued_at')
                ->missing('member.documents'));

        $this->assertInstanceOf(Member::class, $member);
        $this->assertInstanceOf(MembershipApplication::class, $application);
        $this->assertSame($application->id, $member->source_application_id);
        $this->assertNotSame($member->membership_number, $application->reference_code);
    }

    public function test_authorized_staff_can_correct_contact_without_changing_provenance(): void
    {
        $staff = $this->userWithPermissions(['membership-members.view', 'membership-members.update']);
        $application = MembershipApplication::factory()->approved()->create();
        $member = Member::factory()->create([
            'source_application_id' => $application->id,
            'legal_name' => 'Nama Lama',
            'email' => 'lama@example.com',
            'phone' => '081111111111',
            'address' => 'Alamat lama',
        ]);
        $number = $member->membership_number;
        $recognizedAt = $member->recognized_at?->toDateTimeString();

        $this->actingAs($staff)
            ->patch(route('admin.membership.members.update', $member), [
                'legal_name' => 'Nama Baru',
                'email' => 'baru@example.com',
                'phone' => '081222222222',
                'address' => 'Alamat baru yang lengkap',
                'membership_number' => 'FORGED-CARD-NUMBER',
                'source_application_id' => 999999,
                'status' => 'inactive',
                'card_issued' => true,
            ])
            ->assertRedirect(route('admin.membership.members.show', $member));

        $member->refresh();
        $this->assertSame('Nama Baru', $member->legal_name);
        $this->assertSame('baru@example.com', $member->email);
        $this->assertSame($number, $member->membership_number);
        $this->assertSame($application->id, $member->source_application_id);
        $this->assertSame('active', $member->status->value);
        $this->assertSame($recognizedAt, $member->recognized_at?->toDateTimeString());
        $this->assertFalse($member->card_issued);
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse(Role::query()->where('name', 'member')->exists());
        $this->assertDatabaseCount('members', 1);
    }

    public function test_view_permission_cannot_update_member_contact(): void
    {
        $viewer = $this->userWithPermissions(['membership-members.view']);
        $member = Member::factory()->create(['legal_name' => 'Tetap']);

        $this->actingAs($viewer)
            ->patch(route('admin.membership.members.update', $member), [
                'legal_name' => 'Diubah',
                'email' => 'ubah@example.com',
                'phone' => '081234567890',
                'address' => 'Alamat diubah',
            ])
            ->assertForbidden();

        $this->assertSame('Tetap', $member->fresh()->legal_name);
    }

    public function test_invalid_contact_update_is_rejected(): void
    {
        $staff = $this->userWithPermissions(['membership-members.view', 'membership-members.update']);
        $member = Member::factory()->create();

        $this->actingAs($staff)
            ->from(route('admin.membership.members.show', $member))
            ->patch(route('admin.membership.members.update', $member), [
                'legal_name' => '',
                'email' => 'bukan-email',
                'phone' => 'abc',
                'address' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['legal_name', 'email', 'phone', 'address']);
    }

    public function test_there_is_no_public_member_directory(): void
    {
        $member = Member::factory()->create();

        $this->get('/anggota')->assertNotFound();
        $this->get('/members')->assertNotFound();
        $this->get('/keanggotaan/anggota')->assertNotFound();
        $this->get(route('admin.membership.members.index'))->assertRedirect(route('login'));
        $this->get(route('admin.membership.members.show', $member))->assertRedirect(route('login'));
    }
}
