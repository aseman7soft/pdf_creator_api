<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference', 120)->nullable()->index();
            $table->foreignId('api_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name', 150)->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('generation_mode', 10)->default('sync');
            $table->string('status', 20)->default('pending')->index();
            $table->string('file_path', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 120)->default('application/pdf');
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->json('pdf_meta')->nullable();
            $table->longText('payload')->nullable();
            $table->json('options')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['api_project_id', 'created_at']);
            $table->index(['template_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
