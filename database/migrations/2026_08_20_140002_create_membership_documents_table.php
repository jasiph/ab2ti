<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')
                ->constrained('membership_applications')
                ->restrictOnDelete();
            $table->string('document_type');
            $table->string('disk');
            $table->string('object_key');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum')->nullable();
            $table->timestamp('uploaded_at');
            $table->string('uploaded_by_context');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(
                ['membership_application_id', 'document_type'],
                'md_application_type_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_documents');
    }
};
