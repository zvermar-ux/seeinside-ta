<?php

defined('ABSPATH') || exit;

/**
 * HTML for every part of a tour / event / hotel / visa page. Used by shortcodes, Elementor widgets,
 * blocks and the automatic layout. Every section can be replaced with the "sta_section_html" filter
 * or a theme file /seeinside-travel/section-<name>.php.
 */
class STA_Render
{
    public const SECTIONS = [
        'facts' => 'Key facts',
        'price' => 'Price & booking button',
        'gallery' => 'Gallery',
        'description' => 'Description',
        'highlights' => 'Highlights',
        'program' => 'Program by days',
        'departures' => 'Dates & prices',
        'included' => 'Included / not included',
        'extras' => 'Extras & excursions',
        'info' => 'Notes & terms',
        'rooms' => 'Rooms & prices',
        'amenities' => 'Amenities',
        'tickets' => 'Tickets',
        'requirements' => 'Visa requirements',
        'info_cards' => 'Visa info cards',
        'reviews' => 'Reviews',
        'availability' => 'Availability calendar',
        'map' => 'Map',
        'videos' => 'Videos',
        'booking' => 'Booking form',
        'layout' => 'Whole page (all sections)',
    ];

    public static function section(string $name, ?int $postId = null, array $args = []): string
    {
        $postId = $postId ?: get_the_ID();
        $d = sta_data($postId);
        if (! $d) {
            return '';
        }
        $type = $d['type'] ?? STA_Schema::api_type(get_post_type($postId));

        $theme = locate_template('seeinside-travel/section-'.$name.'.php');
        if ($theme) {
            $html = sta_part('section-'.$name, ['d' => $d, 'type' => $type, 'post_id' => $postId, 'args' => $args]);
        } else {
            $method = 's_'.$name;
            $html = method_exists(self::class, $method) ? (string) self::$method($d, $type, $postId, $args) : '';
        }
        $html = apply_filters('sta_section_html', $html, $name, $d, $postId, $args);
        if ($html === '') {
            return '';
        }
        $title = $args['title'] ?? null;
        $heading = ($title !== false && $title) ? '<h3 class="sta-h">'.esc_html($title).'</h3>' : '';

        $extra = $name === 'layout' ? ' alignwide' : '';

        return '<div class="sta sta-section sta-s-'.esc_attr($name).$extra.'" id="sta-'.esc_attr($name).'">'.$heading.$html.'</div>';
    }

    protected static function heading(string $text): string
    {
        return '<h3 class="sta-h">'.esc_html($text).'</h3>';
    }

    /* ---------------- sections ---------------- */

