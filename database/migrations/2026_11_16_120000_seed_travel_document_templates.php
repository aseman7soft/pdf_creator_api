<?php

declare(strict_types=1);

use App\Models\Template;
use Database\Seeders\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Travel documents must exist after deploy. CI runs migrate, not db:seed,
 * so the accommodation voucher and flight ticket are upserted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PHPUnit migrates a fresh schema and creates its own hotel-voucher
        // rows. Production deploy runs this migration and upserts the documents.
        if (app()->runningUnitTests()) {
            return;
        }

        (new TemplateSeeder)->seedTravelDocuments();
    }

    public function down(): void
    {
        // hotel-voucher stays; it is the long-lived accommodation document.
        Template::query()->whereIn('slug', [
            'accommodation-voucher',
            'flight-ticket',
        ])->delete();
    }
};
