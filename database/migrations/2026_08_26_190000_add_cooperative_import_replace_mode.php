<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooperative_import_batches', function (Blueprint $table) {
            $table->string('import_mode')->default('append')->after('status');
            $table->foreignId('replaces_batch_id')
                ->nullable()
                ->after('duplicate_of_batch_id')
                ->constrained('cooperative_import_batches')
                ->nullOnDelete();
            $table->foreignId('replaced_by_batch_id')
                ->nullable()
                ->after('replaces_batch_id')
                ->constrained('cooperative_import_batches')
                ->nullOnDelete();
            $table->timestamp('replace_acknowledged_at')->nullable()->after('reversed_by');
            $table->foreignId('replace_acknowledged_by')
                ->nullable()
                ->after('replace_acknowledged_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replace_acknowledged_by');
            $table->dropColumn('replace_acknowledged_at');
            $table->dropConstrainedForeignId('replaced_by_batch_id');
            $table->dropConstrainedForeignId('replaces_batch_id');
            $table->dropColumn('import_mode');
        });
    }
};
