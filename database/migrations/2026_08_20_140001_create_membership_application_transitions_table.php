<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_application_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')
                ->constrained('membership_applications', indexName: 'mat_application_fk')
                ->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users', indexName: 'mat_actor_user_fk')
                ->nullOnDelete();
            $table->string('actor_context');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['membership_application_id', 'created_at'],
                'mat_application_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_application_transitions');
    }
};
