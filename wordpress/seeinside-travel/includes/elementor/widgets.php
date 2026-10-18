<?php

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
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
        $html = STA_Render::section($s['section'] ?: 'facts', $postId, $args);
        if (! empty($args['title']) && is_string($args['title'])) {
            // replace the built-in heading by the custom one
            $html = preg_replace('#<h3 class="sta-h">.*?</h3>#s', '', $html, 1);
        } elseif (isset($args['title']) && $args['title'] === false) {
            $html = preg_replace('#<h3 class="sta-h">.*?</h3>#s', '', $html, 1);
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
            'tour' => __('Organized tours', 'seeinside-travel'), 'hotel' => __('Hotels & apartments', 'seeinside-travel'),
            'event' => __('Events', 'seeinside-travel'), 'visa' => __('Visas', 'seeinside-travel'), 'all' => __('Everything', 'seeinside-travel'),
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
    }

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
        $this->add_control('layout', ['label' => __('Layout', 'seeinside-travel'), 'type' => Controls_Manager::SELECT, 'default' => 'grid', 'options' => ['grid' => __('Grid', 'seeinside-travel'), 'slider' => __('Carousel', 'seeinside-travel'), 'list' => __('List', 'seeinside-travel')]]);
        $this->add_control('limit', ['label' => __('Number of reviews', 'seeinside-travel'), 'type' => Controls_Manager::NUMBER, 'default' => 6]);
        $this->add_control('featured', ['label' => __('Only featured', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => '']);
        $this->add_control('form', ['label' => __('Form for a new review', 'seeinside-travel'), 'type' => Controls_Manager::SWITCHER, 'default' => '']);
    }
    protected function html(array $s): string
    {
        return STA_Reviews::sc_reviews(['type' => $s['rtype'] ?? '', 'layout' => $s['layout'] ?? 'grid', 'limit' => $s['limit'] ?? 6, 'featured' => ! empty($s['featured']) ? 'yes' : 'no', 'form' => ! empty($s['form']) ? 'yes' : 'no']);
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

function sta_elementor_widget_classes(): array
{
    return [STA_Widget_Section::class, STA_Widget_List::class, STA_Widget_Slider::class, STA_Widget_Reviews::class, STA_Widget_Search::class, STA_Widget_Flights::class, STA_Widget_Charters::class, STA_Widget_Status::class, STA_Widget_Destinations::class];
}
