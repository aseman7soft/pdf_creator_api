<?php

declare(strict_types=1);

use App\Models\Template;
use Database\Seeders\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * show_abs / reservation-v3 documents. Earlier travel-document migrations
 * already ran in production, so these slugs are upserted in a new migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new TemplateSeeder)->seedShowAbsDocuments();
    }

    public function down(): void
    {
        Template::query()->whereIn('slug', [
            'tour-voucher',
            'insurance-voucher',
            'visa-voucher',
            'cip-voucher',
            'reservation-document',
        ])->delete();
    }
};
