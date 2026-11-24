<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participation_entries', function (Blueprint $table) {
            $table->uuid('confirmation_token')
                ->nullable()
                ->after('provenance');

            $table->unique('confirmation_token', 'participation_entries_confirmation_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('participation_entries', function (Blueprint $table) {
            $table->dropUnique('participation_entries_confirmation_token_unique');
            $table->dropColumn('confirmation_token');
        });
    }
};
