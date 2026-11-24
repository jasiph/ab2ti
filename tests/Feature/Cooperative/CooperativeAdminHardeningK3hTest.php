<?php

namespace Tests\Feature\Cooperative;

use App\Actions\Cooperative\AdjustPsnParticipation;
use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewStatus;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMembershipStatus;
use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\PsnAdjustmentReason;
use App\Exceptions\Cooperative\StalePsnConfirmationException;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\CooperativeAdminDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativeAdminHardeningK3hTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_stale_confirmation_is_rejected_and_does_not_mutate(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);
        $stale = $this->validAdjustPayload(6, 5);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(7, 5))
            ->assertRedirect();

        $this->assertSame(7, $membership->psnParticipation()->firstOrFail()->currentUnits());
        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $stale)
            ->assertRedirect(route('admin.cooperative.memberships.psn.edit', $membership))
            ->assertSessionHas('error')
            ->assertSessionHas('stale_psn', [
                'reviewed_units' => 5,
                'current_units' => 7,
            ]);

        $this->assertSame(7, $membership->psnParticipation()->firstOrFail()->currentUnits());
        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_action_stale_state_throws_without_ledger_write(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);

        app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->actionPayload(7, 5));

        $this->expectException(StalePsnConfirmationException::class);

        try {
            app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->actionPayload(6, 5));
        } finally {
            $this->assertSame(7, $membership->psnParticipation()->firstOrFail()->currentUnits());
            $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
        }
    }

    public function test_identical_confirmation_token_does_not_duplicate_ledger(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);
        $token = (string) Str::uuid();
        $payload = $this->validAdjustPayload(6, 5, $token);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect(route('admin.cooperative.memberships.show', $membership));

        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
        $this->assertSame(6, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_reused_token_with_different_desired_units_is_rejected(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);
        $token = (string) Str::uuid();

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(6, 5, $token))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(8, 6, $token))
            ->assertSessionHas('error');

        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
        $this->assertSame(6, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_backend_ignores_submitted_current_units_and_delta(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(4);
        $payload = $this->validAdjustPayload(6, 4);
        $payload['current_units'] = 99;
        $payload['delta'] = 50;
        $payload['previous_value'] = 1;
        $payload['resulting_value'] = 999;
        $payload['amount'] = 1;

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect();

        $adjustment = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->firstOrFail();

        $this->assertSame(2, (int) $adjustment->units);
        $this->assertSame(4, $adjustment->provenance['previous_units']);
        $this->assertSame(6, $adjustment->provenance['desired_units']);
        $this->assertSame(6, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_decimal_scientific_and_negative_units_are_rejected(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);

        foreach (['1.5', '1,5', '1e3', '-1'] as $invalid) {
            $payload = $this->validAdjustPayload(3, 2);
            $payload['desired_units'] = $invalid;

            $this->actingAs($manager)
                ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
                ->assertSessionHasErrors('desired_units');
        }

        $this->assertSame(0, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
        $this->assertSame(2, $membership->psnParticipation()->firstOrFail()->currentUnits());
    }

    public function test_other_reason_without_note_is_rejected(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);
        $payload = $this->validAdjustPayload(3, 2);
        $payload['reason'] = PsnAdjustmentReason::Other->value;
        $payload['note'] = '   ';

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertSessionHasErrors('note');
    }

    public function test_reduction_requires_note_and_acknowledgement(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(10);
        $payload = $this->validAdjustPayload(7, 10);
        $payload['note'] = '';
        $payload['acknowledge_reduction'] = false;
        $payload['reason'] = PsnAdjustmentReason::ReductionVerified->value;

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertSessionHasErrors(['note', 'acknowledge_reduction']);
    }

    public function test_future_effective_date_is_rejected(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);
        $payload = $this->validAdjustPayload(3, 2);
        $payload['effective_date'] = now('Asia/Jakarta')->addDay()->toDateString();

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertSessionHasErrors('effective_date');
    }

    public function test_unit_value_is_snapshotted_on_the_entry(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(2);
        $unitValue = (int) CooperativeBusiness::psn()->current_unit_value;

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(3, 2));

        $adjustment = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->firstOrFail();

        $this->assertSame($unitValue, (int) $adjustment->unit_value);
        $this->assertSame($unitValue, $adjustment->provenance['unit_value']);
        $this->assertSame($unitValue, (int) $adjustment->amount);
    }

    public function test_correction_creates_new_entry_with_correction_of_provenance(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(8, 5))
            ->assertRedirect();

        $wrong = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->firstOrFail();
        $openingUnits = (int) ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::OpeningBalance)
            ->value('units');

        $payload = $this->validAdjustPayload(5, 8);
        $payload['reason'] = PsnAdjustmentReason::AdministrativeCorrection->value;
        $payload['correction_of_entry_id'] = $wrong->id;

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect();

        $correction = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->whereKeyNot($wrong->id)
            ->firstOrFail();

        $this->assertSame(-3, (int) $correction->units);
        $this->assertSame($wrong->id, $correction->provenance['correction_of']);
        $this->assertSame('correction', $correction->provenance['origin']);
        $this->assertNull($correction->reverses_entry_id);
        $this->assertSame(3, (int) $wrong->fresh()->units);
        $this->assertSame($openingUnits, (int) ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::OpeningBalance)
            ->value('units'));
        $this->assertSame(5, $membership->psnParticipation()->firstOrFail()->currentUnits());
        $this->assertSame(2, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_no_ordinary_route_edits_or_deletes_participation_entries(): void
    {
        $mutating = collect(Route::getRoutes())->filter(function ($route): bool {
            $uri = $route->uri();
            $name = (string) $route->getName();
            $methods = array_diff($route->methods(), ['HEAD', 'GET', 'OPTIONS']);

            if ($methods === []) {
                return false;
            }

            return str_contains($uri, 'participation') && (
                str_contains($name, 'participation') || str_contains($uri, 'entries')
            );
        });

        $this->assertTrue($mutating->isEmpty(), 'Ordinary ParticipationEntry mutation routes must not exist.');
        $this->assertSame('POST', collect(Route::getRoutes()->getByName('admin.cooperative.memberships.psn.adjust')->methods())->first());
    }

    public function test_get_edit_and_get_detail_do_not_mutate(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(4);
        $before = ParticipationEntry::query()->count();

        $this->actingAs($manager)
            ->get(route('admin.cooperative.memberships.psn.edit', $membership))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('admin.cooperative.memberships.show', $membership))
            ->assertOk();

        $this->get('/admin/koperasi/anggota/'.$membership->id.'/psn/adjust')
            ->assertMethodNotAllowed();

        $this->assertSame($before, ParticipationEntry::query()->count());
    }

    public function test_unresolved_psn_redirects_to_reconciliation_and_blocks_adjust(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnresolvedPsn();

        $this->actingAs($manager)
            ->get(route('admin.cooperative.memberships.show', $membership))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canAdjustPsn', false)
                ->where('psnAdjustmentBlock', 'unresolved')
                ->where('membership.psn.state', 'unresolved')
                ->where('membership.psn.units', null));

        $this->actingAs($manager)
            ->get(route('admin.cooperative.memberships.psn.edit', $membership))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(4, 0))
            ->assertRedirect();

        $this->assertSame(0, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_inactive_membership_cannot_use_ordinary_adjustment(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(4);
        $membership->status = CooperativeMembershipStatus::Inactive;
        $membership->applyStatusSideEffects();
        $membership->save();

        $this->actingAs($manager)
            ->get(route('admin.cooperative.memberships.psn.edit', $membership))
            ->assertRedirect(route('admin.cooperative.memberships.show', $membership));

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $this->validAdjustPayload(5, 4))
            ->assertRedirect(route('admin.cooperative.memberships.show', $membership));

        $this->assertSame(4, $membership->psnParticipation()->firstOrFail()->currentUnits());
        $this->assertSame(CooperativeMembershipStatus::Inactive, $membership->fresh()->status);
        $this->assertSame(0, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_forged_resource_ids_are_rejected(): void
    {
        $manager = $this->manager();
        $membershipA = $this->membershipWithUnits(3, ['legal_name' => 'Member A']);
        $membershipB = $this->membershipWithUnits(4, ['legal_name' => 'Member B']);
        $payload = $this->validAdjustPayload(5, 3);
        $payload['membership_id'] = $membershipB->id;
        $payload['member_id'] = $membershipB->member_id;
        $payload['participation_id'] = $membershipB->psnParticipation()->firstOrFail()->id;

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membershipA), $payload)
            ->assertRedirect(route('admin.cooperative.memberships.psn.edit', $membershipA))
            ->assertSessionHas('error');

        $this->assertSame(3, $membershipA->psnParticipation()->firstOrFail()->currentUnits());
        $this->assertSame(4, $membershipB->psnParticipation()->firstOrFail()->currentUnits());
        $this->assertSame(0, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_audit_provenance_is_complete(): void
    {
        $manager = $this->manager();
        $membership = $this->membershipWithUnits(5);
        $token = (string) Str::uuid();
        $effective = now('Asia/Jakarta')->toDateString();
        $payload = $this->validAdjustPayload(7, 5, $token);
        $payload['effective_date'] = $effective;
        $payload['note'] = 'Documented correction from minutes';

        $this->actingAs($manager)
            ->post(route('admin.cooperative.memberships.psn.adjust', $membership), $payload)
            ->assertRedirect()
            ->assertSessionHas('psn_adjustment_result');

        $entry = ParticipationEntry::query()
            ->where('entry_type', ParticipationEntryType::Adjustment)
            ->firstOrFail();

        $this->assertSame($manager->id, $entry->provenance['operator_id']);
        $this->assertSame($membership->id, $entry->provenance['membership_id']);
        $this->assertSame($membership->member_id, $entry->provenance['member_id']);
        $this->assertSame(5, $entry->provenance['previous_units']);
        $this->assertSame(7, $entry->provenance['desired_units']);
        $this->assertSame(2, $entry->provenance['delta_units']);
        $this->assertSame(7, $entry->provenance['resulting_units']);
        $this->assertSame(5, $entry->provenance['expected_current_units']);
        $this->assertSame($effective, $entry->provenance['effective_date']);
        $this->assertNotNull($entry->created_at);
        $this->assertSame($effective, $entry->effective_date_start?->toDateString());
        $this->assertSame(PsnAdjustmentReason::AdministrativeCorrection->value, $entry->provenance['reason_code']);
        $this->assertSame('Documented correction from minutes', $entry->provenance['administrative_note']);
        $this->assertSame('correction', $entry->provenance['origin']);
        $this->assertSame($token, $entry->confirmation_token);
        $this->assertSame((int) CooperativeBusiness::psn()->current_unit_value, $entry->provenance['unit_value']);
    }

    public function test_dashboard_stats_use_canonical_queries_without_double_count(): void
    {
        $this->membershipWithUnits(2);
        $this->membershipWithUnits(3);
        $inactive = $this->membershipWithUnits(9);
        $inactive->status = CooperativeMembershipStatus::Inactive;
        $inactive->applyStatusSideEffects();
        $inactive->save();
        $this->membershipWithUnresolvedPsn();

        $dashboard = app(CooperativeAdminDashboard::class)->toArray();

        $this->assertSame(3, $dashboard['active_cooperative_members']);
        $this->assertSame(2, $dashboard['resolved_psn_participants']);
        $this->assertSame(5, $dashboard['resolved_psn_units']);
        $this->assertSame(1, $dashboard['pending_psn_reconciliation_rows']);
        $this->assertSame(1, $dashboard['unique_members_with_pending_review']);
    }

    public function test_search_is_case_whitespace_and_phone_tolerant_without_nik(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view']);
        $match = $this->membershipWithUnits(1, [
            'legal_name' => 'Siti Rahayu',
            'email' => 'siti.rahayu@example.test',
            'membership_number' => 'AB2TI/08/I/0099',
            'phone' => '0812-3456-7890',
        ]);
        $this->membershipWithUnits(1, [
            'legal_name' => 'Other Person',
            'email' => 'other@example.test',
            'phone' => '089900000000',
        ]);

        $this->actingAs($staff);

        foreach (['siti', '  SITI   rahayu ', 'AB2TI/08/I/0099', 'siti.rahayu@example.test', '081234567890', '+6281234567890'] as $term) {
            $this->get(route('admin.cooperative.memberships.index', ['search' => $term]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('memberships.data', 1)
                    ->where('memberships.data.0.id', $match->id));
        }
    }

    public function test_pending_review_filter_excludes_resolved_warnings(): void
    {
        $staff = $this->userWithPermissions(['cooperative.view']);
        $pending = $this->membershipWithUnresolvedPsn(['legal_name' => 'Pending PSN']);
        $resolvedMembership = $this->membershipWithUnits(2, ['legal_name' => 'Resolved Review']);
        $batch = CooperativeImportBatch::factory()->create([
            'status' => CooperativeImportBatchStatus::CompletedWithReview,
            'completed_at' => now(),
        ]);
        $row = CooperativeImportRow::factory()->create([
            'batch_id' => $batch->id,
            'created_member_id' => $resolvedMembership->member_id,
            'validation_status' => CooperativeImportRowStatus::Committed,
        ]);
        CooperativeImportReviewItem::query()->create([
            'batch_id' => $batch->id,
            'import_row_id' => $row->id,
            'category' => CooperativeImportReviewCategory::PsnUnconfirmed,
            'warning_code' => 'amount_not_divisible_by_unit_value',
            'status' => CooperativeImportReviewStatus::Resolved,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.cooperative.memberships.index', ['review' => 'psn_pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('memberships.data', 1)
                ->where('memberships.data.0.id', $pending->id));
    }

    public function test_view_cannot_open_adjust_page(): void
    {
        $viewer = $this->userWithPermissions(['cooperative.view']);
        $membership = $this->membershipWithUnits(2);

        $this->actingAs($viewer)
            ->get(route('admin.cooperative.memberships.psn.edit', $membership))
            ->assertForbidden();
    }

    public function test_adjust_route_is_session_web_post_not_get(): void
    {
        $route = Route::getRoutes()->getByName('admin.cooperative.memberships.psn.adjust');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
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

        return $membership->fresh(['psnParticipation.entries', 'member']);
    }

    /**
     * @param  array<string, mixed>  $memberAttributes
     */
    private function membershipWithUnresolvedPsn(array $memberAttributes = []): CooperativeMembership
    {
        $membership = CooperativeMembership::factory()->create([
            'member_id' => Member::factory()->create($memberAttributes)->id,
        ]);
        $batch = CooperativeImportBatch::factory()->create([
            'status' => CooperativeImportBatchStatus::CompletedWithReview,
            'completed_at' => now(),
        ]);
        $row = CooperativeImportRow::factory()->create([
            'batch_id' => $batch->id,
            'created_member_id' => $membership->member_id,
            'validation_status' => CooperativeImportRowStatus::NeedsReview,
        ]);
        CooperativeImportReviewItem::query()->create([
            'batch_id' => $batch->id,
            'import_row_id' => $row->id,
            'category' => CooperativeImportReviewCategory::PsnUnconfirmed,
            'warning_code' => 'amount_not_divisible_by_unit_value',
            'status' => CooperativeImportReviewStatus::Pending,
        ]);

        return $membership->fresh(['member']);
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

    /**
     * @return array<string, mixed>
     */
    private function actionPayload(int $desired, int $expectedCurrent): array
    {
        $payload = $this->validAdjustPayload($desired, $expectedCurrent);
        unset($payload['confirm'], $payload['acknowledge_reduction']);

        return $payload;
    }
}
