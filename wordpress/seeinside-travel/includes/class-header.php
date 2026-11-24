<?php

defined('ABSPATH') || exit;

/**
 * Header of the theme (e.g. Tourm "Style Five") made better without touching the theme:
 * SeeInside Travel → Header – a top bar with links / phone / e-mail and, on phones, a smaller logo
 * in one row with the magnifier and the menu button, sticky or not, colours.
 */
class STA_Header
{
    public static function defaults(): array
    {
        return [
            // top bar
            'tb_on' => '0',
            'tb_where' => 'all',            // all | mobile | desktop
            'tb_links' => "Last minute|/last-minute/\nВизи|/vizi/\nКонтакт|/contact-us/",
            'tb_phone' => '1',
            'tb_email' => '1',
            'tb_text' => '',                // short text, e.g. "Работно време: пон–пет 9–17"
            'tb_bg' => '#12404d',
            'tb_color' => '#ffffff',
            'tb_size' => 13,
            // phones
            'm_on' => '1',
            'm_logo' => 130,                // px, width of the logo
            'm_pad' => 10,                  // px, space above / below in the header
            'm_bg' => '',
            'm_btn_bg' => '',               // menu button
            'm_btn_color' => '',
            'm_btn_size' => 44,
            'm_sticky' => 'theme',          // theme | off | on
            'm_hide' => '',                 // selectors to hide on phones (one per line)
            'm_menu_contact' => '1',        // phone + e-mail at the end of the phone menu
            'css' => '',
            // the logo while the page loads (before the CSS of the theme arrives)
            'early_on' => '1',
            'logo_max' => 180,              // px, widest the logo may be on computers
        ];
    }

    public static function opt(): array
    {
        $o = get_option('sta_header', []);

        return array_merge(self::defaults(), is_array($o) ? $o : []);
    }

    public static function init(): void
    {
        add_action('wp_footer', [self::class, 'output'], 5);
        add_action('wp_head', [self::class, 'early'], 1);
    }

    /**
     * A tiny style at the very top of <head>: the logo of the header never shows in its natural
     * (huge) size while the CSS of the theme is still loading (LiteSpeed "Load CSS asynchronously" etc.).
     */
    public static function early(): void
    {
        if (is_admin() || isset($_GET['elementor-preview'])) {   // phpcs:ignore
            return;
        }
        $o = self::opt();
        if ($o['early_on'] !== '1') {
            return;
        }
        $max = max(60, (int) $o['logo_max']);
        $sel = 'header .header-logo img,.th-header .header-logo img,.sticky-wrapper .header-logo img,.th-menu-wrapper .mobile-logo img,'
            .'.elementor-location-header .elementor-widget-theme-site-logo img,.elementor-location-header .elementor-widget-image img,header img.custom-logo,.elementor-location-header img.custom-logo';
        $css = $sel.'{max-width:'.$max.'px!important;height:auto!important}';
        if ($o['m_on'] === '1') {
            $css .= '@media (max-width:1199px){'.$sel.'{max-width:'.max(40, (int) $o['m_logo']).'px!important}}';
        }
        echo '<style id="sta-header-early">'.$css.'</style>'."\n";   // phpcs:ignore
    }

    public static function save(array $in): void
    {
        $d = self::defaults();
        $o = [];
        foreach ($d as $k => $def) {
            $v = wp_unslash($in[$k] ?? '');
            if (in_array($k, ['tb_on', 'tb_phone', 'tb_email', 'm_on', 'm_menu_contact', 'early_on'], true)) {
                $o[$k] = ! empty($in[$k]) ? '1' : '0';
            } elseif (is_int($def)) {
                $o[$k] = max(0, min(600, (int) $v));
            } elseif (in_array($k, ['tb_bg', 'tb_color', 'm_bg', 'm_btn_bg', 'm_btn_color'], true)) {
                $o[$k] = sanitize_hex_color((string) $v) ?: '';
            } elseif (in_array($k, ['tb_links', 'm_hide', 'css'], true)) {
                $o[$k] = wp_strip_all_tags((string) $v);
            } else {
                $o[$k] = sanitize_text_field((string) $v);
            }
        }
        $o['tb_where'] = in_array($o['tb_where'], ['all', 'mobile', 'desktop'], true) ? $o['tb_where'] : 'all';
        $o['m_sticky'] = in_array($o['m_sticky'], ['theme', 'off', 'on'], true) ? $o['m_sticky'] : 'theme';
        update_option('sta_header', $o);
    }

    /** "Label|/link/" lines → links (relative links go to this site) */
    protected static function links(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $p = array_map('trim', explode('|', $line, 2));
            if (($p[0] ?? '') === '') {
                continue;
            }
            $url = $p[1] ?? '';
            if ($url !== '' && ! preg_match('#^(https?:|mailto:|tel:|\#)#', $url)) {
                $url = home_url('/'.ltrim($url, '/'));
            }
            $out[] = [$p[0], $url];
        }

