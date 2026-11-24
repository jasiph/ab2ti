<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\FinancialInvariants;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ImportReverser
{
    /**
     * Reverse a committed batch that has not been subsequently modified.
     *
     * Opening participation is offset with an adjustment; fund openings are
     * marked reversed. Original ledger rows are not deleted. Staging rows
     * remain committed so they cannot be committed twice.
     */
    public function reverse(CooperativeImportBatch $batch, User $actor): CooperativeImportBatch
    {
        return DB::transaction(function () use ($batch, $actor) {
            $locked = CooperativeImportBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canReverse()) {
                throw new RuntimeException('This import batch has no committed Cooperative components to reverse.');
            }

            $openings = ParticipationEntry::query()
                ->where('import_batch_id', $locked->id)
                ->where('entry_type', ParticipationEntryType::OpeningBalance)
                ->lockForUpdate()
                ->get();

            foreach ($openings as $entry) {
                if (ParticipationEntry::query()->where('reverses_entry_id', $entry->id)->exists()) {
                    throw new RuntimeException('This batch has already been reversed.');
                }

                $later = ParticipationEntry::query()
                    ->where('business_participation_id', $entry->business_participation_id)
                    ->where('id', '>', $entry->id)
                    ->where(function ($query) use ($locked): void {
                        $query->whereNull('import_batch_id')
                            ->orWhere('import_batch_id', '!=', $locked->id);
                    })
                    ->exists();

                if ($later) {
                    throw new RuntimeException('Batch cannot be reversed: later independent participation entries exist.');
                }

                $reason = 'Reversal of import batch #'.$locked->id;
                FinancialInvariants::assertAdjustment(
                    -1 * (int) $entry->units,
                    (int) $entry->unit_value,
                    -1 * (int) $entry->amount,
                    $reason,
                );
                FinancialInvariants::assertNonNegativeResultingUnits(
                    $entry->participation->currentUnits(),
                    -1 * (int) $entry->units,
                );

                ParticipationEntry::query()->create([
                    'business_participation_id' => $entry->business_participation_id,
                    'entry_type' => ParticipationEntryType::Adjustment,
                    'units' => -1 * (int) $entry->units,
                    'unit_value' => $entry->unit_value,
                    'amount' => -1 * (int) $entry->amount,
                    'effective_date_start' => now()->toDateString(),
                    'effective_date_end' => null,
                    'share_reference' => $entry->share_reference,
                    'units_origin' => $entry->units_origin,
                    'source_type' => CooperativeSourceType::ImportReversal,
                    'import_batch_id' => $locked->id,
                    'legacy_source_row_id' => $entry->legacy_source_row_id,
                    'reverses_entry_id' => $entry->id,
                    'notes' => $reason,
                    'provenance' => [
                        'reverses_entry_id' => $entry->id,
                        'reversed_by' => $actor->id,
                        'reversed_at' => now()->toIso8601String(),
                        'reason' => $reason,
                    ],
                ]);
            }

            CooperativeFundEntry::query()
                ->where('import_batch_id', $locked->id)
                ->whereNull('reversed_at')
                ->update([
                    'reversed_at' => now(),
                    'reversed_by' => $actor->id,
                ]);

            $createdMemberIds = CooperativeImportRow::query()
                ->where('batch_id', $locked->id)
                ->whereNotNull('created_member_id')
                ->pluck('created_member_id');

            $blocked = Member::query()
                ->whereIn('id', $createdMemberIds)
                ->where(function ($query): void {
                    $query->whereNotNull('user_id')
                        ->orWhereHas('lapak');
                })
                ->exists();

            if ($blocked) {
                throw new RuntimeException('Batch cannot reverse migration-created Members that already have site access or independent records. Cooperative ledger reversal is blocked.');
            }

            $summary = $locked->summary_json ?? [];
            $summary['reversed_at'] = now()->toIso8601String();
            $summary['reversed_by'] = $actor->id;

            $locked->forceFill([
                'status' => CooperativeImportBatchStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'summary_json' => $summary,
            ])->save();

            return $locked->fresh() ?? $locked;
        });
    }
}
