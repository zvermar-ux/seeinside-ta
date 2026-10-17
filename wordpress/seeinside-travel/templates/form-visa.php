<?php
/**
 * Visa application: questionnaire and documents defined in SeeInside. Theme override: /seeinside-travel/form-visa.php
 * @var array $d
 */
defined('ABSPATH') || exit;
$questions = $d['questionnaire'] ?? [];
$docs = $d['required_documents'] ?? [];
$sections = [];
foreach ($questions as $q) {
    $sections[(string) ($q['section'] ?? '')][] = $q;
}
$base = ['first_name', 'last_name', 'email', 'phone'];
$label = fn ($q) => sta_t($q['label'] ?? $q['key']);
?>
<form class="sta-form sta-visa" data-sta-visa data-slug="<?php echo esc_attr($d['slug']); ?>" enctype="multipart/form-data" novalidate>
    <h3 class="sta-h"><?php esc_html_e('Online visa application', 'seeinside-travel'); ?></h3>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sta-hp" aria-hidden="true">
    <input type="hidden" name="visa" value="<?php echo esc_attr($d['slug']); ?>">

    <div class="sta-row">
        <label class="sta-field"><span><?php esc_html_e('First name', 'seeinside-travel'); ?> *</span><input name="first_name" required autocomplete="given-name"></label>
        <label class="sta-field"><span><?php esc_html_e('Last name', 'seeinside-travel'); ?> *</span><input name="last_name" required autocomplete="family-name"></label>
        <label class="sta-field"><span><?php esc_html_e('E-mail', 'seeinside-travel'); ?> *</span><input type="email" name="email" required autocomplete="email"></label>
        <label class="sta-field"><span><?php esc_html_e('Phone', 'seeinside-travel'); ?> *</span><input type="tel" name="phone" required autocomplete="tel"></label>
    </div>

    <?php foreach ($sections as $section => $qs) : ?>
        <fieldset class="sta-fieldset">
            <?php if ($section) : ?><legend><?php echo esc_html($section); ?></legend><?php endif; ?>
            <div class="sta-row">
            <?php foreach ($qs as $q) :
                if (in_array($q['key'], $base, true)) { continue; }
                $name = 'answers['.esc_attr($q['key']).']';
                $req = ! empty($q['required']) ? ' required' : '';
                $star = ! empty($q['required']) ? ' *' : '';
                $type = $q['type'] ?? 'text';
                $options = $q['options'] ?? ($type === 'radio' ? [__('Yes', 'seeinside-travel'), __('No', 'seeinside-travel')] : []);
                ?>
                <?php if ($type === 'textarea') : ?>
                    <label class="sta-field sta-col-2"><span><?php echo esc_html($label($q).$star); ?></span><textarea name="<?php echo $name; ?>" rows="3"<?php echo $req; ?>></textarea></label>
                <?php elseif ($type === 'select') : ?>
                    <label class="sta-field"><span><?php echo esc_html($label($q).$star); ?></span><select name="<?php echo $name; ?>"<?php echo $req; ?>><option value=""></option><?php foreach ($options as $o) : ?><option><?php echo esc_html(is_array($o) ? sta_t($o) : $o); ?></option><?php endforeach; ?></select></label>
                <?php elseif ($type === 'radio') : ?>
                    <div class="sta-field sta-col-2"><span><?php echo esc_html($label($q).$star); ?></span><div class="sta-radios"><?php foreach ($options as $o) : ?><label class="sta-check"><input type="radio" name="<?php echo $name; ?>" value="<?php echo esc_attr(is_array($o) ? sta_t($o) : $o); ?>"<?php echo $req; ?>> <?php echo esc_html(is_array($o) ? sta_t($o) : $o); ?></label><?php endforeach; ?></div></div>
                <?php elseif ($type === 'checkbox') : ?>
                    <label class="sta-check sta-col-2"><input type="checkbox" name="<?php echo $name; ?>" value="1"<?php echo $req; ?>> <?php echo esc_html($label($q).$star); ?></label>
                <?php else : ?>
                    <label class="sta-field"><span><?php echo esc_html($label($q).$star); ?></span><input type="<?php echo esc_attr(['date' => 'date', 'email' => 'email', 'phone' => 'tel', 'number' => 'number'][$type] ?? 'text'); ?>" name="<?php echo $name; ?>"<?php echo $req; ?>></label>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php if ($docs) : ?>
        <fieldset class="sta-fieldset"><legend><?php esc_html_e('Documents', 'seeinside-travel'); ?></legend>
            <p class="sta-muted"><?php esc_html_e('PDF or photo (JPG, PNG, HEIC), up to 10 MB per file. Documents are stored privately.', 'seeinside-travel'); ?></p>
            <div class="sta-row">
            <?php foreach ($docs as $doc) : $multi = ! empty($doc['multiple']); ?>
                <label class="sta-field sta-file"><span><?php echo esc_html(sta_t($doc['label'] ?? $doc['key']).(! empty($doc['required']) ? ' *' : '')); ?></span>
                    <input type="file" name="documents[<?php echo esc_attr($doc['key']); ?>]<?php echo $multi ? '[]' : ''; ?>" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,image/*,application/pdf"<?php echo $multi ? ' multiple' : ''; ?><?php echo ! empty($doc['required']) ? ' required' : ''; ?>>
                </label>
            <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endif; ?>

    <?php if (! empty($d['fees']['express'])) : ?>
        <label class="sta-check"><input type="checkbox" name="express" value="1"> <?php echo esc_html(sprintf(__('Express processing (+%s)', 'seeinside-travel'), sta_money($d['fees']['express'], $d['currency'] ?? 'EUR'))); ?></label>
    <?php endif; ?>
    <label class="sta-check sta-consent"><input type="checkbox" required> <span><?php esc_html_e('I confirm the data is correct and agree that it is used to process my visa application.', 'seeinside-travel'); ?></span></label>
    <button type="submit" class="sta-btn sta-btn-lg sta-btn-block"><?php esc_html_e('Send application', 'seeinside-travel'); ?></button>
    <div class="sta-msg" data-sta-msg role="status" aria-live="polite"></div>
</form>
