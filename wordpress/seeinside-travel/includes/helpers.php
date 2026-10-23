<?php

defined('ABSPATH') || exit;

/** Plugin setting (wp_options "sta_settings") */
function sta_setting(string $key, $default = null)
{
    $settings = get_option('sta_settings', []);   // cached by WordPress

    return (isset($settings[$key]) && $settings[$key] !== '') ? $settings[$key] : $default;
}

/** Current language for texts: "en" when the site/page language is English, otherwise "mk" */
function sta_lang(): string
{
    $forced = apply_filters('sta_language', null);
    if ($forced) {
        return $forced;
    }
    if (function_exists('pll_current_language') && pll_current_language()) {
        return str_starts_with(pll_current_language(), 'en') ? 'en' : 'mk';
    }
    if (defined('ICL_LANGUAGE_CODE')) {
        return str_starts_with(ICL_LANGUAGE_CODE, 'en') ? 'en' : 'mk';
    }

    return str_starts_with(determine_locale(), 'en') ? 'en' : 'mk';
}

/** Pick the right language from {"mk":…,"en":…} (falls back to the other language) */
function sta_t($value): string
{
    if (! is_array($value)) {
        return (string) ($value ?? '');
    }
    $lang = sta_lang();
    $other = $lang === 'en' ? 'mk' : 'en';
    $v = $value[$lang] ?? null;
    if ($v === null || $v === '' || $v === []) {
        $v = $value[$other] ?? '';
    }

    return is_array($v) ? implode("\n", $v) : (string) $v;
}

/** Same for lists ({"mk":[…],"en":[…]}) */
function sta_t_list($value): array
{
    if (! is_array($value)) {
        return [];
    }
    if (array_is_list($value)) {
        return $value;
    }
    $lang = sta_lang();
    $list = $value[$lang] ?? [];
    if (! $list) {
        $list = $value[$lang === 'en' ? 'mk' : 'en'] ?? [];
    }

    return array_values(array_filter((array) $list, fn ($x) => $x !== null && $x !== ''));
}

/** The full record from SeeInside as stored at the last sync */
function sta_data(?int $post_id = null): array
{
    $post_id = $post_id ?: get_the_ID();
    $raw = $post_id ? get_post_meta($post_id, '_sta_payload', true) : '';
    if (is_array($raw)) {
        return $raw;
    }
    $data = $raw ? json_decode($raw, true) : null;

    return is_array($data) ? $data : [];
}

function sta_money($amount, ?string $currency = null): string
{
    if ($amount === null || $amount === '') {
        return '';
    }
    $currency = $currency ?: 'EUR';
    $symbols = ['EUR' => '€', 'USD' => '$', 'MKD' => 'ден.'];
    $n = number_format((float) $amount, fmod((float) $amount, 1) ? 2 : 0, ',', '.');

    return $currency === 'MKD' ? $n.' '.$symbols['MKD'] : ($symbols[$currency] ?? $currency.' ').$n;
}

function sta_date(?string $date, bool $withTime = false): string
{
    if (! $date) {
        return '';
    }
    $ts = strtotime($date);

    return $ts ? wp_date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts) : $date;
}

/** Find our post by the SeeInside id */
function sta_find_post(string $postType, int $remoteId): ?int
{
    $ids = get_posts([
        'post_type' => $postType,
        'post_status' => 'any',
        'meta_key' => 'sta_id',
        'meta_value' => $remoteId,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'suppress_filters' => true,
    ]);

    return $ids ? (int) $ids[0] : null;
}

function sta_log(string $message, string $level = 'info'): void
{
    $log = get_option('sta_log', []);
    array_unshift($log, ['t' => current_time('mysql'), 'l' => $level, 'm' => mb_substr($message, 0, 500)]);
    update_option('sta_log', array_slice($log, 0, 100), false);
}

