<?php

namespace App\Services\Ai;

/**
 * Which text fields the operator assistant may fill, per model.
 * kind: text (one line) | area (plain paragraph) | lines (one item per line) | html (rich text)
 */
class AssistantFields
{
    public static function for(string $modelClass): array
    {
        $seo = [
            'seo_title' => ['text', 'SEO title for Google, max 60 characters'],
            'seo_description' => ['area', 'SEO meta description, 140–160 characters, inviting'],
        ];

        return match (class_basename($modelClass)) {
            'Tour' => [
                'title' => ['text', 'Attractive tour title, e.g. "Рим – Вечниот град"'],
                'subtitle' => ['text', 'Route / subtitle, e.g. "Скопје – Рим – Фиренца – Скопје"'],
                'short_description' => ['area', '1–2 sentences teaser for listings'],
                'description' => ['html', 'Main description: inviting, 2–4 paragraphs'],
                'highlights' => ['lines', '4–8 highlights of the trip'],
                'agenda' => ['html', 'Short overview of the itinerary (optional when days are filled)'],
                'included' => ['lines', 'What the price includes'],
                'not_included' => ['lines', 'What the price does not include'],
                'notes' => ['html', 'Notes'],
                'important_info' => ['html', 'Important information: documents, visa, climate, currency'],
                'baggage_info' => ['area', 'Baggage rules'],
                'payment_terms' => ['area', 'Payment terms'],
                'cancellation_policy' => ['area', 'Cancellation policy'],
            ] + $seo,
            'Event' => [
                'title' => ['text', 'Event title'],
                'short_description' => ['area', '1–2 sentences teaser'],
                'description' => ['html', 'Description of the event and the trip to it'],
                'included' => ['lines', 'What the price includes'],
                'not_included' => ['lines', 'What the price does not include'],
                'notes' => ['html', 'Notes'],
            ] + $seo,
            'Accommodation' => [
                'short_description' => ['area', '1–2 sentences teaser for listings'],
                'overview' => ['html', 'Overview of the hotel / apartment: location, rooms, beach, food, facilities'],
                'house_rules' => ['html', 'House rules: check-in/out, pets, deposit'],
                'notes' => ['html', 'Notes'],
                'cancellation_policy' => ['area', 'Cancellation policy'],
            ] + $seo,
            'VisaType' => [
                'name' => ['text', 'Visa name, e.g. "Туристичка виза"'],
                'short_description' => ['area', '1–2 sentences'],
                'description' => ['html', 'Description: who needs it, procedure, how the agency helps'],
                'requirements' => ['lines', 'Required documents and conditions'],
                'processing_time' => ['text', 'Processing time, e.g. "10–15 работни дена"'],
                'validity' => ['text', 'Validity, e.g. "до 90 дена"'],
            ] + $seo,
            // spatie-translatable resources: one language at a time (the language switcher of the page)
            'Group' => [
                'name' => ['text', 'Name of the destination / group'],
                'description' => ['html', 'Inviting description of the destination'],
            ],
            'SliderImage' => [
                'supertitle' => ['text', 'Small text above the title, max 4 words'],
                'title' => ['text', 'Big title, max 6 words'],
                'subtitle' => ['area', 'One short sentence'],
                'button_label' => ['text', 'Button text, 2–3 words'],
                'button2_label' => ['text', 'Second button text, 2–3 words'],
            ],
            'Charter' => [],
            default => [],
        };
    }

    /** Models whose translatable fields hold one language at a time in the form */
    public static function singleLocale(string $modelClass): bool
    {
        return in_array(class_basename($modelClass), ['Group', 'SliderImage', 'TravelObject'], true);
    }

    public static function hasDays(string $modelClass): bool
    {
        return class_basename($modelClass) === 'Tour';
    }
}
