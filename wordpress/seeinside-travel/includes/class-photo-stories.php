<?php

defined('ABSPATH') || exit;

/**
 * "Фотоприказни": travel photos of clients, approved in SeeInside (option sta_photo_stories).
 *  [sta_photo_stories_slider]  homepage: the photos of one client as a mosaic, up to 5 clients change in a slider
 *  [sta_photo_stories]         page with all clients – a click opens all photos of that client
 *  [sta_photo_story_share]     button "Share your trip": booking number + e-mail, then the photos (approved before publishing)
 */
class STA_Photo_Stories
{
    protected static bool $share = false;

    public static function init(): void
    {
        add_action('init', function () {
            add_shortcode('sta_photo_stories_slider', [self::class, 'slider']);
            add_shortcode('sta_photo_stories', [self::class, 'page']);
            add_shortcode('sta_photo_story_share', [self::class, 'share_button']);
        });
        add_action('rest_api_init', function () {
            register_rest_route('seeinside-travel/v1', '/photo-story/verify', ['methods' => 'POST', 'callback' => [self::class, 'r_verify'], 'permission_callback' => '__return_true']);
            register_rest_route('seeinside-travel/v1', '/photo-story', ['methods' => 'POST', 'callback' => [self::class, 'r_store'], 'permission_callback' => '__return_true']);
        });
        add_action('wp_footer', [self::class, 'share_dialog'], 6);
    }

    public static function all(): array
    {
        $all = get_option('sta_photo_stories', []);

        return is_array($all) ? array_values(array_filter($all, fn ($s) => ! empty($s['photos']))) : [];
    }

    protected static function assets(): void
    {
        wp_enqueue_style('sta');
        wp_enqueue_script('sta');
        wp_enqueue_style('sta-script-font', 'https://fonts.bunny.net/css?family=marck-script:400&display=swap', [], null);
    }

    /** "Соња Петровска · Лефкада, Грција" */
    protected static function caption(array $s): string
    {
        return trim(($s['name'] ?? '').(! empty($s['destination']) ? ' · '.$s['destination'] : ''));
    }

    /** All photos of a story for the gallery (data attribute) */
    protected static function gallery_json(array $s): string
    {
        return wp_json_encode(['title' => self::caption($s), 'date' => $s['trip_date'] ?? '', 'story' => $s['story'] ?? '',
            'photos' => array_map(fn ($p) => ['url' => $p['url'], 'w' => $p['w'] ?? 0, 'h' => $p['h'] ?? 0], $s['photos'])]);
    }

    /** Mosaic of up to 7 photos in 5 columns: 1 · 2 · 1 · 2 · 1 (like on the design) */
    protected static function mosaic(array $s): string
    {
        $photos = array_slice($s['photos'], 0, 7);
        $pattern = [1, 2, 1, 2, 1];
        $cols = [];
        $i = 0;
        // the middle first, so that few photos still look centred
        $order = [2, 1, 3, 0, 4];
        $take = array_fill(0, 5, 0);
        $left = count($photos);
        foreach ($order as $c) {
            $n = min($pattern[$c], $left);
            $take[$c] = $n;
            $left -= $n;
        }
        foreach ($take as $c => $n) {
            if (! $n) {
                continue;
            }
            $imgs = '';
            for ($k = 0; $k < $n; $k++, $i++) {
                $p = $photos[$i];
                $imgs .= '<button type="button" class="sta-ps-ph" data-sta-ps-open="'.$i.'"><img src="'.esc_url($p['thumb'] ?? $p['url']).'" alt="'.esc_attr(self::caption($s)).'" loading="lazy"></button>';
            }
            $cols[] = '<div class="sta-ps-col is-'.($n === 1 ? ($c === 2 ? 'tall' : 'low') : 'two').'">'.$imgs.'</div>';
        }

        return '<div class="sta-ps-mosaic" data-n="'.count($cols).'">'.implode('', $cols).'</div>';
    }

