<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secure_capability_grants', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('secret_digest');
        });
    }

    public function down(): void
    {
        Schema::table('secure_capability_grants', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
