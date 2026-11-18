<?php

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/** Elementor widgets – each one shows a part of the synced data (works in Theme Builder templates and pages) */
abstract class STA_Elementor_Widget extends Widget_Base
{
    public function get_categories(): array
    {
        return ['seeinside-travel'];
    }

    public function get_keywords(): array
    {
        return ['seeinside', 'travel', 'tour', 'hotel', 'aries'];
    }

    public function get_style_depends(): array
    {
        return ['sta'];
    }

    public function get_script_depends(): array
    {
        return ['sta'];
    }

    /** In the editor the template itself is the "post": show the newest synced item of the right type */
    protected function preview_post_id(): ?int
    {
        $id = get_the_ID();
        if ($id && in_array(get_post_type($id), STA_Schema::TYPES, true)) {
            return $id;
        }
        $type = 'sta_tour';
        foreach ((array) get_post_meta($id, '_elementor_conditions', true) as $c) {
            if (preg_match('#singular/(sta_[a-z]+)#', (string) $c, $m)) {
                $type = $m[1];
            }
        }
        $latest = get_posts(['post_type' => $type, 'posts_per_page' => 1, 'fields' => 'ids']);

        return $latest ? (int) $latest[0] : null;
    }

    protected function is_editor(): bool
    {
        return class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode());
    }
}

class STA_Widget_Section extends STA_Elementor_Widget
{
    protected static array $sectionsIn = [];

    /** Sections shown by the template being rendered ('reviews' also for the reviews widget, 'layout' = everything) */
    protected static function doc_sections(): array
    {
        try {
            $doc = \Elementor\Plugin::$instance->documents->get_current();
            if (! $doc) {
                return [];
            }
            $id = (int) $doc->get_main_id();
            if (isset(self::$sectionsIn[$id])) {
                return self::$sectionsIn[$id];
            }
            $found = [];
            $walk = function (array $els) use (&$walk, &$found) {
                foreach ($els as $e) {
                    $w = $e['widgetType'] ?? '';
                    if ($w === 'sta-reviews') {
                        $found['reviews'] = true;
                    } elseif ($w === 'sta-section') {
                        $found[($e['settings']['section'] ?? '') ?: 'facts'] = true;
                    }
                    if (! empty($e['elements'])) {
                        $walk($e['elements']);
                    }
                }
            };
            $walk((array) $doc->get_elements_data());

            return self::$sectionsIn[$id] = $found;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function doc_has(string $section): bool
    {
        $in = self::doc_sections();

        return isset($in[$section]) || isset($in['layout']);
    }

    protected static function doc_has_reviews(): bool
    {
        return self::doc_has('reviews');
    }

    public function get_name(): string { return 'sta-section'; }
    public function get_title(): string { return __('Travel: section', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-post-content'; }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Content', 'seeinside-travel')]);
        $this->add_control('section', [
            'label' => __('What to show', 'seeinside-travel'),
            'type' => Controls_Manager::SELECT,
            'default' => 'facts',
            'options' => array_map(fn ($l) => __($l, 'seeinside-travel'), STA_Render::SECTIONS),
        ]);
        $this->add_control('heading', ['label' => __('Own title (empty = default)', 'seeinside-travel'), 'type' => Controls_Manager::TEXT]);
        $this->add_control('hide_heading', ['label' => __('Hide the title', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER]);
        $this->add_control('g_layout', ['label' => __('Gallery look', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'separator' => 'before', 'condition' => ['section' => 'gallery'], 'options' => [
            '' => __('As in Settings → Display', 'seeinside-travel'),
            'mosaic' => __('Mosaic – big photo + 4 small', 'seeinside-travel'),
            'mosaic3' => __('Mosaic – big photo + 2 small', 'seeinside-travel'),
            'slider' => __('Slider – one photo with arrows and thumbnails', 'seeinside-travel'),
            'grid' => __('Grid – all photos the same size', 'seeinside-travel'),
        ]]);
        $this->add_control('g_height', ['label' => __('Height (computer)', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 150, 'max' => 900, 'step' => 10]], 'condition' => ['section' => 'gallery']]);
        $this->add_control('g_height_m', ['label' => __('Height (phone)', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 150, 'max' => 700, 'step' => 10]], 'condition' => ['section' => 'gallery']]);
        $this->add_control('g_gap', ['label' => __('Space between photos', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 30]], 'condition' => ['section' => 'gallery'], 'selectors' => ['{{WRAPPER}} .sta-gallery' => '--g-gap: {{SIZE}}px']]);
        $this->end_controls_section();