    private const EYEBROW = 'Вашите патувања, вашите приказни';
    private const TITLE = 'Во Фотогалерија';
    private const TEXT = 'Споделете ги најубавите моменти од вашите патувања со нас! Прикачете фотографии и споделете ги впечатоците од посетените дестинации. Вашите приказни нека бидат инспирација за нови авантури.';

    /** Script eyebrow, title, text and the share button (homepage slider and the page) */
    private static function head(array $a): string
    {
        return '<div class="sta-ps-head">'
            .($a['eyebrow'] !== '' ? '<p class="sta-ps-eyebrow">'.esc_html($a['eyebrow']).'</p>' : '')
            .($a['title'] !== '' ? '<h2 class="sta-ps-title">'.esc_html($a['title']).'</h2>' : '')
            .($a['text'] !== '' ? '<p class="sta-ps-text">'.esc_html($a['text']).'</p>' : '')
            .($a['button'] !== '' ? self::share_button(['label' => $a['button']]) : '').'</div>';
    }

    public static function slider($atts = []): string
    {
        $a = shortcode_atts(['count' => 5, 'eyebrow' => self::EYEBROW, 'title' => self::TITLE, 'text' => self::TEXT,
            'button' => 'Сподели го твоето патување', 'autoplay' => 7, 'link' => ''], $atts, 'sta_photo_stories_slider');
        self::assets();
        $all = self::all();
        $featured = array_values(array_filter($all, fn ($s) => ! empty($s['is_featured'])));
        $list = array_slice($featured ?: $all, 0, max(1, min(10, (int) $a['count'])));
        $head = self::head($a);
        if (! $list) {
            return '<section class="sta sta-ps">'.$head.'</section>';
        }
        $slides = '';
        foreach ($list as $n => $s) {
            $slides .= '<figure class="sta-ps-slide'.($n === 0 ? ' is-on' : '').'" data-sta-ps-gallery="'.esc_attr(self::gallery_json($s)).'">'.self::mosaic($s)
                .'<figcaption class="sta-ps-cap"><b>'.esc_html($s['name']).'</b>'.(! empty($s['destination']) ? ' · <span>'.esc_html($s['destination']).'</span>' : '')
                .(count($s['photos']) > 7 ? ' <button type="button" class="sta-ps-all" data-sta-ps-open="0">'.esc_html(sprintf(__('All %d photos', 'seeinside-travel'), count($s['photos']))).' →</button>' : '').'</figcaption></figure>';
        }
        $dots = count($list) > 1 ? '<div class="sta-ps-nav"><button type="button" class="sta-ps-arr" data-sta-ps-prev aria-label="‹">‹</button>'
            .implode('', array_map(fn ($i) => '<button type="button" class="sta-ps-dot'.($i === 0 ? ' is-on' : '').'" data-sta-ps-dot="'.$i.'" aria-label="'.($i + 1).'"></button>', array_keys($list)))
            .'<button type="button" class="sta-ps-arr" data-sta-ps-next aria-label="›">›</button></div>' : '';
        $more = $a['link'] !== '' ? '<p class="sta-ps-more"><a href="'.esc_url($a['link']).'">'.esc_html__('All photo stories', 'seeinside-travel').' →</a></p>' : '';

        return '<section class="sta sta-ps" data-sta-ps-slider data-autoplay="'.(int) $a['autoplay'].'">'.$head.'<div class="sta-ps-stage">'.$slides.'</div>'.$dots.$more.'</section>';
    }

