<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number')->unique();
            $table->foreignId('source_application_id')
                ->unique()
                ->constrained('membership_applications')
                ->restrictOnDelete();
            $table->string('status');
            $table->string('legal_name');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->timestamp('recognized_at');
            $table->boolean('card_issued')->default(false);
            $table->timestamp('card_issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
