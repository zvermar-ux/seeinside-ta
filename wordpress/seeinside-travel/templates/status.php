<?php
/** "Check my booking / visa application" – reference number + e-mail. Theme override: /seeinside-travel/status.php */
defined('ABSPATH') || exit;
?>
<form class="sta sta-form sta-status" data-sta-status novalidate>
    <h3 class="sta-h"><?php esc_html_e('Check your booking or visa application', 'seeinside-travel'); ?></h3>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">
    <div class="sta-row">
        <label class="sta-field"><span><?php esc_html_e('Reference number', 'seeinside-travel'); ?></span><input name="reference" placeholder="R2610-ABCDE / V2610-ABCDE" required></label>
        <label class="sta-field"><span><?php esc_html_e('E-mail', 'seeinside-travel'); ?></span><input type="email" name="email" required></label>
    </div>
    <button type="submit" class="sta-btn"><?php esc_html_e('Check', 'seeinside-travel'); ?></button>
    <div class="sta-msg" data-sta-msg role="status" aria-live="polite"></div>
</form>
