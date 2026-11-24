<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['source_application_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('source_application_id')->nullable()->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreign('source_application_id')
                ->references('id')
                ->on('membership_applications')
                ->restrictOnDelete();
            $table->string('source_type', 64)
                ->default('membership_application')
                ->after('source_application_id');
            $table->index('source_type');
        });

        Schema::table('cooperative_import_rows', function (Blueprint $table) {
            $table->foreignId('created_member_id')
                ->nullable()
                ->after('matched_member_id')
                ->constrained('members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_import_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_member_id');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['source_type']);
            $table->dropColumn('source_type');
            $table->dropForeign(['source_application_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('source_application_id')->nullable(false)->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreign('source_application_id')
                ->references('id')
                ->on('membership_applications')
                ->restrictOnDelete();
        });
    }
};
