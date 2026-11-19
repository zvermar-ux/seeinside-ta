<?php
/**
 * Payment choice in the booking forms: card online (deposit or the whole amount) or at the agency.
 * Override: copy to your-theme/seeinside-travel/payment-choice.php
 *
 * @var array $pay   sta_payment()   @var string $currency
 */
defined('ABSPATH') || exit;
if (empty($pay['online'])) {
    return;
}
$full = $pay['mode'] === 'full';
$pct = (int) $pay['percent'];
$cardTitle = $pay['title'] ?: __('Pay by card now', 'seeinside-travel');
$cards = '<span class="sta-pay-cards" aria-hidden="true"><i>VISA</i><i>Mastercard</i><i>Maestro</i></span>';
?>
<fieldset class="sta-pay" data-sta-pay data-percent="<?php echo $pct; ?>" data-currency="<?php echo esc_attr($currency ?? 'EUR'); ?>">
    <legend><?php esc_html_e('How do you want to pay?', 'seeinside-travel'); ?></legend>
    <label class="sta-pay-opt">
        <input type="radio" name="payment_method" value="card" checked>
        <span class="sta-pay-txt"><b><?php echo esc_html($cardTitle); ?> <span class="sta-pay-sum" data-sta-pay-amount></span></b>
            <small><?php echo esc_html($full
                ? __('You pay the whole amount (100%) now by card. The payment is registered on your booking at once.', 'seeinside-travel')
                : sprintf(__('You pay a deposit of %d%% now by card; it is registered on your booking at once. The rest is paid to the agency later.', 'seeinside-travel'), $pct)); ?></small>
            <?php if (! empty($pay['note'])) : ?><small class="sta-muted"><?php echo esc_html($pay['note']); ?></small><?php endif; ?>
        </span>
        <?php echo $cards; // phpcs:ignore ?>
    </label>
    <?php if (! empty($pay['agency'])) : ?>
        <label class="sta-pay-opt">
            <input type="radio" name="payment_method" value="agency">
            <span class="sta-pay-txt"><b><?php esc_html_e('Pay at the agency', 'seeinside-travel'); ?> <span class="sta-pay-sum" data-sta-pay-total></span></b>
                <small><?php esc_html_e('You only book now – nothing is paid online. The agency contacts you, and you pay the whole amount at the agency (cash, card or bank transfer).', 'seeinside-travel'); ?></small></span>
        </label>
    <?php endif; ?>
    <p class="sta-pay-lock" data-sta-pay-lock>🔒 <?php esc_html_e('The card is paid on the secure page of Halk Bank – the card details never reach us.', 'seeinside-travel'); ?></p>
</fieldset>
