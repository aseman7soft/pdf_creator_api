<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Projects restricted to specific templates (e.g. the Aseman site) were only
 * allowed the older travel templates, so the new show_abs documents returned
 * TEMPLATE_NOT_ALLOWED. Any project already allowed one Aseman travel template
 * gets access to the whole travel/show_abs set. Additive only.
 */
return new class extends Migration
{
    private const TRAVEL_SLUGS = [
        'hotel-voucher',
        'accommodation-voucher',
        'flight-ticket',
        'tour-voucher',
        'insurance-voucher',
        'visa-voucher',
        'cip-voucher',
        'reservation-document',
    ];

    public function up(): void
    {
        $templateIds = DB::table('templates')
            ->whereIn('slug', self::TRAVEL_SLUGS)
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return;
        }

        $projectIds = DB::table('api_project_template')
            ->whereIn('template_id', $templateIds)
            ->distinct()
            ->pluck('api_project_id');

        $now = now();
        $rows = [];

        foreach ($projectIds as $projectId) {
            foreach ($templateIds as $templateId) {
                $rows[] = [
                    'api_project_id' => $projectId,
                    'template_id' => $templateId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('api_project_template')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        // Access grants are intentionally left in place.
    }
};