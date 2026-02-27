<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('song_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_song_id')->constrained('songs')->onDelete('cascade');
            $table->foreignId('child_song_id')->constrained('songs')->onDelete('cascade');
            $table->string('type')->default('medley'); // medley, related
            $table->integer('order')->default(0);
            $table->timestamps();

            // Prevent duplicate relations
            $table->unique(['parent_song_id', 'child_song_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_relations');
    }
};
