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
        Schema::create('archived_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('incoming_letter'); // incoming_letter, legal, certificate, report, other
            $table->string('reference_number')->nullable(); // Nomor Surat / Dokumen
            $table->string('source'); // Pengirim / Instansi / Sumber
            $table->string('title'); // Perihal / Judul
            $table->text('description')->nullable();
            $table->date('document_date')->nullable(); // Tanggal di dokumen
            $table->date('received_at'); // Tanggal diterima/diarsipkan
            $table->string('file_path')->nullable();
            $table->uuid('congregation_id')->nullable(); // Optional relation to member
            
            // Standard tracking
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for search
            $table->index('type');
            $table->index('reference_number');
            $table->index('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_documents');
    }
};
