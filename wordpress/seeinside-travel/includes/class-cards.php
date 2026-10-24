<?php

defined('ABSPATH') || exit;

/**
 * Card designer: one design per content type (tour, hotel, event, visa, charter, slide, destination…).
 * A design = look (skin) + which elements are shown and in which order + colours and texts.
 * Every list on the website (lists, sliders, archives, [sta_list], Elementor) uses these cards.
 */
class STA_Cards
{
    public const OPTION = 'sta_card_designs';

    public static function skins(): array
    {
        return [
            'classic' => __('Classic – photo on top, price and button below', 'seeinside-travel'),
            'stamp' => __('Postage stamp – travel style (as in the example)', 'seeinside-travel'),
            'overlay' => __('Text on the photo (portrait)', 'seeinside-travel'),
            'minimal' => __('Minimal – photo and title (category sliders)', 'seeinside-travel'),
            'horizontal' => __('Horizontal – photo left, text right', 'seeinside-travel'),
        ];
    }

    public static function elements(): array
    {
        return [
            'image' => __('Photo', 'seeinside-travel'),
            'flag' => __('Flag of the country', 'seeinside-travel'),
            'badges' => __('Badges (last minute, first minute, best seller)', 'seeinside-travel'),
            'eyebrow' => __('Small text above the title (country / category)', 'seeinside-travel'),
            'title' => __('Title', 'seeinside-travel'),
            'subtitle' => __('Subtitle (route, performer, processing time)', 'seeinside-travel'),
            'rating' => __('Rating (reviews)', 'seeinside-travel'),
            'dates' => __('Dates (+ other dates)', 'seeinside-travel'),
            'duration' => __('Duration', 'seeinside-travel'),
            'transport' => __('Transport (plane / bus)', 'seeinside-travel'),
            'place' => __('Place', 'seeinside-travel'),
            'stars' => __('Hotel stars', 'seeinside-travel'),
            'board' => __('Board (BB, HB, AI…)', 'seeinside-travel'),
            'amenities' => __('Amenities', 'seeinside-travel'),
            'count' => __('Number of offers', 'seeinside-travel'),
            'seats' => __('Seats left', 'seeinside-travel'),
            'excerpt' => __('Short description', 'seeinside-travel'),
            'price' => __('Price', 'seeinside-travel'),
            'button' => __('Button', 'seeinside-travel'),
        ];
    }

    /** Which elements make sense for a content type */
    public static function available(string $source): array
    {
        $term = ['image', 'flag', 'badges', 'title', 'count', 'excerpt', 'price', 'button'];
        $map = [
            'tour' => ['image', 'flag', 'badges', 'eyebrow', 'title', 'subtitle', 'rating', 'dates', 'duration', 'transport', 'place', 'seats', 'excerpt', 'price', 'button'],
            'accommodation' => ['image', 'flag', 'badges', 'eyebrow', 'title', 'stars', 'place', 'rating', 'board', 'amenities', 'excerpt', 'price', 'button'],
            'event' => ['image', 'flag', 'badges', 'eyebrow', 'title', 'subtitle', 'dates', 'place', 'rating', 'excerpt', 'price', 'button'],
            'visa' => ['image', 'flag', 'badges', 'eyebrow', 'title', 'subtitle', 'excerpt', 'price', 'button'],
            'custom' => ['image', 'badges', 'eyebrow', 'title', 'subtitle', 'excerpt', 'price', 'button'],
            'last_minute' => ['image', 'flag', 'badges', 'eyebrow', 'title', 'subtitle', 'stars', 'rating', 'dates', 'duration', 'transport', 'place', 'board', 'seats', 'excerpt', 'price', 'button'],
            'charter' => ['image', 'flag', 'eyebrow', 'title', 'subtitle', 'dates', 'transport', 'seats', 'excerpt', 'price', 'button'],
            'slide' => ['image', 'eyebrow', 'title', 'excerpt', 'button'],
        ];

        return $map[$source] ?? $term;
    }

