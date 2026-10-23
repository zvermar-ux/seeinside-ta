<?php
/**
 * Booking form for tours, hotels and events. Theme override: /seeinside-travel/form-booking.php
 * @var array $d  record from SeeInside   @var string $type   @var int $post_id
 */
defined('ABSPATH') || exit;
$currency = $d['currency'] ?? 'EUR';
$title = [
    'tour' => __('Book this tour', 'seeinside-travel'),
    'accommodation' => __('Check prices & book', 'seeinside-travel'),
    'event' => __('Buy tickets', 'seeinside-travel'),
][$type] ?? __('Send inquiry', 'seeinside-travel');
$deps = array_values(array_filter($d['departures'] ?? [], fn ($x) => ! in_array($x['status'], ['sold_out', 'cancelled'], true)));
?>
<form class="sta-form sta-booking" data-sta-booking data-type="<?php echo esc_attr($type); ?>" data-slug="<?php echo esc_attr($d['slug']); ?>" data-currency="<?php echo esc_attr($currency); ?>" novalidate>
    <h3 class="sta-h"><?php echo esc_html($title); ?></h3>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">

    <?php if ($type === 'tour') : ?>
        <div class="sta-row">
            <label class="sta-field sta-col-2"><span><?php esc_html_e('Departure date', 'seeinside-travel'); ?> *</span>
                <select name="departure_id" required>
                    <option value=""><?php esc_html_e('Choose a date', 'seeinside-travel'); ?></option>
                    <?php foreach ($deps as $dep) : ?>
                        <option value="<?php echo (int) $dep['id']; ?>" data-adult="<?php echo esc_attr($dep['price_adult']); ?>" data-child="<?php echo esc_attr($dep['price_child'] ?? $dep['price_adult']); ?>" data-seats="<?php echo esc_attr($dep['seats_left'] ?? ''); ?>">
                            <?php echo esc_html(sta_date($dep['start_date']).($dep['end_date'] ? ' – '.sta_date($dep['end_date']) : '').' · '.sta_money($dep['price_adult'], $currency)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (! empty($d['departure_places'])) : ?>
                <label class="sta-field"><span><?php esc_html_e('Departure place', 'seeinside-travel'); ?></span>
                    <select name="departure_place_id">
                        <?php foreach ($d['departure_places'] as $p) : ?>
                            <option value="<?php echo (int) $p['id']; ?>" data-surcharge="<?php echo esc_attr($p['surcharge']); ?>"><?php echo esc_html(sta_t($p['name']).($p['surcharge'] ? ' (+'.sta_money($p['surcharge'], $currency).')' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    <?php elseif ($type === 'accommodation' && ! empty($d['book_by_terms'])) : ?>
        <div class="sta-row">
            <label class="sta-field sta-col-2"><span><?php esc_html_e('Term', 'seeinside-travel'); ?> *</span>
                <select name="term_id" required data-sta-term>
                    <option value=""><?php esc_html_e('Choose a term', 'seeinside-travel'); ?></option>
                    <?php foreach ($d['terms']['terms'] ?? [] as $term) : ?>
                        <option value="<?php echo (int) $term['id']; ?>" data-from="<?php echo esc_attr($term['from']); ?>" data-to="<?php echo esc_attr($term['to']); ?>">
                            <?php echo esc_html(sta_date($term['from']).' – '.sta_date($term['to']).' · '.(int) $term['nights'].' '.__('nights', 'seeinside-travel').($term['label'] ? ' · '.$term['label'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <input type="hidden" name="date_from" value="">
        <input type="hidden" name="date_to" value="">
    <?php elseif ($type === 'accommodation') : ?>
        <div class="sta-row">
            <label class="sta-field"><span><?php esc_html_e('Check-in', 'seeinside-travel'); ?> *</span><input type="date" name="date_from" required min="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></label>
            <label class="sta-field"><span><?php esc_html_e('Check-out', 'seeinside-travel'); ?> *</span><input type="date" name="date_to" required min="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></label>
        </div>
        <input type="hidden" name="room_id" value="">
    <?php elseif ($type === 'event') : ?>
        <div class="sta-row">
            <label class="sta-field sta-col-2"><span><?php esc_html_e('Ticket', 'seeinside-travel'); ?> *</span>
                <select name="ticket_id" required>
                    <?php foreach ($d['tickets'] ?? [] as $t) : if ($t['available'] !== null && $t['available'] < 1) { continue; } ?>
                        <option value="<?php echo (int) $t['id']; ?>" data-price="<?php echo esc_attr($t['price']); ?>"><?php echo esc_html(sta_t($t['name']).' · '.sta_money($t['price'], $currency)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="sta-field"><span><?php esc_html_e('Number of tickets', 'seeinside-travel'); ?></span><input type="number" name="quantity" min="1" max="20" value="1"></label>
        </div>
    <?php endif; ?>

    <?php if ($type !== 'event') : ?>
        <div class="sta-row">
            <label class="sta-field"><span><?php esc_html_e('Adults', 'seeinside-travel'); ?></span><input type="number" name="adults" min="1" max="20" value="2"></label>
            <label class="sta-field"><span><?php esc_html_e('Children', 'seeinside-travel'); ?></span><input type="number" name="children" min="0" max="10" value="0"></label>
        </div>
        <div class="sta-row sta-ages" data-sta-ages></div>
    <?php endif; ?>

    <?php if ($type === 'accommodation') : ?>
        <button type="button" class="sta-btn sta-btn-ghost sta-btn-block" data-sta-quote><?php esc_html_e('Show available rooms & prices', 'seeinside-travel'); ?></button>
        <div class="sta-offers" data-sta-offers></div>
    <?php endif; ?>

    <?php if ($type !== 'event') { echo sta_part('passengers'); } // phpcs:ignore ?>

    <?php if ($type === 'tour' && ! empty($d['extras'])) : ?>
        <fieldset class="sta-extras"><legend><?php esc_html_e('Extras', 'seeinside-travel'); ?></legend>
            <?php foreach ($d['extras'] as $e) : ?>
                <label class="sta-check"><input type="checkbox" name="extras[]" value="<?php echo (int) $e['id']; ?>" data-price="<?php echo esc_attr($e['price']); ?>" data-per="<?php echo esc_attr($e['price_type']); ?>"<?php checked(empty($e['optional'])); ?><?php disabled(empty($e['optional'])); ?>>
                    <span><?php echo esc_html(sta_t($e['name'])); ?> <b><?php echo esc_html(sta_money($e['price'], $currency)); ?></b><?php echo $e['price_type'] === 'per_person' ? ' / '.esc_html__('person', 'seeinside-travel') : ''; ?></span></label>
            <?php endforeach; ?>
        </fieldset>
    <?php endif; ?>

    <div class="sta-row">
        <label class="sta-field sta-col-2"><span><?php esc_html_e('Full name', 'seeinside-travel'); ?> *</span><input name="customer[name]" required autocomplete="name"></label>
        <label class="sta-field"><span><?php esc_html_e('Phone', 'seeinside-travel'); ?> *</span><input type="tel" name="customer[phone]" required autocomplete="tel"></label>
        <label class="sta-field"><span><?php esc_html_e('E-mail', 'seeinside-travel'); ?> *</span><input type="email" name="customer[email]" required autocomplete="email"></label>
        <label class="sta-field sta-col-2"><span><?php esc_html_e('City', 'seeinside-travel'); ?></span><input name="customer[city]" autocomplete="address-level2"></label>
        <label class="sta-field sta-col-2"><span><?php esc_html_e('Message', 'seeinside-travel'); ?></span><textarea name="message" rows="3"></textarea></label>
    </div>

    <div class="sta-total" data-sta-total hidden></div>
    <label class="sta-check sta-consent"><input type="checkbox" required> <span><?php echo wp_kses_post(sprintf(__('I agree that my data is used to process this request (%s).', 'seeinside-travel'), get_privacy_policy_url() ? '<a href="'.esc_url(get_privacy_policy_url()).'" target="_blank">'.__('privacy policy', 'seeinside-travel').'</a>' : __('privacy policy', 'seeinside-travel'))); ?></span></label>
    <button type="submit" class="sta-btn sta-btn-lg sta-btn-block"><?php echo esc_html($type === 'accommodation' ? __('Send booking request', 'seeinside-travel') : __('Send booking', 'seeinside-travel')); ?></button>
    <div class="sta-msg" data-sta-msg role="status" aria-live="polite"></div>
</form>
