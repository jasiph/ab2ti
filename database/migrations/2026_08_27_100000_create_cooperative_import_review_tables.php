<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_import_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('cooperative_import_batches')
                ->restrictOnDelete();
            $table->foreignId('import_row_id')
                ->constrained('cooperative_import_rows')
                ->restrictOnDelete();
            $table->string('category');
            $table->string('warning_code');
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('administrative_note')->nullable();
            $table->json('audit_metadata')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'import_row_id', 'category', 'warning_code'], 'coop_import_review_unique');
            $table->index(['batch_id', 'status']);
            $table->index(['batch_id', 'category']);
        });

        Schema::create('cooperative_import_review_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_item_id')
                ->constrained('cooperative_import_review_items')
                ->restrictOnDelete();
            $table->foreignId('batch_id')
                ->constrained('cooperative_import_batches')
                ->restrictOnDelete();
            $table->foreignId('import_row_id')
                ->constrained('cooperative_import_rows')
                ->restrictOnDelete();
            $table->string('category');
            $table->string('warning_code');
            $table->string('action');
            $table->string('outcome');
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->text('administrative_note')->nullable();
            $table->json('audit_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['batch_id', 'import_row_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_import_review_actions');
        Schema::dropIfExists('cooperative_import_review_items');
    }
};
