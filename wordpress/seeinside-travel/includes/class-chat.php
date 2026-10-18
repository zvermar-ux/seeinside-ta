<?php

defined('ABSPATH') || exit;

/**
 * Live chat widget. Settings come from SeeInside (option sta_chat, synced in real time);
 * the widget talks straight to SeeInside from the visitor's browser (/api/v1/public/<site>/chat/…).
 */
class STA_Chat
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'assets'], 30);
    }

    public static function enabled(): bool
    {
        $c = get_option('sta_chat', []);

        return ! empty($c['enabled']) && empty(sta_setting('chat_off')) && STA_Api::configured()
            && apply_filters('sta_chat_enabled', ! is_admin() && ! wp_doing_ajax() && ! (function_exists('is_customize_preview') && is_customize_preview()));
    }

    /** Address of an offer on this website: tour → /tours/<slug>/ */
    protected static function paths(): array
    {
        $out = [];
        foreach (STA_Schema::TYPES as $api => $pt) {
            $out[$api] = home_url('/'.STA_Post_Types::slug($pt).'/%s/');
        }

        return $out;
    }

    public static function assets(): void
    {
        if (! self::enabled()) {
            return;
        }
        $c = get_option('sta_chat', []);
        $lang = sta_lang();
        foreach ((array) ($c['questionnaires'] ?? []) as $i => $q) {
            if (! empty($q['image'])) {
                $c['questionnaires'][$i]['image'] = sta_local_image($q['image'], 'medium_large');
            }
        }
        wp_enqueue_style('sta-chat', STA_URL.'assets/css/chat.css', [], STA_VERSION);
        wp_enqueue_script('sta-chat', STA_URL.'assets/js/chat.js', [], STA_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
        wp_localize_script('sta-chat', 'STA_CHAT', [
            'api' => esc_url_raw(sta_public_api().'chat/'),
            'lang' => $lang,
            'paths' => self::paths(),
            'currency' => (get_option('sta_meta', [])['agency']['currency'] ?? 'EUR'),
            'config' => $c,
            'i18n' => [
                'placeholder' => __('Write a message…', 'seeinside-travel'),
                'send' => __('Send', 'seeinside-travel'),
                'close' => __('Close', 'seeinside-travel'),
                'back' => __('Back', 'seeinside-travel'),
                'aiChat' => __('Chat with our assistant', 'seeinside-travel'),
                'aiChatSub' => __('Answers right away, 24/7', 'seeinside-travel'),
                'choose' => __('Help me choose', 'seeinside-travel'),
                'chooseSub' => __('A few questions → offers for you', 'seeinside-travel'),
                'callMe' => __('Call me back', 'seeinside-travel'),
                'callMeSub' => __('Leave your number, an agent will call', 'seeinside-travel'),
                'viber' => 'Viber', 'messenger' => 'Messenger', 'whatsapp' => 'WhatsApp',
                'phone' => __('Call', 'seeinside-travel'), 'email' => __('E-mail', 'seeinside-travel'),
                'next' => __('Next', 'seeinside-travel'), 'showOffers' => __('Show offers', 'seeinside-travel'),
                'skip' => __('Skip', 'seeinside-travel'),
                'name' => __('Name', 'seeinside-travel'), 'phoneField' => __('Phone', 'seeinside-travel'), 'emailField' => __('E-mail', 'seeinside-travel'),
                'note' => __('What are you interested in?', 'seeinside-travel'),
                'sendContact' => __('Send', 'seeinside-travel'),
                'from' => __('from', 'seeinside-travel'),
                'view' => __('View', 'seeinside-travel'),
                'agent' => __('Agent', 'seeinside-travel'),
                'typing' => __('typing…', 'seeinside-travel'),
                'error' => __('Something went wrong. Please try again or call us.', 'seeinside-travel'),
                'restart' => __('New conversation', 'seeinside-travel'),
                'humanOn' => __('An agent has joined the conversation.', 'seeinside-travel'),
                'contactNeeded' => __('Please enter a phone or an e-mail.', 'seeinside-travel'),
                'or' => __('or write to us on', 'seeinside-travel'),
            ],
        ]);
    }
}
