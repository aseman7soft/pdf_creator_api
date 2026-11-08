<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 60)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('rate_limit_per_minute')->nullable();
            $table->unsignedInteger('monthly_quota')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_projects');
    }
};
