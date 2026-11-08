<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('category', 60)->default('general')->index();
            $table->text('description')->nullable();
            $table->longText('html');
            $table->longText('css')->nullable();
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->default('fa');
            $table->unsignedInteger('version')->default(1);
            $table->string('page_size', 20)->default('A4');
            $table->string('orientation', 20)->default('portrait');
            $table->json('margins')->nullable();
            $table->json('pdf_options')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
