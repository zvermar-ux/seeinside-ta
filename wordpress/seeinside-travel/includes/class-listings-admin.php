<?php

defined('ABSPATH') || exit;

/** SeeInside Travel → Lists & cards: card designer per type, lists with live preview, archive pages */
class STA_Listings_Admin
{
    public const ARCHIVES = 'sta_archive_lists';

    public static function init(): void
    {
        add_action('admin_menu', fn () => add_submenu_page('seeinside-travel', __('Lists & cards', 'seeinside-travel'), __('Lists & cards', 'seeinside-travel'), 'manage_options', 'seeinside-travel-lists', [self::class, 'page']), 21);
        add_action('admin_post_sta_list_save', [self::class, 'save_list']);
        add_action('admin_post_sta_list_action', [self::class, 'list_action']);
        add_action('admin_post_sta_card_save', [self::class, 'save_card']);
        add_action('admin_post_sta_archives_save', [self::class, 'save_archives']);
        add_action('wp_ajax_sta_lc_preview', [self::class, 'preview']);
    }

    protected static function url(array $args = []): string
    {
        return add_query_arg($args, admin_url('admin.php?page=seeinside-travel-lists'));
    }

    protected static function two(string $name, string $label, $value, string $help = ''): string
    {
        $v = is_array($value) ? $value : ['mk' => (string) $value, 'en' => (string) $value];

        return '<tr><th>'.esc_html($label).'</th><td><div class="sta-two"><label><small>МК</small><input type="text" name="'.esc_attr($name).'[mk]" value="'.esc_attr($v['mk'] ?? '').'"></label><label><small>EN</small><input type="text" name="'.esc_attr($name).'[en]" value="'.esc_attr($v['en'] ?? '').'"></label></div>'.($help ? '<p class="description">'.esc_html($help).'</p>' : '').'</td></tr>';
    }

    protected static function select(string $name, $value, array $options): string
    {
        $o = '';
        foreach ($options as $k => $l) {
            $o .= '<option value="'.esc_attr($k).'"'.selected((string) $value, (string) $k, false).'>'.esc_html($l).'</option>';
        }

        return '<select name="'.esc_attr($name).'">'.$o.'</select>';
    }

    protected static function row(string $label, string $input, string $help = ''): string
    {
        return '<tr><th>'.esc_html($label).'</th><td>'.$input.($help ? '<p class="description">'.esc_html($help).'</p>' : '').'</td></tr>';
    }

    protected static function check(string $name, bool $on, string $label): string
    {
        return '<label class="sta-check"><input type="hidden" name="'.esc_attr($name).'" value="0"><input type="checkbox" name="'.esc_attr($name).'" value="1"'.checked($on, true, false).'> '.esc_html($label).'</label>';
    }

    protected static function num(string $name, $value, int $min, int $max, string $unit = ''): string
    {
        return '<input type="number" class="small-text" name="'.esc_attr($name).'" value="'.esc_attr($value).'" min="'.$min.'" max="'.$max.'"> '.esc_html($unit);
    }

    /* ======================== page ======================== */

