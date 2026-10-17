<?php

defined('ABSPATH') || exit;

/**
 * Homepage slider: content comes from SeeInside (Слајдер на почетна), the look is set here
 * (SeeInside Travel → Slider). Default design: full-screen photo, handwritten supertitle, light
 * large title, teal + outline pill buttons, vertical arrows with progress line and a liquid-glass
 * effect that follows the mouse.
 */
class STA_Slider
{
    public const FONTS_SCRIPT = ['Marck Script', 'Bad Script', 'Caveat', 'Lobster', 'Pacifico', 'Kurale', 'Neucha', 'Amatic SC', 'Comfortaa'];

    public const FONTS_SANS = ['Manrope', 'Montserrat', 'Inter', 'Raleway', 'Nunito', 'Rubik', 'Golos Text', 'Commissioner', 'Open Sans', 'Roboto', 'Playfair Display', 'Lora', 'Oswald', 'Russo One'];

    public static function defaults(): array
    {
        return [
            // size & layout
            'height' => '100vh',
            'height_mobile' => '88vh',
            'min_height' => 520,
            'radius' => 0,
            'full_width' => '1',
            'content_align' => 'left',
            'content_valign' => 'center',
            'content_width' => 760,
            'content_offset' => 6,          // % from the side
            'overlay_color' => '#0b1220',
            'overlay_opacity' => 0.28,
            'overlay_gradient' => '1',
            // texts
            'super_font' => 'Marck Script',
            'super_size' => 34,
            'super_color' => '#ffffff',
            'title_font' => 'Manrope',
            'title_size' => 66,
            'title_weight' => 300,
            'title_color' => '#ffffff',
            'title_upper' => '',
            'sub_size' => 18,
            'sub_color' => '#e6edf5',
            'text_shadow' => '1',
            // buttons
            'btn_bg' => '#2cb0c8',
            'btn_color' => '#ffffff',
            'btn_hover' => '#1f97ad',
            'btn2_style' => 'outline',      // outline | glass | solid | link
            'btn2_color' => '#ffffff',
            'btn_radius' => 999,
            'btn_size' => 'md',              // sm | md | lg
            'btn_arrow' => '1',
            'btn2_default_label' => 'Сите понуди',
            'btn2_default_label_en' => 'All offers',
            'btn2_default_link' => '/last-minute/',
            // navigation
            'arrows' => 'right-vertical',    // right-vertical | left-vertical | sides | bottom-right | bottom-center | none
            'arrow_style' => 'glass',        // glass | solid | outline | minimal
            'arrow_size' => 54,
            'arrow_bg' => 'rgba(255,255,255,.22)',
            'arrow_active' => '#2cb0c8',
            'arrow_color' => '#ffffff',
            'progress' => '1',
            'pagination' => 'none',          // none | dots | numbers | lines
            'autoplay' => 7,
            'pause_hover' => '1',
            'keyboard' => '1',
            // effects
            'mouse_effect' => 'liquid',      // liquid | ripple | none
            'effect_strength' => 1.0,
            'effect_size' => 1.0,
            'transition' => 'liquid',        // liquid | fade | slide | zoom | none
            'transition_ms' => 1300,
            'text_anim' => 'rise',           // rise | letters | blur | none
            'ken_burns' => '',
            'effects_mobile' => '1',
        ];
    }

