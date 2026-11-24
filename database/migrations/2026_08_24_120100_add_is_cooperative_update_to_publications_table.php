<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->boolean('is_cooperative_update')->default(false)->after('youtube_url');
            $table->index('is_cooperative_update');
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropIndex(['is_cooperative_update']);
            $table->dropColumn('is_cooperative_update');
        });
    }
};
