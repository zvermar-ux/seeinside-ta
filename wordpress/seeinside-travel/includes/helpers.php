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
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'filter' => '<path d="M4 5h16M7 12h10M10 19h4"/>',
        'home' => '<path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
    ];
    $path = $icons[$name] ?? $icons['check'];

    return '<svg class="sta-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
}

/** Icon of an amenity (by its key in SeeInside; own amenities get one by their heroicon name or a check mark) */
function sta_amenity_icon(string $key, ?string $hero = null): string
{
    $p = [
        'wifi' => '<path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.8 16a5 5 0 0 1 6.4 0"/><circle cx="12" cy="19.3" r=".9" fill="currentColor"/>',
        'parking' => '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M10 17V7h3a3 3 0 0 1 0 6h-3"/>',
        'kitchen' => '<path d="M6 3v7a2 2 0 0 0 4 0V3M8 10v11M17 21V3c-2 1-3 4-3 7h3"/>',
        'air_conditioning' => '<path d="M12 2v20M4.9 6.5l14.2 11M19.1 6.5 4.9 17.5M9.5 3.5 12 6l2.5-2.5M9.5 20.5 12 18l2.5 2.5"/>',
        'fitness' => '<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>',
        'jacuzzi' => '<path d="M3 13h18v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM7 13V6a2 2 0 0 1 4 0M9 9.5c.8-.8.8-1.7 0-2.5M14 9.5c.8-.8.8-1.7 0-2.5M18 9.5c.8-.8.8-1.7 0-2.5"/>',
        'kids_club' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/>',
        'pool' => '<path d="M2 18c2 0 2-1.5 4-1.5s2 1.5 4 1.5 2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5M8 15V5a2 2 0 0 1 4 0M16 15V5a2 2 0 0 0-4 0M8 8h8M8 12h8"/>',
        'indoor_pool' => '<path d="m3 9 9-6 9 6M2 19c2 0 2-1.5 4-1.5s2 1.5 4 1.5 2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5M9 16V9M15 16V9M9 12h6"/>',
        'sauna' => '<path d="M8 21c-2-2-2-4 0-6s2-4 0-6M12 21c-2-2-2-4 0-6s2-4 0-6M16 21c-2-2-2-4 0-6s2-4 0-6"/>',
        'spa' => '<path d="M12 20c-4.5 0-8-3-8-7 3 0 6 1.5 8 4 2-2.5 5-4 8-4 0 4-3.5 7-8 7ZM12 17c-2-2.5-2-6.5 0-10 2 3.5 2 7.5 0 10Z"/>',
        'ski_shuttle' => '<rect x="4" y="3" width="16" height="15" rx="2"/><path d="M4 11h16M8 18v2M16 18v2"/>',
        'ski_storage' => '<path d="M7 21 17 3M11 21 21 3M4 17l4 2M8 17l4 2"/>',
        'airport_transfer' => '<path d="M10.5 13.5 3 11l1.5-1.5 7 1 4-4a2 2 0 0 1 3 3l-4 4 1 7L19 22l-2.5-7.5"/>',
        'restaurant' => '<path d="M7 3v8a2 2 0 0 0 2 2v8M5 3v5M9 3v5M17 21V3a4 4 0 0 0-3 4v7h3"/>',
        'bar' => '<path d="M5 4h14l-7 8zM12 12v8M8 20h8"/>',
        'beach' => '<path d="M3 20h18M12 20V9M4 9a8 8 0 0 1 16 0zM12 4V2"/>',
        'sunbeds' => '<path d="M3 17h18M5 17l2-5h11l2 5M7 12l-2-5M15 3 9 9"/>',
        'balcony' => '<path d="M4 12h16v8H4zM8 12v8M12 12v8M16 12v8M7 12V4h10v8"/>',
        'sea_view' => '<path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'tv' => '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'fridge' => '<rect x="6" y="2.5" width="12" height="19" rx="2"/><path d="M6 10h12M9 6v2M9 13v3"/>',
        'elevator' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m9 9 3-3 3 3M9 15l3 3 3-3"/>',
        'reception_24h' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pets' => '<circle cx="5.5" cy="11" r="2"/><circle cx="9" cy="6.5" r="2"/><circle cx="15" cy="6.5" r="2"/><circle cx="18.5" cy="11" r="2"/><path d="M12 12c-3 0-5 3.5-5 5.5S9 20 12 19.5 17 19.5 17 17.5 15 12 12 12Z"/>',
        'accessible' => '<circle cx="11" cy="4.5" r="1.8"/><path d="M11 8v6h5l2 5M11 11h5M8.5 10.5a5.5 5.5 0 1 0 6.8 8"/>',
        'playground' => '<path d="M4 21V8l4-4 4 4v13M4 13h8M12 11l8 10M16 3v5"/>',
        'laundry' => '<rect x="4" y="2.5" width="16" height="19" rx="2"/><circle cx="12" cy="13" r="4.5"/><path d="M7 6h.01M10 6h.01"/>',
    ];
    $byHero = ['heroicon-o-wifi' => 'wifi', 'heroicon-o-truck' => 'ski_shuttle', 'heroicon-o-fire' => 'sauna', 'heroicon-o-sun' => 'beach', 'heroicon-o-tv' => 'tv',
        'heroicon-o-home' => 'balcony', 'heroicon-o-eye' => 'sea_view', 'heroicon-o-clock' => 'reception_24h', 'heroicon-o-heart' => 'pets', 'heroicon-o-user' => 'accessible',
        'heroicon-o-face-smile' => 'kids_club', 'heroicon-o-paper-airplane' => 'airport_transfer', 'heroicon-o-cake' => 'restaurant', 'heroicon-o-beaker' => 'bar',
        'heroicon-o-lifebuoy' => 'pool', 'heroicon-o-sparkles' => 'spa', 'heroicon-o-cube' => 'fridge', 'heroicon-o-bolt' => 'fitness', 'heroicon-o-arrows-up-down' => 'elevator'];
    $path = $p[$key] ?? $p[$byHero[$hero ?? ''] ?? ''] ?? '<path d="m5 12 4.5 4.5L19 7"/>';

    return '<svg class="sta-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
}

/** Amenities as icon + name (hotel or room) */
function sta_amenities_html(array $list, string $class = ''): string
{
    $h = '';
    foreach ($list as $a) {
        if (is_string($a)) {
            $a = ['key' => $a, 'name' => ucfirst(str_replace('_', ' ', $a))];
        }
        $name = sta_t($a['name'] ?? '');
        if ($name !== '') {
            $h .= '<li>'.sta_amenity_icon((string) ($a['key'] ?? ''), $a['icon'] ?? null).'<span>'.esc_html($name).'</span></li>';
        }
    }

    return $h ? '<ul class="sta-amen '.esc_attr($class).'">'.$h.'</ul>' : '';
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
