<?php

defined('ABSPATH') || exit;

/**
 * Reviews: the approved ones come from SeeInside (option sta_reviews), new ones are sent to SeeInside
 * and appear on the website after the agency approves them.
 * Shortcode: [sta_reviews] (all), [sta_reviews type="tour" slug="..."], [sta_review_form]
 */
class STA_Reviews
{
    public static function init(): void
    {
        add_action('init', function () {
            add_shortcode('sta_reviews', [self::class, 'sc_reviews']);
            add_shortcode('sta_review_form', [self::class, 'sc_form']);
        });
        add_action('rest_api_init', function () {
            register_rest_route('seeinside-travel/v1', '/review', ['methods' => 'POST', 'callback' => [self::class, 'r_review'], 'permission_callback' => '__return_true']);
        });
        add_action('wp_head', [self::class, 'schema'], 30);
    }

    public static function all(): array
    {
        $all = get_option('sta_reviews', []);

        return is_array($all) ? $all : [];
    }

    /** Reviews of one offer, or of the agency in general ($type = 'agency'), or all */
    public static function filter(?string $type = null, ?int $remoteId = null, ?string $slug = null, bool $onlyFeatured = false): array
    {
        return array_values(array_filter(self::all(), function ($r) use ($type, $remoteId, $slug, $onlyFeatured) {
            if ($onlyFeatured && empty($r['is_featured'])) {
                return false;
            }
            if ($type === 'agency') {
                return empty($r['item']);
            }
            if ($type) {
                $it = $r['item'] ?? null;
                if (! $it || $it['type'] !== $type) {
                    return false;
                }
                if ($remoteId && (int) $it['id'] !== $remoteId) {
                    return false;
                }
                if ($slug && $it['slug'] !== $slug) {
                    return false;
                }
            }

            return true;
        }));
    }

    public static function summary(array $items): array
    {
        $n = count($items);

        return ['count' => $n, 'average' => $n ? round(array_sum(array_column($items, 'rating')) / $n, 1) : 0];
    }

    /** Summary for an offer page / card */
    public static function for_post(int $postId): array
    {
        $d = sta_data($postId);
        if (! $d) {
            return [];
        }

        return self::filter($d['type'] ?? '', (int) ($d['id'] ?? 0));
    }

    public static function card_badge(int $postId): string
    {
        $s = self::summary(self::for_post($postId));

        return $s['count'] ? '<span class="sta-card-rating">★ '.esc_html(number_format_i18n($s['average'], 1)).' <small>('.(int) $s['count'].')</small></span>' : '';
    }

    public static function item_html(array $r, bool $showItem = false): string
    {
        $meta = implode(' · ', array_filter([$r['city'] ?? null, $r['trip_date'] ?? null]));
        $item = '';
        if ($showItem && ! empty($r['item'])) {
            $postId = sta_find_post(STA_Schema::post_type($r['item']['type']) ?: '', (int) $r['item']['id']);
            $title = sta_t($r['item']['title'] ?? null);
            if ($title) {
                $item = '<div class="sta-review-item">'.($postId && get_post_status($postId) === 'publish' ? '<a href="'.esc_url(get_permalink($postId)).'">'.esc_html($title).'</a>' : esc_html($title)).'</div>';
            }
        }
        $initial = mb_strtoupper(mb_substr(trim((string) $r['name']), 0, 1));

        return '<article class="sta-review">'
            .'<header class="sta-review-head"><span class="sta-review-avatar" aria-hidden="true">'.esc_html($initial).'</span>'
            .'<div><b class="sta-review-name">'.esc_html($r['name']).'</b>'.($meta ? '<small>'.esc_html($meta).'</small>' : '').'</div>'
            .sta_stars((float) $r['rating']).'</header>'
            .$item
            .(! empty($r['title']) ? '<h4 class="sta-review-title">'.esc_html($r['title']).'</h4>' : '')
            .'<div class="sta-review-body">'.wpautop(esc_html($r['body'])).'</div>'
            .(! empty($r['reply']) ? '<div class="sta-review-reply"><b>'.esc_html__('Reply from the agency', 'seeinside-travel').'</b>'.wpautop(esc_html($r['reply'])).'</div>' : '')
            .(! empty($r['date']) ? '<time class="sta-review-date" datetime="'.esc_attr($r['date']).'">'.esc_html(sta_date($r['date'])).'</time>' : '')
            .'</article>';
    }