        return $out;
    }

    public static function output(): void
    {
        if (is_admin() || isset($_GET['elementor-preview'])) {   // phpcs:ignore
            return;
        }
        $o = self::opt();
        if ($o['tb_on'] !== '1' && $o['m_on'] !== '1' && $o['m_menu_contact'] !== '1' && trim($o['css']) === '') {
            return;
        }
        $agency = (array) (get_option('sta_meta', [])['agency'] ?? []);
        $bar = '';
        if ($o['tb_on'] === '1') {
            $left = '';
            if ($o['tb_phone'] === '1' && ! empty($agency['phone'])) {
                $left .= '<a href="tel:'.esc_attr(preg_replace('/[^0-9+]/', '', (string) $agency['phone'])).'">'.sta_icon('phone').'<span>'.esc_html($agency['phone']).'</span></a>';
            }
            if ($o['tb_email'] === '1' && ! empty($agency['email'])) {
                $left .= '<a href="mailto:'.esc_attr($agency['email']).'" class="sta-tb-mail">'.sta_icon('mail').'<span>'.esc_html($agency['email']).'</span></a>';
            }
            if ($o['tb_text'] !== '') {
                $left .= '<span class="sta-tb-text">'.esc_html($o['tb_text']).'</span>';
            }
            $right = '';
            foreach (self::links($o['tb_links']) as [$label, $url]) {
                $right .= '<a href="'.esc_url($url ?: '#').'">'.esc_html($label).'</a>';
            }
            $bar = '<div class="sta-topbar is-'.esc_attr($o['tb_where']).'"><div class="sta-topbar-in"><div class="sta-tb-l">'.$left.'</div><nav class="sta-tb-r">'.$right.'</nav></div></div>';
        }
        $v = ['--tb-bg' => $o['tb_bg'], '--tb-c' => $o['tb_color'], '--tb-s' => $o['tb_size'].'px', '--mh-logo' => $o['m_logo'].'px', '--mh-pad' => $o['m_pad'].'px',
            '--mh-bg' => $o['m_bg'], '--mh-btn-bg' => $o['m_btn_bg'], '--mh-btn-c' => $o['m_btn_color'], '--mh-btn-s' => $o['m_btn_size'].'px'];
        $css = ':root{';
        foreach ($v as $k => $val) {
            if ($val !== '' && $val !== 'px') {
                $css .= $k.':'.$val.';';
            }
        }
        $css .= '}';
        $m = '';
        if ($o['m_bg']) {
            $m .= '.sta-mh .sta-mh-head,.sta-mh .sta-mh-row{background:'.$o['m_bg'].'!important}';
        }
        if ($o['m_btn_bg']) {
            $m .= '.sta-mh .sta-mh-toggle{background:'.$o['m_btn_bg'].'!important;border-color:'.$o['m_btn_bg'].'!important}';
        }
        if ($o['m_btn_color']) {
            $m .= '.sta-mh .sta-mh-toggle,.sta-mh .sta-mh-toggle *{color:'.$o['m_btn_color'].'!important}';
        }
        if ($m !== '' && $o['m_on'] === '1') {
            $css .= '@media (max-width:1199px){'.$m.'}';
        }
        $hide = array_filter(array_map('trim', preg_split('/\r?\n/', (string) $o['m_hide'])));
        if ($hide && $o['m_on'] === '1') {
            $css .= '@media (max-width:1199px){'.implode(',', array_map(fn ($sel) => preg_replace('/[{}<>]/', '', $sel), $hide)).'{display:none!important}}';
        }
        $css .= str_replace('</', '', (string) $o['css']);
        echo '<style id="sta-header-style">'.$css.'</style>';   // phpcs:ignore
        // phone and e-mail of the agency at the end of the phone menu of the theme
        $mc = '';
        if ($o['m_menu_contact'] === '1' && (! empty($agency['phone']) || ! empty($agency['email']))) {
            $mc = '<div class="sta-mmenu-contact">'
                .(! empty($agency['phone']) ? '<a href="tel:'.esc_attr(preg_replace('/[^0-9+]/', '', (string) $agency['phone'])).'">'.sta_icon('phone').'<span>'.esc_html($agency['phone']).'</span></a>' : '')
                .(! empty($agency['email']) ? '<a href="mailto:'.esc_attr($agency['email']).'">'.sta_icon('mail').'<span>'.esc_html($agency['email']).'</span></a>' : '')
                .'</div>';
        }
        echo '<template data-sta-header data-mobile="'.esc_attr($o['m_on']).'" data-sticky="'.esc_attr($o['m_sticky']).'">'.$bar.'</template>';   // phpcs:ignore
        if ($mc !== '') {
            echo '<template data-sta-mmenu>'.$mc.'</template>';   // phpcs:ignore
        }
    }
}
