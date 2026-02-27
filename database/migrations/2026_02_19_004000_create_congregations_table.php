<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('congregations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identity
            $table->string('unit_number')->index(); // NIJ
            $table->string('family_card_number')->nullable()->index(); // KK
            $table->string('nik')->nullable()->index(); // NIK
            $table->string('name');
            $table->enum('gender', ['male', 'female']);
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('blood_type')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Status
            $table->enum('marital_status', ['single', 'married', 'widowed', 'divorced'])->default('single');
            $table->date('baptism_date')->nullable();
            $table->date('sidi_date')->nullable();
            $table->date('join_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'moved', 'deceased'])->default('active');

            // Relationships (Self-Referencing)
            $table->foreignUuid('father_id')->nullable()->constrained('congregations')->nullOnDelete();
            $table->foreignUuid('mother_id')->nullable()->constrained('congregations')->nullOnDelete();
            $table->foreignUuid('spouse_id')->nullable()->constrained('congregations')->nullOnDelete();
            $table->foreignUuid('head_of_family_id')->nullable()->constrained('congregations')->nullOnDelete();

            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('congregations');
    }
};