    protected static function els(array $on, array $all): array
    {
        $out = [];
        foreach ($on as $k) {
            $out[] = ['key' => $k, 'on' => true];
        }
        foreach ($all as $k) {
            if (! in_array($k, $on, true)) {
                $out[] = ['key' => $k, 'on' => false];
            }
        }

        return $out;
    }

    public static function defaults(string $source): array
    {
        $base = [
            'skin' => 'classic', 'ratio' => '4/3', 'radius' => 16, 'shadow' => 'soft', 'hover' => 'lift', 'align' => 'left',
            'bg' => '#ffffff', 'text' => '#10233f', 'muted' => '#6b7280', 'accent' => '', 'title_size' => 19, 'title_lines' => 2, 'excerpt_words' => 18,
            'price_label' => ['mk' => 'од', 'en' => 'from'], 'button_text' => ['mk' => 'Повеќе', 'en' => 'More'], 'button_style' => 'outline',
            'featured_badge' => true, 'featured_text' => ['mk' => 'BEST SELLER', 'en' => 'BEST SELLER'], 'postmark' => true,
            'count_label' => ['mk' => 'понуди', 'en' => 'offers'], 'more_dates' => true, 'image_height' => 0,
        ];
        $all = self::available($source);
        $d = match ($source) {
            'tour' => ['skin' => 'stamp', 'bg' => '#fdf7ef', 'accent' => '#e8742a', 'price_label' => ['mk' => 'по лице од', 'en' => 'per person from'],
                'elements' => self::els(['image', 'flag', 'badges', 'eyebrow', 'title', 'dates', 'price', 'button'], $all)],
            'last_minute' => ['skin' => 'stamp', 'bg' => '#fdf7ef', 'accent' => '#e8742a', 'price_label' => ['mk' => 'по лице од', 'en' => 'per person from'],
                'elements' => self::els(['image', 'flag', 'badges', 'eyebrow', 'title', 'dates', 'price', 'button'], $all)],
            'accommodation' => ['elements' => self::els(['image', 'badges', 'stars', 'title', 'place', 'rating', 'board', 'price', 'button'], $all), 'price_label' => ['mk' => 'од', 'en' => 'from']],
            'event' => ['elements' => self::els(['image', 'badges', 'eyebrow', 'title', 'dates', 'place', 'price', 'button'], $all), 'button_text' => ['mk' => 'Билети', 'en' => 'Tickets']],
            'visa' => ['ratio' => '16/9', 'elements' => self::els(['image', 'flag', 'eyebrow', 'title', 'subtitle', 'price', 'button'], $all), 'button_text' => ['mk' => 'Аплицирај', 'en' => 'Apply']],
            'custom' => ['elements' => self::els(['image', 'badges', 'eyebrow', 'title', 'subtitle', 'price', 'button'], $all)],
            'charter' => ['ratio' => '16/9', 'elements' => self::els(['flag', 'eyebrow', 'title', 'subtitle', 'dates', 'seats', 'price', 'button'], $all), 'button_text' => ['mk' => 'Резервирај', 'en' => 'Book']],
            'slide' => ['skin' => 'overlay', 'ratio' => '3/4', 'elements' => self::els(['image', 'eyebrow', 'title', 'excerpt', 'button'], $all)],
            'destination', 'group' => ['skin' => 'overlay', 'ratio' => '2/3', 'radius' => 22, 'button_text' => ['mk' => 'Види сите', 'en' => 'View all'],
                'count_label' => ['mk' => 'понуди', 'en' => 'listings'], 'elements' => self::els(['image', 'title', 'count', 'button'], $all)],
            default => ['skin' => 'minimal', 'ratio' => '1/1', 'radius' => 18, 'align' => 'center', 'button_style' => 'link', 'button_text' => ['mk' => 'Види повеќе', 'en' => 'See more'],
                'elements' => self::els(['image', 'title', 'button'], $all)],
        };

        return array_replace($base, $d);
    }

    public static function all_designs(): array
    {
        $saved = get_option(self::OPTION, []);

        return is_array($saved) ? $saved : [];
    }

