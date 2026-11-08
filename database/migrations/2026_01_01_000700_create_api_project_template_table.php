<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_project_template', function (Blueprint $table) {
            $table->foreignId('api_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['api_project_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_project_template');
    }
};
