<?php

namespace App\Services\Aries;

use DOMDocument;
use DOMXPath;

/** Helpers that turn the HTML fragments Aries puts into its grid cells into plain values. */
class AriesParser
{
    public static function text(mixed $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text);

        return ($text === '' || $text === '-' || $text === '/') ? null : $text;
    }

    public static function checked(mixed $html): bool
    {
        return (bool) preg_match('/\bchecked\b/i', (string) $html);
    }

    /** value="..." of the first input in the fragment, or plain text when there is no input */
    public static function value(mixed $html): ?string
    {
        $html = (string) $html;

        if (preg_match('/<(input|textarea|select)\b/i', $html)) {
            if (preg_match('/\bvalue\s*=\s*(["\'])(.*?)\1/is', $html, $m)) {
                $v = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                return $v === '' ? null : $v;
            }

            return null;
        }

        return self::text($html);
    }

    public static function imgSrc(mixed $html): ?string
    {
        if (preg_match('/<img[^>]+src\s*=\s*(["\'])(.*?)\1/is', (string) $html, $m) && trim($m[2]) !== '') {
            return trim($m[2]);
        }

        return null;
    }

    public static function percent(mixed $value): ?float
    {
        $v = self::text($value);
        if ($v === null) {
            return null;
        }
        $v = str_replace([',', '%', ' '], ['.', '', ''], $v);

        return is_numeric($v) ? (float) $v : null;
    }

    /** Clean up a rich-text description; "-" or empty means none */
    public static function html(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '' || $html === '-' || self::text($html) === null && ! str_contains($html, '<img')) {
            return null;
        }

        return $html;
    }

    /** Options of a <select> fragment: value => label */
    public static function options(string $html): array
    {
        preg_match_all('/<option[^>]*value\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/option>/is', $html, $m, PREG_SET_ORDER);

        $out = [];
        foreach ($m as $o) {
            $out[$o[2]] = trim(html_entity_decode(strip_tags($o[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $out;
    }

    /**
     * Parse the "sliki-lista" gallery HTML.
     *
     * @return array<int, array{external_id: ?string, src: string, caption: ?string, is_main: bool}>
     */
    public static function gallery(string $html): array
    {
        if (! str_contains($html, '<img')) {
            return [];
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>');
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        $images = [];
        foreach ($xp->query('//img') as $img) {
            $src = trim($img->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            $id = preg_match('/(\d+)/', $img->getAttribute('id'), $mm) ? $mm[1] : null;
            $box = $img->parentNode;

            $caption = null;
            if ($id && ($span = $xp->query('.//span[@id="span'.$id.'"]', $box)->item(0))) {
                $caption = trim($span->getAttribute('data-opis')) ?: trim($span->textContent) ?: null;
            }

            $style = strtolower($box instanceof \DOMElement ? $box->getAttribute('style') : '');
            $hasMakeMainButton = $xp->query('.//button[contains(@onclick,"glavnaslika")]', $box)->length > 0;
            $isMain = str_contains($style, 'background-color:#fff1ab') || str_contains($style, 'background-color: #fff1ab') || ! $hasMakeMainButton;

            $images[] = ['external_id' => $id, 'src' => $src, 'caption' => $caption, 'is_main' => $isMain];
        }

        // Exactly one main image
        $mainFound = false;
        foreach ($images as &$i) {
            if ($i['is_main'] && ! $mainFound) {
                $mainFound = true;
            } else {
                $i['is_main'] = false;
            }
        }
        unset($i);
        if (! $mainFound && $images) {
            $images[0]['is_main'] = true;
        }

        return $images;
    }

    /** Parse dates such as 24.06.2026, 2026-06-24 or 24/06/2026 */
    public static function date(mixed $value): ?string
    {
        $v = self::value($value);
        if (! $v) {
            return null;
        }
        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.y'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