    /** Saved design of a type, merged with its defaults (new elements are added, unknown ones dropped) */
    public static function design(string $source, ?array $override = null): array
    {
        $def = self::defaults($source);
        $saved = $override ?? (self::all_designs()[$source] ?? []);
        $d = array_replace($def, is_array($saved) ? $saved : []);
        $avail = self::available($source);
        $els = [];
        foreach ((array) ($d['elements'] ?? []) as $e) {
            $k = is_array($e) ? ($e['key'] ?? '') : (string) $e;
            if (in_array($k, $avail, true) && ! isset($els[$k])) {
                $els[$k] = ['key' => $k, 'on' => is_array($e) ? ! empty($e['on']) : true];
            }
        }
        foreach ($def['elements'] as $e) {
            if (! isset($els[$e['key']])) {
                $els[$e['key']] = $e + ['on' => false];
            }
        }
        $d['elements'] = array_values($els);

        return $d;
    }

    /** Clean values from the admin form */
    public static function sanitize(string $source, array $in): array
    {
        $def = self::defaults($source);
        $out = [];
        $out['skin'] = array_key_exists($in['skin'] ?? '', self::skins()) ? $in['skin'] : $def['skin'];
        $out['ratio'] = in_array($in['ratio'] ?? '', ['4/3', '3/2', '16/9', '1/1', '3/4', '2/3', '4/5'], true) ? $in['ratio'] : $def['ratio'];
        foreach (['radius' => [0, 40], 'title_size' => [12, 40], 'title_lines' => [1, 4], 'excerpt_words' => [5, 60], 'image_height' => [0, 600]] as $k => [$min, $max]) {
            $out[$k] = max($min, min($max, (int) ($in[$k] ?? $def[$k])));
        }
        $out['shadow'] = in_array($in['shadow'] ?? '', ['none', 'soft', 'strong'], true) ? $in['shadow'] : $def['shadow'];
        $out['hover'] = in_array($in['hover'] ?? '', ['none', 'lift', 'zoom', 'tilt'], true) ? $in['hover'] : $def['hover'];
        $out['align'] = ($in['align'] ?? '') === 'center' ? 'center' : 'left';
        $out['button_style'] = in_array($in['button_style'] ?? '', ['outline', 'solid', 'link', 'arrow'], true) ? $in['button_style'] : $def['button_style'];
        foreach (['bg', 'text', 'muted', 'accent'] as $k) {
            $out[$k] = sanitize_hex_color((string) ($in[$k] ?? '')) ?: ($k === 'accent' ? '' : $def[$k]);
        }
        foreach (['price_label', 'button_text', 'featured_text', 'count_label'] as $k) {
            $out[$k] = ['mk' => sanitize_text_field((string) ($in[$k]['mk'] ?? $def[$k]['mk'])), 'en' => sanitize_text_field((string) ($in[$k]['en'] ?? $def[$k]['en']))];
        }
        foreach (['featured_badge', 'postmark', 'more_dates'] as $k) {
            $out[$k] = ! empty($in[$k]);
        }
        $avail = self::available($source);
        $order = array_values(array_filter(array_map('sanitize_key', explode(',', (string) ($in['order'] ?? ''))), fn ($k) => in_array($k, $avail, true)));
        $on = array_map('sanitize_key', (array) ($in['on'] ?? []));
        $out['elements'] = array_map(fn ($k) => ['key' => $k, 'on' => in_array($k, $on, true)], $order ?: array_column($def['elements'], 'key'));

        return self::design($source, $out);
    }

    /* ======================== HTML ======================== */

    protected static function txt($v): string
    {
        return is_array($v) ? (string) ($v[sta_lang()] ?? $v['mk'] ?? '') : (string) $v;
    }

    protected static function short_date(?string $d, bool $year = true): string
    {
        if (! $d) {
            return '';
        }
        $t = strtotime($d);

        return $t ? date_i18n($year ? 'd.m.Y' : 'd.m', $t) : '';
    }