    public static function options(): array
    {
        return [
            'content_align' => ['left' => __('Left', 'seeinside-travel'), 'center' => __('Centre', 'seeinside-travel'), 'right' => __('Right', 'seeinside-travel')],
            'content_valign' => ['top' => __('Top', 'seeinside-travel'), 'center' => __('Middle', 'seeinside-travel'), 'bottom' => __('Bottom', 'seeinside-travel')],
            'btn2_style' => ['outline' => __('Outline', 'seeinside-travel'), 'glass' => __('Frosted glass', 'seeinside-travel'), 'solid' => __('Solid white', 'seeinside-travel'), 'link' => __('Text link', 'seeinside-travel')],
            'btn_size' => ['sm' => __('Small', 'seeinside-travel'), 'md' => __('Medium', 'seeinside-travel'), 'lg' => __('Large', 'seeinside-travel')],
            'arrows' => [
                'right-vertical' => __('Right, vertical (up / down)', 'seeinside-travel'),
                'left-vertical' => __('Left, vertical', 'seeinside-travel'),
                'sides' => __('Both sides', 'seeinside-travel'),
                'bottom-right' => __('Bottom right', 'seeinside-travel'),
                'bottom-center' => __('Bottom centre', 'seeinside-travel'),
                'none' => __('No arrows', 'seeinside-travel'),
            ],
            'arrow_style' => ['glass' => __('Frosted glass circle', 'seeinside-travel'), 'solid' => __('Solid circle', 'seeinside-travel'), 'outline' => __('Outline circle', 'seeinside-travel'), 'minimal' => __('Arrow only', 'seeinside-travel')],
            'pagination' => ['none' => __('None', 'seeinside-travel'), 'dots' => __('Dots', 'seeinside-travel'), 'numbers' => __('Numbers 01 / 03', 'seeinside-travel'), 'lines' => __('Lines', 'seeinside-travel')],
            'mouse_effect' => [
                'liquid' => __('Liquid glass trail (as in the video)', 'seeinside-travel'),
                'ripple' => __('Water ripples', 'seeinside-travel'),
                'none' => __('No effect', 'seeinside-travel'),
            ],
            'transition' => [
                'liquid' => __('Liquid morph', 'seeinside-travel'),
                'fade' => __('Cross-fade', 'seeinside-travel'),
                'slide' => __('Slide', 'seeinside-travel'),
                'zoom' => __('Zoom & fade', 'seeinside-travel'),
                'none' => __('Instant', 'seeinside-travel'),
            ],
            'text_anim' => ['rise' => __('Rise & fade (as in the video)', 'seeinside-travel'), 'letters' => __('Letter by letter', 'seeinside-travel'), 'blur' => __('Blur in', 'seeinside-travel'), 'none' => __('No animation', 'seeinside-travel')],
        ];
    }

