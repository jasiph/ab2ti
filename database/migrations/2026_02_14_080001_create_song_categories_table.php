<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('song_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('general'); // genre, theme, mood
            $table->timestamps();
        });

        Schema::create('song_category_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('songs')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('song_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_category_pivot');
        Schema::dropIfExists('song_categories');
    }
};
