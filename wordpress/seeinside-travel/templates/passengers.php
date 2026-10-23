<?php
/**
 * Passenger details (name, date of birth, passport, phone) – one row per adult / child, built by frontend.js.
 * They go into the confirmation e-mail and the travel contract.
 * Override: your-theme/seeinside-travel/passengers.php (keep data-sta-pax)
 */
defined('ABSPATH') || exit;
$passport = apply_filters('sta_passenger_passport', true);
$phone = apply_filters('sta_passenger_phone', true);
?>
<fieldset class="sta-pax" data-sta-pax data-passport="<?php echo $passport ? '1' : ''; ?>" data-phone="<?php echo $phone ? '1' : ''; ?>">
    <legend><?php esc_html_e('Passengers', 'seeinside-travel'); ?></legend>
    <p class="sta-muted sta-pax-hint"><?php esc_html_e('Name and date of birth of every passenger, as in the passport – they are written in the travel contract.', 'seeinside-travel'); ?></p>
    <div class="sta-pax-rows" data-sta-pax-rows></div>
</fieldset>
