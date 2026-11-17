<?php

declare(strict_types=1);

use App\Models\Template;
use Database\Seeders\Support\TravelDocumentCatalog;
use Database\Seeders\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Redesigned flight-ticket: one page per passenger and segment, ticket card
 * with origin/destination, Jalali + Gregorian dates, baggage and passenger
 * type, separate baggage / cancellation / terms / flight-notes boxes, and
 * rial amounts shown in toman.
 *
 * Additive only: updates the template HTML, upserts variables (none are
 * removed), and records a new template version so history is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $template = Template::query()->where('slug', 'flight-ticket')->first();
        if (! $template instanceof Template) {
            (new TemplateSeeder)->seedTravelDocuments();

            return;
        }

        $template->forceFill([
            'html' => TravelDocumentCatalog::html('flight-ticket.html'),
            'footer_html' => TravelDocumentCatalog::footer(),
            'is_active' => true,
        ])->save();

        foreach (TravelDocumentCatalog::flightVariables() as $variable) {
            $template->variables()->updateOrCreate(
                ['name' => $variable['name']],
                array_merge([
                    'label' => $variable['name'],
                    'type' => 'string',
                    'is_required' => false,
                    'default_value' => null,
                    'description' => null,
                    'validation_rules' => null,
                    'sort_order' => 0,
                ], $variable),
            );
        }

        $template->load('variables');
        $template->snapshotVersion(null, 'بازطراحی بلیط هواپیما (کارت بلیط، کادرهای جدای قوانین، مبلغ تومان)');
    }

    public function down(): void
    {
        // Content-only refresh; previous HTML stays in template_versions.
    }
};