    public static function settings(array $override = []): array
    {
        $saved = get_option('sta_slider_settings', []);

        return array_merge(self::defaults(), is_array($saved) ? $saved : [], array_filter($override, fn ($v) => $v !== null && $v !== ''));
    }

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'register_assets']);
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_post_sta_slider_save', [self::class, 'save']);
    }

    public static function register_assets(): void
    {
        wp_register_style('sta-slider', STA_URL.'assets/css/slider.css', [], STA_VERSION);
        wp_register_script('sta-slider', STA_URL.'assets/js/slider.js', [], STA_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
    }

    protected static function font_url(array $s): string
    {
        $fams = array_unique(array_filter([$s['super_font'], $s['title_font']], fn ($f) => $f && $f !== 'inherit'));
        if (! $fams) {
            return '';
        }
        $w = (int) $s['title_weight'];

        return 'https://fonts.bunny.net/css?family='.implode('|', array_map(fn ($f) => strtolower(str_replace(' ', '-', $f)).':'.($f === $s['title_font'] ? implode(',', array_unique([300, 400, 500, $w])) : '400'), $fams)).'&display=swap';
    }

    /** Shortcode / widget overrides: only known values, CSS lengths only */
    protected static function clean_atts(array $a): array
    {
        $opts = self::options();
        foreach ($a as $k => $v) {
            if (is_array($v)) { unset($a[$k]); continue; }
            $v = trim((string) $v);
            if (isset($opts[$k]) && ! isset($opts[$k][$v])) { unset($a[$k]); continue; }
            if (in_array($k, ['height', 'height_mobile'], true)) {
                if (is_numeric($v)) { $v .= 'px'; }
                if (! preg_match('/^\d+(\.\d+)?(px|vh|svh|dvh|%|rem|em)$/', $v)) { unset($a[$k]); continue; }
            } elseif (is_numeric(self::defaults()[$k] ?? null)) {
                if (! is_numeric($v)) { unset($a[$k]); continue; }
                $v = $v + 0;
            } else {
                $v = sanitize_text_field($v);
            }
            $a[$k] = $v;
        }

        return $a;
    }

    /** Local copy of a slide photo (needed for the WebGL effects: same origin) */
    protected static function local_image(string $url): string
    {
        $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sta_source', 'meta_value' => md5($url), 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true]);

        return $found ? (wp_get_attachment_image_url((int) $found[0], 'full') ?: $url) : $url;
    }

    protected static function link(?string $url): string
    {
        if (! $url) {
            return '';
        }

        return str_starts_with($url, '/') ? home_url($url) : $url;
    }

    public static function render(array $atts = []): string
    {
        $slides = get_option('sta_slider', []);
        if (! $slides) {
            return '';
        }
        $s = self::settings(self::clean_atts(array_intersect_key($atts, self::defaults())));
        wp_enqueue_style('sta-slider');
        wp_enqueue_script('sta-slider');
        $fontUrl = self::font_url($s);
        if ($fontUrl) {
            wp_enqueue_style('sta-slider-fonts', $fontUrl, [], null);
        }

        $font = fn ($f) => $f && $f !== 'inherit' ? "'".esc_attr($f)."', " : '';
        $vars = [
            '--h' => $s['height'], '--h-m' => $s['height_mobile'], '--min-h' => (int) $s['min_height'].'px', '--radius' => (int) $s['radius'].'px',
            '--content-w' => (int) $s['content_width'].'px', '--offset' => (float) $s['content_offset'].'%',
            '--ov' => $s['overlay_color'], '--ov-o' => (float) $s['overlay_opacity'],
            '--super-f' => $font($s['super_font']).'cursive', '--super-s' => (int) $s['super_size'].'px', '--super-c' => $s['super_color'],
            '--title-f' => $font($s['title_font']).'system-ui, sans-serif', '--title-s' => (int) $s['title_size'].'px', '--title-w' => (int) $s['title_weight'], '--title-c' => $s['title_color'],
            '--sub-s' => (int) $s['sub_size'].'px', '--sub-c' => $s['sub_color'],
            '--btn-bg' => $s['btn_bg'], '--btn-c' => $s['btn_color'], '--btn-hover' => $s['btn_hover'], '--btn2-c' => $s['btn2_color'], '--btn-r' => (int) $s['btn_radius'].'px',
            '--arrow-s' => (int) $s['arrow_size'].'px', '--arrow-bg' => $s['arrow_bg'], '--arrow-active' => $s['arrow_active'], '--arrow-c' => $s['arrow_color'],
            '--t-ms' => (int) $s['transition_ms'].'ms',
        ];
        $style = implode(';', array_map(fn ($k, $v) => $k.':'.$v, array_keys($vars), $vars));
        $classes = [
            'sta-hero', 'is-align-'.$s['content_align'], 'is-valign-'.$s['content_valign'], 'is-arrows-'.$s['arrows'], 'is-arrow-'.$s['arrow_style'],
            'is-btn-'.$s['btn_size'], 'is-btn2-'.$s['btn2_style'], 'is-text-'.$s['text_anim'], 'is-pag-'.$s['pagination'],
        ];
        if ($s['overlay_gradient']) {
            $classes[] = 'has-gradient';
        }
        if ($s['full_width']) {
            $classes[] = 'is-full';
            $classes[] = 'alignfull';
        }
        if ($s['text_shadow']) {
            $classes[] = 'has-shadow';
        }
        if ($s['title_upper']) {
            $classes[] = 'is-upper';
        }
        if ($s['ken_burns']) {
            $classes[] = 'has-kenburns';
        }
        $config = [
            'autoplay' => (float) $s['autoplay'], 'pauseHover' => (bool) $s['pause_hover'], 'keyboard' => (bool) $s['keyboard'],
            'mouse' => $s['mouse_effect'], 'strength' => (float) $s['effect_strength'], 'size' => (float) $s['effect_size'],
            'transition' => $s['transition'], 'duration' => (int) $s['transition_ms'], 'kenBurns' => (bool) $s['ken_burns'],
            'mobile' => (bool) $s['effects_mobile'], 'progress' => (bool) $s['progress'],
        ];

        $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
        $html = '<section class="'.esc_attr(implode(' ', $classes)).'" style="'.esc_attr($style).'" data-sta-hero=\''.esc_attr(wp_json_encode($config)).'\' aria-roledescription="carousel">';
        $html .= '<div class="sta-hero-media">';
        foreach ($slides as $i => $sl) {
            $img = self::local_image((string) ($sl['image'] ?? ''));
            $html .= '<div class="sta-hero-bg'.($i === 0 ? ' is-active' : '').'" data-src="'.esc_url($img).'" style="background-image:url(\''.esc_url($img).'\')"></div>';
        }
        $html .= '<canvas class="sta-hero-gl" aria-hidden="true"></canvas><div class="sta-hero-overlay"></div></div>';

        $html .= '<div class="sta-hero-slides">';
        foreach ($slides as $i => $sl) {
            $btn = sta_t($sl['button']['label'] ?? null);
            $url = self::link($sl['button']['url'] ?? '');
            $btn2 = sta_t($sl['button2']['label'] ?? null) ?: (sta_lang() === 'en' ? $s['btn2_default_label_en'] : $s['btn2_default_label']);
            $url2 = self::link($sl['button2']['url'] ?? '') ?: self::link($s['btn2_default_link']);
            $target = ! empty($sl['button']['new_tab']) ? ' target="_blank" rel="noopener"' : '';
            $title = sta_t($sl['title'] ?? null);
            $html .= '<div class="sta-hero-slide'.($i === 0 ? ' is-active' : '').'" role="group" aria-roledescription="slide" aria-label="'.($i + 1).' / '.count($slides).'"'.($i ? ' aria-hidden="true"' : '').'>'
                .'<div class="sta-hero-content">'
                .(sta_t($sl['supertitle'] ?? null) ? '<div class="sta-hero-super">'.esc_html(sta_t($sl['supertitle'])).'</div>' : '')
                .($title ? '<h2 class="sta-hero-title">'.esc_html($title).'</h2>' : '')
                .(sta_t($sl['subtitle'] ?? null) ? '<p class="sta-hero-sub">'.esc_html(sta_t($sl['subtitle'])).'</p>' : '')
                .'<div class="sta-hero-btns">'
                .($btn && $url ? '<a class="sta-hero-btn" href="'.esc_url($url).'"'.$target.'><span>'.esc_html($btn).'</span>'.($s['btn_arrow'] ? $arrow : '').'</a>' : '')
                .($btn2 && $url2 ? '<a class="sta-hero-btn sta-hero-btn2" href="'.esc_url($url2).'"><span>'.esc_html($btn2).'</span>'.($s['btn_arrow'] ? $arrow : '').'</a>' : '')
                .'</div></div></div>';
        }
        $html .= '</div>';

        if (count($slides) > 1) {
            $up = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M6 11l6-6 6 6"/></svg>';
            if ($s['arrows'] !== 'none') {
                $html .= '<div class="sta-hero-nav"><button type="button" class="sta-hero-arrow sta-hero-prev" aria-label="'.esc_attr__('Previous', 'seeinside-travel').'">'.$up.'</button>'
                    .($s['progress'] ? '<span class="sta-hero-progress"><i></i></span>' : '')
                    .'<button type="button" class="sta-hero-arrow sta-hero-next" aria-label="'.esc_attr__('Next', 'seeinside-travel').'">'.$up.'</button></div>';
            }
            if ($s['pagination'] !== 'none') {
                $html .= '<div class="sta-hero-pag">';
                if ($s['pagination'] === 'numbers') {
                    $html .= '<b class="sta-hero-cur">01</b><span>/</span><span>'.sprintf('%02d', count($slides)).'</span>';
                } else {
                    foreach ($slides as $i => $sl) {
                        $html .= '<button type="button" aria-label="'.($i + 1).'"'.($i === 0 ? ' class="is-active"' : '').'></button>';
                    }
                }
                $html .= '</div>';
            }
        }

        return $html.'</section>';
    }

    /* ======================== admin ======================== */

    public static function menu(): void
    {
        add_submenu_page('seeinside-travel', __('Slider', 'seeinside-travel'), __('Slider', 'seeinside-travel'), 'manage_options', 'seeinside-travel-slider', [self::class, 'page']);
    }

    protected static function field(string $key, string $label, string $type = 'text', array $opts = []): string
    {
        $s = self::settings();
        $v = $s[$key] ?? '';
        $name = 'sta_slider['.$key.']';
        $in = match ($type) {
            'select' => '<select name="'.$name.'">'.implode('', array_map(fn ($k, $l) => '<option value="'.esc_attr($k).'"'.selected((string) $v, (string) $k, false).'>'.esc_html($l).'</option>', array_keys($opts), $opts)).'</select>',
            'color' => '<input type="text" class="sta-color" name="'.$name.'" value="'.esc_attr($v).'" data-default-color="'.esc_attr(self::defaults()[$key] ?? '').'">',
            'check' => '<label><input type="checkbox" name="'.$name.'" value="1"'.checked((bool) $v, true, false).'> '.esc_html($opts['label'] ?? '').'</label>',
            'number' => '<input type="number" name="'.$name.'" value="'.esc_attr($v).'" step="'.esc_attr($opts['step'] ?? 1).'" min="'.esc_attr($opts['min'] ?? 0).'" max="'.esc_attr($opts['max'] ?? 9999).'" class="small-text"> '.esc_html($opts['unit'] ?? ''),
            'range' => '<input type="range" name="'.$name.'" value="'.esc_attr($v).'" step="'.esc_attr($opts['step'] ?? 0.1).'" min="'.esc_attr($opts['min'] ?? 0).'" max="'.esc_attr($opts['max'] ?? 2).'" oninput="this.nextElementSibling.textContent=this.value"> <output>'.esc_html($v).'</output>',
            'font' => '<select name="'.$name.'"><option value="inherit"'.selected($v, 'inherit', false).'>'.esc_html__('Theme font', 'seeinside-travel').'</option><optgroup label="'.esc_attr__('Handwritten', 'seeinside-travel').'">'.implode('', array_map(fn ($f) => '<option'.selected($v, $f, false).'>'.esc_html($f).'</option>', self::FONTS_SCRIPT)).'</optgroup><optgroup label="'.esc_attr__('Regular', 'seeinside-travel').'">'.implode('', array_map(fn ($f) => '<option'.selected($v, $f, false).'>'.esc_html($f).'</option>', self::FONTS_SANS)).'</optgroup></select>',
            default => '<input type="text" class="regular-text" name="'.$name.'" value="'.esc_attr($v).'">',
        };

        return '<tr><th>'.esc_html($label).'</th><td>'.$in.(! empty($opts['help']) ? '<p class="description">'.esc_html($opts['help']).'</p>' : '').'</td></tr>';
    }

    public static function page(): void
    {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('sta', STA_URL.'assets/css/frontend.css', [], STA_VERSION);
        wp_enqueue_style('sta-admin', STA_URL.'assets/css/admin.css', [], STA_VERSION);
        $o = self::options();

        echo '<div class="wrap sta-admin"><h1><span class="dashicons dashicons-images-alt2"></span> '.esc_html__('Slider', 'seeinside-travel').'</h1>';
        if (! empty($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Saved.', 'seeinside-travel').'</p></div>';
        }
        echo '<p>'.wp_kses_post(__('Slides (photo, supertitle, title, subtitle, buttons) are entered in SeeInside → <b>Homepage slider</b>. Here you set how the slider looks. Use it with <code>[sta_slider]</code>, the Elementor widget „Travel: homepage slider“ or the Gutenberg block.', 'seeinside-travel')).'</p>';

        echo '<div class="sta-card sta-slider-preview"><h2>'.esc_html__('Preview', 'seeinside-travel').'</h2>'.(get_option('sta_slider') ? self::render(['height' => '520px']) : '<p>'.esc_html__('No slides yet – they arrive from SeeInside.', 'seeinside-travel').'</p>').'</div>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('sta_slider_save');
        echo '<input type="hidden" name="action" value="sta_slider_save"><div class="sta-grid2">';

        echo '<div class="sta-card"><h2>'.esc_html__('Effects', 'seeinside-travel').'</h2><table class="form-table">'
            .self::field('mouse_effect', __('Mouse effect', 'seeinside-travel'), 'select', $o['mouse_effect'])
            .self::field('effect_strength', __('Effect strength', 'seeinside-travel'), 'range', ['min' => 0.2, 'max' => 2, 'step' => 0.1])
            .self::field('effect_size', __('Effect size', 'seeinside-travel'), 'range', ['min' => 0.4, 'max' => 2, 'step' => 0.1])
            .self::field('transition', __('Change of slide', 'seeinside-travel'), 'select', $o['transition'])
            .self::field('transition_ms', __('Change duration', 'seeinside-travel'), 'number', ['min' => 200, 'max' => 4000, 'step' => 100, 'unit' => 'ms'])
            .self::field('text_anim', __('Text animation', 'seeinside-travel'), 'select', $o['text_anim'])
            .self::field('ken_burns', __('Slow zoom of the photo', 'seeinside-travel'), 'check', ['label' => __('Ken Burns effect', 'seeinside-travel')])
            .self::field('effects_mobile', __('Phones', 'seeinside-travel'), 'check', ['label' => __('Effects also on phones (touch)', 'seeinside-travel')])
            .'</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Arrows & navigation', 'seeinside-travel').'</h2><table class="form-table">'
            .self::field('arrows', __('Arrows position', 'seeinside-travel'), 'select', $o['arrows'])
            .self::field('arrow_style', __('Arrows look', 'seeinside-travel'), 'select', $o['arrow_style'])
            .self::field('arrow_size', __('Arrow size', 'seeinside-travel'), 'number', ['min' => 30, 'max' => 90, 'unit' => 'px'])
            .self::field('arrow_bg', __('Arrow background', 'seeinside-travel'), 'color')
            .self::field('arrow_active', __('Arrow hover / active', 'seeinside-travel'), 'color')
            .self::field('arrow_color', __('Arrow icon', 'seeinside-travel'), 'color')
            .self::field('progress', __('Progress line', 'seeinside-travel'), 'check', ['label' => __('Line between the arrows shows the time to the next slide', 'seeinside-travel')])
            .self::field('pagination', __('Pagination', 'seeinside-travel'), 'select', $o['pagination'])
            .self::field('autoplay', __('Next slide after', 'seeinside-travel'), 'number', ['min' => 0, 'max' => 60, 'unit' => __('seconds (0 = off)', 'seeinside-travel')])
            .self::field('pause_hover', __('Pause', 'seeinside-travel'), 'check', ['label' => __('Pause while the mouse is over the slider', 'seeinside-travel')])
            .'</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Texts & fonts', 'seeinside-travel').'</h2><table class="form-table">'
            .self::field('super_font', __('Supertitle font', 'seeinside-travel'), 'font')
            .self::field('super_size', __('Supertitle size', 'seeinside-travel'), 'number', ['min' => 12, 'max' => 90, 'unit' => 'px'])
            .self::field('super_color', __('Supertitle colour', 'seeinside-travel'), 'color')
            .self::field('title_font', __('Title font', 'seeinside-travel'), 'font')
            .self::field('title_size', __('Title size', 'seeinside-travel'), 'number', ['min' => 20, 'max' => 140, 'unit' => 'px'])
            .self::field('title_weight', __('Title weight', 'seeinside-travel'), 'select', [200 => '200', 300 => '300 – '.__('light', 'seeinside-travel'), 400 => '400', 500 => '500', 600 => '600', 700 => '700 – '.__('bold', 'seeinside-travel'), 800 => '800'])
            .self::field('title_color', __('Title colour', 'seeinside-travel'), 'color')
            .self::field('title_upper', __('Capitals', 'seeinside-travel'), 'check', ['label' => __('Title in capital letters', 'seeinside-travel')])
            .self::field('sub_size', __('Subtitle size', 'seeinside-travel'), 'number', ['min' => 10, 'max' => 40, 'unit' => 'px'])
            .self::field('sub_color', __('Subtitle colour', 'seeinside-travel'), 'color')
            .self::field('text_shadow', __('Shadow', 'seeinside-travel'), 'check', ['label' => __('Soft shadow under the text (better reading on bright photos)', 'seeinside-travel')])
            .'</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Buttons', 'seeinside-travel').'</h2><table class="form-table">'
            .self::field('btn_bg', __('Main button', 'seeinside-travel'), 'color')
            .self::field('btn_hover', __('Main button – hover', 'seeinside-travel'), 'color')
            .self::field('btn_color', __('Main button text', 'seeinside-travel'), 'color')
            .self::field('btn2_style', __('Second button', 'seeinside-travel'), 'select', $o['btn2_style'])
            .self::field('btn2_color', __('Second button colour', 'seeinside-travel'), 'color')
            .self::field('btn_radius', __('Corners', 'seeinside-travel'), 'number', ['min' => 0, 'max' => 999, 'unit' => 'px', 'help' => __('999 = pill', 'seeinside-travel')])
            .self::field('btn_size', __('Size', 'seeinside-travel'), 'select', $o['btn_size'])
            .self::field('btn_arrow', __('Arrow', 'seeinside-travel'), 'check', ['label' => __('Arrow icon in the buttons', 'seeinside-travel')])
            .self::field('btn2_default_label', __('Second button text when the slide has none (MK)', 'seeinside-travel'))
            .self::field('btn2_default_label_en', __('Second button text when the slide has none (EN)', 'seeinside-travel'))
            .self::field('btn2_default_link', __('Second button link when the slide has none', 'seeinside-travel'))
            .'</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Size & layout', 'seeinside-travel').'</h2><table class="form-table">'
            .self::field('height', __('Height (computer)', 'seeinside-travel'), 'text', ['help' => __('e.g. 100vh (full screen), 640px', 'seeinside-travel')])
            .self::field('height_mobile', __('Height (phone)', 'seeinside-travel'))
            .self::field('min_height', __('Minimum height', 'seeinside-travel'), 'number', ['min' => 200, 'max' => 1200, 'unit' => 'px'])
            .self::field('full_width', __('Full width', 'seeinside-travel'), 'check', ['label' => __('Stretch to the full browser width, even inside a narrower page container', 'seeinside-travel')])
            .self::field('radius', __('Rounded corners', 'seeinside-travel'), 'number', ['min' => 0, 'max' => 60, 'unit' => 'px'])
            .self::field('content_align', __('Text position', 'seeinside-travel'), 'select', $o['content_align'])
            .self::field('content_valign', __('Vertical position', 'seeinside-travel'), 'select', $o['content_valign'])
            .self::field('content_width', __('Text width', 'seeinside-travel'), 'number', ['min' => 300, 'max' => 1400, 'unit' => 'px'])
            .self::field('content_offset', __('Distance from the edge', 'seeinside-travel'), 'number', ['min' => 0, 'max' => 30, 'step' => 0.5, 'unit' => '%'])
            .'</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Photo overlay', 'seeinside-travel').'</h2><table class="form-table">'
            .self::field('overlay_color', __('Overlay colour', 'seeinside-travel'), 'color')
            .self::field('overlay_opacity', __('Overlay strength', 'seeinside-travel'), 'range', ['min' => 0, 'max' => 0.9, 'step' => 0.02])
            .self::field('overlay_gradient', __('Gradient', 'seeinside-travel'), 'check', ['label' => __('Darker on the text side, clear on the other', 'seeinside-travel')])
            .'</table></div>';

        echo '</div><p>';
        submit_button(__('Save and preview', 'seeinside-travel'), 'primary', 'submit', false);
        echo ' <button class="button" name="reset" value="1" onclick="return confirm(\''.esc_js(__('Back to the design from the video?', 'seeinside-travel')).'\')">'.esc_html__('Restore default design', 'seeinside-travel').'</button></p></form>';
        echo '<script>jQuery(function($){$(".sta-color").wpColorPicker();});</script></div>';
    }

    public static function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('403');
        }
        check_admin_referer('sta_slider_save');
        if (! empty($_POST['reset'])) {
            delete_option('sta_slider_settings');
        } else {
            $in = (array) wp_unslash($_POST['sta_slider'] ?? []);
            $out = [];
            foreach (self::defaults() as $k => $def) {
                if (in_array($k, ['overlay_gradient', 'text_shadow', 'title_upper', 'btn_arrow', 'progress', 'full_width', 'pause_hover', 'keyboard', 'ken_burns', 'effects_mobile'], true)) {
                    $out[$k] = ! empty($in[$k]) ? '1' : '';
                    continue;
                }
                $v = isset($in[$k]) ? sanitize_text_field((string) $in[$k]) : $def;
                if (is_int($def) || is_float($def)) {
                    $v = is_float($def) ? (float) $v : (int) $v;
                }
                $opts = self::options()[$k] ?? null;
                if ($opts && ! array_key_exists($v, $opts)) {
                    $v = $def;
                }
                $out[$k] = $v;
            }
            update_option('sta_slider_settings', $out, false);
        }
        wp_safe_redirect(admin_url('admin.php?page=seeinside-travel-slider&saved=1'));
        exit;
    }
}
