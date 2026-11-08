<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('html');
            $table->longText('css')->nullable();
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->string('page_size', 20)->default('A4');
            $table->string('orientation', 20)->default('portrait');
            $table->json('margins')->nullable();
            $table->json('pdf_options')->nullable();
            $table->json('variables')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['template_id', 'version']);
            $table->index(['template_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
