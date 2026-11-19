<?php
/**
 * Payment choice in the booking forms: card online (deposit or all) or at the agency.
 * Override: copy to your-theme/seeinside-travel/payment-choice.php
 *
 * @var array $pay   sta_payment()   @var string $currency
 */
defined('ABSPATH') || exit;
if (empty($pay['online'])) {
    return;
}
$cardTitle = $pay['title'] ?: __('Card payment', 'seeinside-travel');
$what = $pay['mode'] === 'full'
    ? __('The whole amount is paid now by card.', 'seeinside-travel')
    : sprintf(__('Deposit %d%% now by card, the rest later.', 'seeinside-travel'), (int) $pay['percent']);
$cards = '<span class="sta-pay-cards" aria-hidden="true"><i>VISA</i><i>Mastercard</i><i>Maestro</i></span>';
?>
<fieldset class="sta-pay" data-sta-pay data-percent="<?php echo (int) $pay['percent']; ?>" data-currency="<?php echo esc_attr($currency ?? 'EUR'); ?>">
    <legend><?php esc_html_e('Payment', 'seeinside-travel'); ?></legend>
    <label class="sta-pay-opt">
        <input type="radio" name="payment_method" value="card" checked>
        <span class="sta-pay-txt"><b><?php echo esc_html($cardTitle); ?></b>
            <small><?php echo esc_html($what); ?> <span data-sta-pay-amount></span></small>
            <?php if (! empty($pay['note'])) : ?><small class="sta-muted"><?php echo esc_html($pay['note']); ?></small><?php endif; ?>
        </span>
        <?php echo $cards; // phpcs:ignore ?>
    </label>
    <?php if (! empty($pay['agency'])) : ?>
        <label class="sta-pay-opt">
            <input type="radio" name="payment_method" value="agency">
            <span class="sta-pay-txt"><b><?php esc_html_e('Pay at the agency', 'seeinside-travel'); ?></b>
                <small><?php esc_html_e('Book now, pay at our office or by bank transfer.', 'seeinside-travel'); ?></small></span>
        </label>
    <?php endif; ?>
    <p class="sta-pay-lock">🔒 <?php esc_html_e('You pay on the secure page of Halk Bank – the card details never reach us.', 'seeinside-travel'); ?></p>
</fieldset>
