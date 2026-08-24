<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lapak_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'lc_active_sort_idx');
        });

        Schema::create('lapaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')
                ->unique()
                ->constrained('members')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('location_text')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('moderation_status');
            $table->text('moderation_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('moderation_status', 'lapaks_moderation_idx');
        });

        Schema::create('lapak_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lapak_id')
                ->constrained('lapaks', indexName: 'll_lapak_fk')
                ->restrictOnDelete();
            $table->foreignId('category_id')
                ->constrained('lapak_categories', indexName: 'll_category_fk')
                ->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('price_mode');
            $table->decimal('price_amount', 12, 2)->nullable();
            $table->string('price_unit', 32)->nullable();
            $table->string('availability');
            $table->string('moderation_status');
            $table->text('moderation_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lapak_id', 'slug'], 'll_lapak_slug_unique');
            $table->index(['moderation_status', 'availability'], 'll_status_avail_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lapak_listings');
        Schema::dropIfExists('lapaks');
        Schema::dropIfExists('lapak_categories');
    }
};
