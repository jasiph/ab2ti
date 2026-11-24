<?php

namespace Tests\Feature\Cooperative;

use App\Actions\Cooperative\AdjustPsnParticipation;
use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\PsnAdjustmentReason;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\Concerns\InteractsWithAcl;
use Tests\TestCase;

class CooperativeAdminHardeningK3hMysqlTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('K3H MySQL verification runs only when DB_CONNECTION=mysql on an isolated schema.');
        }
    }

    public function test_stale_and_idempotent_adjustments_on_mysql(): void
    {
        $manager = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $membership = $this->membershipWithUnits(5);
        $token = (string) Str::uuid();

        app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->payload(7, 5, $token));

        $this->assertSame(7, $membership->psnParticipation()->firstOrFail()->ledgerUnitsSum());

        $replayed = app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->payload(7, 5, $token));
        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
        $this->assertSame($token, $replayed->confirmation_token);

        try {
            app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->payload(8, 5));
            $this->fail('Stale expected_current_units must be rejected on MySQL.');
        } catch (\App\Exceptions\Cooperative\StalePsnConfirmationException $exception) {
            $this->assertSame(5, $exception->reviewedUnits);
            $this->assertSame(7, $exception->currentUnits);
        }

        $this->assertSame(7, $membership->psnParticipation()->firstOrFail()->ledgerUnitsSum());
        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', ParticipationEntryType::Adjustment)->count());
    }

    public function test_lock_for_update_blocks_a_second_mysql_connection(): void
    {
        $membership = $this->membershipWithUnits(5);
        $participationId = $membership->psnParticipation()->firstOrFail()->id;

        DB::beginTransaction();
        BusinessParticipation::query()->whereKey($participationId)->lockForUpdate()->firstOrFail();

        $pdo = $this->secondMysqlPdo();
        $pdo->exec('SET innodb_lock_wait_timeout = 1');
        $pdo->beginTransaction();

        $timedOut = false;
        try {
            $statement = $pdo->prepare('SELECT id FROM business_participations WHERE id = ? FOR UPDATE');
            $statement->execute([$participationId]);
            $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            $timedOut = str_contains($exception->getMessage(), 'Lock wait timeout')
                || str_contains($exception->getMessage(), '1205');
        }

        $pdo->rollBack();
        DB::rollBack();

        $this->assertTrue($timedOut, 'A second MySQL connection must wait on lockForUpdate of the participation row.');
    }

    public function test_two_sequential_committed_adjustments_match_ledger_sum(): void
    {
        $manager = $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
        $membership = $this->membershipWithUnits(5);

        app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->payload(6, 5));
        app(AdjustPsnParticipation::class)->handle($membership, $manager, $this->payload(8, 6));

        $sum = $membership->psnParticipation()->firstOrFail()->ledgerUnitsSum();
        $this->assertSame(8, $sum);
        $this->assertSame(
            8,
            (int) ParticipationEntry::query()
                ->where('business_participation_id', $membership->psnParticipation()->firstOrFail()->id)
                ->sum('units'),
        );
    }

    private function secondMysqlPdo(): PDO
    {
        $config = config('database.connections.mysql');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['database'],
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
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
    private function payload(int $desired, int $expectedCurrent, ?string $token = null): array
    {
        return [
            'desired_units' => $desired,
            'expected_current_units' => $expectedCurrent,
            'confirmation_token' => $token ?? (string) Str::uuid(),
            'effective_date' => now('Asia/Jakarta')->toDateString(),
            'reason' => PsnAdjustmentReason::AdministrativeCorrection->value,
            'note' => 'Isolated MySQL verification',
        ];
    }
}
