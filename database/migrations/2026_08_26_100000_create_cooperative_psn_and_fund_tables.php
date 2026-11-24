<?php

use App\Enums\CooperativeBusinessStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_businesses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status');
            $table->string('participation_unit_name');
            $table->unsignedBigInteger('current_unit_value');
            $table->timestamps();
        });

        $now = now();

        DB::table('cooperative_businesses')->insert([
            'code' => 'PSN',
            'name' => 'Penggilingan Padi',
            'status' => CooperativeBusinessStatus::Active->value,
            'participation_unit_name' => 'Saham PSN',
            'current_unit_value' => 1_000_000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::create('business_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_membership_id')
                ->constrained('cooperative_memberships')
                ->restrictOnDelete();
            $table->foreignId('cooperative_business_id')
                ->constrained('cooperative_businesses')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['cooperative_membership_id', 'cooperative_business_id'],
                'business_participations_membership_business_unique',
            );
        });

        Schema::create('participation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_participation_id')
                ->constrained('business_participations')
                ->restrictOnDelete();
            $table->string('entry_type');
            $table->bigInteger('units');
            $table->unsignedBigInteger('unit_value');
            $table->bigInteger('amount');
            $table->date('effective_date_start')->nullable();
            $table->date('effective_date_end')->nullable();
            $table->string('share_reference')->nullable();
            $table->string('units_origin')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->unsignedBigInteger('legacy_source_row_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamps();

            $table->index('business_participation_id');
            $table->index(['import_batch_id', 'legacy_source_row_id'], 'participation_entries_import_row_index');
        });

        Schema::create('cooperative_fund_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_membership_id')
                ->constrained('cooperative_memberships')
                ->restrictOnDelete();
            $table->string('fund_type');
            $table->string('entry_type');
            $table->unsignedBigInteger('amount');
            $table->date('effective_date_start')->nullable();
            $table->date('effective_date_end')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->unsignedBigInteger('legacy_source_row_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamps();

            $table->index(['cooperative_membership_id', 'fund_type'], 'coop_fund_entries_membership_type_index');
            $table->index(['import_batch_id', 'legacy_source_row_id'], 'coop_fund_entries_import_row_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_fund_entries');
        Schema::dropIfExists('participation_entries');
        Schema::dropIfExists('business_participations');
        Schema::dropIfExists('cooperative_businesses');
    }
};
