<?php

declare(strict_types=1);

use Database\Seeders\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-upsert travel document templates so production picks up the version
 * that no longer prints supplier codes or supplier-side ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new TemplateSeeder)->seedTravelDocuments();
    }

    public function down(): void
    {
        // Content-only refresh; nothing to roll back.
    }
};
