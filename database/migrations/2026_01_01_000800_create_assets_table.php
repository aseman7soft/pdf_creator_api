<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 80)->unique();
            $table->string('name', 150);
            $table->string('type', 20)->default('other')->index();
            $table->string('mime_type', 120);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->string('disk', 20)->default('local');
            $table->string('path', 255)->unique();
            $table->char('checksum', 64)->index();
            $table->json('metadata')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
