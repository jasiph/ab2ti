<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64);
            $table->string('status');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_batch_id')
                ->nullable()
                ->constrained('cooperative_import_batches')
                ->nullOnDelete();
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->index('file_hash');
            $table->index('status');
        });

        Schema::table('participation_entries', function (Blueprint $table) {
            $table->foreign('import_batch_id')
                ->references('id')
                ->on('cooperative_import_batches')
                ->nullOnDelete();
        });

        Schema::table('cooperative_fund_entries', function (Blueprint $table) {
            $table->foreign('import_batch_id')
                ->references('id')
                ->on('cooperative_import_batches')
                ->nullOnDelete();
        });

        Schema::create('cooperative_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('cooperative_import_batches')
                ->restrictOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->foreignId('matched_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();
            $table->string('match_method')->nullable();
            $table->string('validation_status');
            $table->json('validation_errors')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_row_number']);
            $table->index('validation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_import_rows');

        Schema::table('participation_entries', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });

        Schema::table('cooperative_fund_entries', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });

        Schema::dropIfExists('cooperative_import_batches');
    }
};