        $this->start_controls_section('style', ['label' => __('Colours', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('primary', ['label' => __('Main colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta' => '--sta-primary: {{VALUE}}']]);
        $this->add_control('accent', ['label' => __('Accent (badges)', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta' => '--sta-accent: {{VALUE}}']]);
        $this->add_control('radius', ['label' => __('Corner radius', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 32]], 'selectors' => ['{{WRAPPER}} .sta' => '--sta-radius: {{SIZE}}px']]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $postId = $this->preview_post_id();
        if (! $postId) {
            if ($this->is_editor()) {
                echo '<div class="sta-empty">'.esc_html__('Nothing synced yet – run the import in SeeInside Travel → Sync.', 'seeinside-travel').'</div>';
            }

            return;
        }
        $args = [];
        if (! empty($s['hide_heading'])) {
            $args['title'] = false;
        } elseif (! empty($s['heading'])) {
            $args['title'] = $s['heading'];
        }
        if (($s['section'] ?? '') === 'gallery') {
            if (! empty($s['g_layout'])) {
                $args['layout'] = $s['g_layout'];
            }
            if (! empty($s['g_height']['size'])) {
                $args['height'] = (int) $s['g_height']['size'];
            }
            if (! empty($s['g_height_m']['size'])) {
                $args['height_mobile'] = (int) $s['g_height_m']['size'];
            }
        }
        $html = STA_Render::section($s['section'] ?: 'facts', $postId, $args);
        if (! empty($args['title']) && is_string($args['title'])) {
            // replace the built-in heading by the custom one
            $html = preg_replace('#<h3 class="sta-h">.*?</h3>#s', '', $html, 1);
        } elseif (isset($args['title']) && $args['title'] === false) {
            $html = preg_replace('#<h3 class="sta-h">.*?</h3>#s', '', $html, 1);
        }
        // templates made before a section existed: new sections are added next to a related one
        $name = $s['section'] ?? '';
        if (apply_filters('sta_auto_sections', true, $postId) && $name !== 'layout') {
            $before = '';
            if ($name === 'program' && ! self::doc_has('route_map')) {
                $html .= STA_Render::section('route_map', $postId);
            }
            $roomsAnchor = self::doc_has('availability') ? 'availability' : 'rooms';
            if ($name === $roomsAnchor && ! self::doc_has('terms')) {
                $html .= STA_Render::section('terms', $postId);
            }
            if ($name === 'booking') {
                if (! self::doc_has('program') && ! self::doc_has('route_map')) {
                    $before .= STA_Render::section('route_map', $postId);
                }
                if (! self::doc_has('rooms') && ! self::doc_has('availability') && ! self::doc_has('terms')) {
                    $before .= STA_Render::section('terms', $postId);
                }
                if (! self::doc_has('galleries')) {
                    $before .= STA_Render::section('galleries', $postId);
                }
                $html = $before.$html;
                if (! self::doc_has_reviews() && apply_filters('sta_auto_reviews', true, $postId)) {
                    $html .= STA_Render::section('reviews', $postId);
                }
            }
        }
        if ($html === '' && $this->is_editor()) {
            $html = '<div class="sta-empty">'.esc_html(sprintf(__('"%s" is empty for this item.', 'seeinside-travel'), __(STA_Render::SECTIONS[$s['section']] ?? '', 'seeinside-travel'))).'</div>';
        }
        echo $html; // phpcs:ignore -- escaped in STA_Render
    }
}

class STA_Widget_List extends STA_Elementor_Widget
{
    public function get_name(): string { return 'sta-list'; }
    public function get_title(): string { return __('Travel: offer list', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-posts-grid'; }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Content', 'seeinside-travel')]);
        $this->add_control('query', ['label' => __('Source', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'custom', 'options' => [
            'custom' => __('Choose below', 'seeinside-travel'),
            'current' => __('Current archive (in archive templates)', 'seeinside-travel'),
        ]]);
        $this->add_control('type', ['label' => __('Type', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'tour', 'condition' => ['query' => 'custom'], 'options' => [
            'tour' => __('Organized tours', 'seeinside-travel'), 'hotel' => __('Apartments', 'seeinside-travel'),
            'event' => __('Events', 'seeinside-travel'), 'visa' => __('Visas', 'seeinside-travel'), 'custom' => __('Other offers', 'seeinside-travel'), 'all' => __('Everything', 'seeinside-travel'),
        ]]);
        $terms = get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => false]);
        $this->add_control('destination', ['label' => __('Destination', 'seeinside-travel'), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'condition' => ['query' => 'custom'],
            'options' => is_wp_error($terms) ? [] : wp_list_pluck($terms, 'name', 'slug')]);
        $this->add_control('last_minute', ['label' => __('Only last minute', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'condition' => ['query' => 'custom']]);
        $this->add_control('featured', ['label' => __('Only featured', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'condition' => ['query' => 'custom']]);
        $this->add_control('orderby', ['label' => __('Order', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'date', 'condition' => ['query' => 'custom'], 'options' => [
            'date' => __('Newest', 'seeinside-travel'), 'departure' => __('Next date', 'seeinside-travel'), 'price' => __('Lowest price', 'seeinside-travel'),
            'title' => __('Name', 'seeinside-travel'), 'random' => __('Random', 'seeinside-travel'),
        ]]);
        $this->add_control('limit', ['label' => __('Number', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 6, 'condition' => ['query' => 'custom']]);
        $this->add_control('columns', ['label' => __('Columns', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 3, 'min' => 1, 'max' => 6]);
        $this->add_control('search', ['label' => __('Use the search form filters', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'condition' => ['query' => 'custom']]);
        $this->add_control('pagination', ['label' => __('Pagination', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER]);
        $this->end_controls_section();

        $this->start_controls_section('style', ['label' => __('Colours', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('primary', ['label' => __('Main colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta' => '--sta-primary: {{VALUE}}']]);
        $this->add_control('radius', ['label' => __('Corner radius', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 32]], 'selectors' => ['{{WRAPPER}} .sta' => '--sta-radius: {{SIZE}}px']]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $s = $this->get_settings_for_display();
        $html = STA_Frontend::sc_list([
            'query' => $s['query'] ?? 'custom',
            'type' => $s['type'] ?? 'tour',
            'destination' => implode(',', (array) ($s['destination'] ?? [])),
            'last_minute' => ($s['last_minute'] ?? '') === 'yes' ? '1' : '',
            'featured' => ($s['featured'] ?? '') === 'yes' ? '1' : '',
            'orderby' => $s['orderby'] ?? 'date',
            'limit' => (int) ($s['limit'] ?? 6) ?: 6,
            'columns' => (int) ($s['columns'] ?? 3) ?: 3,
            'search' => ($s['search'] ?? '') === 'yes' ? '1' : '',
            'pagination' => ($s['pagination'] ?? '') === 'yes' ? '1' : '',
        ]);
        echo $html; // phpcs:ignore
        if (($s['query'] ?? '') === 'current' && ($s['pagination'] ?? '') === 'yes') {
            echo '<nav class="sta sta-pagination">'.paginate_links().'</nav>'; // phpcs:ignore
        }
    }
}

/** Simple widgets around one shortcode */
abstract class STA_Simple_Widget extends STA_Elementor_Widget
{
    abstract protected function html(array $s): string;

    protected function extra_controls(): void {}

    protected function register_controls(): void
    {
        $this->start_controls_section('content', ['label' => __('Content', 'seeinside-travel')]);
        $this->add_control('note', ['type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__('Data comes from SeeInside Travel automatically.', 'seeinside-travel')]);
        $this->extra_controls();
        $this->end_controls_section();
        $this->start_controls_section('style', ['label' => __('Colours', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('primary', ['label' => __('Main colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta' => '--sta-primary: {{VALUE}}']]);
        $this->end_controls_section();
        $this->style_controls();
    }

    /** More design settings of a widget (Style tab) */
    protected function style_controls(): void {}

    protected function render(): void
    {
        $html = $this->html($this->get_settings_for_display());
        if ($html === '' && $this->is_editor()) {
            $html = '<div class="sta-empty">'.esc_html($this->get_title()).': '.esc_html__('no data yet', 'seeinside-travel').'</div>';
        }
        echo $html; // phpcs:ignore
    }
}

class STA_Widget_Slider extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-slider'; }
    public function get_title(): string { return __('Travel: homepage slider', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-slider-push'; }
    protected function extra_controls(): void
    {
        $this->add_control('note', ['type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__('Design, effects and fonts are set in SeeInside Travel → Slider. Fields left empty use those settings.', 'seeinside-travel'), 'content_classes' => 'elementor-descriptor']);
        $this->add_control('height', ['label' => __('Height (e.g. 100vh or 640px)', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'default' => '']);
        $this->add_control('autoplay', ['label' => __('Change every (seconds, 0 = off)', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => '']);
        $o = STA_Slider::options();
        $this->add_control('mouse_effect', ['label' => __('Mouse effect', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'options' => ['' => __('From settings', 'seeinside-travel')] + $o['mouse_effect'], 'default' => '']);
        $this->add_control('transition', ['label' => __('Transition', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'options' => ['' => __('From settings', 'seeinside-travel')] + $o['transition'], 'default' => '']);
        $this->add_control('arrows', ['label' => __('Arrows', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'options' => ['' => __('From settings', 'seeinside-travel')] + $o['arrows'], 'default' => '']);
    }
    protected function html(array $s): string
    {
        return STA_Slider::render(array_intersect_key($s, array_flip(['height', 'autoplay', 'mouse_effect', 'transition', 'arrows'])));
    }
}

class STA_Widget_Listing extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-listing'; }
    public function get_title(): string { return __('Travel: list (designed)', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-posts-carousel'; }
    public function get_script_depends(): array { return ['sta-listings']; }
    public function get_style_depends(): array { return ['sta-listings']; }
    protected function extra_controls(): void
    {
        $opts = ['' => __('– choose –', 'seeinside-travel')];
        foreach (STA_Listings::all() as $id => $c) {
            $opts[(string) $id] = ($c['name'] ?? '#'.$id).' · '.(STA_Listings::layouts()[$c['layout'] ?? 'grid'] ?? '');
        }
        $this->add_control('note', ['type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__('Lists and card designs are made in SeeInside Travel → Lists & cards. Fields below are optional overrides.', 'seeinside-travel'), 'content_classes' => 'elementor-descriptor']);
        $this->add_control('list', ['label' => __('List', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'options' => $opts, 'default' => (string) (array_key_first(STA_Listings::all()) ?? '')]);
        $this->add_control('layout', ['label' => __('Layout', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'options' => ['' => __('From the list', 'seeinside-travel')] + STA_Listings::layouts(), 'default' => '']);
        $this->add_control('limit', ['label' => __('Number', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => '']);
        $this->add_control('columns', ['label' => __('Columns', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => '', 'min' => 1, 'max' => 6]);
        $this->add_control('current', ['label' => __('On a category page: show that category', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => '']);
    }
    protected function html(array $s): string
    {
        if (empty($s['list'])) {
            return '<div class="sta-empty">'.esc_html__('Choose a list.', 'seeinside-travel').'</div>';
        }

        return STA_Listings::shortcode(array_filter(['id' => $s['list'], 'layout' => $s['layout'] ?? '', 'limit' => $s['limit'] ?? '', 'columns' => $s['columns'] ?? '', 'current' => ! empty($s['current']) ? '1' : ''], fn ($v) => $v !== '' && $v !== null));
    }
}

class STA_Widget_Reviews extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-reviews'; }
    public function get_title(): string { return __('Travel: reviews', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-review'; }
    protected function extra_controls(): void
    {
        $this->add_control('rtype', ['label' => __('Which reviews', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => [
            '' => __('All (on an offer page: that offer)', 'seeinside-travel'), 'agency' => __('About the agency', 'seeinside-travel'),
            'tour' => __('Tours', 'seeinside-travel'), 'event' => __('Events', 'seeinside-travel'), 'accommodation' => __('Hotels', 'seeinside-travel'), 'visa' => __('Visas', 'seeinside-travel'),
        ]]);
        $this->add_control('layout', ['label' => __('Layout', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'grid', 'options' => [
            'testimonials' => __('Testimonials carousel (centred)', 'seeinside-travel'), 'grid' => __('Grid', 'seeinside-travel'), 'slider' => __('Carousel', 'seeinside-travel'), 'list' => __('List', 'seeinside-travel')]]);
        $this->add_control('subtitle', ['label' => __('Small title above', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'default' => '']);
        $this->add_control('heading', ['label' => __('Title', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'default' => '']);
        $this->add_control('per_view', ['label' => __('Cards visible', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '3', 'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'], 'condition' => ['layout' => 'testimonials']]);
        $this->add_control('autoplay', ['label' => __('Autoplay (seconds, 0 = off)', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 6, 'condition' => ['layout' => 'testimonials']]);
        $this->add_control('role', ['label' => __('Text under the name (when the review has no offer)', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'default' => __('Traveller', 'seeinside-travel'), 'condition' => ['layout' => 'testimonials']]);
        $this->add_control('min_rating', ['label' => __('Minimum rating', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '0', 'options' => ['0' => __('All', 'seeinside-travel'), '4' => '4★+', '5' => '5★']]);
        $this->add_control('limit', ['label' => __('Number of reviews', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 6]);
        $this->add_control('featured', ['label' => __('Only featured', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => '']);
        $this->add_control('form', ['label' => __('Form for a new review', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => '']);
    }
    protected function html(array $s): string
    {
        return STA_Reviews::sc_reviews(['type' => $s['rtype'] ?? '', 'layout' => $s['layout'] ?? 'grid', 'limit' => $s['limit'] ?? 6, 'featured' => ! empty($s['featured']) ? 'yes' : 'no', 'form' => ! empty($s['form']) ? 'yes' : 'no',
            'title' => $s['heading'] ?? '', 'subtitle' => $s['subtitle'] ?? '', 'per_view' => $s['per_view'] ?? 3, 'autoplay' => $s['autoplay'] ?? 6, 'role' => $s['role'] ?? '', 'min_rating' => $s['min_rating'] ?? 0]);
    }
}

class STA_Widget_Search extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-search'; }
    public function get_title(): string { return __('Travel: search', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-search'; }
    protected function extra_controls(): void
    {
        $this->add_control('types', ['label' => __('Tabs', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'default' => 'tour,accommodation,event', 'description' => 'tour, accommodation, event, visa']);
    }
    protected function html(array $s): string { return STA_Frontend::sc_search(['types' => $s['types'] ?? 'tour,accommodation,event']); }
}

class STA_Widget_Flights extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-flights'; }
    public function get_title(): string { return __('Travel: flight search', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-flip-box'; }
    protected function html(array $s): string { return STA_Frontend::sc_flights([]); }
}

class STA_Widget_Charters extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-charters'; }
    public function get_title(): string { return __('Travel: charter seats', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-table'; }
    protected function html(array $s): string { return STA_Frontend::sc_charters([]); }
}

class STA_Widget_Status extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-status'; }
    public function get_title(): string { return __('Travel: check booking', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-search-results'; }
    protected function html(array $s): string { return STA_Frontend::sc_status([]); }
}

class STA_Widget_Destinations extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-destinations'; }
    public function get_title(): string { return __('Travel: destinations', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-gallery-grid'; }
    protected function extra_controls(): void
    {
        $this->add_control('taxonomy', ['label' => __('Show', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'sta_destination', 'options' => ['sta_destination' => __('Destinations', 'seeinside-travel'), 'sta_group' => __('Groups', 'seeinside-travel')]]);
        $this->add_control('limit', ['label' => __('Number', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 8]);
        $this->add_control('columns', ['label' => __('Columns', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 4]);
    }
    protected function html(array $s): string { return STA_Frontend::sc_destinations(['taxonomy' => $s['taxonomy'] ?? 'sta_destination', 'limit' => $s['limit'] ?? 8, 'columns' => $s['columns'] ?? 4]); }
}

class STA_Widget_Search_Box extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-search-box'; }
    public function get_title(): string { return __('Travel: search box (tabs)', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-search'; }
    protected function extra_controls(): void
    {
        $this->add_control('tabs', ['label' => __('Tabs', 'seeinside-travel'), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'default' => ['apartments', 'hotels', 'tours', 'flights'], 'options' => [
            'apartments' => __('Apartments', 'seeinside-travel'), 'hotels' => __('Hotels', 'seeinside-travel'), 'tours' => __('Tours', 'seeinside-travel'), 'flights' => __('Flights', 'seeinside-travel'),
        ]]);
        $this->add_control('active', ['label' => __('Open tab', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => [
            '' => __('First', 'seeinside-travel'), 'apartments' => __('Apartments', 'seeinside-travel'), 'hotels' => __('Hotels', 'seeinside-travel'), 'tours' => __('Tours', 'seeinside-travel'), 'flights' => __('Flights', 'seeinside-travel'),
        ]]);
        $this->add_control('title', ['label' => __('Title', 'seeinside-travel'), 'type' => Controls_Manager::TEXT]);
        $this->add_control('subtitle', ['label' => __('Subtitle', 'seeinside-travel'), 'type' => Controls_Manager::TEXT]);
        $this->add_control('tours_label', ['label' => __('Tours tab name', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'placeholder' => __('Tours', 'seeinside-travel')]);
    }
    protected function html(array $s): string
    {
        return STA_Search_Box::shortcode(['tabs' => implode(',', (array) ($s['tabs'] ?: ['apartments', 'hotels', 'tours', 'flights'])), 'active' => $s['active'] ?? '', 'title' => $s['title'] ?? '', 'subtitle' => $s['subtitle'] ?? '', 'tours_label' => $s['tours_label'] ?? '']);
    }
}

class STA_Widget_Term_Header extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-term-header'; }
    public function get_title(): string { return __('Travel: page header (destination, place, group, type)', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-header'; }
    protected function extra_controls(): void
    {
        $this->add_control('page_type', ['label' => __('This page shows', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => ['' => __('Destination / place / group / type of this archive', 'seeinside-travel'), 'tour' => __('All organized tours', 'seeinside-travel'), 'accommodation' => __('All apartments', 'seeinside-travel'), 'hotels' => __('Hotels (booking system)', 'seeinside-travel'), 'event' => __('All events', 'seeinside-travel')]]);
        $this->add_control('preview_tax', ['label' => __('Preview in the editor with', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'sta_destination', 'options' => sta_term_page_taxonomies(), 'condition' => ['page_type' => '']]);
        $this->add_control('page_title', ['label' => __('Title', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'placeholder' => __('The page title', 'seeinside-travel'), 'condition' => ['page_type!' => '']]);
        $this->add_control('page_label', ['label' => __('Badge text', 'seeinside-travel'), 'type' => Controls_Manager::TEXT, 'condition' => ['page_type!' => '']]);
        $this->add_control('height', ['label' => __('Height', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 340]);
        // background photo: the one of the destination/place/group from the app, or this one
        $this->add_control('bg_image', ['label' => __('Background photo', 'seeinside-travel'), 'type' => Controls_Manager::MEDIA, 'separator' => 'before']);
        $this->add_control('bg_mode', ['label' => __('Use this photo', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'fallback', 'options' => [
            'fallback' => __('Only when the page has no photo in the app', 'seeinside-travel'),
            'always' => __('Always (instead of the photo from the app)', 'seeinside-travel'),
        ]]);
    }
    protected function html(array $s): string { return STA_Term_Page::header(['page_type' => $s['page_type'] ?? '', 'title' => $s['page_title'] ?? '', 'label' => $s['page_label'] ?? '', 'preview_tax' => $s['preview_tax'] ?? '', 'height' => $s['height'] ?? 340, 'image' => $s['bg_image']['url'] ?? '', 'image_mode' => $s['bg_mode'] ?? 'fallback', 'kind' => ($s['show_kind'] ?? 'yes') === 'yes' ? '1' : '', 'crumbs' => ($s['show_crumbs'] ?? 'yes') === 'yes' ? '1' : '', 'count' => ($s['show_count'] ?? 'yes') === 'yes' ? '1' : '', 'desc' => ($s['show_desc'] ?? 'yes') === 'yes' ? '1' : '']); }

    protected function style_controls(): void
    {
        $w = '{{WRAPPER}} .sta-term-head';
        // the box: size, background, photo darkening, position of the text
        $this->start_controls_section('th_box', ['label' => __('Header box', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('th_height', ['label' => __('Height', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', 'vh'], 'range' => ['px' => ['min' => 120, 'max' => 900], 'vh' => ['min' => 10, 'max' => 100]],
            'selectors' => [$w => 'min-height: {{SIZE}}{{UNIT}} !important;']]);
        $this->add_control('th_c1', ['label' => __('Background colour 1', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$w => '--th-c1: {{VALUE}};']]);
        $this->add_control('th_c2', ['label' => __('Background colour 2', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$w => '--th-c2: {{VALUE}};']]);
        $this->add_control('th_angle', ['label' => __('Gradient angle', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['deg'], 'range' => ['deg' => ['min' => 0, 'max' => 360]], 'selectors' => [$w => '--th-angle: {{SIZE}}deg;']]);
        $this->add_control('th_overlay', ['label' => __('Darkening over the photo', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'description' => __('Colour with transparency, from the bottom up.', 'seeinside-travel'),
            'selectors' => [$w => '--th-overlay: linear-gradient(180deg, transparent 20%, {{VALUE}});']]);
        $this->add_control('th_overlay_full', ['label' => __('Even darkening (whole photo)', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$w => '--th-overlay-full: {{VALUE}};']]);
        $this->add_responsive_control('th_align', ['label' => __('Text alignment', 'seeinside-travel'), 'type' => Controls_Manager::CHOOSE, 'options' => [
            'left' => ['title' => __('Left', 'seeinside-travel'), 'icon' => 'eicon-text-align-left'], 'center' => ['title' => __('Centre', 'seeinside-travel'), 'icon' => 'eicon-text-align-center'], 'right' => ['title' => __('Right', 'seeinside-travel'), 'icon' => 'eicon-text-align-right']],
            'selectors_dictionary' => ['left' => 'justify-content:flex-start;text-align:left;', 'center' => 'justify-content:center;text-align:center;', 'right' => 'justify-content:flex-end;text-align:right;'],
            'selectors' => [$w => '{{VALUE}}']]);
        $this->add_responsive_control('th_valign', ['label' => __('Vertical position', 'seeinside-travel'), 'type' => Controls_Manager::CHOOSE, 'options' => [
            'flex-start' => ['title' => __('Top', 'seeinside-travel'), 'icon' => 'eicon-v-align-top'], 'center' => ['title' => __('Middle', 'seeinside-travel'), 'icon' => 'eicon-v-align-middle'], 'flex-end' => ['title' => __('Bottom', 'seeinside-travel'), 'icon' => 'eicon-v-align-bottom']],
            'selectors' => [$w => 'align-items: {{VALUE}};']]);
        $this->add_responsive_control('th_width', ['label' => __('Text width', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px', '%'], 'range' => ['px' => ['min' => 200, 'max' => 1400], '%' => ['min' => 20, 'max' => 100]],
            'selectors' => ['{{WRAPPER}} .sta-term-head-in' => 'max-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('th_padding', ['label' => __('Inner space', 'seeinside-travel'), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'],
            'selectors' => [$w => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('th_radius', ['label' => __('Corner radius', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => [$w => 'border-radius: {{SIZE}}px;']]);
        $this->add_responsive_control('th_margin', ['label' => __('Space below', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 120]], 'selectors' => [$w => 'margin-bottom: {{SIZE}}px;']]);
        $this->end_controls_section();

        // the title
        $this->start_controls_section('th_title', ['label' => __('Title', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('th_title_color', ['label' => __('Colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-term-head h1' => 'color: {{VALUE}} !important;']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'th_title_typo', 'selector' => '{{WRAPPER}} .sta-term-head h1']);
        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name' => 'th_title_shadow', 'selector' => '{{WRAPPER}} .sta-term-head h1']);
        $this->end_controls_section();

        // the badge (type: destination, place…), the path and the number of offers
        $this->start_controls_section('th_kind', ['label' => __('Badge, path and number of offers', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('show_kind', ['label' => __('Show the badge (e.g. "Accommodation type")', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('th_kind_bg', ['label' => __('Badge background', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'condition' => ['show_kind' => 'yes'], 'selectors' => ['{{WRAPPER}} .sta-term-kind' => 'background: {{VALUE}};']]);
        $this->add_control('th_kind_color', ['label' => __('Badge text', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'condition' => ['show_kind' => 'yes'], 'selectors' => ['{{WRAPPER}} .sta-term-kind' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'th_kind_typo', 'label' => __('Badge font', 'seeinside-travel'), 'condition' => ['show_kind' => 'yes'], 'selector' => '{{WRAPPER}} .sta-term-kind']);
        $this->add_control('show_crumbs', ['label' => __('Show the path (Greece › Lefkada)', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'separator' => 'before']);
        $this->add_control('show_count', ['label' => __('Show the number of offers', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('th_meta_color', ['label' => __('Colour of the path and number', 'seeinside-travel'), 'type' => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .sta-term-count, {{WRAPPER}} .sta-term-crumbs, {{WRAPPER}} .sta-term-crumbs a' => 'color: {{VALUE}} !important;']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'th_meta_typo', 'label' => __('Font of the path and number', 'seeinside-travel'), 'selector' => '{{WRAPPER}} .sta-term-count, {{WRAPPER}} .sta-term-crumbs']);
        $this->end_controls_section();

        // the description under the header
        $this->start_controls_section('th_desc', ['label' => __('Description under the header', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('show_desc', ['label' => __('Show the description', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('th_desc_color', ['label' => __('Colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'condition' => ['show_desc' => 'yes'], 'selectors' => ['{{WRAPPER}} .sta-term-desc' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'th_desc_typo', 'condition' => ['show_desc' => 'yes'], 'selector' => '{{WRAPPER}} .sta-term-desc']);
        $this->end_controls_section();
    }
}

class STA_Widget_Term_Offers extends STA_Simple_Widget
{
    public function get_name(): string { return 'sta-term-offers'; }
    public function get_title(): string { return __('Travel: offers of this page', 'seeinside-travel'); }
    public function get_icon(): string { return 'eicon-posts-grid'; }
    protected function extra_controls(): void
    {
        $this->add_control('page_type', ['label' => __('This page shows', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => ['' => __('Destination / place / group / type of this archive', 'seeinside-travel'), 'tour' => __('All organized tours', 'seeinside-travel'), 'accommodation' => __('All apartments', 'seeinside-travel'), 'hotels' => __('Hotels (booking system)', 'seeinside-travel'), 'event' => __('All events', 'seeinside-travel')]]);
        $this->add_control('preview_tax', ['label' => __('Preview in the editor with', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'sta_destination', 'options' => sta_term_page_taxonomies(), 'condition' => ['page_type' => '']]);
        $this->add_control('types', ['label' => __('Show', 'seeinside-travel'), 'condition' => ['page_type' => ''], 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'default' => ['tour', 'accommodation', 'event'], 'options' => [
            'tour' => __('Organized tours', 'seeinside-travel'), 'accommodation' => __('Accommodation (with filters)', 'seeinside-travel'), 'event' => __('Events', 'seeinside-travel'),
        ]]);
        $this->add_control('children', ['label' => __('Sub-destinations / places as tiles', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('columns', ['label' => __('Columns', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 3]);
        // section titles ("Apartments", "Organized tours"…)
        $this->add_control('show_titles', ['label' => __('Show the section titles', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'separator' => 'before',
            'description' => __('E.g. "Apartments" above the list. Empty fields below = the usual names.', 'seeinside-travel')]);
        foreach (['title_accommodation' => __('Title for accommodation', 'seeinside-travel'), 'title_tour' => __('Title for tours', 'seeinside-travel'), 'title_event' => __('Title for events', 'seeinside-travel'), 'title_children' => __('Title for the places / sub-destinations', 'seeinside-travel')] as $k => $l) {
            $this->add_control($k, ['label' => $l, 'type' => Controls_Manager::TEXT, 'condition' => ['show_titles' => 'yes']]);
        }
        $this->add_control('hide_filters', ['label' => __('Hide the filters on the left', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'separator' => 'before', 'return_value' => 'yes',
            'selectors' => ['{{WRAPPER}} .sta-finder-side, {{WRAPPER}} .sta-finder-toggle' => 'display: none !important;', '{{WRAPPER}} .sta-finder-wrap' => 'grid-template-columns: 1fr !important;']]);
        $this->add_control('hide_sort', ['label' => __('Hide the number and the sorting', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes',
            'selectors' => ['{{WRAPPER}} .sta-finder-head' => 'display: none !important;']]);
        $this->add_control('hide_tags', ['label' => __('Hide the labels under the cards (type, beach)', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes',
            'selectors' => ['{{WRAPPER}} .sta-finder-tags' => 'display: none !important;']]);
    }
    protected function html(array $s): string
    {
        return STA_Term_Page::offers(['page_type' => $s['page_type'] ?? '', 'preview_tax' => $s['preview_tax'] ?? '', 'types' => implode(',', (array) ($s['types'] ?: ['tour', 'accommodation', 'event'])), 'children' => ($s['children'] ?? '') === 'yes' ? '1' : '', 'columns' => $s['columns'] ?? 3,
            'titles' => ($s['show_titles'] ?? 'yes') === 'yes' ? '1' : '', 'title_accommodation' => $s['title_accommodation'] ?? '', 'title_tour' => $s['title_tour'] ?? '', 'title_event' => $s['title_event'] ?? '', 'title_children' => $s['title_children'] ?? '']);
    }

    protected function style_controls(): void
    {
        // section titles
        $this->start_controls_section('of_titles', ['label' => __('Section titles', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['show_titles' => 'yes']]);
        $this->add_control('of_title_color', ['label' => __('Colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-term-sec > .sta-h' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'of_title_typo', 'selector' => '{{WRAPPER}} .sta-term-sec > .sta-h']);
        $this->add_responsive_control('of_title_align', ['label' => __('Text alignment', 'seeinside-travel'), 'type' => Controls_Manager::CHOOSE, 'options' => [
            'left' => ['title' => __('Left', 'seeinside-travel'), 'icon' => 'eicon-text-align-left'], 'center' => ['title' => __('Centre', 'seeinside-travel'), 'icon' => 'eicon-text-align-center'], 'right' => ['title' => __('Right', 'seeinside-travel'), 'icon' => 'eicon-text-align-right']],
            'selectors' => ['{{WRAPPER}} .sta-term-sec > .sta-h' => 'text-align: {{VALUE}};']]);
        $this->add_responsive_control('of_title_space', ['label' => __('Space below', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]], 'selectors' => ['{{WRAPPER}} .sta-term-sec > .sta-h' => 'margin-bottom: {{SIZE}}px;']]);
        $this->end_controls_section();

        // cards
        $c = '{{WRAPPER}} .sta-c';
        $this->start_controls_section('of_cards', ['label' => __('Cards', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('of_gap', ['label' => __('Space between cards', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .sta-l' => '--l-gap: {{SIZE}}px;']]);
        $this->add_control('of_card_bg', ['label' => __('Card background', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$c => '--c-bg: {{VALUE}};']]);
        $this->add_control('of_card_text', ['label' => __('Text colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$c => '--c-text: {{VALUE}};']]);
        $this->add_control('of_card_muted', ['label' => __('Colour of the place and small text', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$c => '--c-muted: {{VALUE}};']]);
        $this->add_control('of_card_accent', ['label' => __('Accent (icons, button)', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$c => '--c-accent: {{VALUE}};']]);
        $this->add_responsive_control('of_card_radius', ['label' => __('Corner radius', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => [$c => '--c-radius: {{SIZE}}px;']]);
        $this->add_control('of_card_ratio', ['label' => __('Photo shape', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => [
            '' => __('Default (4:3)', 'seeinside-travel'), '1/1' => __('Square', 'seeinside-travel'), '3/2' => '3:2', '16/9' => __('Wide (16:9)', 'seeinside-travel'), '3/4' => __('Tall (3:4)', 'seeinside-travel')],
            'selectors' => [$c => '--c-ratio: {{VALUE}};']]);
        $this->add_control('of_card_shadow', ['label' => __('Shadow', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => [
            '' => __('Default', 'seeinside-travel'), 'none' => __('None', 'seeinside-travel'), 'soft' => __('Soft', 'seeinside-travel'), 'strong' => __('Strong', 'seeinside-travel')],
            'selectors_dictionary' => ['none' => 'box-shadow: none !important;', 'soft' => 'box-shadow: 0 6px 24px -10px rgba(15,23,42,.22) !important;', 'strong' => 'box-shadow: 0 18px 40px -16px rgba(15,23,42,.45) !important;'],
            'selectors' => [$c => '{{VALUE}}']]);
        $this->add_control('of_card_border', ['label' => __('Border colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$c => 'border: 1px solid {{VALUE}};']]);
        $this->add_control('of_card_title_h', ['label' => __('Title', 'seeinside-travel'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'of_card_title', 'selector' => '{{WRAPPER}} .sta-c-title']);
        $this->add_control('of_price_h', ['label' => __('Price', 'seeinside-travel'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('of_price_color', ['label' => __('Colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-c-price b' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'of_price_typo', 'selector' => '{{WRAPPER}} .sta-c-price b']);
        $this->add_control('of_btn_h', ['label' => __('Button', 'seeinside-travel'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('of_btn_color', ['label' => __('Text colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-c-btn' => 'color: {{VALUE}} !important;']]);
        $this->add_control('of_btn_bg', ['label' => __('Background', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-c-btn' => 'background: {{VALUE}} !important;']]);
        $this->add_control('of_btn_border', ['label' => __('Border colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-c-btn' => 'border-color: {{VALUE}} !important;']]);
        $this->add_control('of_btn_radius', ['label' => __('Corner radius', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => ['{{WRAPPER}} .sta-c-btn' => 'border-radius: {{SIZE}}px !important;']]);
        $this->end_controls_section();

        // filters column
        $f = '{{WRAPPER}} .sta-finder-side';
        $this->start_controls_section('of_filters', ['label' => __('Filters', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['hide_filters!' => 'yes']]);
        $this->add_responsive_control('of_f_width', ['label' => __('Width', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 200, 'max' => 420]], 'selectors' => ['{{WRAPPER}} .sta-finder-wrap' => 'grid-template-columns: {{SIZE}}px minmax(0, 1fr);']]);
        $this->add_control('of_f_bg', ['label' => __('Background', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$f => 'background: {{VALUE}};']]);
        $this->add_control('of_f_border', ['label' => __('Border colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$f => 'border-color: {{VALUE}};', $f.' .sta-ff' => 'border-color: {{VALUE}};']]);
        $this->add_control('of_f_radius', ['label' => __('Corner radius', 'seeinside-travel'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => [$f => 'border-radius: {{SIZE}}px;']]);
        $this->add_control('of_f_head', ['label' => __('Heading colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$f.' h4' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'of_f_head_typo', 'label' => __('Heading font', 'seeinside-travel'), 'selector' => $f.' h4']);
        $this->add_control('of_f_text', ['label' => __('Text colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => [$f.' label, '.$f.' span' => 'color: {{VALUE}};']]);
        $this->end_controls_section();

        // labels under the cards
        $this->start_controls_section('of_tags', ['label' => __('Labels under the cards', 'seeinside-travel'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['hide_tags!' => 'yes']]);
        $this->add_control('of_tag_bg', ['label' => __('Background', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-finder-tags span' => 'background: {{VALUE}};']]);
        $this->add_control('of_tag_color', ['label' => __('Text colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-finder-tags span' => 'color: {{VALUE}};']]);
        $this->add_control('of_tag_border', ['label' => __('Border colour', 'seeinside-travel'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .sta-finder-tags span' => 'border-color: {{VALUE}};']]);
        $this->end_controls_section();
    }
}

function sta_term_page_taxonomies(): array
{
    return [
        'sta_destination' => __('Destination', 'seeinside-travel'), 'sta_place' => __('Place', 'seeinside-travel'), 'sta_group' => __('Group', 'seeinside-travel'),
        'sta_tour_type' => __('Tour type', 'seeinside-travel'), 'sta_hotel_type' => __('Accommodation type', 'seeinside-travel'), 'sta_event_category' => __('Event category', 'seeinside-travel'),
    ];
}

function sta_elementor_widget_classes(): array
{
    return [STA_Widget_Search_Box::class, STA_Widget_Term_Header::class, STA_Widget_Term_Offers::class, STA_Widget_Section::class, STA_Widget_List::class, STA_Widget_Slider::class, STA_Widget_Listing::class, STA_Widget_Reviews::class, STA_Widget_Search::class, STA_Widget_Flights::class, STA_Widget_Charters::class, STA_Widget_Status::class, STA_Widget_Destinations::class];
}