    protected static function s_facts(array $d, string $type): string
    {
        $items = [];
        $add = function ($icon, $label, $value) use (&$items) {
            if ($value !== null && $value !== '' && $value !== false) {
                $items[] = '<li>'.sta_icon($icon).'<span><small>'.esc_html($label).'</small><b>'.wp_kses_post($value).'</b></span></li>';
            }
        };

        if ($type === 'tour') {
            $days = $d['duration']['days'] ?? null;
            $nights = $d['duration']['nights'] ?? null;
            $add('clock', __('Duration', 'seeinside-travel'), $days ? sprintf(_n('%d day', '%d days', $days, 'seeinside-travel'), $days).($nights ? ' / '.sprintf(_n('%d night', '%d nights', $nights, 'seeinside-travel'), $nights) : '') : null);
            $add($d['is_flight'] ? 'plane' : 'bus', __('Transport', 'seeinside-travel'), $d['is_flight'] ? trim(__('Flight', 'seeinside-travel').' '.($d['airline']['name'] ?? '')) : sta_t($d['tour_type']['label'] ?? null));
            $add('pin', __('Destination', 'seeinside-travel'), trim(($d['location'] ?? '').(! empty($d['country']) ? ', '.$d['country'] : ''), ', '));
            $add('calendar', __('Next departure', 'seeinside-travel'), sta_date($d['next_departure'] ?? null));
            $min = $d['group_size']['min'] ?? null;
            $max = $d['group_size']['max'] ?? null;
            $add('users', __('Group size', 'seeinside-travel'), $min || $max ? trim(($min ?: '').'–'.($max ?: ''), '–') : null);
            if (! empty($d['has_guide'])) {
                $add('flag', __('Tour leader', 'seeinside-travel'), __('Yes', 'seeinside-travel'));
            }
            if (! empty($d['aries_quality'])) {
                $add('shield', __('Quality', 'seeinside-travel'), __('Guaranteed', 'seeinside-travel'));
            }
        } elseif ($type === 'event') {
            $add('calendar', __('Date', 'seeinside-travel'), sta_date($d['starts_at'] ?? null, true));
            $add('pin', __('Venue', 'seeinside-travel'), trim(($d['venue'] ?? '').', '.($d['city'] ?? ''), ', '));
            $add('users', __('Performer', 'seeinside-travel'), $d['performer'] ?? null);
            $add('clock', __('Doors open', 'seeinside-travel'), $d['doors_open'] ?? null);
            $add('ticket', __('Category', 'seeinside-travel'), sta_t($d['category']['label'] ?? null));
        } elseif ($type === 'accommodation') {
            $add('star', __('Category', 'seeinside-travel'), ! empty($d['stars']) ? str_repeat('★', (int) $d['stars']) : sta_t($d['accommodation_type']['label'] ?? null));
            $add('pin', __('Location', 'seeinside-travel'), trim(($d['place'] ?? '').', '.($d['country'] ?? ''), ', '));
            $add('wave', __('Beach', 'seeinside-travel'), isset($d['distances']['beach_m']) && $d['distances']['beach_m'] !== null ? $d['distances']['beach_m'].' m' : null);
            $add('home', __('Centre', 'seeinside-travel'), isset($d['distances']['center_m']) && $d['distances']['center_m'] !== null ? $d['distances']['center_m'].' m' : null);
            $add('clock', __('Check-in / out', 'seeinside-travel'), trim(($d['check_in'] ?? '').' / '.($d['check_out'] ?? ''), ' /'));
            $add('bed', __('Board', 'seeinside-travel'), implode(', ', $d['board_types'] ?? []));
        } elseif ($type === 'visa') {
            $add('flag', __('Country', 'seeinside-travel'), $d['country'] ?? null);
            $add('doc', __('Visa type', 'seeinside-travel'), sta_t($d['category']['label'] ?? null));
            $add('clock', __('Processing time', 'seeinside-travel'), sta_t($d['processing_time'] ?? null));
            $add('calendar', __('Validity', 'seeinside-travel'), sta_t($d['validity'] ?? null));
        }

        return $items ? '<ul class="sta-facts">'.implode('', $items).'</ul>' : '';
    }

    public static function promo_badges(array $d): string
    {
        $out = '';
        if (! empty($d['promotions']['last_minute'])) {
            $lm = $d['promotions']['last_minute'];
            $out .= '<span class="sta-badge sta-badge-lm">'.sta_icon('bolt').' LAST MINUTE'.(! empty($lm['discount_percent']) ? ' −'.(float) $lm['discount_percent'].'%' : '').'</span>';
        }
        if (! empty($d['promotions']['first_minute'])) {
            $fm = $d['promotions']['first_minute'];
            $out .= '<span class="sta-badge sta-badge-fm">FIRST MINUTE '.($fm['type'] === 'percent' ? '−'.(float) $fm['discount'].'%' : '−'.sta_money($fm['discount'], $d['currency'] ?? 'EUR')).'</span>';
        }

        return $out;
    }

