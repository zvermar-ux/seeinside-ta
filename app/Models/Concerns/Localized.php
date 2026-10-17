<?php

namespace App\Models\Concerns;

/**
 * Text attributes stored as {"mk": "...", "en": "..."} (cast to array).
 */
trait Localized
{
    public const LOCALES = ['mk', 'en'];

    /** Store Cyrillic as real characters (searchable with LIKE) instead of \u escapes */
    protected function asJson($value, $flags = 0)
    {
        return parent::asJson($value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Value in the requested (or current) language, falling back to any language that has a value */
    public function tr(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->getAttribute($attribute);

        if (! is_array($value)) {
            return filled($value) ? (string) $value : null;
        }

        $locale ??= app()->getLocale();
        foreach (array_unique([$locale, 'mk', 'en']) as $l) {
            if (filled($value[$l] ?? null)) {
                return (string) $value[$l];
            }
        }

        return null;
    }

    /** One item per line (highlights, included, not included…) */
    public function trLines(string $attribute, ?string $locale = null): array
    {
        $text = (string) $this->tr($attribute, $locale);

        return array_values(array_filter(array_map(
            fn ($l) => trim(ltrim(trim($l), "-•*✓✔ \t")),
            preg_split('/\r\n|\r|\n/', $text) ?: [],
        ), fn ($l) => $l !== ''));
    }

    /** Both languages, for the API */
    public function trAll(string $attribute, bool $asLines = false): array
    {
        $out = [];
        foreach (self::LOCALES as $l) {
            $out[$l] = $asLines ? $this->trLines($attribute, $l) : $this->tr($attribute, $l);
        }

        return $out;
    }
}
