<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('legal_name');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->text('applicant_notes')->nullable();
            $table->string('status');
            $table->timestamp('submitted_at');
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revision_requested_at')->nullable();
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('revision_reason')->nullable();
            $table->text('staff_notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('submitted_at');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