    protected static function s_price(array $d, string $type): string
    {
        $price = $type === 'visa' ? ($d['price'] ?? null) : ($d['price_from'] ?? null);
        $currency = $d['currency'] ?? 'EUR';
        $lm = $d['promotions']['last_minute'] ?? null;
        $old = null;
        if ($lm && $price) {
            $old = $price;
            $price = $lm['price'] ?? ($lm['discount_percent'] ? $price * (1 - $lm['discount_percent'] / 100) : $price);
        }
        $per = match ($type) {
            'tour' => __('per person', 'seeinside-travel'),
            'accommodation' => __('per night', 'seeinside-travel'),
            'event' => __('per ticket', 'seeinside-travel'),
            default => '',
        };
        $cta = match ($type) {
            'visa' => __('Apply online', 'seeinside-travel'),
            'accommodation' => __('Check availability', 'seeinside-travel'),
            'event' => __('Buy tickets', 'seeinside-travel'),
            default => __('Book now', 'seeinside-travel'),
        };

        $html = '<div class="sta-pricebox">'.self::promo_badges($d);
        if ($price !== null) {
            $html .= '<div class="sta-price-label">'.esc_html($type === 'visa' ? __('Price', 'seeinside-travel') : __('Price from', 'seeinside-travel')).'</div>';
            $html .= '<div class="sta-price">'.($old ? '<del>'.esc_html(sta_money($old, $currency)).'</del> ' : '').'<b>'.esc_html(sta_money($price, $currency)).'</b> <small>'.esc_html($per).'</small></div>';
        } else {
            $html .= '<div class="sta-price"><b>'.esc_html__('Price on request', 'seeinside-travel').'</b></div>';
        }
        if (! empty($lm['until'])) {
            $html .= '<div class="sta-muted">'.esc_html(sprintf(__('Offer valid until %s', 'seeinside-travel'), sta_date($lm['until']))).'</div>';
        }
        if ($type === 'event' && ! empty($d['external_ticket_url']) && empty($d['booking']['enabled'])) {
            $html .= '<a class="sta-btn sta-btn-block" href="'.esc_url($d['external_ticket_url']).'" target="_blank" rel="noopener">'.esc_html($cta).'</a>';
        } else {
            $html .= '<a class="sta-btn sta-btn-block" href="#sta-booking">'.esc_html($cta).'</a>';
        }
        $agency = (get_option('sta_meta', [])['agency'] ?? []);
        if (! empty($agency['phone'])) {
            $html .= '<a class="sta-call" href="tel:'.esc_attr(preg_replace('/[^0-9+]/', '', $agency['phone'])).'">'.esc_html__('or call', 'seeinside-travel').' <b>'.esc_html($agency['phone']).'</b></a>';
        }

        return $html.'</div>';
    }