    /** Testimonial card: avatar, name, who / what, stars, quote and the round quote mark at the bottom */
    public static function testimonial_html(array $r, bool $showItem = true, string $role = ''): string
    {
        $sub = '';
        if ($showItem && ! empty($r['item'])) {
            $sub = sta_t($r['item']['title'] ?? null);
        }
        $sub = $sub ?: implode(' · ', array_filter([$r['city'] ?? null, $r['trip_date'] ?? null])) ?: $role;
        $name = trim((string) $r['name']);
        $parts = preg_split('/\s+/u', $name) ?: [];
        $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
        $hue = abs(crc32($name)) % 360;
        $body = trim((string) ($r['body'] ?? ''));

        return '<article class="sta-tm-card">'
            .'<header class="sta-tm-head">'
            .'<span class="sta-tm-avatar" style="--h:'.(int) $hue.'" aria-hidden="true">'.esc_html($initials).'</span>'
            .'<div class="sta-tm-who"><b>'.esc_html($name).'</b>'.($sub ? '<small>'.esc_html($sub).'</small>' : '').'</div>'
            .sta_stars((float) $r['rating'], 'sta-tm-stars')
            .'</header>'
            .(! empty($r['title']) ? '<h4 class="sta-tm-title">'.esc_html($r['title']).'</h4>' : '')
            .'<blockquote class="sta-tm-text">“'.esc_html($body).'”</blockquote>'
            .'<span class="sta-tm-quote" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M9.6 6C6.5 7.3 4.5 9.9 4.5 13.4V18h6v-6H7.6c.2-1.9 1.4-3.3 3.3-4.1L9.6 6Zm9 0c-3.1 1.3-5.1 3.9-5.1 7.4V18h6v-6h-2.9c.2-1.9 1.4-3.3 3.3-4.1L18.6 6Z"/></svg></span>'
            .'</article>';
    }

    /** Centred testimonial carousel (the middle card is raised and highlighted) */
    public static function testimonials(array $items, array $args = []): string
    {
        $items = array_slice($items, 0, max(1, (int) ($args['limit'] ?? 9)));
        if (! $items) {
            return '';
        }
        $cards = '';
        foreach ($items as $r) {
            $cards .= self::testimonial_html($r, ! empty($args['show_item']), (string) ($args['role'] ?? ''));
        }
        $dots = '';
        foreach ($items as $i => $r) {
            $dots .= '<button type="button" class="sta-tm-dot" aria-label="'.esc_attr(sprintf(__('Review %d', 'seeinside-travel'), $i + 1)).'"></button>';
        }

        return '<div class="sta-tm" data-sta-tm data-autoplay="'.esc_attr((string) max(0, (int) ($args['autoplay'] ?? 6))).'" data-per="'.esc_attr((string) max(1, min(4, (int) ($args['per_view'] ?? 3)))).'" aria-roledescription="carousel">'
            .'<div class="sta-tm-viewport"><div class="sta-tm-track">'.$cards.'</div></div>'
            .(count($items) > 1 ? '<div class="sta-tm-nav">'
                .'<button type="button" class="sta-tm-arrow" data-dir="-1" aria-label="'.esc_attr__('Previous', 'seeinside-travel').'">‹</button>'
                .'<div class="sta-tm-dots">'.$dots.'</div>'
                .'<button type="button" class="sta-tm-arrow" data-dir="1" aria-label="'.esc_attr__('Next', 'seeinside-travel').'">›</button></div>' : '')
            .'</div>';
    }

