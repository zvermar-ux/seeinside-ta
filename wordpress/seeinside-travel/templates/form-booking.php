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
$pre = fn ($k) => isset($_GET[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET[$k]) ? (string) $_GET[$k] : '';   // phpcs:ignore -- dates from the hotel finder
$tr = $type === 'accommodation' && ! empty($d['transport']) ? $d['transport'] : null;
$deps = array_values(array_filter($d['departures'] ?? [], fn ($x) => ! in_array($x['status'], ['sold_out', 'cancelled'], true)));
// rooms of the accommodation: the chosen room is shown in the form, and its capacity limits the guests
$roomsJs = $type === 'accommodation' ? array_values(array_map(fn ($r) => [
    'id' => (int) $r['id'], 'name' => sta_t($r['name'] ?? ''), 'board' => is_array($r['board'] ?? null) ? sta_t($r['board']['label'] ?? ($r['board']['value'] ?? '')) : (string) ($r['board'] ?? ''), 'beds' => $r['beds'] ?? '',
    'max_adults' => (int) ($r['max_adults'] ?? 0), 'max_children' => (int) ($r['max_children'] ?? 0),
    'image' => $r['cover_image'] ?? (($r['images'][0]['url'] ?? $r['images'][0] ?? '') ?: ''),
], (array) ($d['rooms'] ?? []))) : [];
$termPriceType = $d['terms']['price_type'] ?? '';
?>
<form class="sta-form sta-booking" data-sta-booking data-type="<?php echo esc_attr($type); ?>" data-slug="<?php echo esc_attr($d['slug']); ?>" data-currency="<?php echo esc_attr($currency); ?>"<?php if ($roomsJs) : ?> data-rooms="<?php echo esc_attr(wp_json_encode($roomsJs)); ?>" data-price-type="<?php echo esc_attr($termPriceType); ?>"<?php endif; ?> novalidate>
    <h3 class="sta-h"><?php echo esc_html($title); ?></h3>
    <?php if ($roomsJs) : ?><div class="sta-selroom" data-sta-selroom hidden></div><?php endif; ?>
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
                    <?php foreach ($d['terms']['terms'] ?? [] as $term) :
                        $avail = [];
                        // the most expensive price of a room (by the people in it: 2, 2+1 … 3+1) for "from X to Y"
                        $top = [];
                        foreach ((array) ($term['cells'] ?? []) as $key => $x) {
                            $rid0 = (int) explode('|', (string) $key, 2)[0];
                            if ($x && isset($x['price'])) {
                                $top[$rid0] = max($top[$rid0] ?? 0, (float) $x['price']);
                            }
                        }
                        foreach ((array) ($term['rooms'] ?? []) as $rid => $x) {
                            $avail[$rid] = $x ? ['f' => ! empty($x['free']) ? 1 : 0, 'p' => $x['price'] ?? null, 'r' => $x['regular'] ?? null, 'x' => $top[$rid] ?? ($x['price'] ?? null)] : null;
                        } ?>
                        <option value="<?php echo (int) $term['id']; ?>" data-from="<?php echo esc_attr($term['from']); ?>" data-to="<?php echo esc_attr($term['to']); ?>" data-nights="<?php echo (int) $term['nights']; ?>" data-label="<?php echo esc_attr((string) ($term['label'] ?? '')); ?>" data-rooms="<?php echo esc_attr(wp_json_encode($avail)); ?>">
                            <?php echo esc_html(sta_date($term['from']).' – '.sta_date($term['to']).' · '.(int) $term['nights'].' '.__('nights', 'seeinside-travel').($term['label'] ? ' · '.$term['label'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="sta-tp" data-sta-tp></div>
            </label>
        </div>
        <input type="hidden" name="date_from" value="">
        <input type="hidden" name="date_to" value="">
        <input type="hidden" name="room_id" value="">
    <?php elseif ($type === 'accommodation') : ?>
        <div class="sta-row">
            <label class="sta-field"><span><?php esc_html_e('Check-in', 'seeinside-travel'); ?> *</span><input type="date" name="date_from" required min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo esc_attr($pre('from')); ?>"></label>
            <label class="sta-field"><span><?php esc_html_e('Check-out', 'seeinside-travel'); ?> *</span><input type="date" name="date_to" required min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo esc_attr($pre('to')); ?>"></label>
        </div>
        <input type="hidden" name="room_id" value="">
    <?php elseif ($type === 'custom') : ?>
        <div class="sta-row">
            <label class="sta-field"><span><?php esc_html_e('Date', 'seeinside-travel'); ?></span><input type="date" name="date_from" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></label>
            <?php if (in_array($d['record_type']['price_label'] ?? '', ['per_day', 'per_night'], true)) : ?>
                <label class="sta-field"><span><?php esc_html_e('Until', 'seeinside-travel'); ?></span><input type="date" name="date_to" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></label>
            <?php endif; ?>
        </div>
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
            <small class="sta-cap-note sta-col-2" data-sta-capnote hidden></small>
        </div>
        <div class="sta-row sta-ages" data-sta-ages></div>
    <?php endif; ?>

    <?php if ($tr) : ?>
        <fieldset class="sta-transport" data-sta-transport data-adult="<?php echo esc_attr($tr['price_adult'] ?? 0); ?>" data-child="<?php echo esc_attr($tr['price_child'] ?? ($tr['price_adult'] ?? 0)); ?>" data-free="<?php echo esc_attr($tr['free_under'] ?? 2); ?>" data-child-to="<?php echo esc_attr($tr['child_age_to'] ?? 12); ?>">
            <label class="sta-check"><input type="checkbox" name="with_transport" value="1"> <span><b><?php echo esc_html(($tr['type'] ?? 'bus') === 'flight' ? __('Add flight', 'seeinside-travel') : __('Add organized bus transport', 'seeinside-travel')); ?></b>
                <?php echo esc_html(sprintf(__('%1$s per adult, %2$s per child (%3$d–%4$d years)', 'seeinside-travel'), sta_money($tr['price_adult'] ?? 0, $currency), sta_money($tr['price_child'] ?? ($tr['price_adult'] ?? 0), $currency), (int) ($tr['free_under'] ?? 2), (int) ($tr['child_age_to'] ?? 12))); ?></span></label>
            <?php if (! empty($tr['places'])) : ?>
                <label class="sta-field" data-sta-transport-place hidden><span><?php esc_html_e('Departure place', 'seeinside-travel'); ?></span>
                    <select name="departure_place_id">
                        <?php foreach ($tr['places'] as $p) : ?>
                            <option value="<?php echo (int) $p['id']; ?>"><?php echo esc_html(sta_t($p['name'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <?php if (! empty($tr['note'])) : ?><small class="sta-muted"><?php echo esc_html(sta_t($tr['note'])); ?></small><?php endif; ?>
        </fieldset>
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
    <?php $pay = sta_payment($d, $type); echo sta_part('payment-choice', ['pay' => $pay, 'currency' => $currency]); // phpcs:ignore ?>
    <ul class="sta-trust">
        <?php if (empty($pay['online'])) : ?><li><?php esc_html_e('No payment now – the agency confirms first', 'seeinside-travel'); ?></li><?php else : ?><li data-sta-pay-trust data-card="<?php esc_attr_e('Paid by card – the booking goes on with the payment registered', 'seeinside-travel'); ?>" data-agency="<?php esc_attr_e('No payment now – the agency contacts you about the payment', 'seeinside-travel'); ?>"><?php esc_html_e('Paid by card – the booking goes on with the payment registered', 'seeinside-travel'); ?></li><?php endif; ?>
        <li><?php esc_html_e('Your own page to follow the booking, documents and messages', 'seeinside-travel'); ?></li>
        <li><?php esc_html_e('Answer by e-mail, Viber or WhatsApp', 'seeinside-travel'); ?></li>
    </ul>
    <label class="sta-check sta-remember"><input type="checkbox" data-sta-remember checked> <span><?php esc_html_e('Remember my details on this device', 'seeinside-travel'); ?></span></label>
    <?php echo sta_terms_check(); // phpcs:ignore ?>
    <label class="sta-check sta-consent"><input type="checkbox" required> <span><?php echo wp_kses_post(sprintf(__('I agree that my data is used to process this request (%s).', 'seeinside-travel'), get_privacy_policy_url() ? '<a href="'.esc_url(get_privacy_policy_url()).'" target="_blank">'.__('privacy policy', 'seeinside-travel').'</a>' : __('privacy policy', 'seeinside-travel'))); ?></span></label>
    <button type="submit" class="sta-btn sta-btn-lg sta-btn-block"><?php echo esc_html($type === 'accommodation' ? __('Send booking request', 'seeinside-travel') : __('Send booking', 'seeinside-travel')); ?></button>
    <div class="sta-msg" data-sta-msg role="status" aria-live="polite"></div>
</form>
