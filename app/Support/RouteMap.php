<?php

namespace App\Support;

/**
 * Itinerary map of a tour. Accepts:
 *  - a Google Maps embed code (<iframe src="https://www.google.com/maps/embed?...">)
 *  - a Google Maps link (…/maps/dir/Skopje/Bitola/Athens, …/maps/embed?…, …/maps?q=…)
 *  - a simple route "Скопје – Битола – Атина" (one stop per line also works)
 * and returns a safe Google Maps embed URL (only google.com/maps is ever embedded).
 */
class RouteMap
{
    public static function stops(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_contains($raw, '<') || preg_match('~^https?://~i', $raw)) {
            if (preg_match('~/maps/dir/([^?@]+)~', html_entity_decode($raw), $m)) {
                return array_values(array_filter(array_map(fn ($s) => trim(urldecode(str_replace('+', ' ', $s))), explode('/', $m[1]))));
            }

            return [];
        }
        $parts = preg_split('/\s*(?:–|—|→|->|>|;|\r?\n|\s-\s)\s*/u', $raw);

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    public static function embedUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        // embed code → its src
        if (str_contains($raw, '<')) {
            if (! preg_match('~src=["\']([^"\']+)["\']~i', $raw, $m)) {
                return null;
            }
            $raw = html_entity_decode($m[1]);
        }
        if (preg_match('~^https?://~i', $raw)) {
            $u = parse_url($raw);
            $host = strtolower($u['host'] ?? '');
            $path = $u['path'] ?? '';
            if (! preg_match('~(^|\.)google\.[a-z.]+$~', $host) || ! str_starts_with($path, '/maps')) {
                return null;
            }
            if (str_starts_with($path, '/maps/embed') || str_contains($u['query'] ?? '', 'output=embed')) {
                return 'https://'.$host.$path.(isset($u['query']) ? '?'.$u['query'] : '');
            }
            $stops = self::stops($raw);
            if (count($stops) >= 2) {
                return self::directions($stops);
            }
            if (preg_match('~/maps/(?:place|search)/([^/@?]+)~', $path, $m)) {
                return 'https://maps.google.com/maps?q='.urlencode(urldecode(str_replace('+', ' ', $m[1]))).'&output=embed';
            }
            parse_str($u['query'] ?? '', $q);
            if (! empty($q['q'])) {
                return 'https://maps.google.com/maps?q='.urlencode($q['q']).'&output=embed';
            }

            return null;
        }
        $stops = self::stops($raw);
        if (! $stops) {
            return null;
        }

        return count($stops) === 1 ? 'https://maps.google.com/maps?q='.urlencode($stops[0]).'&output=embed' : self::directions($stops);
    }

    protected static function directions(array $stops): string
    {
        $first = array_shift($stops);

        return 'https://maps.google.com/maps?saddr='.urlencode($first).'&daddr='.implode('+to:', array_map('urlencode', $stops)).'&output=embed';
    }
}