    public static function page($atts = []): string
    {
        $a = shortcode_atts(['eyebrow' => self::EYEBROW, 'title' => self::TITLE, 'text' => self::TEXT, 'button' => 'Сподели го твоето патување', 'per_page' => 24], $atts, 'sta_photo_stories');
        self::assets();
        $all = self::all();
        $html = '<section class="sta sta-ps-page">'.self::head($a);
        if (! $all) {
            return $html.'<p class="sta-empty">'.esc_html__('No photo stories yet – be the first to share your trip!', 'seeinside-travel').'</p></section>';
        }
        $html .= '<div class="sta-ps-grid" data-sta-ps-grid data-per="'.max(6, (int) $a['per_page']).'">';
        foreach ($all as $i => $s) {
            $ph = array_slice($s['photos'], 0, 5);
            $rest = count($s['photos']) - count($ph);
            $tiles = '';
            foreach ($ph as $k => $p) {
                $tiles .= '<button type="button" class="sta-ps-t t'.$k.'" data-sta-ps-open="'.$k.'" aria-label="'.esc_attr(self::caption($s)).'">'
                    .'<img src="'.esc_url($p['thumb'] ?? $p['url']).'" alt="" loading="lazy">'
                    .($rest > 0 && $k === count($ph) - 1 ? '<i>+'.$rest.'</i>' : '').'</button>';
            }
            $html .= '<article class="sta-ps-card" data-sta-ps-gallery="'.esc_attr(self::gallery_json($s)).'"'.($i >= (int) $a['per_page'] ? ' hidden' : '').'>'
                .'<div class="sta-ps-tiles n'.count($ph).'">'.$tiles
                .'<em>'.esc_html(sprintf(_n('%d photo', '%d photos', count($s['photos']), 'seeinside-travel'), count($s['photos']))).'</em></div>'
                .'<div class="sta-ps-card-b"><b>'.esc_html($s['name']).'</b>'.(! empty($s['destination']) ? '<span>'.esc_html($s['destination']).'</span>' : '')
                .(! empty($s['trip_date']) ? '<small>'.esc_html($s['trip_date']).'</small>' : '')
                .(! empty($s['story']) ? '<p>'.esc_html(wp_trim_words($s['story'], 26)).'</p>' : '').'</div></article>';
        }
        $html .= '</div>';
        if (count($all) > (int) $a['per_page']) {
            $html .= '<p class="sta-ps-more"><button type="button" class="sta-btn sta-btn-ghost" data-sta-ps-more>'.esc_html__('Show more', 'seeinside-travel').'</button></p>';
        }

        return $html.'</section>';
    }

    public static function share_button($atts = []): string
    {
        $a = shortcode_atts(['label' => 'Сподели го твоето патување'], $atts, 'sta_photo_story_share');
        self::$share = true;
        self::assets();

        return '<button type="button" class="sta-ps-share" data-sta-ps-share>'.esc_html($a['label']).' <span aria-hidden="true">→</span></button>';
    }

