<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->text('address')->nullable()->change();
        });

        Schema::table('cooperative_import_rows', function (Blueprint $table) {
            $table->timestamp('member_committed_at')->nullable()->after('committed_at');
            $table->timestamp('membership_committed_at')->nullable()->after('member_committed_at');
            $table->timestamp('funds_committed_at')->nullable()->after('membership_committed_at');
            $table->timestamp('psn_committed_at')->nullable()->after('funds_committed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_import_rows', function (Blueprint $table) {
            $table->dropColumn([
                'member_committed_at',
                'membership_committed_at',
                'funds_committed_at',
                'psn_committed_at',
            ]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
        });
    }
};
