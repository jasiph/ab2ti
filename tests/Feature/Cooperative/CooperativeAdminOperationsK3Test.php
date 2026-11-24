<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\PsnAdjustmentReason;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativeAdminOperationsK3Test extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_nonstaff_cannot_access_cooperative_dashboard(): void
    {
        $this->syncAclFromConfig();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.cooperative.dashboard'))
            ->assertForbidden();
    }

    public function test_staff_without_cooperative_view_denied(): void
    {
        $staff = $this->userWithPermissions(['lapak.view']);

        $this->actingAs($staff)
            ->get(route('admin.cooperative.dashboard'))
            ->assertForbidden();
    }

    public function test_cooperative_view_can_see_dashboard_but_cannot_adjust(): void
    {
        $viewer = $this->userWithPermissions(['cooperative.view']);
        $membership = $this->membershipWithUnits(3);

        $this->actingAs($viewer)
            ->get(route('admin.cooperative.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Dashboard')
                ->has('dashboard.active_cooperative_members')
                ->has('dashboard.attention')
                ->where('canManage', false));

        $this->actingAs($viewer)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(5, 3))
            ->assertForbidden();
    }

    public function test_cooperative_manage_can_adjust_psn(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(3);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(5, 3))
            ->assertRedirect(route('admin.cooperative.memberships.show', $membership));

        $participation = $membership->psnParticipation()->firstOrFail();
        $this->assertSame(5, $participation->fresh()->currentUnits());
        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_member_user_cannot_call_admin_psn_adjustment(): void
    {
        $this->syncAclFromConfig();
        $memberUser = User::factory()->create();
        $membership = $this->membershipWithUnits(2);

        $this->actingAs($memberUser)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(4, 2))
            ->assertForbidden();
    }

    public function test_increase_creates_positive_adjustment(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(3);
        $openingId = ParticipationEntry::query()->where('entry_type', ParticipationEntryType::OpeningBalance)->value('id');

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(5, 3));

        $adjustment = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->firstOrFail();

        $this->assertSame(2, (int) $adjustment->units);
        $this->assertSame(CooperativeSourceType::Staff, $adjustment->source_type);
        $this->assertSame(3, $adjustment->provenance['previous_units']);
        $this->assertSame(5, $adjustment->provenance['desired_units']);
        $this->assertSame(3, (int) ParticipationEntry::query()->findOrFail($openingId)->units);
        $this->assertSame(5, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_decrease_creates_negative_adjustment(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(3, 5));

        $adjustment = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->firstOrFail();

        $this->assertSame(-2, (int) $adjustment->units);
        $this->assertSame(3, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_noop_desired_units_rejected(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);

        $this->actingAs($manager)
            ->from(route('admin.cooperative.memberships.psn.edit', $membership))
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(5, 5))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_negative_resulting_units_rejected(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);

        $this->actingAs($manager)
            ->from(route('admin.cooperative.memberships.psn.edit', $membership))
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(-1, 2))
            ->assertSessionHasErrors('desired_units');

        $this->assertSame(2, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_adjustment_reason_required(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);
        $payload = $this->validAdjustPayload(4, 2);
        unset($payload['reason']);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertSessionHasErrors('reason');
    }

    public function test_confirm_required_for_adjustment(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);
        $payload = $this->validAdjustPayload(4, 2);
        $payload['confirm'] = false;

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertSessionHasErrors('confirm');
    }

    public function test_member_list_supports_search_and_filters(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view']);
        $withPsn = $this->membershipWithUnits(2, ['legal_name' => 'Alpha PSN', 'email' => 'alpha.psn@example.test']);
        CooperativeMembership::factory()->create([
            'member_id' => Member::factory()->create([
                'legal_name' => 'Beta NoPsn',
                'email' => 'beta.nopsn@example.test',
            ])->id,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.cooperative.memberships.index', ['search' => 'Alpha', 'psn' => 'has_psn']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Memberships/Index')
                ->has('memberships.data', 1)
                ->where('memberships.data.0.id', $withPsn->id)
                ->where('memberships.data.0.psn_units', 2));
    }

    public function test_member_detail_is_unified_profile(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view']);
        $membership = $this->membershipWithUnits(4);

        $this->actingAs($staff)
            ->get(route('admin.cooperative.memberships.show', $membership))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Memberships/Show')
                ->where('membership.psn.units', 4)
                ->has('membership.historical_funds')
                ->has('membership.review')
                ->has('membership.psn.history'));
    }

    public function test_psn_edit_page_is_separate_workflow(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(1);

        $this->actingAs($manager)
            ->get(route('admin.cooperative.memberships.psn.edit', $membership))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Memberships/AdjustPsn')
                ->has('reasons')
                ->where('membership.psn.units', 1));
    }

    public function test_double_identical_submit_after_success_is_noop(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(3);
        $token = (string) Str::uuid();
        $payload = $this->validAdjustPayload(5, 3, $token);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect(route('admin.cooperative.memberships.show', $membership));

        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
        $this->assertSame(5, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    private function manager(): User
    {
        return $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
    }

    /**
     * @param  array<string, mixed>  $memberAttributes
     */
    private function membershipWithUnits(int $units, array $memberAttributes = []): CooperativeMembership
    {
        $membership = CooperativeMembership::factory()->create([
            'member_id' => Member::factory()->create($memberAttributes)->id,
        ]);
        $participation = BusinessParticipation::factory()->create([
            'cooperative_membership_id' => $membership->id,
            'cooperative_business_id' => CooperativeBusiness::psn()->id,
        ]);
        $unitValue = (int) CooperativeBusiness::psn()->current_unit_value;

        ParticipationEntry::factory()->create([
            'business_participation_id' => $participation->id,
            'entry_type' => ParticipationEntryType::OpeningBalance,
            'units' => $units,
            'unit_value' => $unitValue,
            'amount' => $units * $unitValue,
            'source_type' => CooperativeSourceType::LegacyImport,
        ]);

        return $membership->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validAdjustPayload(int $desired, int $expectedCurrent, ?string $token = null): array
    {
        return [
            'desired_units' => $desired,
            'expected_current_units' => $expectedCurrent,
            'confirmation_token' => $token ?? (string) Str::uuid(),
            'effective_date' => now('Asia/Jakarta')->toDateString(),
            'reason' => PsnAdjustmentReason::AdministrativeCorrection->value,
            'note' => 'Test adjustment',
            'confirm' => true,
            'acknowledge_reduction' => $desired < $expectedCurrent,
        ];
    }
}
