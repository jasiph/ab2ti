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
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // BAPTIS-ANAK, SIDI, etc.
            $table->text('content_template'); // HTML/Blade content
            $table->json('data_schema')->nullable(); // JSON schema for form fields
            $table->timestamps();
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('letter_template_id')->constrained()->cascadeOnDelete();
            $table->string('letter_number')->unique()->nullable(); // Generated upon approval
            $table->json('content_data'); // Filled form data
            $table->string('status')->default('draft'); // draft, pending_review, approved, published, archived
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('security_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('letter_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // Pastor, Clerk, etc.
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_signers');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_templates');
    }
};
