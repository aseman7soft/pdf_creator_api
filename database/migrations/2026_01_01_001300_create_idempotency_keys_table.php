<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->foreignId('generated_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['api_project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