    public static function page(): void
    {
        STA_Listings::enqueue();
        wp_enqueue_style('sta-admin', STA_URL.'assets/css/admin.css', [], STA_VERSION);
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('sta-lc-admin', STA_URL.'assets/js/listings-admin.js', ['jquery', 'jquery-ui-sortable'], STA_VERSION, true);
        wp_localize_script('sta-lc-admin', 'STA_LC', ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sta_lc_preview'), 'copied' => __('Copied', 'seeinside-travel'), 'confirmDelete' => __('Delete this list?', 'seeinside-travel'), 'leave' => __('Unsaved changes will be lost. Continue?', 'seeinside-travel')]);
        $bunny = 'https://fonts.bunny.net/css?family=marck-script:400&display=swap';
        wp_enqueue_style('sta-lc-font', $bunny, [], null);

        $tab = sanitize_key($_GET['tab'] ?? 'lists');
        echo '<div class="wrap sta-admin sta-lc"><h1><span class="dashicons dashicons-screenoptions"></span> '.esc_html__('Lists & cards', 'seeinside-travel').'</h1>';
        if (! empty($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Saved.', 'seeinside-travel').'</p></div>';
        }
        echo '<nav class="nav-tab-wrapper">';
        foreach (['lists' => __('Lists', 'seeinside-travel'), 'cards' => __('Card designs', 'seeinside-travel'), 'archives' => __('Archive & category pages', 'seeinside-travel')] as $k => $l) {
            echo '<a class="nav-tab'.($tab === $k ? ' nav-tab-active' : '').'" href="'.esc_url(self::url(['tab' => $k])).'">'.esc_html($l).'</a>';
        }
        echo '</nav>';

        if ($tab === 'cards') {
            self::cards_tab();
        } elseif ($tab === 'archives') {
            self::archives_tab();
        } elseif (isset($_GET['edit'])) {
            self::edit_list();
        } else {
            self::lists_tab();
        }
        echo '</div>';
    }

    /* ---------------- lists ---------------- */

    protected static function lists_tab(): void
    {
        $all = STA_Listings::all();
        echo '<p class="sta-lead">'.esc_html__('Every list can be placed anywhere with its shortcode or with the Elementor widget "Travel: list (designed)". Make as many lists as you need for each type – e.g. "Tours – Europe carousel" and "Tours – Greece grid".', 'seeinside-travel').'</p>';

        echo '<form class="sta-card sta-lc-new" method="get" action="'.esc_url(admin_url('admin.php')).'"><input type="hidden" name="page" value="seeinside-travel-lists"><input type="hidden" name="edit" value="new">'
            .'<b>'.esc_html__('New list', 'seeinside-travel').':</b> '.self::select('source', 'tour', array_map(fn ($s) => $s[0], STA_Items::sources()))
            .' '.self::select('layout', 'grid', STA_Listings::layouts()).' <button class="button button-primary">'.esc_html__('Create', 'seeinside-travel').'</button></form>';

        echo '<table class="widefat striped sta-lc-table"><thead><tr><th>'.esc_html__('Name', 'seeinside-travel').'</th><th>'.esc_html__('Shows', 'seeinside-travel').'</th><th>'.esc_html__('Layout', 'seeinside-travel').'</th><th>'.esc_html__('Shortcode', 'seeinside-travel').'</th><th></th></tr></thead><tbody>';
        foreach ($all as $id => $c) {
            $code = '[sta_listing id="'.(int) $id.'"]';
            $act = fn ($do, $label, $cls = '') => '<a class="'.$cls.'" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=sta_list_action&do='.$do.'&id='.(int) $id), 'sta_list_action')).'">'.esc_html($label).'</a>';
            echo '<tr><td><a href="'.esc_url(self::url(['edit' => $id])).'"><b>'.esc_html($c['name'] ?: '#'.$id).'</b></a></td>'
                .'<td>'.esc_html(STA_Items::label($c['source'] ?? 'tour')).'</td>'
                .'<td>'.esc_html(STA_Listings::layouts()[$c['layout'] ?? 'grid'] ?? '').'</td>'
                .'<td><code class="sta-copy" title="'.esc_attr__('Click to copy', 'seeinside-travel').'">'.esc_html($code).'</code></td>'
                .'<td class="sta-lc-actions"><a href="'.esc_url(self::url(['edit' => $id])).'">'.esc_html__('Edit', 'seeinside-travel').'</a> · '.$act('duplicate', __('Duplicate', 'seeinside-travel')).' · '.$act('delete', __('Delete', 'seeinside-travel'), 'sta-del').'</td></tr>';
        }
        if (! $all) {
            echo '<tr><td colspan="5">'.esc_html__('No lists yet.', 'seeinside-travel').'</td></tr>';
        }
        echo '</tbody></table>';

