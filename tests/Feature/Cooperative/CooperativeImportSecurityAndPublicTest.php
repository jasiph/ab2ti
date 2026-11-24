<?php

namespace Tests\Feature\Cooperative;

use App\Models\BusinessParticipation;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\CooperativePublicStats;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativeImportSecurityAndPublicTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_non_staff_cannot_access_imports(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.cooperative.imports.index'))
            ->assertForbidden();
    }

    public function test_editor_without_cooperative_permission_is_denied(): void
    {
        $editor = $this->userWithPermissions(['cms.view']);

        $this->actingAs($editor)
            ->get(route('admin.cooperative.imports.index'))
            ->assertForbidden();

        $this->actingAs($editor)
            ->get(route('admin.cooperative.psn.index'))
            ->assertForbidden();
    }

    public function test_member_cannot_import_or_see_another_members_holdings(): void
    {
        $this->syncAclFromConfig();
        $account = User::factory()->create();
        $member = Member::factory()->linked($account)->create();
        CooperativeMembership::factory()->create(['member_id' => $member->id]);

        $other = CooperativeMembership::factory()->create();
        $participation = BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $other->id,
        ]);
        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'units' => 7,
            'amount' => 7_000_000,
            'share_reference' => '99-SECRET',
        ]);

        $this->actingAs($account)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent('x.csv', "Nama\nA\n"),
            ])
            ->assertForbidden();

        $this->actingAs($account)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cooperative.psn.has_psn', false)
                ->missing('cooperative.psn.raw_data'))
            ->assertDontSee('99-SECRET');
    }

    public function test_public_stats_use_committed_canonical_holdings_only(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $active = CooperativeMembership::factory()->create();
        $inactive = CooperativeMembership::factory()->inactive()->create();

        $activeParticipation = BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $active->id,
        ]);
        ParticipationEntry::factory()->create([
            'business_participation_id' => $activeParticipation->id,
            'units' => 50,
            'amount' => 50_000_000,
        ]);

        $inactiveParticipation = BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $inactive->id,
        ]);
        ParticipationEntry::factory()->create([
            'business_participation_id' => $inactiveParticipation->id,
            'units' => 2,
            'amount' => 2_000_000,
        ]);

        CooperativeImportBatch::factory()->create();

        $stats = app(CooperativePublicStats::class)->toArray();

        $this->assertNull($stats['total_psn_shares']);
        $this->assertNull($stats['psn_participants']);
        $this->assertFalse($stats['psn_legacy_reconciliation_complete']);

        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_psn_shares', null)
                ->where('stats.psn_participants', null)
                ->where('stats.member_registry_complete', false)
                ->where('stats.psn_legacy_reconciliation_complete', false)
                ->where('stats.total_ab2ti_members', null)
                ->where('stats.participation_percentage', null)
                ->missing('stats.raw_data')
                ->missing('memberships'))
            ->assertDontSee($active->member->legal_name)
            ->assertDontSee('raw_data')
            ->assertDontSee('100,0%');
    }

    public function test_public_psn_aggregates_appear_only_when_reconciliation_is_marked_complete(): void
    {
        config(['cooperative.psn_legacy_reconciliation_complete' => true]);

        $active = CooperativeMembership::factory()->create();
        $participation = BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $active->id,
        ]);
        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'units' => 50,
            'amount' => 50_000_000,
        ]);

        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.psn_legacy_reconciliation_complete', true)
                ->where('stats.total_psn_shares', 50)
                ->where('stats.psn_participants', 1));
    }

    public function test_zero_psn_participation_renders_gracefully(): void
    {
        $this->get('/koperasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_psn_shares', null)
                ->where('stats.psn_participants', null));
    }

    public function test_member_sees_only_own_psn_position(): void
    {
        $this->syncAclFromConfig();
        $account = User::factory()->create();
        $member = Member::factory()->linked($account)->create();
        $membership = CooperativeMembership::factory()->create(['member_id' => $member->id]);
        $participation = BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $membership->id,
        ]);
        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'units' => 50,
            'amount' => 50_000_000,
            'share_reference' => '32-1-000181',
        ]);

        $this->actingAs($account)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cooperative.psn.has_psn', true)
                ->where('cooperative.psn.units', 50)
                ->missing('cooperative.psn.raw_data')
                ->missing('cooperative.notes'));
    }

    public function test_membership_admin_role_receives_cooperative_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = \Spatie\Permission\Models\Role::findByName('membership-admin');

        $this->assertTrue($role->hasPermissionTo('cooperative.view'));
        $this->assertTrue($role->hasPermissionTo('cooperative.manage'));
    }
}