    /** List + summary + form. $args: limit, layout (grid|list|slider|testimonials), form (bool), show_item (bool), type, slug */
    public static function block(array $items, array $args = []): string
    {
        if (($args['layout'] ?? '') === 'testimonials') {
            $html = self::testimonials($items, $args);
            if (! empty($args['form'])) {
                $html .= '<div class="sta-center"><button type="button" class="sta-btn sta-btn-ghost" data-sta-review-open>'.esc_html__('Write a review', 'seeinside-travel').'</button></div>'
                    .self::form($args['type'] ?? '', $args['slug'] ?? '', false);
            } elseif (! $items) {
                return '';
            }

            return $html;
        }
        $limit = max(1, (int) ($args['limit'] ?? 6));
        $layout = in_array($args['layout'] ?? 'grid', ['grid', 'list', 'slider'], true) ? $args['layout'] : 'grid';
        $s = self::summary($items);
        $html = '';
        if ($s['count']) {
            $dist = array_fill(1, 5, 0);
            foreach ($items as $r) {
                $dist[(int) $r['rating']] = ($dist[(int) $r['rating']] ?? 0) + 1;
            }
            $bars = '';
            for ($i = 5; $i >= 1; $i--) {
                $bars .= '<div class="sta-rbar"><span>'.$i.'★</span><i><b style="width:'.round($dist[$i] / $s['count'] * 100).'%"></b></i><small>'.$dist[$i].'</small></div>';
            }
            $html .= '<div class="sta-reviews-summary"><div class="sta-reviews-avg"><b>'.esc_html(number_format_i18n($s['average'], 1)).'</b>'.sta_stars($s['average'])
                .'<small>'.esc_html(sprintf(_n('%d review', '%d reviews', $s['count'], 'seeinside-travel'), $s['count'])).'</small></div>'
                .'<div class="sta-reviews-bars">'.$bars.'</div>'
                .(! empty($args['form']) ? '<button type="button" class="sta-btn sta-btn-ghost" data-sta-review-open>'.esc_html__('Write a review', 'seeinside-travel').'</button>' : '')
                .'</div>';
            $html .= '<div class="sta-reviews sta-reviews-'.esc_attr($layout).'" data-sta-reviews data-step="'.$limit.'">';
            foreach ($items as $i => $r) {
                $html .= str_replace('<article class="sta-review"', '<article class="sta-review'.($i >= $limit ? ' is-hidden' : '').'"', self::item_html($r, ! empty($args['show_item'])));
            }
            $html .= '</div>';
            if (count($items) > $limit) {
                $html .= '<div class="sta-center"><button type="button" class="sta-btn sta-btn-ghost" data-sta-reviews-more>'.esc_html__('Show more reviews', 'seeinside-travel').'</button></div>';
            }
        } elseif (! empty($args['form'])) {
            $html .= '<p class="sta-muted">'.esc_html__('No reviews yet. Travelled with us? Be the first to write one.', 'seeinside-travel').'</p>';
        }
        if (! empty($args['form'])) {
            $html .= self::form($args['type'] ?? '', $args['slug'] ?? '', ! $s['count']);
        }

        return $html;
    }

    public static function form(string $type = '', string $slug = '', bool $open = true): string
    {
        $stars = '';
        for ($i = 5; $i >= 1; $i--) {
            $stars .= '<input type="radio" id="sta-rs-%1$s-'.$i.'" name="rating" value="'.$i.'" required><label for="sta-rs-%1$s-'.$i.'" title="'.$i.'">★</label>';
        }
        $uid = wp_unique_id();

        return '<form class="sta-form sta-review-form'.($open ? '' : ' is-collapsed').'" data-sta-review novalidate>'
            .'<h4 class="sta-h4">'.esc_html__('Write a review', 'seeinside-travel').'</h4>'
            .'<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">'
            .'<input type="hidden" name="type" value="'.esc_attr($type).'"><input type="hidden" name="slug" value="'.esc_attr($slug).'">'
            .'<div class="sta-field"><span>'.esc_html__('Your rating', 'seeinside-travel').' *</span><div class="sta-rate">'.sprintf($stars, $uid).'</div></div>'
            .'<div class="sta-row">'
            .'<label class="sta-field"><span>'.esc_html__('Your name', 'seeinside-travel').' *</span><input name="name" required maxlength="120" autocomplete="name"></label>'
            .'<label class="sta-field"><span>'.esc_html__('E-mail', 'seeinside-travel').' *</span><input type="email" name="email" required maxlength="150" autocomplete="email"><small class="sta-muted">'.esc_html__('Not published.', 'seeinside-travel').'</small></label>'
            .'<label class="sta-field"><span>'.esc_html__('City', 'seeinside-travel').'</span><input name="city" maxlength="100" autocomplete="address-level2"></label>'
            .'<label class="sta-field"><span>'.esc_html__('When did you travel?', 'seeinside-travel').'</span><input name="trip_date" maxlength="40" placeholder="'.esc_attr__('e.g. August 2026', 'seeinside-travel').'"></label>'
            .'<label class="sta-field sta-col-2"><span>'.esc_html__('Title', 'seeinside-travel').'</span><input name="title" maxlength="200"></label>'
            .'<label class="sta-field sta-col-2"><span>'.esc_html__('Your experience', 'seeinside-travel').' *</span><textarea name="body" rows="5" required minlength="10" maxlength="5000"></textarea></label>'
            .'</div>'
            .'<p class="sta-muted">'.esc_html__('Reviews are published after the agency checks them.', 'seeinside-travel').'</p>'
            .'<div data-sta-msg></div><button type="submit" class="sta-btn">'.esc_html__('Send review', 'seeinside-travel').'</button>'
            .'</form>';
    }