    public static function date_range(?string $from, ?string $to): string
    {
        if (! $from) {
            return '';
        }
        if (! $to || $to === $from) {
            return self::short_date($from);
        }

        return self::short_date($from, substr($from, 0, 4) !== substr($to, 0, 4)).' – '.self::short_date($to);
    }

    public static function style_vars(array $d): string
    {
        $vars = [
            '--c-bg' => $d['bg'], '--c-text' => $d['text'], '--c-muted' => $d['muted'], '--c-radius' => (int) $d['radius'].'px',
            '--c-title' => (int) $d['title_size'].'px', '--c-lines' => (int) $d['title_lines'], '--c-ratio' => $d['ratio'],
        ];
        if ($d['accent']) {
            $vars['--c-accent'] = $d['accent'];
        }
        if (! empty($d['image_height'])) {
            $vars['--c-img-h'] = (int) $d['image_height'].'px';
        }

        return implode(';', array_map(fn ($k, $v) => $k.':'.$v, array_keys($vars), $vars));
    }

    public static function classes(array $d, string $source): string
    {
        return 'sta-c sta-c--'.$d['skin'].' sta-c-t-'.sanitize_html_class($source).' is-sh-'.$d['shadow'].' is-hv-'.$d['hover'].' is-al-'.$d['align'].' is-btn-'.$d['button_style'];
    }

    protected static function icon(string $name): string
    {
        return function_exists('sta_icon') ? sta_icon($name) : '';
    }

    protected static function heart(): string
    {
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21s-7.5-4.6-9.6-9.3C.9 8.4 2.8 4.5 6.6 4.1c2.1-.2 3.9.9 5.4 2.8 1.5-1.9 3.3-3 5.4-2.8 3.8.4 5.7 4.3 4.2 7.6C19.5 16.4 12 21 12 21z"/></svg>';
    }

    protected static function postmark(): string
    {
        $name = mb_strtoupper(mb_substr((string) (get_option('sta_meta', [])['agency']['name'] ?? get_bloginfo('name')), 0, 14));
        $id = 'pm'.wp_unique_id();

        return '<svg class="sta-c-postmark" viewBox="0 0 120 64" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.2">'
            .'<path d="M2 18c6-5 12 5 18 0s12 5 18 0 12 5 18 0M2 30c6-5 12 5 18 0s12 5 18 0 12 5 18 0M2 42c6-5 12 5 18 0s12 5 18 0 12 5 18 0"/>'
            .'<circle cx="90" cy="32" r="26"/><circle cx="90" cy="32" r="20" stroke-width="1.2"/></g>'
            .'<defs><path id="'.$id.'" d="M70 32a20 20 0 1 1 40 0"/></defs>'
            .'<text font-size="9" font-weight="800" letter-spacing="1" fill="currentColor"><textPath href="#'.$id.'" startOffset="50%" text-anchor="middle">'.esc_html($name).'</textPath></text></svg>';
    }