/** Template part (theme can override in /seeinside-travel/<name>.php) */
function sta_part(string $name, array $vars = []): string
{
    $file = locate_template('seeinside-travel/'.$name.'.php') ?: STA_DIR.'templates/'.$name.'.php';
    if (! file_exists($file)) {
        return '';
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    include $file;

    return (string) ob_get_clean();
}

function sta_icon(string $name): string
{
    $icons = [
        'calendar' => '<path d="M7 3v2M17 3v2M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'plane' => '<path d="M10.5 13.5 3 11l1.5-1.5 7 1 4-4a2 2 0 0 1 3 3l-4 4 1 7L19 22l-2.5-7.5"/>',
        'bus' => '<rect x="4" y="3" width="16" height="15" rx="2"/><path d="M4 11h16M8 18v2M16 18v2M7.5 14.5h.01M16.5 14.5h.01"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'bed' => '<path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="2"/>',
        'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v4h18v-4a2 2 0 0 1 0-4V4H3v4Z"/><path d="M14 4v12"/>',
        'doc' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
        'wave' => '<path d="M2 12c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2M2 17c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/>',
        'home' => '<path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
    ];
    $path = $icons[$name] ?? $icons['check'];

    return '<svg class="sta-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
}

/** "push": the app sends everything (this server cannot reach the app); "pull": the plugin reads the API */
function sta_push_mode(): bool
{
    return sta_setting('mode', 'pull') === 'push';
}

/** Public form address of this site in the app (push mode): …/api/v1/public/<key prefix>/ */
function sta_public_api(): string
{
    $key = (string) sta_setting('api_key');
    $prefix = preg_match('/^ta_([a-z0-9]{8})_/', $key, $m) ? $m[1] : '';

    return rtrim((string) sta_setting('api_url', 'https://ta.seeinsidesolution.com'), '/').'/api/v1/public/'.$prefix.'/';
}

/** Local copy (media library) of a photo from SeeInside, or the original address */
function sta_local_image(?string $url, string $size = 'full'): string
{
    if (! $url) {
        return '';
    }
    static $cache = [];
    $key = $url.'|'.$size;
    if (! isset($cache[$key])) {
        $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sta_source', 'meta_value' => md5($url), 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true]);
        $cache[$key] = $found ? (wp_get_attachment_image_url((int) $found[0], $size) ?: $url) : $url;
    }

    return $cache[$key];
}

/** ★★★★☆ */
function sta_stars(float $rating, string $class = ''): string
{
    $pct = max(0, min(100, $rating / 5 * 100));

    return '<span class="sta-stars '.esc_attr($class).'" role="img" aria-label="'.esc_attr(sprintf(__('%s out of 5', 'seeinside-travel'), number_format_i18n($rating, 1))).'"><span class="sta-stars-fill" style="width:'.round($pct, 1).'%"></span></span>';
}

/**
 * Regular and promotional price of an offer: [price, old (struck through) or null, promo label or '', "pay until" date or ''].
 * The app sends price_from already discounted and price_regular as the regular price.
 */
function sta_price_info(array $d, string $type = ''): array
{
    $price = $type === 'visa' ? ($d['price'] ?? null) : ($d['price_from'] ?? null);
    $price = $price !== null ? (float) $price : null;
    $old = isset($d['price_regular']) && $d['price_regular'] !== null && $price !== null && (float) $d['price_regular'] > $price ? (float) $d['price_regular'] : null;
    $lm = $d['promotions']['last_minute'] ?? null;
    $fm = $d['promotions']['first_minute'] ?? null;
    $label = '';
    $until = '';
    if ($lm) {
        $label = 'Last minute'.(! empty($lm['discount_percent']) ? ' −'.(float) $lm['discount_percent'].'%' : '');
        $until = (string) ($lm['until'] ?? '');
        // older app without price_regular: the price was not discounted yet
        if (! array_key_exists('price_regular', $d) && $price) {
            $new = $lm['price'] ?? (! empty($lm['discount_percent']) ? $price * (1 - $lm['discount_percent'] / 100) : $price);
            if ($new < $price) {
                $old = $price;
                $price = (float) $new;
            }
        }
    } elseif ($fm) {
        $off = ($fm['type'] ?? '') === 'percent' ? '−'.(float) $fm['discount'].'%' : '−'.sta_money($fm['discount'], $d['currency'] ?? 'EUR');
        $label = trim(($fm['label'] ?? '') ?: 'Early Booking').' '.$off;
        $until = (string) ($fm['until'] ?? '');
        if ($old === null && ! array_key_exists('price_regular', $d) && $price) {
            $old = ($fm['type'] ?? '') === 'percent' ? round($price / max(0.01, 1 - $fm['discount'] / 100), 2) : $price + (float) $fm['discount'];
        }
    }

    return [$price, $old, $label, $until];
}