        echo '<div class="sta-card"><h2>'.esc_html__('Without saving a list', 'seeinside-travel').'</h2><p>'.esc_html__('Any list can also be written directly:', 'seeinside-travel').'</p><p><code class="sta-copy">[sta_listing source="tour" layout="carousel" limit="8" destination="grcija" title="Грција"]</code></p><p class="description">'
            .esc_html__('source: tour, accommodation, event, visa, last_minute, charter, slide, destination, group, tour_type, event_category, hotel_type · layout: grid, filters, arc, coverflow, carousel, masonry · also: columns, autoplay, orderby, featured, group, tour_type, category, hotel_type, country, eyebrow, subtitle, link, link_text, id="3" (+ overrides)', 'seeinside-travel').'</p></div>';
    }

    protected static function edit_list(): void
    {
        $id = (int) ($_GET['edit'] ?? 0);
        $c = $id ? STA_Listings::get($id) : null;
        if (! $c) {
            $source = sanitize_key($_GET['source'] ?? 'tour');
            $layout = sanitize_key($_GET['layout'] ?? 'grid');
            $c = STA_Listings::sanitize(['source' => $source, 'layout' => $layout, 'name' => STA_Items::label($source).' – '.(STA_Listings::layouts()[$layout] ?? $layout)]);
        }
        if (! empty($_GET['source']) && $id) {
            $c = STA_Listings::sanitize(['source' => sanitize_key($_GET['source'])] + $c, $c);
        }
        $src = $c['source'];
        $kind = STA_Items::kind($src);

        echo '<p><a href="'.esc_url(self::url()).'">← '.esc_html__('All lists', 'seeinside-travel').'</a></p>';
        echo '<div class="sta-lc-editor"><form class="sta-lc-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'" data-mode="list">';
        wp_nonce_field('sta_list_save');
        echo '<input type="hidden" name="action" value="sta_list_save"><input type="hidden" name="list[id]" value="'.(int) $c['id'].'">';

        echo '<div class="sta-card"><h2>'.esc_html__('List', 'seeinside-travel').'</h2><table class="form-table">'
            .self::row(__('Name', 'seeinside-travel'), '<input type="text" class="regular-text" name="list[name]" value="'.esc_attr($c['name']).'" required>', __('Only for you (in the list of lists and in Elementor).', 'seeinside-travel'))
            .self::row(__('Shows', 'seeinside-travel'), self::select('list[source]', $src, array_map(fn ($s) => $s[0], STA_Items::sources())), __('Changing it reloads the form.', 'seeinside-travel'))
            .'</table><div class="sta-lc-layouts">';
        foreach (STA_Listings::layouts() as $k => $l) {
            echo '<label class="sta-lc-layout'.($c['layout'] === $k ? ' is-on' : '').'"><input type="radio" name="list[layout]" value="'.esc_attr($k).'"'.checked($c['layout'], $k, false).'><span class="sta-lc-ico sta-lc-ico-'.esc_attr($k).'"><i></i><i></i><i></i><i></i><i></i></span><b>'.esc_html($l).'</b></label>';
        }
        echo '</div>';
        if ($c['id']) {
            echo '<p>'.esc_html__('Shortcode', 'seeinside-travel').': <code class="sta-copy">[sta_listing id="'.(int) $c['id'].'"]</code></p>';
        }
        echo '</div>';

        // heading
        echo '<details class="sta-card" open><summary><h2>'.esc_html__('Heading above the list', 'seeinside-travel').'</h2></summary><table class="form-table">'
            .self::two('list[eyebrow]', __('Small text above', 'seeinside-travel'), $c['eyebrow'])
            .self::two('list[title]', __('Title', 'seeinside-travel'), $c['title'])
            .self::two('list[highlight]', __('Highlighted word in the title', 'seeinside-travel'), $c['highlight'], __('Coloured and underlined, e.g. "патувања".', 'seeinside-travel'))
            .self::two('list[subtitle]', __('Subtitle', 'seeinside-travel'), $c['subtitle'])
            .self::two('list[link_text]', __('Button "View all"', 'seeinside-travel'), $c['link_text'])
            .self::row(__('Button link', 'seeinside-travel'), '<input type="text" class="regular-text" name="list[link_url]" value="'.esc_attr($c['link_url']).'" placeholder="/aranzmani/">')
            .self::row(__('Alignment', 'seeinside-travel'), self::select('list[heading_align]', $c['heading_align'], ['left' => __('Left', 'seeinside-travel'), 'center' => __('Centre', 'seeinside-travel')]))
            .'</table></details>';

        // content
        echo '<details class="sta-card" open><summary><h2>'.esc_html__('What to show', 'seeinside-travel').'</h2></summary><table class="form-table">';
        echo self::row(__('Number', 'seeinside-travel'), self::num('list[limit]', $c['limit'], 1, 100));
        $orders = $kind === 'term'
            ? ['date' => __('Name', 'seeinside-travel'), 'count' => __('Most offers', 'seeinside-travel'), 'random' => __('Random', 'seeinside-travel')]
            : ['date' => __('Newest', 'seeinside-travel'), 'departure' => __('Next date', 'seeinside-travel'), 'price' => __('Lowest price', 'seeinside-travel'), 'title' => __('Name', 'seeinside-travel'), 'random' => __('Random', 'seeinside-travel')];
        echo self::row(__('Order', 'seeinside-travel'), self::select('list[orderby]', $c['orderby'], $orders));
        if ($kind === 'post') {
            echo self::row(__('Only', 'seeinside-travel'), self::check('list[featured]', $c['featured'], __('featured offers', 'seeinside-travel')).'<br>'.($src !== 'last_minute' ? self::check('list[last_minute]', $c['last_minute'], __('last minute offers', 'seeinside-travel')) : ''));
            foreach (STA_Items::filter_taxonomies($src) as $tax) {
                $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
                if (is_wp_error($terms) || ! $terms) {
                    continue;
                }
                $chosen = $c['terms'][$tax] ?? [];
                $boxes = '';
                foreach ($terms as $t) {
                    $boxes .= '<label class="sta-check"><input type="checkbox" name="list[terms]['.esc_attr($tax).'][]" value="'.esc_attr($t->slug).'"'.checked(in_array($t->slug, $chosen, true), true, false).'> '.esc_html($t->name).' <small>('.(int) $t->count.')</small></label>';
                }
                echo self::row(STA_Schema::taxonomies()[$tax]['plural'], '<div class="sta-boxes">'.$boxes.'</div>', __('None ticked = all.', 'seeinside-travel'));
            }
        } elseif ($kind === 'term') {
            $tax = STA_Items::sources()[$src][2];
            if (is_taxonomy_hierarchical($tax)) {
                $parents = ['' => __('All levels', 'seeinside-travel'), '0' => __('Only main ones', 'seeinside-travel')];
                foreach (get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'parent' => 0]) as $t) {
                    $parents[$t->slug] = __('Under', 'seeinside-travel').': '.$t->name;
                }
                echo self::row(__('Which', 'seeinside-travel'), self::select('list[parent]', $c['parent'], $parents));
            }
            echo self::row(__('Empty ones', 'seeinside-travel'), self::check('list[hide_empty]', $c['hide_empty'], __('Hide categories without offers', 'seeinside-travel')));
        }
        echo '</table></details>';

        // layout options
        echo '<details class="sta-card" open><summary><h2>'.esc_html__('Layout settings', 'seeinside-travel').'</h2></summary><table class="form-table">'
            .self::row(__('Columns', 'seeinside-travel'), self::num('list[columns]', $c['columns'], 1, 6, __('computer', 'seeinside-travel')).' '.self::num('list[columns_tablet]', $c['columns_tablet'], 1, 4, __('tablet', 'seeinside-travel')).' '.self::num('list[columns_mobile]', $c['columns_mobile'], 1, 3, __('phone', 'seeinside-travel')), __('Grid, masonry and carousel.', 'seeinside-travel'))
            .self::row(__('Space between cards', 'seeinside-travel'), self::num('list[gap]', $c['gap'], 0, 80, 'px'))
            .self::row(__('Sliders', 'seeinside-travel'), self::num('list[autoplay]', $c['autoplay'], 0, 30, __('seconds per slide (0 = no autoplay)', 'seeinside-travel')).'<br>'.self::check('list[loop]', $c['loop'], __('Endless (start again after the last)', 'seeinside-travel')).'<br>'.self::check('list[arrows]', $c['arrows'], __('Arrows', 'seeinside-travel')).'<br>'.self::check('list[dots]', $c['dots'], __('Dots', 'seeinside-travel')))
            .self::row(__('More offers', 'seeinside-travel'), self::select('list[pagination]', $c['pagination'], ['none' => __('No', 'seeinside-travel'), 'more' => __('"Show more" button', 'seeinside-travel'), 'numbers' => __('Page numbers', 'seeinside-travel')]));
        if ($kind === 'post') {
            $taxes = ['' => '—'];
            foreach (STA_Items::filter_taxonomies($src) as $tax) {
                $taxes[$tax] = STA_Schema::taxonomies()[$tax]['plural'];
            }
            echo self::row(__('Filters on the side', 'seeinside-travel'), self::select('list[filter_tax]', $c['filter_tax'], $taxes).'<br>'.self::check('list[filter_all]', $c['filter_all'], __('"All offers" first', 'seeinside-travel')).'<br>'.self::check('list[filter_counts]', $c['filter_counts'], __('Number of offers and promo', 'seeinside-travel')), __('Only for the layout "Grid with filters".', 'seeinside-travel'))
                .self::two('list[filter_label]', __('Word after the number', 'seeinside-travel'), $c['filter_label'], __('e.g. "7 патувања".', 'seeinside-travel'))
                .self::two('list[filter_tip]', __('Tip under the filters', 'seeinside-travel'), $c['filter_tip'], __('e.g. "<b>Совет од експертот:</b> есента носи помали гужви…"', 'seeinside-travel'));
        }
        echo '</table></details>';

        // card
        echo '<details class="sta-card" open><summary><h2>'.esc_html__('Card', 'seeinside-travel').'</h2></summary><p>'.self::check('list[own_design]', $c['own_design'], __('This list has its own card design', 'seeinside-travel'))
            .' — <a href="'.esc_url(self::url(['tab' => 'cards', 'type' => $src])).'">'.esc_html__('or edit the design for all lists of this type', 'seeinside-travel').'</a></p>'
            .'<div class="sta-lc-own"'.($c['own_design'] ? '' : ' hidden').'>'.self::designer('list[design]', $src, STA_Listings::design_of($c)).'</div></details>';

        echo '<p class="sta-lc-bar"><button class="button button-primary button-hero">'.esc_html__('Save list', 'seeinside-travel').'</button></p></form>';
        echo '<div class="sta-lc-preview-wrap"><div class="sta-lc-preview-head"><b>'.esc_html__('Preview', 'seeinside-travel').'</b><span class="sta-lc-sizes"><button type="button" data-w="100%" class="is-on">'.esc_html__('Computer', 'seeinside-travel').'</button><button type="button" data-w="820px">'.esc_html__('Tablet', 'seeinside-travel').'</button><button type="button" data-w="390px">'.esc_html__('Phone', 'seeinside-travel').'</button></span></div><div class="sta-lc-preview" id="sta-lc-preview">'.STA_Listings::render($c).'</div></div>';
        echo '</div>';
    }

    /** Card design fields (used on the cards tab and inside a list) */
    protected static function designer(string $n, string $source, array $d): string
    {
        $skins = '';
        foreach (STA_Cards::skins() as $k => $l) {
            $skins .= '<label class="sta-lc-skin'.($d['skin'] === $k ? ' is-on' : '').'"><input type="radio" name="'.$n.'[skin]" value="'.esc_attr($k).'"'.checked($d['skin'], $k, false).'><span class="sta-lc-skin-ico is-'.esc_attr($k).'"><i></i><b></b><em></em></span><small>'.esc_html($l).'</small></label>';
        }
        $labels = STA_Cards::elements();
        $els = '';
        foreach ($d['elements'] as $e) {
            $els .= '<li data-key="'.esc_attr($e['key']).'"><span class="dashicons dashicons-menu"></span><label><input type="checkbox" name="'.$n.'[on][]" value="'.esc_attr($e['key']).'"'.checked(! empty($e['on']), true, false).'> '.esc_html($labels[$e['key']] ?? $e['key']).'</label></li>';
        }
        $color = fn ($k, $label) => '<label class="sta-color-field"><input type="color" name="'.$n.'['.$k.']" value="'.esc_attr($d[$k] ?: '#0e7490').'"'.($k === 'accent' && ! $d[$k] ? ' data-empty="1"' : '').'> '.esc_html($label).'</label>';

        return '<div class="sta-lc-skins">'.$skins.'</div>'
            .'<div class="sta-grid2"><div><h3>'.esc_html__('Elements – tick, drag to change the order', 'seeinside-travel').'</h3><ul class="sta-lc-els">'.$els.'</ul><input type="hidden" class="sta-lc-order" name="'.$n.'[order]" value="'.esc_attr(implode(',', array_column($d['elements'], 'key'))).'"><p class="description">'
            .esc_html__('Photo, flag and badges are always on the photo. Price followed by the button share one line.', 'seeinside-travel').'</p></div><div><table class="form-table">'
            .self::row(__('Photo shape', 'seeinside-travel'), self::select($n.'[ratio]', $d['ratio'], ['4/3' => '4:3', '3/2' => '3:2', '16/9' => '16:9', '1/1' => '1:1', '4/5' => '4:5', '3/4' => '3:4', '2/3' => '2:3']).' '.self::num($n.'[image_height]', $d['image_height'], 0, 600, __('px fixed height (0 = by shape)', 'seeinside-travel')))
            .self::row(__('Corners', 'seeinside-travel'), self::num($n.'[radius]', $d['radius'], 0, 40, 'px'))
            .self::row(__('Shadow', 'seeinside-travel'), self::select($n.'[shadow]', $d['shadow'], ['none' => __('None', 'seeinside-travel'), 'soft' => __('Soft', 'seeinside-travel'), 'strong' => __('Strong', 'seeinside-travel')]))
            .self::row(__('On mouse over', 'seeinside-travel'), self::select($n.'[hover]', $d['hover'], ['lift' => __('Lift', 'seeinside-travel'), 'zoom' => __('Zoom the photo', 'seeinside-travel'), 'tilt' => __('Tilt', 'seeinside-travel'), 'none' => __('Nothing', 'seeinside-travel')]))
            .self::row(__('Text alignment', 'seeinside-travel'), self::select($n.'[align]', $d['align'], ['left' => __('Left', 'seeinside-travel'), 'center' => __('Centre', 'seeinside-travel')]))
            .self::row(__('Colours', 'seeinside-travel'), $color('bg', __('Card', 'seeinside-travel')).$color('text', __('Text', 'seeinside-travel')).$color('muted', __('Small text', 'seeinside-travel')).$color('accent', __('Accent', 'seeinside-travel')))
            .self::row(__('Title', 'seeinside-travel'), self::num($n.'[title_size]', $d['title_size'], 12, 40, 'px').' '.self::num($n.'[title_lines]', $d['title_lines'], 1, 4, __('lines at most', 'seeinside-travel')))
            .self::row(__('Short description', 'seeinside-travel'), self::num($n.'[excerpt_words]', $d['excerpt_words'], 5, 60, __('words', 'seeinside-travel')))
            .self::row(__('Button look', 'seeinside-travel'), self::select($n.'[button_style]', $d['button_style'], ['outline' => __('Outline', 'seeinside-travel'), 'solid' => __('Solid', 'seeinside-travel'), 'link' => __('Text link', 'seeinside-travel'), 'arrow' => __('Round arrow', 'seeinside-travel')]))
            .self::two($n.'[button_text]', __('Button text', 'seeinside-travel'), $d['button_text'])
            .self::two($n.'[price_label]', __('Text above the price', 'seeinside-travel'), $d['price_label'])
            .self::two($n.'[count_label]', __('Word after the number of offers', 'seeinside-travel'), $d['count_label'])
            .self::row(__('Extras', 'seeinside-travel'), self::check($n.'[featured_badge]', $d['featured_badge'], __('Heart badge on featured offers', 'seeinside-travel')).'<br>'.self::check($n.'[postmark]', $d['postmark'], __('Postmark on the stamp', 'seeinside-travel')).'<br>'.self::check($n.'[more_dates]', $d['more_dates'], __('"+5" with the other dates', 'seeinside-travel')))
            .self::two($n.'[featured_text]', __('Text in the heart badge', 'seeinside-travel'), $d['featured_text'])
            .'</table></div></div>';
    }

    /* ---------------- card designs ---------------- */

    protected static function cards_tab(): void
    {
        $type = sanitize_key($_GET['type'] ?? 'tour');
        if (! isset(STA_Items::sources()[$type])) {
            $type = 'tour';
        }
        echo '<p class="sta-lead">'.esc_html__('The card design of a type is used in all its lists, archives, [sta_list] and Elementor widgets (unless a list has its own design).', 'seeinside-travel').'</p><div class="sta-lc-types">';
        foreach (STA_Items::sources() as $k => [$label]) {
            echo '<a class="'.($k === $type ? 'is-on' : '').'" href="'.esc_url(self::url(['tab' => 'cards', 'type' => $k])).'">'.esc_html($label).'</a>';
        }
        echo '</div><div class="sta-lc-editor"><form class="sta-lc-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'" data-mode="card" data-source="'.esc_attr($type).'">';
        wp_nonce_field('sta_card_save');
        echo '<input type="hidden" name="action" value="sta_card_save"><input type="hidden" name="source" value="'.esc_attr($type).'">';
        echo '<div class="sta-card"><h2>'.esc_html(sprintf(__('Card: %s', 'seeinside-travel'), STA_Items::label($type))).'</h2>'.self::designer('design', $type, STA_Cards::design($type)).'</div>';
        echo '<p class="sta-lc-bar"><button class="button button-primary button-hero">'.esc_html__('Save card design', 'seeinside-travel').'</button> <button class="button" name="reset" value="1" onclick="return confirm(\''.esc_js(__('Back to the standard design?', 'seeinside-travel')).'\')">'.esc_html__('Standard design', 'seeinside-travel').'</button></p></form>';
        echo '<div class="sta-lc-preview-wrap"><div class="sta-lc-preview-head"><b>'.esc_html__('Preview', 'seeinside-travel').'</b></div><div class="sta-lc-preview" id="sta-lc-preview">'.self::card_preview($type, STA_Cards::design($type)).'</div></div></div>';
    }

    protected static function card_preview(string $source, array $design): string
    {
        $c = STA_Listings::sanitize(['source' => $source, 'layout' => 'grid', 'limit' => 3, 'columns' => 3, 'pagination' => 'none', 'own_design' => true]);
        $c['design'] = $design;
        $res = STA_Listings::query($c);
        if (! $res['items']) {
            return '<p class="description">'.esc_html__('Nothing of this type yet – sync from SeeInside first.', 'seeinside-travel').'</p>';
        }

        return '<section class="sta sta-l sta-l--grid" style="--l-cols:3;--l-cols-t:2;--l-cols-m:1;--l-gap:24px"><div class="sta-l-items">'.STA_Listings::cards($res['items'], $c).'</div></section>';
    }

    /* ---------------- archives ---------------- */

    public static function archive_map(): array
    {
        $m = get_option(self::ARCHIVES, []);

        return is_array($m) ? $m : [];
    }

    protected static function archives_tab(): void
    {
        $map = self::archive_map();
        $lists = ['' => __('Standard grid', 'seeinside-travel')];
        foreach (STA_Listings::all() as $id => $c) {
            $lists[(string) $id] = ($c['name'] ?: '#'.$id).' ('.STA_Items::label($c['source']).')';
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="sta-card">';
        wp_nonce_field('sta_archives_save');
        echo '<input type="hidden" name="action" value="sta_archives_save"><p>'.esc_html__('Which list is shown on the automatic pages of each type and category (when the theme / Elementor has no own template). The list keeps its layout and filters; on a category page it shows only that category.', 'seeinside-travel').'</p><table class="form-table">';
        foreach (STA_Schema::TYPES as $api => $pt) {
            $link = get_post_type_archive_link($pt);
            echo self::row(STA_Items::label($api), self::select('map['.$pt.']', $map[$pt] ?? '', $lists).($link ? ' <a href="'.esc_url($link).'" target="_blank">'.esc_html__('Open', 'seeinside-travel').' ↗</a>' : ''));
        }
        foreach (STA_Schema::taxonomies() as $tax => $t) {
            if ($tax === 'sta_amenity') {
                continue;
            }
            echo self::row($t['plural'], self::select('map['.$tax.']', $map[$tax] ?? '', $lists));
        }
        echo '</table><p><button class="button button-primary">'.esc_html__('Save', 'seeinside-travel').'</button></p></form>';
    }

    /* ======================== saving ======================== */

    protected static function guard(string $nonce): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('403');
        }
        check_admin_referer($nonce);
    }

    public static function save_list(): void
    {
        self::guard('sta_list_save');
        $in = wp_unslash((array) ($_POST['list'] ?? []));
        $base = ! empty($in['id']) ? STA_Listings::get((int) $in['id']) : null;
        $c = STA_Listings::sanitize($in, $base);
        if (! isset($in['terms'])) {
            $c['terms'] = [];
        }
        if (! empty($c['own_design'])) {
            $c['design'] = STA_Cards::sanitize($c['source'], (array) ($in['design'] ?? []));
        }
        $id = STA_Listings::save($c);
        wp_safe_redirect(self::url(['edit' => $id, 'saved' => 1]));
        exit;
    }

    public static function list_action(): void
    {
        self::guard('sta_list_action');
        $id = (int) ($_GET['id'] ?? 0);
        $do = sanitize_key($_GET['do'] ?? '');
        if ($do === 'delete') {
            STA_Listings::delete($id);
        } elseif ($do === 'duplicate' && ($c = STA_Listings::get($id))) {
            $c['id'] = 0;
            $c['name'] .= ' (2)';
            $id = STA_Listings::save($c);
            wp_safe_redirect(self::url(['edit' => $id, 'saved' => 1]));
            exit;
        }
        wp_safe_redirect(self::url(['saved' => 1]));
        exit;
    }

    public static function save_card(): void
    {
        self::guard('sta_card_save');
        $source = sanitize_key($_POST['source'] ?? 'tour');
        $all = STA_Cards::all_designs();
        if (! empty($_POST['reset'])) {
            unset($all[$source]);
        } else {
            $all[$source] = STA_Cards::sanitize($source, wp_unslash((array) ($_POST['design'] ?? [])));
        }
        update_option(STA_Cards::OPTION, $all, false);
        wp_safe_redirect(self::url(['tab' => 'cards', 'type' => $source, 'saved' => 1]));
        exit;
    }

    public static function save_archives(): void
    {
        self::guard('sta_archives_save');
        $map = array_filter(array_map('intval', (array) ($_POST['map'] ?? [])));
        update_option(self::ARCHIVES, $map, false);
        wp_safe_redirect(self::url(['tab' => 'archives', 'saved' => 1]));
        exit;
    }

    /** Live preview while editing */
    public static function preview(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('403', 403);
        }
        check_ajax_referer('sta_lc_preview');
        if (($_POST['mode'] ?? '') === 'card') {
            $source = sanitize_key($_POST['source'] ?? 'tour');
            wp_send_json_success(['html' => self::card_preview($source, STA_Cards::sanitize($source, wp_unslash((array) ($_POST['design'] ?? []))))]);
        }
        $in = wp_unslash((array) ($_POST['list'] ?? []));
        if (! isset($in['terms'])) {
            $in['terms'] = [];
        }
        $c = STA_Listings::sanitize($in);
        if ($c['own_design']) {
            $c['design'] = STA_Cards::sanitize($c['source'], (array) ($in['design'] ?? []));
        }
        $c['id'] = 0;   // the preview must use the edited (not the saved) settings
        wp_send_json_success(['html' => STA_Listings::render($c)]);
    }
}
