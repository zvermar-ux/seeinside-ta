<?php

defined('ABSPATH') || exit;

/**
 * Website helpers: "Preview" from SeeInside, contacts / social networks / newsletter, main menu generator.
 */
class STA_Site
{
    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'preview'], 1);
        add_action('init', function () {
            add_shortcode('sta_contacts', [self::class, 'sc_contacts']);
            add_shortcode('sta_social', [self::class, 'sc_social']);
            add_shortcode('sta_newsletter', [self::class, 'sc_newsletter']);
            add_shortcode('sta_phone', [self::class, 'sc_phone']);
        });
    }

    /* ======================== Preview button in SeeInside ======================== */

    /** /?sta_preview=tour&id=12&slug=… → page of that record on this website */
    public static function preview(): void
    {
        if (empty($_GET['sta_preview'])) {   // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }
        $type = sanitize_key(wp_unslash($_GET['sta_preview']));   // phpcs:ignore
        $id = (int) ($_GET['id'] ?? 0);   // phpcs:ignore
        $slug = sanitize_title(wp_unslash((string) ($_GET['slug'] ?? '')));   // phpcs:ignore
        $pt = STA_Schema::post_type($type);
        if (! $pt) {
            return;
        }
        $post = $id ? sta_find_post($pt, $id) : null;
        if (! $post && $slug) {
            $p = get_page_by_path($slug, OBJECT, $pt);
            $post = $p ? $p->ID : null;
        }
        nocache_headers();
        if ($post && get_post_status($post) === 'publish') {
            wp_safe_redirect(add_query_arg('sta_v', time(), get_permalink($post)));
            exit;
        }
        if ($post && current_user_can('edit_post', $post)) {
            wp_safe_redirect(get_preview_post_link($post));
            exit;
        }
        // not on the website yet (just saved – the sync takes a few seconds) or not published
        $msg = $post
            ? __('This offer is on the website but not published (inactive or hidden). Activate it in SeeInside.', 'seeinside-travel')
            : __('The offer is not on the website yet. If you just saved it, the website receives it in a few seconds – this page refreshes by itself. Check that the offer is active and marked "Show on website".', 'seeinside-travel');
        status_header(200);
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .($post ? '' : '<meta http-equiv="refresh" content="5">')
            .'<title>'.esc_html__('Preview', 'seeinside-travel').'</title><style>body{font:16px/1.6 system-ui,sans-serif;display:grid;place-items:center;min-height:90vh;margin:0;background:#f6f7f9;color:#1f2937}div{max-width:520px;background:#fff;padding:28px 32px;border-radius:14px;box-shadow:0 6px 30px rgba(0,0,0,.08)}</style></head><body><div><h2>'
            .esc_html__('Preview', 'seeinside-travel').'</h2><p>'.esc_html($msg).'</p><p><a href="'.esc_url(home_url('/')).'">'.esc_html(get_bloginfo('name')).'</a></p></div></body></html>';
        exit;
    }

    /* ======================== contacts & social ======================== */

    public static function contacts(): array
    {
        $a = get_option('sta_meta', [])['agency'] ?? [];

        return (array) ($a['contacts'] ?? []) + ['phones' => [], 'email' => $a['email'] ?? '', 'address' => $a['address'] ?? '', 'social' => [], 'newsletter_url' => '', 'working_hours' => ''];
    }

    protected static function digits(string $n): string
    {
        $d = preg_replace('/\D+/', '', $n);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = '389'.substr($d, 1);   // local number → international (MK)
        }

        return $d;
    }

    /** One phone with call / Viber / WhatsApp buttons */
    protected static function phone(array $p, bool $apps = true): string
    {
        $num = (string) ($p['number'] ?? '');
        if ($num === '') {
            return '';
        }
        $d = self::digits($num);
        $h = '<li class="sta-ct-phone"><a class="sta-ct-call" href="tel:+'.esc_attr($d).'">'.sta_icon('phone').'<span><b>'.esc_html($num).'</b>'
            .(! empty($p['label']) ? '<small>'.esc_html(sta_t($p['label'])).'</small>' : '').'</span></a>';
        if ($apps && (! empty($p['viber']) || ! empty($p['whatsapp']))) {
            $h .= '<span class="sta-ct-apps">';
            if (! empty($p['viber'])) {
                $h .= '<a class="sta-ct-viber" href="viber://chat?number=%2B'.esc_attr($d).'" aria-label="Viber" title="Viber">'.self::svg('viber').'</a>';
            }
            if (! empty($p['whatsapp'])) {
                $h .= '<a class="sta-ct-wa" href="https://wa.me/'.esc_attr($d).'" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">'.self::svg('whatsapp').'</a>';
            }
            $h .= '</span>';
        }

        return $h.'</li>';
    }

    /** [sta_contacts show="all|main|emergency" title="1"] */
    public static function sc_contacts($atts = []): string
    {
        $a = shortcode_atts(['show' => 'all', 'title' => '1', 'social' => '1', 'layout' => 'columns'], $atts, 'sta_contacts');
        wp_enqueue_style('sta');
        $c = self::contacts();
        $groups = [
            'mobile' => __('Mobile · Viber · WhatsApp', 'seeinside-travel'),
            'landline' => __('Office phones', 'seeinside-travel'),
            'emergency' => __('Emergency (24/7 during travel)', 'seeinside-travel'),
        ];
        $want = $a['show'] === 'emergency' ? ['emergency'] : ($a['show'] === 'main' ? ['mobile', 'landline'] : array_keys($groups));
        $h = '<div class="sta sta-contacts sta-contacts--'.esc_attr(sanitize_key($a['layout'])).'">';
        foreach ($want as $g) {
            $list = array_filter((array) $c['phones'], fn ($p) => ($p['type'] ?? 'mobile') === $g);
            if (! $list) {
                continue;
            }
            $h .= '<div class="sta-ct-group sta-ct-'.esc_attr($g).'">'.($a['title'] ? '<h4>'.esc_html($groups[$g]).'</h4>' : '').'<ul>';
            foreach ($list as $p) {
                $h .= self::phone($p);
            }
            $h .= '</ul></div>';
        }
        if ($a['show'] === 'all') {
            $more = '';
            if (! empty($c['email'])) {
                $more .= '<li><a href="mailto:'.esc_attr($c['email']).'">'.sta_icon('mail').'<span><b>'.esc_html($c['email']).'</b></span></a></li>';
            }
            if (! empty($c['address'])) {
                $more .= '<li><a href="https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $c['address']).'" target="_blank" rel="noopener">'.sta_icon('pin').'<span><b>'.esc_html($c['address']).'</b></span></a></li>';
            }
            if (! empty($c['working_hours'])) {
                $more .= '<li><span class="sta-ct-hours">'.sta_icon('clock').'<span>'.nl2br(esc_html(sta_t($c['working_hours']))).'</span></span></li>';
            }
            if ($more) {
                $h .= '<div class="sta-ct-group sta-ct-other">'.($a['title'] ? '<h4>'.esc_html__('E-mail & address', 'seeinside-travel').'</h4>' : '').'<ul>'.$more.'</ul></div>';
            }
            if ($a['social'] && array_filter((array) $c['social'])) {
                $h .= '<div class="sta-ct-group">'.($a['title'] ? '<h4>'.esc_html__('Follow us', 'seeinside-travel').'</h4>' : '').self::sc_social([]).(! empty($c['newsletter_url']) ? self::sc_newsletter([]) : '').'</div>';
            }
        }

        return $h.'</div>';
    }

    /** [sta_phone type="mobile|landline|emergency"] – first number of that type, for headers / footers */
    public static function sc_phone($atts = []): string
    {
        $a = shortcode_atts(['type' => 'mobile', 'apps' => '1'], $atts, 'sta_phone');
        wp_enqueue_style('sta');
        foreach ((array) self::contacts()['phones'] as $p) {
            if (($p['type'] ?? 'mobile') === $a['type']) {
                return '<ul class="sta sta-contacts sta-contacts--inline">'.self::phone($p, (bool) $a['apps']).'</ul>';
            }
        }

        return '';
    }

    /** [sta_social] – icons with links */
    public static function sc_social($atts = []): string
    {
        $a = shortcode_atts(['size' => 40], $atts, 'sta_social');
        wp_enqueue_style('sta');
        $names = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'];
        $h = '';
        foreach ($names as $k => $label) {
            $url = self::contacts()['social'][$k] ?? '';
            if ($url) {
                $h .= '<a class="sta-so-'.$k.'" href="'.esc_url($url).'" target="_blank" rel="noopener me" aria-label="'.esc_attr($label).'" title="'.esc_attr($label).'">'.self::svg($k).'</a>';
            }
        }

        return $h ? '<div class="sta sta-social" style="--so:'.(int) $a['size'].'px">'.$h.'</div>' : '';
    }

    /** [sta_newsletter] – button to the Mailchimp sign-up form */
    public static function sc_newsletter($atts = []): string
    {
        $a = shortcode_atts(['text' => ''], $atts, 'sta_newsletter');
        $url = self::contacts()['newsletter_url'] ?? '';
        if (! $url) {
            return '';
        }
        wp_enqueue_style('sta');

        return '<div class="sta sta-newsletter"><p>'.esc_html__('Offers and last minute deals first – in your inbox.', 'seeinside-travel').'</p><a class="sta-btn" href="'.esc_url($url).'" target="_blank" rel="noopener">'.sta_icon('mail').' '.esc_html($a['text'] ?: __('Subscribe to the newsletter', 'seeinside-travel')).'</a></div>';
    }

    public static function svg(string $k): string
    {
        $p = [
            'facebook' => '<path d="M14 8h2.5V4.5H14c-2.5 0-4 1.6-4 4.1V11H7.5v3.5H10V21h3.5v-6.5H16l.5-3.5h-3V8.8c0-.5.3-.8.5-.8Z" fill="currentColor"/>',
            'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.3" cy="6.7" r="1.2" fill="currentColor"/>',
            'youtube' => '<path d="M21.6 7.2a2.6 2.6 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.6 2.6 0 0 0 2.4 7.2 27 27 0 0 0 2 12a27 27 0 0 0 .4 4.8 2.6 2.6 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.6 2.6 0 0 0 1.8-1.8A27 27 0 0 0 22 12a27 27 0 0 0-.4-4.8ZM10 15V9l5.2 3Z" fill="currentColor"/>',
            'tiktok' => '<path d="M16.5 3c.3 2.2 1.6 3.7 3.8 3.9v3.2a7 7 0 0 1-3.8-1.2v6.2A5.9 5.9 0 1 1 10.6 9v3.3a2.7 2.7 0 1 0 2.6 2.8V3Z" fill="currentColor"/>',
            'linkedin' => '<path d="M4.5 9h3.2v11H4.5zM6.1 3.8a1.9 1.9 0 1 1 0 3.8 1.9 1.9 0 0 1 0-3.8ZM10 9h3v1.5c.5-.9 1.7-1.8 3.4-1.8 3.4 0 4.1 2.2 4.1 5.2V20h-3.2v-5.4c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V20H10Z" fill="currentColor"/>',
            'viber' => '<path d="M12 2.5c-4.6 0-8.4 2.6-8.4 8.2 0 3 1 5 2.8 6.3v3.9l3-2.4c.8.2 1.7.3 2.6.3 4.6 0 8.4-2.6 8.4-8.1S16.6 2.5 12 2.5Zm3.9 11.6c-.3.6-1.3 1.2-1.9 1.1-2.5-.4-5.5-3.4-5.9-5.9-.1-.6.5-1.6 1.1-1.9.3-.1.6 0 .8.2l1 1.4c.2.3.2.7-.1 1l-.4.4c.4 1.1 1.3 2 2.4 2.4l.4-.4c.3-.3.7-.3 1-.1l1.4 1c.2.2.3.5.2.8Z" fill="currentColor"/>',
            'whatsapp' => '<path d="M12 2.5a9.4 9.4 0 0 0-8.1 14.2L2.6 21.5l4.9-1.3A9.4 9.4 0 1 0 12 2.5Zm5.4 13.2c-.2.6-1.3 1.2-1.8 1.3-.5 0-1 .2-3.3-.7a11.5 11.5 0 0 1-4.6-4.1c-.4-.5-1-1.6-1-2.9 0-1.4.7-2 1-2.3.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.3.5-.4.5c-.2.2-.3.3-.1.6.2.3.8 1.4 1.8 2.2 1.2 1.1 2.2 1.4 2.5 1.6.3.1.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.5.3.1.2.1.8-.1 1.4Z" fill="currentColor"/>',
        ][$k] ?? '';

        return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">'.$p.'</svg>';
    }

    /* ======================== main menu ======================== */

    /**
     * Creates (or rebuilds) the menu "SeeInside – главно мени":
     * Аранжмани → groups, Сместување → destinations (with sub-destinations), Настани, Визи, other offers, Last minute, Авио карти, Контакт.
     */
    public static function build_menu(): array
    {
        $name = __('SeeInside – main menu', 'seeinside-travel');
        $menu = wp_get_nav_menu_object($name);
        $menuId = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu($name);
        if (! $menuId || is_wp_error($menuId)) {
            return ['error' => __('The menu could not be created.', 'seeinside-travel')];
        }
        foreach ((array) wp_get_nav_menu_items($menuId) as $it) {
            wp_delete_post($it->ID, true);
        }
        $pages = get_option('sta_pages', []);
        $add = function (string $title, array $args, int $parent = 0) use ($menuId): int {
            $id = wp_update_nav_menu_item($menuId, 0, array_merge(['menu-item-title' => $title, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent], $args));

            return is_wp_error($id) ? 0 : (int) $id;
        };
        $page = fn ($slug) => ! empty($pages[$slug]) && get_post_status($pages[$slug]) === 'publish' ? (int) $pages[$slug] : 0;
        $link = fn ($slug, $pt) => $page($slug) ? ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $page($slug)]
            : ['menu-item-type' => 'post_type_archive', 'menu-item-object' => $pt];
        $terms = function (string $tax, int $parent, int $menuParent, int $depth = 0) use (&$terms, $add) {
            $list = get_terms(['taxonomy' => $tax, 'hide_empty' => true, 'parent' => $parent, 'orderby' => 'name']);
            foreach (is_wp_error($list) ? [] : $list as $t) {
                $mid = $add($t->name, ['menu-item-type' => 'taxonomy', 'menu-item-object' => $tax, 'menu-item-object-id' => $t->term_id], $menuParent);
                if ($depth < 1) {
                    $terms($tax, $t->term_id, $mid, $depth + 1);
                }
            }
        };
        $count = fn ($pt) => (int) (wp_count_posts($pt)->publish ?? 0);

        if ($count('sta_tour')) {
            $m = $add(__('Organized tours', 'seeinside-travel'), $link('aranzmani', 'sta_tour'));
            $terms('sta_group', 0, $m);
        }
        if ($count('sta_hotel')) {
            $m = $add(__('Accommodation', 'seeinside-travel'), $link('hoteli', 'sta_hotel'));
            $types = get_terms(['taxonomy' => 'sta_hotel_type', 'hide_empty' => true]);
            foreach (is_wp_error($types) ? [] : $types as $t) {
                $add($t->name, ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'sta_hotel_type', 'menu-item-object-id' => $t->term_id], $m);
            }
            // destinations that have accommodation → the hotels page with that destination filtered
            $hotelIds = get_posts(['post_type' => 'sta_hotel', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
            $dests = $hotelIds ? get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => true, 'object_ids' => $hotelIds, 'orderby' => 'name']) : [];
            $base = $page('hoteli') ? get_permalink($page('hoteli')) : get_post_type_archive_link('sta_hotel');
            if (! is_wp_error($dests) && $dests && $base) {
                $d = $add(__('By destination', 'seeinside-travel'), ['menu-item-type' => 'custom', 'menu-item-url' => $base], $m);
                $ids = wp_list_pluck($dests, 'term_id');
                $parents = [];
                foreach ($dests as $t) {
                    foreach (get_ancestors($t->term_id, 'sta_destination') as $anc) {
                        if (! in_array($anc, $ids, true)) {
                            $parents[$anc] = get_term($anc, 'sta_destination');
                        }
                    }
                }
                $all = array_merge(array_values(array_filter($parents)), $dests);
                $menuOf = [];
                foreach ([0, 1, 2] as $level) {
                    foreach ($all as $t) {
                        if (count(get_ancestors($t->term_id, 'sta_destination')) !== $level || isset($menuOf[$t->term_id])) {
                            continue;
                        }
                        $menuOf[$t->term_id] = $add($t->name, ['menu-item-type' => 'custom', 'menu-item-url' => add_query_arg('f_dest', $t->slug, $base)], $menuOf[$t->parent] ?? $d);
                    }
                }
            }
        }
        if ($count('sta_event')) {
            $m = $add(__('Events', 'seeinside-travel'), $link('nastani', 'sta_event'));
            $terms('sta_event_category', 0, $m);
        }
        if ($count('sta_offer')) {
            $types = get_terms(['taxonomy' => 'sta_offer_type', 'hide_empty' => true]);
            foreach (is_wp_error($types) ? [] : $types as $t) {
                $add($t->name, ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'sta_offer_type', 'menu-item-object-id' => $t->term_id]);
            }
        }
        if ($count('sta_visa')) {
            $add(__('Visas', 'seeinside-travel'), $link('vizi', 'sta_visa'));
        }
        if ($page('last-minute')) {
            $add('Last minute', $link('last-minute', ''));
        }
        if ($page('avio-karti')) {
            $add(__('Flights', 'seeinside-travel'), $link('avio-karti', ''));
        }
        if ($page('kontakt')) {
            $add(__('Contact', 'seeinside-travel'), $link('kontakt', ''));
        }

        // put it in the theme's main location when that one is empty
        $locs = get_nav_menu_locations();
        $reg = array_keys(get_registered_nav_menus());
        $main = array_values(array_filter($reg, fn ($l) => preg_match('/primary|main|header|menu-1/i', $l)))[0] ?? ($reg[0] ?? null);
        if ($main && empty($locs[$main])) {
            $locs[$main] = $menuId;
            set_theme_mod('nav_menu_locations', $locs);
        }

        return ['id' => $menuId, 'name' => $name, 'items' => count((array) wp_get_nav_menu_items($menuId))];
    }
}