    protected static function s_gallery(array $d, string $type, int $postId, array $args = []): string
    {
        $ids = [];
        if (get_post_thumbnail_id($postId)) {
            $ids[] = (int) get_post_thumbnail_id($postId);
        }
        $gallery = function_exists('get_field') ? get_field('gallery', $postId, false) : get_post_meta($postId, 'gallery', true);
        foreach ((array) $gallery as $g) {
            $id = is_array($g) ? (int) ($g['ID'] ?? $g['id'] ?? 0) : (int) $g;
            if ($id && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        if (! $ids) {
            return '';
        }
        $html = '<div class="sta-gallery sta-gallery-'.min(5, count($ids)).'" data-sta-lightbox>';
        foreach ($ids as $i => $id) {
            $full = wp_get_attachment_image_url($id, 'full');
            $html .= '<a href="'.esc_url($full).'" class="sta-gallery-item'.($i > 4 ? ' sta-hidden' : '').'">'.wp_get_attachment_image($id, $i === 0 ? 'large' : 'medium_large', false, ['loading' => $i ? 'lazy' : 'eager']).($i === 4 && count($ids) > 5 ? '<span class="sta-more">+'.(count($ids) - 5).'</span>' : '').'</a>';
        }

        return $html.'</div>';
    }

    protected static function s_description(array $d, string $type): string
    {
        $html = sta_t($type === 'accommodation' ? ($d['overview'] ?? null) : ($d['description'] ?? null));
        $short = sta_t($d['short_description'] ?? null);
        if (! $html && ! $short) {
            return '';
        }

        return ($short && ! $html ? '<p class="sta-lead">'.esc_html($short).'</p>' : '').'<div class="sta-prose">'.wp_kses_post(wpautop($html)).'</div>'
            .($type === 'tour' && sta_t($d['agenda'] ?? null) ? '<div class="sta-prose">'.wp_kses_post(wpautop(sta_t($d['agenda']))).'</div>' : '');
    }

    protected static function list_html(array $items, string $icon = 'check', string $class = ''): string
    {
        if (! $items) {
            return '';
        }

        return '<ul class="sta-list '.$class.'">'.implode('', array_map(fn ($i) => '<li>'.sta_icon($icon).'<span>'.esc_html($i).'</span></li>', $items)).'</ul>';
    }

    protected static function s_highlights(array $d): string
    {
        $items = sta_t_list($d['highlights'] ?? []);

        return $items ? self::heading(__('Highlights', 'seeinside-travel')).self::list_html($items, 'star', 'sta-list-grid') : '';
    }

    protected static function s_program(array $d, string $type): string
    {
        if (empty($d['program'])) {
            return '';
        }
        $html = self::heading(__('Program', 'seeinside-travel')).'<div class="sta-timeline">';
        foreach ($d['program'] as $i => $day) {
            $meals = is_array($day['meals'] ?? null) ? implode(', ', $day['meals']) : ($day['meals'] ?? '');
            $html .= '<details class="sta-day"'.($i === 0 ? ' open' : '').'><summary><span class="sta-day-n">'.esc_html(sprintf(__('Day %d', 'seeinside-travel'), $day['day'])).'</span><span class="sta-day-t">'.esc_html(sta_t($day['title'])).'</span></summary>'
                .'<div class="sta-day-body sta-prose">'.wp_kses_post(wpautop(sta_t($day['description'])))
                .($meals || ! empty($day['overnight']) ? '<p class="sta-muted">'.($meals ? esc_html__('Meals:', 'seeinside-travel').' '.esc_html($meals).' ' : '').(! empty($day['overnight']) ? esc_html__('Overnight:', 'seeinside-travel').' '.esc_html($day['overnight']) : '').'</p>' : '')
                .'</div></details>';
        }
        $html .= '</div>';
        if (! empty($d['program_pdf'])) {
            $html .= '<p><a class="sta-btn sta-btn-ghost" href="'.esc_url($d['program_pdf']).'" target="_blank" rel="noopener">'.sta_icon('doc').' '.esc_html__('Program (PDF)', 'seeinside-travel').'</a></p>';
        }

        return $html;
    }

    protected static function s_departures(array $d, string $type): string
    {
        if ($type !== 'tour') {
            return '';
        }
        $currency = $d['currency'] ?? 'EUR';
        $html = self::heading(__('Dates & prices', 'seeinside-travel'));
        if (empty($d['departures'])) {
            return $html.'<p class="sta-muted">'.esc_html__('New dates coming soon – send an inquiry.', 'seeinside-travel').'</p>';
        }
        $html .= '<div class="sta-table-wrap"><table class="sta-table"><thead><tr><th>'.esc_html__('Departure', 'seeinside-travel').'</th><th>'.esc_html__('Return', 'seeinside-travel').'</th><th>'.esc_html__('Adult', 'seeinside-travel').'</th><th>'.esc_html__('Child', 'seeinside-travel').'</th><th>'.esc_html__('Status', 'seeinside-travel').'</th><th></th></tr></thead><tbody>';
        foreach ($d['departures'] as $dep) {
            $closed = in_array($dep['status'], ['sold_out', 'cancelled'], true);
            $seats = $dep['seats_left'];
            $status = sta_t($dep['status_label'] ?? null) ?: $dep['status'];
            if (! $closed && $seats !== null && $seats <= 5) {
                $status = sprintf(_n('Last %d seat', 'Last %d seats', $seats, 'seeinside-travel'), $seats);
            }
            $html .= '<tr class="'.($closed ? 'is-closed' : '').'"><td><b>'.esc_html(sta_date($dep['start_date'])).'</b>'.(! empty($dep['is_last_minute']) ? ' <span class="sta-badge sta-badge-lm">LM</span>' : '').'</td><td>'.esc_html(sta_date($dep['end_date'])).'</td>'
                .'<td>'.esc_html(sta_money($dep['price_adult'], $currency)).'</td><td>'.esc_html($dep['price_child'] !== null ? sta_money($dep['price_child'], $currency) : '—').'</td>'
                .'<td><span class="sta-status sta-status-'.esc_attr($dep['status']).'">'.esc_html($status).'</span></td>'
                .'<td>'.($closed ? '' : '<a class="sta-btn sta-btn-sm" href="#sta-booking" data-sta-departure="'.(int) $dep['id'].'">'.esc_html__('Book', 'seeinside-travel').'</a>').'</td></tr>';
        }
        $html .= '</tbody></table></div>';

        if (! empty($d['departure_places'])) {
            $html .= '<h4 class="sta-h4">'.esc_html__('Departure places', 'seeinside-travel').'</h4><ul class="sta-chips">';
            foreach ($d['departure_places'] as $p) {
                $html .= '<li>'.sta_icon('pin').esc_html(sta_t($p['name'])).($p['time'] ? ' · '.esc_html($p['time']) : '').($p['surcharge'] ? ' · +'.esc_html(sta_money($p['surcharge'], $currency)) : '').'</li>';
            }
            $html .= '</ul>';
        }
        $prices = $d['prices'] ?? [];
        $notes = [];
        if (! empty($prices['child_age']['to'])) {
            $notes[] = sprintf(__('Child price for ages %1$s–%2$s.', 'seeinside-travel'), $prices['child_age']['from'] ?? 0, $prices['child_age']['to']);
        }
        if (! empty($prices['single_supplement'])) {
            $notes[] = sprintf(__('Single room supplement: %s.', 'seeinside-travel'), sta_money($prices['single_supplement'], $currency));
        }
        if (! empty($prices['deposit_percent'])) {
            $notes[] = sprintf(__('Deposit when booking: %s%%.', 'seeinside-travel'), (float) $prices['deposit_percent']);
        }

        return $html.($notes ? '<p class="sta-muted">'.esc_html(implode(' ', $notes)).'</p>' : '');
    }

    protected static function s_included(array $d): string
    {
        $in = sta_t_list($d['included'] ?? []);
        $out = sta_t_list($d['not_included'] ?? []);
        if (! $in && ! $out) {
            return '';
        }

        return '<div class="sta-cols">'
            .($in ? '<div>'.self::heading(__('Included in the price', 'seeinside-travel')).self::list_html($in, 'check', 'sta-list-yes').'</div>' : '')
            .($out ? '<div>'.self::heading(__('Not included', 'seeinside-travel')).self::list_html($out, 'x', 'sta-list-no').'</div>' : '')
            .'</div>';
    }

    protected static function s_extras(array $d): string
    {
        if (empty($d['extras'])) {
            return '';
        }
        $html = self::heading(__('Extras & excursions', 'seeinside-travel')).'<div class="sta-cards-sm">';
        foreach ($d['extras'] as $e) {
            $html .= '<div class="sta-card-sm"><b>'.esc_html(sta_t($e['name'])).'</b><span class="sta-price-sm">'.esc_html(sta_money($e['price'], $d['currency'] ?? 'EUR')).($e['price_type'] === 'per_person' ? ' / '.esc_html__('person', 'seeinside-travel') : '').'</span>'
                .(sta_t($e['description']) ? '<p class="sta-muted">'.esc_html(sta_t($e['description'])).'</p>' : '').'</div>';
        }

        return $html.'</div>';
    }

    protected static function s_info(array $d, string $type): string
    {
        $blocks = [
            'notes' => __('Note', 'seeinside-travel'),
            'important_info' => __('Important information', 'seeinside-travel'),
            'baggage_info' => __('Baggage', 'seeinside-travel'),
            'house_rules' => __('House rules', 'seeinside-travel'),
            'payment_terms' => __('Payment terms', 'seeinside-travel'),
            'cancellation_policy' => __('Cancellation policy', 'seeinside-travel'),
        ];
        $html = '';
        foreach ($blocks as $key => $label) {
            $text = sta_t($d[$key] ?? null);
            if ($text) {
                $html .= '<details class="sta-acc"><summary>'.esc_html($label).'</summary><div class="sta-prose">'.wp_kses_post(wpautop($text)).'</div></details>';
            }
        }
        foreach ($d['links'] ?? [] as $l) {
            if (! empty($l['url'])) {
                $html .= '<p><a href="'.esc_url($l['url']).'" target="_blank" rel="noopener">'.esc_html($l['label'] ?: $l['url']).' ↗</a></p>';
            }
        }

        return $html ? self::heading(__('Good to know', 'seeinside-travel')).$html : '';
    }

    protected static function s_rooms(array $d, string $type, int $postId): string
    {
        if ($type !== 'accommodation' || empty($d['rooms'])) {
            return '';
        }
        $currency = $d['currency'] ?? 'EUR';
        $html = self::heading(__('Rooms & prices', 'seeinside-travel')).'<div class="sta-rooms">';
        foreach ($d['rooms'] as $r) {
            $img = $r['cover_image'] ?? ($r['images'][0]['url'] ?? null);
            $html .= '<div class="sta-room">'
                .($img ? '<div class="sta-room-img"><img src="'.esc_url($img).'" alt="" loading="lazy"></div>' : '')
                .'<div class="sta-room-body"><h4>'.esc_html(sta_t($r['name'])).'</h4>'
                .'<p class="sta-muted">'.esc_html(implode(' · ', array_filter([sta_t($r['board']['label'] ?? null), $r['beds'] ?? null, $r['size_m2'] ? $r['size_m2'].' m²' : null, sprintf(__('max %1$d adults + %2$d children', 'seeinside-travel'), $r['max_adults'], $r['max_children'])]))).'</p>'
                .(sta_t($r['description']) ? '<p>'.esc_html(sta_t($r['description'])).'</p>' : '');
            if (! empty($r['rates'])) {
                $html .= '<details class="sta-acc sta-acc-sm"><summary>'.esc_html__('Prices by period', 'seeinside-travel').'</summary><table class="sta-table sta-table-sm"><tbody>';
                foreach ($r['rates'] as $rate) {
                    $html .= '<tr><td>'.esc_html(sta_date($rate['from']).' – '.sta_date($rate['to'])).'</td><td><b>'.esc_html(sta_money($rate['price'], $currency)).'</b> '.esc_html($rate['price_type'] === 'per_person' ? __('per person', 'seeinside-travel') : __('per room', 'seeinside-travel')).'</td><td>'.($rate['min_nights'] > 1 ? esc_html(sprintf(__('min. %d nights', 'seeinside-travel'), $rate['min_nights'])) : '').($rate['is_last_minute'] ? ' <span class="sta-badge sta-badge-lm">LM</span>' : '').'</td></tr>';
                }
                $html .= '</tbody></table></details>';
            }
            $from = $r['price'] ?? (count($r['rates'] ?? []) ? min(array_column($r['rates'], 'price')) : null);
            $html .= '</div><div class="sta-room-side">'.($from !== null ? '<small>'.esc_html__('from', 'seeinside-travel').'</small><b>'.esc_html(sta_money($from, $currency)).'</b><small>'.esc_html__('per night', 'seeinside-travel').'</small>' : '').'<a class="sta-btn sta-btn-sm" href="#sta-booking" data-sta-room="'.(int) $r['id'].'">'.esc_html__('Choose', 'seeinside-travel').'</a></div></div>';
        }

        return $html.'</div>';
    }

    protected static function s_amenities(array $d, string $type): string
    {
        if (empty($d['amenities'])) {
            return '';
        }

        return self::heading(__('Amenities', 'seeinside-travel')).self::list_html(array_map(fn ($a) => sta_t($a['name']), $d['amenities']), 'check', 'sta-list-grid');
    }

    protected static function s_tickets(array $d, string $type): string
    {
        if ($type !== 'event' || empty($d['tickets'])) {
            return '';
        }
        $html = self::heading(__('Tickets', 'seeinside-travel')).'<div class="sta-cards-sm">';
        foreach ($d['tickets'] as $t) {
            $html .= '<div class="sta-card-sm"><b>'.esc_html(sta_t($t['name'])).'</b><span class="sta-price-sm">'.esc_html(sta_money($t['price'], $d['currency'] ?? 'EUR')).'</span>'
                .(sta_t($t['description']) ? '<p class="sta-muted">'.esc_html(sta_t($t['description'])).'</p>' : '')
                .($t['available'] !== null && $t['available'] < 1 ? '<span class="sta-status sta-status-sold_out">'.esc_html__('Sold out', 'seeinside-travel').'</span>' : '').'</div>';
        }

        return $html.'</div>';
    }

    protected static function s_requirements(array $d, string $type): string
    {
        if ($type !== 'visa') {
            return '';
        }
        $html = '';
        $req = sta_t_list($d['requirements'] ?? []);
        if ($req) {
            $html .= self::heading(__('What you need', 'seeinside-travel')).self::list_html($req, 'doc');
        }
        $fees = $d['fees'] ?? [];
        $rows = array_filter([
            __('Service fee', 'seeinside-travel') => $fees['service'] ?? 0,
            __('Consular fee', 'seeinside-travel') => $fees['consular'] ?? 0,
            __('Express processing (optional)', 'seeinside-travel') => $fees['express'] ?? 0,
        ]);
        if ($rows) {
            $html .= '<h4 class="sta-h4">'.esc_html__('Fees', 'seeinside-travel').'</h4><table class="sta-table sta-table-sm"><tbody>';
            foreach ($rows as $label => $v) {
                $html .= '<tr><td>'.esc_html($label).'</td><td><b>'.esc_html(sta_money($v, $d['currency'] ?? 'EUR')).'</b></td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    /** Visa info cards: photo, title, start of the text; "More information" opens the whole card */
    protected static function s_info_cards(array $d, string $type): string
    {
        $cards = $d['info_cards'] ?? [];
        if ($type !== 'visa' || ! $cards) {
            return '';
        }
        $html = '<div class="sta-icards">';
        foreach ($cards as $i => $c) {
            $title = sta_t($c['title'] ?? null);
            $text = (string) sta_t($c['text'] ?? null);
            $img = sta_local_image($c['image'] ?? null, 'large');
            $plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(preg_replace('#</(p|li|h\d|div)>|<br\s*/?>#i', '$0 ', $text))));
            $excerpt = wp_trim_words($plain, (int) apply_filters('sta_info_card_words', 26), '…');
            $id = 'sta-icard-'.($d['id'] ?? 0).'-'.$i;
            $html .= '<article class="sta-icard">'
                .($img ? '<div class="sta-icard-img"><img src="'.esc_url($img).'" alt="'.esc_attr($title).'" loading="lazy"></div>' : '')
                .'<div class="sta-icard-body">'.($title ? '<h4 class="sta-icard-title">'.esc_html($title).'</h4>' : '')
                .($excerpt ? '<p class="sta-icard-text">'.esc_html($excerpt).'</p>' : '')
                .($plain !== $excerpt || $img ? '<button type="button" class="sta-icard-more" data-sta-dialog="'.esc_attr($id).'">'.esc_html__('More information', 'seeinside-travel').' →</button>' : '')
                .'</div>'
                .'<dialog class="sta-dialog sta-icard-dialog" id="'.esc_attr($id).'" aria-label="'.esc_attr($title).'">'
                .'<button type="button" class="sta-dialog-close" data-sta-dialog-close aria-label="'.esc_attr__('Close', 'seeinside-travel').'">×</button>'
                .($img ? '<img class="sta-icard-dialog-img" src="'.esc_url($img).'" alt="">' : '')
                .'<div class="sta-icard-dialog-body">'.($title ? '<h3>'.esc_html($title).'</h3>' : '').'<div class="sta-rich">'.wp_kses_post($text).'</div></div>'
                .'</dialog></article>';
        }

        return $html.'</div>';
    }

    /** Approved reviews of this offer + form for a new one */
    protected static function s_reviews(array $d, string $type, int $postId, array $args = []): string
    {
        if (! apply_filters('sta_show_reviews', true, $type, $postId)) {
            return '';
        }
        $items = STA_Reviews::filter($type, (int) ($d['id'] ?? 0));

        return self::heading(__('Reviews', 'seeinside-travel')).STA_Reviews::block($items, ['limit' => (int) ($args['limit'] ?? 4), 'layout' => 'list', 'form' => $args['form'] ?? true, 'type' => $type, 'slug' => $d['slug'] ?? '']);
    }

    /** Month calendar with free / booked nights per room (live from SeeInside) */
    protected static function s_availability(array $d, string $type): string
    {
        if ($type !== 'accommodation' || empty($d['rooms'])) {
            return '';
        }
        $opts = '';
        foreach ($d['rooms'] as $r) {
            $opts .= '<option value="'.(int) $r['id'].'">'.esc_html(sta_t($r['name'])).'</option>';
        }

        return self::heading(__('Availability', 'seeinside-travel'))
            .'<div class="sta-cal" data-sta-calendar data-slug="'.esc_attr($d['slug']).'">'
            .'<div class="sta-cal-head"><select class="sta-cal-room">'.$opts.'</select><div class="sta-cal-nav"><button type="button" data-dir="-1" aria-label="‹">‹</button><b class="sta-cal-month"></b><button type="button" data-dir="1" aria-label="›">›</button></div></div>'
            .'<div class="sta-cal-grid"></div>'
            .'<div class="sta-cal-legend"><span class="is-free"></span>'.esc_html__('Free', 'seeinside-travel').' <span class="is-booked"></span>'.esc_html__('Booked', 'seeinside-travel').'</div></div>';
    }

    protected static function s_map(array $d): string
    {
        $q = $d['map'] ?? '';
        if (! $q) {
            $q = trim(implode(', ', array_filter([$d['address'] ?? null, $d['venue'] ?? null, $d['place'] ?? $d['location'] ?? $d['city'] ?? null, $d['country'] ?? null])));
        }
        if (! $q) {
            return '';
        }

        return self::heading(__('Location', 'seeinside-travel')).'<div class="sta-map"><iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q='.rawurlencode($q).'&z=13&output=embed" title="'.esc_attr__('Map', 'seeinside-travel').'"></iframe></div>';
    }

    protected static function s_videos(array $d): string
    {
        $videos = array_filter(array_map(fn ($v) => is_array($v) ? ($v['url'] ?? '') : $v, $d['videos'] ?? []));
        if (! $videos) {
            return '';
        }
        $html = self::heading(__('Video', 'seeinside-travel')).'<div class="sta-videos">';
        foreach ($videos as $url) {
            $embed = wp_oembed_get($url);
            $html .= '<div class="sta-video">'.($embed ?: '<a href="'.esc_url($url).'" target="_blank" rel="noopener">'.esc_html($url).'</a>').'</div>';
        }

        return $html.'</div>';
    }

    protected static function s_booking(array $d, string $type, int $postId): string
    {
        if ($type === 'visa') {
            return sta_part('form-visa', ['d' => $d, 'post_id' => $postId]);
        }
        if ($type === 'event' && empty($d['booking']['enabled'])) {
            return ! empty($d['external_ticket_url'])
                ? '<a class="sta-btn" href="'.esc_url($d['external_ticket_url']).'" target="_blank" rel="noopener">'.esc_html__('Buy tickets', 'seeinside-travel').' ↗</a>'
                : '';
        }

        return sta_part('form-booking', ['d' => $d, 'type' => $type, 'post_id' => $postId]);
    }

    /** The whole page in a sensible order (used when no builder template exists) */
    protected static function s_layout(array $d, string $type, int $postId): string
    {
        $main = ['gallery', 'facts', 'description', 'info_cards', 'highlights', 'program', 'departures', 'rooms', 'availability', 'tickets', 'requirements', 'included', 'extras', 'amenities', 'info', 'videos', 'map'];
        // aside first: on narrow screens the price / booking button comes before the details
        $html = '<div class="sta-page"><aside class="sta-aside"><div class="sta-sticky">'.self::section('price', $postId).'</div></aside><div class="sta-main">';
        foreach ($main as $s) {
            $html .= self::section($s, $postId);
        }
        $html .= self::section('booking', $postId).self::section('reviews', $postId).'</div></div>';

        return $html;
    }

    /* ---------------- cards for listings ---------------- */

    /** Card of one offer – drawn with the card design of its type (SeeInside Travel → Lists & cards) */
    public static function card(int $postId): string
    {
        $it = STA_Items::from_post($postId);
        if (! $it) {
            return '';
        }
        STA_Listings::enqueue();

        return STA_Cards::render($it, null, $it['type']);
    }
}
