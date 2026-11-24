<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooperative_import_batches', function (Blueprint $table) {
            $table->string('worksheet_name', 64)->nullable()->after('file_hash');
            $table->boolean('worksheet_visible')->nullable()->after('worksheet_name');
            $table->unsignedInteger('header_row_number')->nullable()->after('worksheet_visible');
            $table->json('worksheets_json')->nullable()->after('summary_json');
            $table->index(['file_hash', 'worksheet_name'], 'coop_import_batches_hash_sheet_index');
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_import_batches', function (Blueprint $table) {
            $table->dropIndex('coop_import_batches_hash_sheet_index');
            $table->dropColumn([
                'worksheet_name',
                'worksheet_visible',
                'header_row_number',
                'worksheets_json',
            ]);
        });
    }
};
