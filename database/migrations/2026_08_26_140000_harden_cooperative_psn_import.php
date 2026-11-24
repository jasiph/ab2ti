<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participation_entries', function (Blueprint $table) {
            $table->foreignId('reverses_entry_id')
                ->nullable()
                ->after('legacy_source_row_id')
                ->constrained('participation_entries')
                ->restrictOnDelete();

            $table->unique('reverses_entry_id', 'participation_entries_reverses_unique');
            $table->unique(
                ['import_batch_id', 'legacy_source_row_id', 'entry_type'],
                'participation_entries_batch_row_type_unique',
            );
        });

        Schema::table('cooperative_fund_entries', function (Blueprint $table) {
            $table->unique(
                ['import_batch_id', 'legacy_source_row_id', 'fund_type'],
                'coop_fund_entries_batch_row_type_unique',
            );
        });

        Schema::table('cooperative_import_rows', function (Blueprint $table) {
            $table->string('source_fingerprint', 64)->nullable()->after('raw_data');
            $table->foreignId('matched_by')->nullable()->after('match_method')->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable()->after('matched_by');
            $table->index('source_fingerprint');
            $table->index(['batch_id', 'validation_status']);
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_import_rows', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'validation_status']);
            $table->dropIndex(['source_fingerprint']);
            $table->dropConstrainedForeignId('matched_by');
            $table->dropColumn(['source_fingerprint', 'matched_at']);
        });

        Schema::table('cooperative_fund_entries', function (Blueprint $table) {
            $table->dropUnique('coop_fund_entries_batch_row_type_unique');
        });

        Schema::table('participation_entries', function (Blueprint $table) {
            $table->dropUnique('participation_entries_batch_row_type_unique');
            $table->dropUnique('participation_entries_reverses_unique');
            $table->dropConstrainedForeignId('reverses_entry_id');
        });
    }
};