    /** The share window: 1) booking number + e-mail  2) name, place, story, photos */
    public static function share_dialog(): void
    {
        if (! self::$share) {
            return;
        }
        $privacy = get_privacy_policy_url();
        echo '<dialog class="sta-psd" data-sta-psd><div class="sta-psd-in">'
            .'<button type="button" class="sta-qs-x" data-sta-psd-close aria-label="×">×</button>'
            .'<h3>'.esc_html__('Share your trip', 'seeinside-travel').'</h3>'
            // step 1
            .'<form class="sta-form" data-sta-psd-1 novalidate><p class="sta-muted">'.esc_html__('Photos can be shared by clients who travelled with us: enter the booking number and the e-mail of the booking.', 'seeinside-travel').'</p>'
            .'<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">'
            .'<div class="sta-row"><label class="sta-field"><span>'.esc_html__('Booking number', 'seeinside-travel').' *</span><input name="reference" required placeholder="R2611-ABCDE" autocomplete="off" style="text-transform:uppercase"></label>'
            .'<label class="sta-field"><span>'.esc_html__('E-mail of the booking', 'seeinside-travel').' *</span><input type="email" name="email" required autocomplete="email"></label></div>'
            .'<label class="sta-check sta-consent"><input type="checkbox" required> <span>'.esc_html__('I am not a robot', 'seeinside-travel').'</span></label>'
            .'<button type="submit" class="sta-btn sta-btn-block">'.esc_html__('Continue', 'seeinside-travel').'</button><div class="sta-msg" data-sta-msg role="status"></div></form>'
            // step 2
            .'<form class="sta-form" data-sta-psd-2 novalidate hidden>'
            .'<div class="sta-row"><label class="sta-field"><span>'.esc_html__('Name and surname (shown under the photos)', 'seeinside-travel').' *</span><input name="name" required></label>'
            .'<label class="sta-field"><span>'.esc_html__('Where you travelled / stayed', 'seeinside-travel').'</span><input name="destination"></label>'
            .'<label class="sta-field"><span>'.esc_html__('When', 'seeinside-travel').'</span><input name="trip_date" placeholder="Август 2026"></label></div>'
            .'<label class="sta-field"><span>'.esc_html__('A few words about the trip', 'seeinside-travel').'</span><textarea name="story" rows="3" maxlength="3000"></textarea></label>'
            .'<div class="sta-psd-drop" data-sta-psd-drop><input type="file" accept="image/jpeg,image/png,image/webp,image/*" multiple data-sta-psd-files><b>'.esc_html__('Choose photos', 'seeinside-travel').'</b><small>'.esc_html__('or drag them here · up to 15 photos', 'seeinside-travel').'</small></div>'
            .'<div class="sta-psd-prev" data-sta-psd-prev></div>'
            .'<label class="sta-check sta-consent"><input type="checkbox" required> <span>'.wp_kses_post(sprintf(__('I agree that the photos and my name are published on the website after approval (%s).', 'seeinside-travel'), $privacy ? '<a href="'.esc_url($privacy).'" target="_blank">'.__('privacy policy', 'seeinside-travel').'</a>' : __('privacy policy', 'seeinside-travel'))).'</span></label>'
            .'<button type="submit" class="sta-btn sta-btn-block">'.esc_html__('Send the photos', 'seeinside-travel').'</button><div class="sta-msg" data-sta-msg role="status"></div></form>'
            .'</div></dialog>';   // phpcs:ignore
    }

    public static function r_verify(WP_REST_Request $r): WP_REST_Response
    {
        if ($blocked = STA_Frontend::check($r)) {
            return $blocked;
        }

        return STA_Frontend::pass(STA_Api::post('photo-stories/verify', [
            'reference' => sanitize_text_field((string) $r->get_param('reference')),
            'email' => sanitize_email((string) $r->get_param('email')),
        ]));
    }

    public static function r_store(WP_REST_Request $r): WP_REST_Response
    {
        if ($blocked = STA_Frontend::check($r)) {
            return $blocked;
        }
        $fields = ['locale' => sta_lang()];
        foreach (['reference', 'email', 'name', 'destination', 'trip_date'] as $k) {
            $fields[$k] = sanitize_text_field((string) $r->get_param($k));
        }
        $fields['story'] = sanitize_textarea_field((string) $r->get_param('story'));
        $files = [];
        $raw = $_FILES['photos'] ?? null;   // phpcs:ignore
        if ($raw && is_array($raw['name'])) {
            foreach ($raw['name'] as $i => $name) {
                if ($raw['error'][$i] !== UPLOAD_ERR_OK || ! is_uploaded_file($raw['tmp_name'][$i]) || $raw['size'][$i] > 15 * MB_IN_BYTES) {
                    continue;
                }
                $check = wp_check_filetype_and_ext($raw['tmp_name'][$i], $name, ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']);
                if (! $check['type']) {
                    continue;
                }
                $files['photos[]'][] = [$raw['tmp_name'][$i], sanitize_file_name($name), $check['type']];
            }
        }
        $res = STA_Api::post_multipart('photo-stories', $fields, $files, 120);
        if ($res[0] === 201) {
            sta_log('photo story from '.$fields['name']);
        }

        return STA_Frontend::pass($res);
    }
}