    /** [sta_reviews type="tour|event|accommodation|visa|agency" slug="" limit="6" layout="grid|list|slider" form="no" featured="no" title=""] */
    public static function sc_reviews($atts = []): string
    {
        $a = shortcode_atts(['type' => '', 'slug' => '', 'limit' => 6, 'layout' => 'grid', 'form' => 'no', 'featured' => 'no', 'title' => '', 'subtitle' => '',
            'autoplay' => 6, 'per_view' => 3, 'role' => __('Traveller', 'seeinside-travel'), 'min_rating' => 0], $atts, 'sta_reviews');
        $yes = fn ($v) => in_array(strtolower((string) $v), ['1', 'yes', 'true', 'da', 'да'], true);
        $type = sanitize_key($a['type']);
        $slug = sanitize_title($a['slug']);
        // on an offer page without attributes: that offer's reviews
        if (! $type && is_singular(array_values(STA_Schema::TYPES))) {
            $d = sta_data(get_the_ID());
            $type = $d['type'] ?? '';
            $slug = $d['slug'] ?? '';
        }
        $items = self::filter($type ?: null, null, $slug ?: null, $yes($a['featured']));
        if ((int) $a['min_rating'] > 0) {
            $items = array_values(array_filter($items, fn ($r) => (float) $r['rating'] >= (int) $a['min_rating']));
        }
        $html = self::block($items, ['limit' => (int) $a['limit'], 'layout' => $a['layout'], 'form' => $yes($a['form']), 'show_item' => ! $slug, 'type' => $type === 'agency' ? '' : $type, 'slug' => $slug,
            'autoplay' => (int) $a['autoplay'], 'per_view' => (int) $a['per_view'], 'role' => (string) $a['role']]);
        if ($html === '') {
            return '';
        }
        $head = '';
        if ($a['subtitle'] || $a['title']) {
            $head = '<div class="sta-tm-heading">'.($a['subtitle'] ? '<span class="sta-tm-sub">'.esc_html($a['subtitle']).'</span>' : '').($a['title'] ? '<h2 class="sta-h">'.esc_html($a['title']).'</h2>' : '').'</div>';
        }

        return '<div class="sta sta-reviews-wrap'.($a['layout'] === 'testimonials' ? ' sta-reviews-wrap--tm' : '').'">'.$head.$html.'</div>';
    }

    public static function sc_form($atts = []): string
    {
        $a = shortcode_atts(['type' => '', 'slug' => ''], $atts, 'sta_review_form');

        return '<div class="sta">'.self::form(sanitize_key($a['type']), sanitize_title($a['slug'])).'</div>';
    }

    /** Stars in Google results (AggregateRating) on offer pages */
    public static function schema(): void
    {
        if (! is_singular(array_values(STA_Schema::TYPES))) {
            return;
        }
        $id = get_queried_object_id();
        $s = self::summary(self::for_post($id));
        if (! $s['count'] || ! apply_filters('sta_review_schema', true, $id)) {
            return;
        }
        $type = (sta_data($id)['type'] ?? '') === 'accommodation' ? 'Hotel' : 'Product';
        echo '<script type="application/ld+json">'.wp_json_encode([
            '@context' => 'https://schema.org', '@type' => $type, 'name' => get_the_title($id), 'url' => get_permalink($id),
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => $s['average'], 'reviewCount' => $s['count'], 'bestRating' => 5, 'worstRating' => 1],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'</script>'."\n";
    }

    public static function r_review(WP_REST_Request $r): WP_REST_Response
    {
        $p = $r->get_json_params() ?: $r->get_body_params();
        $fake = new WP_REST_Request('POST');
        foreach (['website', 'sta_ts'] as $k) {
            $fake->set_param($k, $p[$k] ?? null);
        }
        if ($blocked = STA_Frontend::check($fake)) {
            return $blocked;
        }
        $data = ['locale' => sta_lang(), 'page_url' => esc_url_raw(wp_get_referer() ?: home_url('/'))];
        foreach (['type', 'slug', 'name', 'email', 'city', 'title', 'body', 'trip_date'] as $k) {
            if (isset($p[$k]) && $p[$k] !== '') {
                $data[$k] = $k === 'body' ? sanitize_textarea_field((string) $p[$k]) : sanitize_text_field((string) $p[$k]);
            }
        }
        $data['rating'] = max(1, min(5, (int) ($p['rating'] ?? 0)));
        $res = STA_Api::post('reviews', $data);
        if ($res[0] === 201) {
            sta_log('review from '.($data['name'] ?? '').' ('.$data['rating'].'/5)');
        }

        return STA_Frontend::pass($res);
    }
}
