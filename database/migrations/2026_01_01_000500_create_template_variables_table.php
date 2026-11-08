<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('label', 120);
            $table->string('type', 20)->default('string');
            $table->boolean('is_required')->default(false);
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();
            $table->string('validation_rules', 500)->nullable();
            $table->unsignedSmallInteger('min_length')->nullable();
            $table->unsignedSmallInteger('max_length')->nullable();
            $table->decimal('min_value', 18, 4)->nullable();
            $table->decimal('max_value', 18, 4)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_variables');
    }
};
