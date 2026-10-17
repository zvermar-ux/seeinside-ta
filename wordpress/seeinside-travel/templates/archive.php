<?php
/** Archive of tours / hotels / events / visas and destination pages (used when the theme / builder has none) */
defined('ABSPATH') || exit;
get_header();
$type = get_query_var('post_type') ?: 'sta_tour';
$searchTypes = ['sta_tour' => 'tour', 'sta_hotel' => 'accommodation', 'sta_event' => 'event', 'sta_visa' => 'visa'];
?>
<main class="sta sta-archive">
    <header class="sta-archive-head">
        <h1 class="sta-archive-title"><?php echo is_tax() ? esc_html(single_term_title('', false)) : esc_html(post_type_archive_title('', false)); ?></h1>
        <?php if (is_tax() && term_description()) : ?><div class="sta-prose"><?php echo wp_kses_post(term_description()); ?></div><?php endif; ?>
        <?php echo STA_Frontend::sc_search(['types' => $searchTypes[is_string($type) ? $type : 'sta_tour'] ?? 'tour']); ?>
    </header>
    <?php echo STA_Frontend::sc_list(['query' => 'current', 'columns' => 3, 'pagination' => '0']); ?>
    <nav class="sta-pagination"><?php echo paginate_links(); ?></nav>
</main>
<?php
get_footer();
