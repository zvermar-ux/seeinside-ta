<?php
/** Charter seats with a quick inquiry. Theme override: /seeinside-travel/charters.php  @var array $rows */
defined('ABSPATH') || exit;
?>
<div class="sta sta-charters">
    <div class="sta-table-wrap"><table class="sta-table">
        <thead><tr>
            <th><?php esc_html_e('Destination', 'seeinside-travel'); ?></th>
            <th><?php esc_html_e('Departure', 'seeinside-travel'); ?></th>
            <th><?php esc_html_e('Return', 'seeinside-travel'); ?></th>
            <th><?php esc_html_e('From', 'seeinside-travel'); ?></th>
            <th><?php esc_html_e('Price', 'seeinside-travel'); ?></th>
            <th><?php esc_html_e('Seats', 'seeinside-travel'); ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $c) : $full = $c['seats_left'] !== null && $c['seats_left'] < 1; ?>
            <tr class="<?php echo $full ? 'is-closed' : ''; ?>">
                <td><b><?php echo esc_html($c['destination'] ?: $c['region']); ?></b><?php if ($c['note']) : ?><br><small class="sta-muted"><?php echo esc_html($c['note']); ?></small><?php endif; ?></td>
                <td><?php echo esc_html(sta_date($c['departure_date'])); ?></td>
                <td><?php echo esc_html(sta_date($c['return_date'])); ?></td>
                <td><?php echo esc_html($c['departure_place'] ?: '—'); ?></td>
                <td><b><?php echo esc_html(sta_money($c['price'], $c['currency'])); ?></b><?php if ($c['transport_only_price'] !== null) : ?><br><small><?php echo esc_html(__('transport only', 'seeinside-travel').' '.sta_money($c['transport_only_price'], $c['currency'])); ?></small><?php endif; ?></td>
                <td><?php echo $c['seats_left'] === null ? '—' : esc_html($full ? __('Sold out', 'seeinside-travel') : (string) $c['seats_left']); ?></td>
                <td><?php if (! $full) : ?><button type="button" class="sta-btn sta-btn-sm" data-sta-charter="<?php echo (int) $c['id']; ?>" data-transport="<?php echo $c['transport_only_price'] !== null ? '1' : '0'; ?>" data-title="<?php echo esc_attr(($c['destination'] ?: $c['region']).' · '.sta_date($c['departure_date'])); ?>"><?php esc_html_e('Book', 'seeinside-travel'); ?></button><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <dialog class="sta-dialog" data-sta-charter-dialog>
        <form class="sta-form" data-sta-booking data-type="charter" novalidate>
            <button type="button" class="sta-dialog-close" data-close aria-label="×">×</button>
            <h3 class="sta-h" data-title></h3>
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">
            <input type="hidden" name="charter_id" value="">
            <div class="sta-row">
                <label class="sta-field"><span><?php esc_html_e('Adults', 'seeinside-travel'); ?></span><input type="number" name="adults" min="1" max="20" value="2"></label>
                <label class="sta-field"><span><?php esc_html_e('Children', 'seeinside-travel'); ?></span><input type="number" name="children" min="0" max="10" value="0"></label>
                <label class="sta-check sta-col-2" data-transport-only><input type="checkbox" name="transport_only" value="1"> <?php esc_html_e('Transport only', 'seeinside-travel'); ?></label>
                <label class="sta-field sta-col-2"><span><?php esc_html_e('Full name', 'seeinside-travel'); ?> *</span><input name="customer[name]" required></label>
                <label class="sta-field"><span><?php esc_html_e('Phone', 'seeinside-travel'); ?> *</span><input type="tel" name="customer[phone]" required></label>
                <label class="sta-field"><span><?php esc_html_e('E-mail', 'seeinside-travel'); ?> *</span><input type="email" name="customer[email]" required></label>
                <label class="sta-field sta-col-2"><span><?php esc_html_e('Message', 'seeinside-travel'); ?></span><textarea name="message" rows="2"></textarea></label>
            </div>
            <button type="submit" class="sta-btn sta-btn-block"><?php esc_html_e('Send booking', 'seeinside-travel'); ?></button>
            <div class="sta-msg" data-sta-msg role="status" aria-live="polite"></div>
        </form>
    </dialog>
</div>
