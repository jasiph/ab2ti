<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('member_id');
            $table->index(['status', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_memberships');
    }
};