    /** One card */
    public static function render(array $it, ?array $design = null, string $source = ''): string
    {
        $source = $source ?: ($it['kind'] === 'term' ? $it['type'] : $it['type']);
        $d = $design ?? self::design($source);
        $on = [];
        foreach ($d['elements'] as $e) {
            if (! empty($e['on'])) {
                $on[] = $e['key'];
            }
        }
        $has = fn ($k) => in_array($k, $on, true);
        $url = (string) $it['url'];
        $title = (string) $it['title'];
        $lang = sta_lang();

        // media
        $media = '';
        if ($has('image') || in_array($d['skin'], ['overlay'], true)) {
            $img = $it['image'] ? '<img src="'.esc_url($it['image']).'" alt="'.esc_attr($title).'" loading="lazy" decoding="async">' : '<span class="sta-c-noimg">'.self::icon($it['transport'] === 'flight' ? 'plane' : 'pin').'</span>';
            $over = '';
            if ($has('flag') && $it['country_code']) {
                $over .= '<img class="sta-c-flag" src="'.esc_url(STA_Countries::flag($it['country_code'], 80)).'" alt="" width="40" height="28" loading="lazy" onerror="this.remove()">';
            }
            if ($has('badges')) {
                $b = '';
                foreach ($it['badges'] as [$cls, $label]) {
                    $b .= '<span class="sta-c-badge is-'.esc_attr($cls).'">'.esc_html(trim($label)).'</span>';
                }
                if ($b) {
                    $over .= '<div class="sta-c-badges">'.$b.'</div>';
                }
                if ($it['featured'] && ! empty($d['featured_badge'])) {
                    $over .= '<span class="sta-c-heart">'.self::heart().'<b>'.esc_html(self::txt($d['featured_text'])).'</b></span>';
                }
            }
            if ($d['skin'] === 'stamp' && ! empty($d['postmark'])) {
                $over .= self::postmark();
            }
            $frame = $d['skin'] === 'stamp' ? '<div class="sta-c-stamp">'.$img.'</div>' : $img;
            $media = '<div class="sta-c-media">'.$frame.$over.'</div>';
        } elseif ($has('flag') && $it['country_code']) {
            $media = '<div class="sta-c-flagonly"><img class="sta-c-flag" src="'.esc_url(STA_Countries::flag($it['country_code'], 80)).'" alt="" width="40" height="28" loading="lazy" onerror="this.parentNode.remove()"></div>';
        }

        // body, in the chosen order
        $parts = [];
        $chips = [];
        $flush = function () use (&$chips, &$parts) {
            if ($chips) {
                $parts[] = '<div class="sta-c-chips">'.implode('', $chips).'</div>';
                $chips = [];
            }
        };
        $foot = ['price' => '', 'button' => ''];
        $order = array_values(array_filter($on, fn ($k) => ! in_array($k, ['image', 'flag', 'badges'], true)));
        foreach ($order as $i => $k) {
            $chip = '';
            switch ($k) {
                case 'eyebrow':
                    $flush();
                    if ($it['eyebrow']) {
                        $parts[] = '<div class="sta-c-eyebrow">'.esc_html($it['eyebrow']).'</div>';
                    }
                    break;
                case 'title':
                    $flush();
                    $parts[] = '<h3 class="sta-c-title">'.($url ? '<a href="'.esc_url($url).'" class="sta-c-stretch">'.esc_html($title).'</a>' : esc_html($title)).'</h3>';
                    break;
                case 'subtitle':
                    $flush();
                    if ($it['subtitle']) {
                        $parts[] = '<div class="sta-c-sub">'.esc_html($it['subtitle']).'</div>';
                    }
                    break;
                case 'excerpt':
                    $flush();
                    if ($it['excerpt']) {
                        $parts[] = '<p class="sta-c-excerpt">'.esc_html(wp_trim_words($it['excerpt'], (int) $d['excerpt_words'], '…')).'</p>';
                    }
                    break;
                case 'rating':
                    if ($it['reviews']) {
                        $chip = '<span class="sta-c-chip sta-c-rating">★ <b>'.esc_html(number_format_i18n($it['rating'], 1)).'</b> <small>('.(int) $it['reviews'].')</small></span>';
                    }
                    break;
                case 'stars':
                    if ($it['stars']) {
                        $chip = '<span class="sta-c-chip sta-c-stars" aria-label="'.(int) $it['stars'].'*">'.str_repeat('★', (int) $it['stars']).'</span>';
                    }
                    break;
                case 'dates':
                    if ($it['date_from']) {
                        $extra = count($it['dates']) - 1;
                        $more = '';
                        if ($extra > 0 && ! empty($d['more_dates'])) {
                            $list = '';
                            foreach (array_slice($it['dates'], 1, 12) as $x) {
                                $list .= '<li>'.esc_html(self::date_range($x['from'], $x['to'] ?? null)).(! empty($x['lm']) ? ' <em>LM</em>' : '').'</li>';
                            }
                            $more = '<span class="sta-c-more"><button type="button" class="sta-c-more-btn" aria-expanded="false">+'.$extra.' <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"/></svg></button><ul class="sta-c-more-list">'.$list.'</ul></span>';
                        }
                        $time = ! empty($it['dates'][0]['time']) && $it['dates'][0]['time'] !== '00:00' ? ' · '.$it['dates'][0]['time'] : '';
                        $chip = '<span class="sta-c-chip sta-c-dates">'.self::icon('calendar').esc_html(self::date_range($it['date_from'], $it['date_to']).$time).'</span>'.$more;
                    }
                    break;
                case 'duration':
                    if ($it['duration']) {
                        $chip = '<span class="sta-c-chip">'.self::icon('clock').esc_html($it['duration']).'</span>';
                    }
                    break;
                case 'transport':
                    if ($it['transport']) {
                        $chip = '<span class="sta-c-chip">'.self::icon($it['transport'] === 'flight' ? 'plane' : 'bus').esc_html($it['transport'] === 'flight' ? __('Flight', 'seeinside-travel') : __('Bus', 'seeinside-travel')).'</span>';
                    }
                    break;
                case 'place':
                    if ($it['place']) {
                        $chip = '<span class="sta-c-chip">'.self::icon('pin').esc_html($it['place']).'</span>';
                    }
                    break;
                case 'board':
                    if ($it['board']) {
                        $chip = '<span class="sta-c-chip">'.self::icon('bed').esc_html($it['board']).'</span>';
                    }
                    break;
                case 'amenities':
                    if ($it['amenities']) {
                        $chip = '<span class="sta-c-chip sta-c-amen">'.esc_html(implode(' · ', $it['amenities'])).'</span>';
                    }
                    break;
                case 'count':
                    if ($it['count'] !== null) {
                        $chip = '<span class="sta-c-chip sta-c-count">'.(int) $it['count'].' '.esc_html(self::txt($d['count_label'])).($it['promo_count'] ? ' <em class="sta-c-promo">'.(int) $it['promo_count'].' promo</em>' : '').'</span>';
                    }
                    break;
                case 'seats':
                    if ($it['seats'] !== null && $it['seats'] <= 20) {
                        $chip = '<span class="sta-c-chip sta-c-seats">'.self::icon('users').esc_html(sprintf(_n('Last %d seat', 'Last %d seats', (int) $it['seats'], 'seeinside-travel'), (int) $it['seats'])).'</span>';
                    }
                    break;
                case 'price':
                    $flush();
                    if ($it['price'] !== null && $it['price'] > 0) {
                        $foot['price'] = '<div class="sta-c-price"><small>'.esc_html(self::txt($d['price_label'])).'</small><b>'.esc_html(sta_money($it['price'], $it['currency'])).'</b>'
                            .($it['old_price'] ? '<s>'.esc_html(sta_money($it['old_price'], $it['currency'])).'</s>' : '').'</div>';
                    }
                    $next = $order[$i + 1] ?? null;
                    if ($next !== 'button') {
                        $parts[] = '<div class="sta-c-foot">'.$foot['price'].'</div>';
                        $foot['price'] = '';
                    }
                    break;
                case 'button':
                    $flush();
                    $label = ! empty($it['button']) ? $it['button'] : self::txt($d['button_text']);
                    $btn = $url && $label ? '<a class="sta-c-btn" href="'.esc_url($url).'">'.esc_html($label).' <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>' : '';
                    $parts[] = '<div class="sta-c-foot'.($foot['price'] ? '' : ' is-btn-only').'">'.$foot['price'].$btn.'</div>';
                    $foot['price'] = '';
                    break;
            }
            if ($chip !== '') {
                $chips[] = $chip;
            }
        }
        $flush();

        return '<article class="'.esc_attr(self::classes($d, $source)).'" style="'.esc_attr(self::style_vars($d)).'" data-key="'.esc_attr($it['key']).'">'
            .$media.'<div class="sta-c-body">'.implode('', $parts).'</div></article>';
    }
}
