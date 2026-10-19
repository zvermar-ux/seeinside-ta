<?php
/**
 * Quick booking from the availability calendar (hotels / apartments).
 * Override: copy to your-theme/seeinside-travel/quick-booking.php
 *
 * @var array $d synced item
 */
defined('ABSPATH') || exit;
$privacy = get_privacy_policy_url();
?>
<dialog class="sta-dialog sta-quick" data-sta-quick-dialog>
    <form class="sta-form sta-booking" data-sta-booking data-quick data-type="accommodation" data-slug="<?php echo esc_attr($d['slug']); ?>" data-currency="<?php echo esc_attr($d['currency'] ?? 'EUR'); ?>" novalidate>
        <button type="button" class="sta-dialog-close" data-close aria-label="×">×</button>
        <h3 class="sta-h"><?php esc_html_e('Quick booking', 'seeinside-travel'); ?></h3>
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">
        <input type="hidden" name="date_from" value="">
        <input type="hidden" name="date_to" value="">
        <input type="hidden" name="room_id" value="">
        <div class="sta-offer is-selected sta-quick-sum" data-total="0" data-sta-quick-sum></div>
        <div class="sta-row">
            <label class="sta-field"><span><?php esc_html_e('Adults', 'seeinside-travel'); ?></span><input type="number" name="adults" min="1" max="20" value="2"></label>
            <label class="sta-field"><span><?php esc_html_e('Children', 'seeinside-travel'); ?></span><input type="number" name="children" min="0" max="10" value="0"></label>
        </div>
        <div class="sta-row sta-ages" data-sta-ages></div>
        <div class="sta-row">
            <label class="sta-field sta-col-2"><span><?php esc_html_e('Full name', 'seeinside-travel'); ?> *</span><input name="customer[name]" required autocomplete="name"></label>
            <label class="sta-field"><span><?php esc_html_e('Phone', 'seeinside-travel'); ?> *</span><input type="tel" name="customer[phone]" required autocomplete="tel"></label>
            <label class="sta-field"><span><?php esc_html_e('E-mail', 'seeinside-travel'); ?> *</span><input type="email" name="customer[email]" required autocomplete="email"></label>
            <label class="sta-field sta-col-2"><span><?php esc_html_e('Message', 'seeinside-travel'); ?></span><textarea name="message" rows="2"></textarea></label>
        </div>
        <div class="sta-total" data-sta-total hidden></div>
        <label class="sta-check sta-consent"><input type="checkbox" required> <span><?php echo wp_kses_post(sprintf(__('I agree that my data is used to process this request (%s).', 'seeinside-travel'), $privacy ? '<a href="'.esc_url($privacy).'" target="_blank">'.__('privacy policy', 'seeinside-travel').'</a>' : __('privacy policy', 'seeinside-travel'))); ?></span></label>
        <button type="submit" class="sta-btn sta-btn-block"><?php esc_html_e('Send booking', 'seeinside-travel'); ?></button>
        <div class="sta-msg" data-sta-msg role="status" aria-live="polite"></div>
    </form>
</dialog>
