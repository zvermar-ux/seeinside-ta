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
        // menu: a class on Tours / Hotels / Apartments, so their sub-menus get their own icon (plane, hotel, summer)
        add_filter('nav_menu_css_class', [self::class, 'menu_classes'], 10, 2);
        // SEO title / description entered in SeeInside
        add_filter('document_title_parts', [self::class, 'seo_title_parts'], 20);
        add_action('wp_head', [self::class, 'seo_head'], 2);
        add_filter('wpseo_title', fn ($t) => self::seo('title') ?: $t);
        add_filter('wpseo_metadesc', fn ($t) => self::seo('description') ?: $t);
        add_filter('wpseo_opengraph_title', fn ($t) => self::seo('title') ?: $t);
        add_filter('wpseo_opengraph_desc', fn ($t) => self::seo('description') ?: $t);
        add_filter('rank_math/frontend/title', fn ($t) => self::seo('title') ?: $t);
        add_filter('rank_math/frontend/description', fn ($t) => self::seo('description') ?: $t);
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

    /* ======================== SEO ======================== */

    /** SEO title / description of the offer shown (from SeeInside), or the short description */
    public static function seo(string $what): string
    {
        if (! is_singular() || ! ($id = get_queried_object_id()) || strpos((string) get_post_type($id), 'sta_') !== 0) {
            return '';
        }
        $d = sta_data($id);
        $v = trim((string) sta_t($d['seo'][$what] ?? null));
        if ($v === '' && $what === 'description') {
            $v = wp_trim_words(wp_strip_all_tags((string) sta_t($d['short_description'] ?? null) ?: (string) sta_t($d['description'] ?? ($d['overview'] ?? null))), 28, '…');
        }

        return $v;
    }

    protected static function seo_plugin(): bool
    {
        return defined('WPSEO_VERSION') || class_exists('RankMath') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
    }

    public static function seo_title_parts(array $parts): array
    {
        if (! self::seo_plugin() && ($t = self::seo('title'))) {
            $parts['title'] = $t;
        }

        return $parts;
    }

    public static function seo_head(): void
    {
        if (self::seo_plugin() || ! is_singular()) {
            return;
        }
        $desc = self::seo('description');
        if ($desc === '') {
            return;
        }
        $id = get_queried_object_id();
        $img = get_the_post_thumbnail_url($id, 'large');
        echo '<meta name="description" content="'.esc_attr($desc).'">'."\n"
            .'<meta property="og:type" content="website"><meta property="og:title" content="'.esc_attr(self::seo('title') ?: get_the_title($id)).'">'
            .'<meta property="og:description" content="'.esc_attr($desc).'"><meta property="og:url" content="'.esc_url(get_permalink($id)).'">'
            .($img ? '<meta property="og:image" content="'.esc_url($img).'">' : '')."\n";
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
     * Menu "SeeInside – главно мени": Аранжмани → groups, Хотели → countries/regions/areas, Апартмани → types/destinations, Настани, Визи, other offers, Last minute, Авио карти.
     *
     * $fresh = false (the button "Update"): the menu as you arranged it stays – order, names, moved and deleted items –
     *   only items that never existed are added (under the right parent); hotel/destination links that have no hotels any more are removed.
     * $fresh = true ("Create again"): your arrangement (order, names, where items are) still stays, but items you deleted
     *   earlier come back and every item of ours whose offer / destination is gone is removed.
     * New items are put where they belong: under their parent, between their neighbours (e.g. a new country in alphabetical order).
     */
    public static function build_menu(bool $fresh = false): array
    {
        $name = __('SeeInside – main menu', 'seeinside-travel');
        $menu = wp_get_nav_menu_object($name);
        $menuId = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu($name);
        if (! $menuId || is_wp_error($menuId)) {
            return ['error' => __('The menu could not be created.', 'seeinside-travel')];
        }
        $existing = (array) wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
        $histKey = 'sta_menu_keys_'.$menuId;
        $history = $fresh ? [] : array_flip((array) get_option($histKey, []));
        $keyOf = fn (string $type, string $object, int $oid, string $url) => $type === 'custom' ? 'u:'.self::menu_url_key($url) : $type.':'.$object.':'.$oid;
        $byKey = [];
        $titleOf = [];
        foreach ($existing as $it) {
            $byKey[$keyOf((string) $it->type, (string) $it->object, (int) $it->object_id, (string) $it->url)] ??= (int) $it->ID;
            $titleOf[(int) $it->ID] = (string) $it->title;
        }
        $produced = [];
        $added = 0;
        $isNew = [];
        $pages = get_option('sta_pages', []);
        // -1 = skip (deleted by you earlier) – its sub-items are skipped too
        $add = function (string $title, array $args, int $parent = 0) use ($menuId, $keyOf, &$byKey, $history, &$produced, &$added, &$isNew, $titleOf): int {
            $old = (string) ($args['_old'] ?? '');   // the name it had before (e.g. Latin "LEFKADA" → "Лефкада")
            unset($args['_old']);
            $k = $keyOf((string) ($args['menu-item-type'] ?? 'custom'), (string) ($args['menu-item-object'] ?? ''), (int) ($args['menu-item-object-id'] ?? 0), (string) ($args['menu-item-url'] ?? ''));
            $produced[$k] ??= count($produced);   // the place in the logical order
            if (isset($byKey[$k])) {
                $id = $byKey[$k];
                if ($old !== '' && $old !== $title && mb_strtolower($titleOf[$id] ?? '') === mb_strtolower($old)) {
                    wp_update_post(['ID' => $id, 'post_title' => $title]);   // you did not rename it – the Macedonian name
                }

                return $id;                 // already in the menu – stays where and how you put it
            }
            if ($parent < 0 || isset($history[$k])) {
                return -1;                  // you removed it (or its parent) – not added again
            }
            $id = wp_update_nav_menu_item($menuId, 0, array_merge(['menu-item-title' => $title, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent], $args));
            if (is_wp_error($id)) {
                return -1;
            }
            $added++;
            $isNew[(int) $id] = true;

            return $byKey[$k] = (int) $id;
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

        /*
         * "By destination" under a menu item: country → region → place (Greece → Halkidiki → Hanioti), from the offers given.
         * A place always goes under its destination – the destination (and its parents) are added even when no offer is on them directly.
         */
        $byDest = function (array $postIds, string $base, int $under) use ($add) {
            if (! $postIds || $base === '') {
                return;
            }
            $dests = get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => true, 'object_ids' => $postIds, 'orderby' => 'name']);
            $dests = is_wp_error($dests) ? [] : $dests;
            $places = get_terms(['taxonomy' => 'sta_place', 'hide_empty' => true, 'object_ids' => $postIds]);
            $places = is_wp_error($places) ? [] : $places;
            if (! $dests && ! $places) {
                return;
            }
            $destOfRemote = function (int $remote) {
                $f = $remote ? get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => false, 'meta_key' => 'sta_id', 'meta_value' => $remote, 'number' => 1]) : [];

                return $f && ! is_wp_error($f) ? $f[0] : null;
            };
            // places with their parent places; the destination of every top place joins the destinations
            $pById = [];
            foreach ($places as $t) {
                $pById[$t->term_id] = $t;
                foreach (get_ancestors($t->term_id, 'sta_place') as $anc) {
                    if (! isset($pById[$anc]) && ($at = get_term($anc, 'sta_place')) instanceof WP_Term) {
                        $pById[$anc] = $at;
                    }
                }
            }
            $placeDest = [];
            $dById = [];
            foreach ($dests as $t) {
                $dById[$t->term_id] = $t;
            }
            foreach ($pById as $t) {
                if (! $t->parent && ($dt = $destOfRemote((int) get_term_meta($t->term_id, 'sta_destination_id', true)))) {
                    $placeDest[$t->term_id] = $dt->term_id;
                    $dById[$dt->term_id] = $dt;
                }
            }
            foreach (array_keys($dById) as $id) {
                foreach (get_ancestors($id, 'sta_destination') as $anc) {
                    if (! isset($dById[$anc]) && ($at = get_term($anc, 'sta_destination')) instanceof WP_Term) {
                        $dById[$anc] = $at;
                    }
                }
            }
            uasort($dById, fn ($x, $y) => strcmp($x->name, $y->name));
            $d = $add(__('By destination', 'seeinside-travel'), ['menu-item-type' => 'custom', 'menu-item-url' => $base], $under);
            $menuOf = [];
            foreach ([0, 1, 2, 3] as $level) {
                foreach ($dById as $t) {
                    if (count(get_ancestors($t->term_id, 'sta_destination')) !== $level || isset($menuOf[$t->term_id])) {
                        continue;
                    }
                    $menuOf[$t->term_id] = $add($t->name, ['menu-item-type' => 'custom', 'menu-item-url' => add_query_arg('f_dest', $t->slug, $base)], $menuOf[$t->parent] ?? $d);
                }
            }
            uasort($pById, fn ($x, $y) => strcmp($x->name, $y->name));
            $menuOfPlace = [];
            foreach ([0, 1, 2, 3] as $level) {
                foreach ($pById as $t) {
                    if (count(get_ancestors($t->term_id, 'sta_place')) !== $level || isset($menuOfPlace[$t->term_id])) {
                        continue;
                    }
                    $parentItem = $t->parent ? ($menuOfPlace[$t->parent] ?? $d) : ($menuOf[$placeDest[$t->term_id] ?? 0] ?? $d);
                    $menuOfPlace[$t->term_id] = $add($t->name, ['menu-item-type' => 'custom', 'menu-item-url' => add_query_arg('f_place', $t->slug, $base)], $parentItem);
                }
            }
        };

        if ($count('sta_tour')) {
            $m = $add(__('Organized tours', 'seeinside-travel'), $link('aranzmani', 'sta_tour'));
            $terms('sta_group', 0, $m);
            // tours by destination, like the apartments
            $tourIds = get_posts(['post_type' => 'sta_tour', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
            $tbase = $page('aranzmani') ? get_permalink($page('aranzmani')) : get_post_type_archive_link('sta_tour');
            $byDest($tourIds, (string) $tbase, $m);
        }
        // hotels from a booking system: "Hotels" → its destinations (the search asks for the dates)
        $api = STA_Hotels::enabled();
        if ($api && $page('hoteli')) {
            // Hotels → country (only its hotels) → region (destination) → area (zone)
            $hm = $add(__('Hotels', 'seeinside-travel'), $link('hoteli', ''));
            $byCountry = [];
            foreach (STA_Hotels::destinations() as $hd) {
                $byCountry[$hd['country'] ?? ''][] = $hd;
            }
            $hbase = get_permalink($page('hoteli'));
            foreach ($byCountry as $country => $list) {
                $code = (string) ($list[0]['country_code'] ?? '');
                $parent = count($byCountry) > 1 && $country !== '' ? $add($country, ['menu-item-type' => 'custom', 'menu-item-url' => $code ? add_query_arg('country', $code, $hbase) : $hbase], $hm) : $hm;
                foreach (array_slice($list, 0, 30) as $hd) {
                    $durl = add_query_arg('destination', $hd['id'], $hbase);
                    $dm = $add($hd['name'], ['menu-item-type' => 'custom', 'menu-item-url' => $durl, '_old' => (string) ($hd['name_en'] ?? '')], $parent);
                    $zones = (array) ($hd['zones'] ?? []);
                    if (count($zones) > 1) {
                        foreach (array_slice($zones, 0, 12) as $z) {
                            $add((string) ($z['label'] ?? $z['name']), ['menu-item-type' => 'custom', 'menu-item-url' => add_query_arg('zone', rawurlencode($z['name']), $durl), '_old' => (string) $z['name']], $dm);
                        }
                    }
                }
            }
        }
        // own accommodation of every type (apartments, studios, villas, own hotels…) → "Apartments"
        $hotelIds = get_posts(['post_type' => 'sta_hotel', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
        if ($hotelIds) {
            $m = $add(__('Apartments', 'seeinside-travel'), $link($page('apartmani') ? 'apartmani' : 'hoteli', 'sta_hotel'));
            $types = get_terms(['taxonomy' => 'sta_hotel_type', 'hide_empty' => true]);
            foreach (is_wp_error($types) ? [] : $types as $t) {
                $add($t->name, ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'sta_hotel_type', 'menu-item-object-id' => $t->term_id], $m);
            }
            // destinations / places that have accommodation → the apartments page filtered
            $base = $page('apartmani') ? get_permalink($page('apartmani')) : ($page('hoteli') ? get_permalink($page('hoteli')) : get_post_type_archive_link('sta_hotel'));
            $byDest($hotelIds, (string) $base, $m);
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
        // hotel / destination links that have no hotels any more (e.g. areas without imported hotels) go away
        $removed = 0;
        foreach ($existing as $it) {
            $k = $keyOf((string) $it->type, (string) $it->object, (int) $it->object_id, (string) $it->url);
            if (isset($produced[$k])) {
                continue;
            }
            if ($it->type === 'custom') {
                parse_str((string) wp_parse_url((string) $it->url, PHP_URL_QUERY), $qs);
                $hotelLink = (bool) array_intersect(array_keys($qs), ['destination', 'zone', 'country']);
                $ours = $hotelLink || array_intersect(array_keys($qs), ['f_dest', 'f_place']);
                $gone = $ours && ($fresh || isset($history[$k]) || (! $history && $hotelLink));
            } else {
                // "create again": groups, types, categories of ours without offers any more
                $gone = $fresh && str_starts_with((string) $it->object, 'sta_');
            }
            if ($gone) {
                wp_delete_post($it->ID, true);
                $removed++;
            }
        }
        // sub-items whose parent was removed go too (only ours)
        if ($removed) {
            $left = (array) wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
            $ids = array_flip(array_map(fn ($i) => (int) $i->ID, $left));
            foreach ($left as $it) {
                $k = $keyOf((string) $it->type, (string) $it->object, (int) $it->object_id, (string) $it->url);
                if ((int) $it->menu_item_parent && ! isset($ids[(int) $it->menu_item_parent]) && ! isset($produced[$k])) {
                    wp_delete_post($it->ID, true);
                    $removed++;
                }
            }
        }
        self::menu_order($menuId, $produced, $isNew, $keyOf);
        update_option($histKey, array_keys($history + $produced), false);

        // put it in the theme's main location when that one is empty
        $locs = get_nav_menu_locations();
        $reg = array_keys(get_registered_nav_menus());
        $main = array_values(array_filter($reg, fn ($l) => preg_match('/primary|main|header|menu-1/i', $l)))[0] ?? ($reg[0] ?? null);
        if ($main && empty($locs[$main])) {
            $locs[$main] = $menuId;
            set_theme_mod('nav_menu_locations', $locs);
        }

        return ['id' => $menuId, 'name' => $name, 'items' => count((array) wp_get_nav_menu_items($menuId)), 'added' => $added, 'removed' => $removed];
    }

    /**
     * Order of the menu: your items keep their order; every new item goes between its neighbours of the logical order
     * (e.g. "Кипар" between "Италија" and "Малта"), under its parent.
     */
    protected static function menu_order(int $menuId, array $gen, array $isNew, callable $keyOf): void
    {
        global $wpdb;
        $items = (array) wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
        if (! $items) {
            return;
        }
        $ids = [];
        foreach ($items as $it) {
            $ids[(int) $it->ID] = $it;
        }
        $kids = [];
        foreach ($items as $it) {   // already sorted by menu_order
            $p = (int) $it->menu_item_parent;
            $kids[isset($ids[$p]) ? $p : 0][] = $it;
        }
        $g = fn ($it) => $gen[$keyOf((string) $it->type, (string) $it->object, (int) $it->object_id, (string) $it->url)] ?? null;
        foreach ($kids as $p => $list) {
            $old = array_values(array_filter($list, fn ($it) => empty($isNew[(int) $it->ID])));
            $new = array_values(array_filter($list, fn ($it) => ! empty($isNew[(int) $it->ID])));
            usort($new, fn ($a, $b) => ($g($a) ?? PHP_INT_MAX) <=> ($g($b) ?? PHP_INT_MAX));
            foreach ($new as $n) {
                $gn = $g($n);
                $pos = count($old);
                foreach ($old as $i => $o) {
                    $go = $g($o);
                    if ($gn !== null && $go !== null && $go > $gn) {
                        $pos = $i;
                        break;
                    }
                }
                array_splice($old, $pos, 0, [$n]);
            }
            $kids[$p] = $old;
        }
        $n = 0;
        $walk = function (int $parent) use (&$walk, &$kids, &$n, $wpdb) {
            foreach ($kids[$parent] ?? [] as $it) {
                $n++;
                if ((int) $it->menu_order !== $n) {
                    $wpdb->update($wpdb->posts, ['menu_order' => $n], ['ID' => (int) $it->ID]);
                    clean_post_cache((int) $it->ID);
                }
                $walk((int) $it->ID);
            }
        };
        $walk(0);
    }

    public static function menu_classes($classes, $item): array
    {
        $classes = (array) $classes;
        if ((int) ($item->menu_item_parent ?? 0)) {
            return $classes;
        }
        $pages = (array) get_option('sta_pages', []);
        $is = fn (string $slug, string $pt) => ($item->object === 'page' && (int) $item->object_id && (int) $item->object_id === (int) ($pages[$slug] ?? 0))
            || ($item->type === 'post_type_archive' && $item->object === $pt);
        if ($is('aranzmani', 'sta_tour')) {
            $classes[] = 'sta-mi-tours';
        } elseif ($is('apartmani', 'sta_hotel')) {
            $classes[] = 'sta-mi-apartments';
        } elseif ($is('hoteli', '')) {
            $classes[] = 'sta-mi-hotels';
        }

        return $classes;
    }

    /** Same link = same item, whatever the domain, order of the parameters or the last slash */
    protected static function menu_url_key(string $url): string
    {
        $path = untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $q);
        ksort($q);

        return strtolower(rawurldecode($path)).($q ? '?'.http_build_query($q) : '');
    }
}
