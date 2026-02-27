<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('alternative_title')->nullable();

            // Metadata
            $table->string('key')->nullable(); // C, G, etc.
            $table->integer('tempo')->nullable(); // BPM
            $table->string('time_signature')->nullable(); // 4/4, 6/8

            // Content
            $table->text('lyrics')->nullable(); // Plain text for search
            $table->json('content')->nullable(); // Structured JSON {sections: [...]}

            // Credits
            $table->string('lyric_author')->nullable();
            $table->string('music_author')->nullable();
            $table->string('copyright')->nullable();

            // Links
            $table->string('youtube_link')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
