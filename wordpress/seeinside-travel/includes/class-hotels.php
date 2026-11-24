<?php

defined('ABSPATH') || exit;

/**
 * Hotels from booking systems (Hotelbeds…) with live prices: [sta_hotels] = search results with filters,
 * hotel page (photos, facilities, rooms and rates) and the booking form. Nothing is stored in WordPress –
 * every price comes from the app at the moment of the search.
 */
class STA_Hotels
{
    public static function init(): void
    {
        add_action('init', fn () => add_shortcode('sta_hotels', [self::class, 'shortcode']));
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function enabled(): bool
    {
        return ! empty((get_option('sta_meta', [])['hotels']['enabled'] ?? false));
    }

    /** Hotel destinations chosen in the app: [['id' => '3:SKG', 'name', 'country', 'hotels']] */
    public static function destinations(): array
    {
        return (array) (get_option('sta_meta', [])['hotels']['destinations'] ?? []);
    }

    /** Hotel features of the booking systems (filters) */
    public static function features(): array
    {
        return ['pool' => ['mk' => 'Базен', 'en' => 'Swimming pool'], 'beach' => ['mk' => 'Плажа', 'en' => 'Beach'], 'wifi' => ['mk' => 'Wi-Fi', 'en' => 'Wi-Fi'], 'parking' => ['mk' => 'Паркинг', 'en' => 'Parking'], 'ac' => ['mk' => 'Клима уред', 'en' => 'Air conditioning'], 'restaurant' => ['mk' => 'Ресторан', 'en' => 'Restaurant'], 'spa' => ['mk' => 'Спа и сауна', 'en' => 'Spa & sauna'], 'gym' => ['mk' => 'Фитнес', 'en' => 'Gym'], 'kids' => ['mk' => 'За деца', 'en' => 'For children'], 'pets' => ['mk' => 'Дозволени миленици', 'en' => 'Pets allowed'], 'access' => ['mk' => 'Пристап за лица со попреченост', 'en' => 'Accessible']];
    }

    /** Page with the hotel results */
    public static function page_url(): string
    {
        $pages = get_option('sta_pages', []);
        $pc = ! empty($pages['hoteli']) ? (string) get_post_field('post_content', $pages['hoteli']) : '';
        if (! empty($pages['hoteli']) && get_post_status($pages['hoteli']) === 'publish' && (has_shortcode($pc, 'sta_hotels') || str_contains($pc, 'page_type="hotels"'))) {
            return (string) get_permalink($pages['hoteli']);
        }
        foreach (get_posts(['post_type' => 'page', 'post_status' => 'publish', 's' => '[sta_hotels', 'numberposts' => 1]) as $p) {
            return (string) get_permalink($p);
        }

        return home_url('/hoteli/');
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('sta');
        if (class_exists('STA_Listings')) {
            STA_Listings::enqueue();   // the cards look like the apartment cards
        }
        wp_enqueue_script('sta');
        wp_enqueue_script('sta-hotels', STA_URL.'assets/js/hotels.js', ['sta'], STA_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
        $privacy = get_privacy_policy_url();
        wp_localize_script('sta-hotels', 'STA_HOTELS', [
            'payment' => sta_payment([], 'hotel'),   // card online / at the agency for hotels (rule of the agency)
            'terms' => sta_terms_check(),
            'consent' => sprintf(__('I agree that my data is used to process this request (%s).', 'seeinside-travel'), $privacy ? '<a href="'.esc_url($privacy).'" target="_blank">'.esc_html__('privacy policy', 'seeinside-travel').'</a>' : esc_html__('privacy policy', 'seeinside-travel')),
            'destinations' => self::destinations(),
            // the apartment card (same design) with placeholders – the hotels are filled in by hotels.js
            'card' => self::card_prototype(),
            'priceToken' => sta_money(98765, 'EUR'),
            // names of the filters of the results (the same as the app gives without dates)
            'types' => ['hotel' => ['mk' => 'Хотел', 'en' => 'Hotel'], 'aparthotel' => ['mk' => 'Апартхотел', 'en' => 'Aparthotel'], 'apartment' => ['mk' => 'Апартмани', 'en' => 'Apartments'], 'resort' => ['mk' => 'Ресорт', 'en' => 'Resort'], 'villa' => ['mk' => 'Вила', 'en' => 'Villa'], 'hostel' => ['mk' => 'Хостел', 'en' => 'Hostel'], 'guesthouse' => ['mk' => 'Пансион / B&B', 'en' => 'Guest house / B&B'], 'other' => ['mk' => 'Друго', 'en' => 'Other']],
            'features' => self::features(),
            'i18n' => [
                'allDest' => __('All destinations', 'seeinside-travel'),
                'showAll' => __('Show all hotels', 'seeinside-travel'),
                'allCountries' => __('All countries', 'seeinside-travel'),
                'allZones' => __('All areas', 'seeinside-travel'),
                'typeH' => __('Type of accommodation', 'seeinside-travel'),
                'afterDates' => __('Prices, free rooms and free cancellation: choose your dates above.', 'seeinside-travel'),
                'payCard' => __('Pay by card now', 'seeinside-travel'),
                'payHow' => __('How do you want to pay?', 'seeinside-travel'),
                'payAgency' => __('Pay at the agency', 'seeinside-travel'),
                'payAgencyNote' => __('You only book now; the whole amount is paid at the agency:', 'seeinside-travel'),
                'payFull' => __('The whole amount (100%) now by card:', 'seeinside-travel'),
                'payDeposit' => __('Deposit %d%% now by card, the rest at the agency:', 'seeinside-travel'),
                'payLock' => __('You pay on the secure page of Halk Bank – the card details never reach us.', 'seeinside-travel'),
                'payTitle' => __('Payment', 'seeinside-travel'),
                'payGo' => __('Booking received – opening the card payment…', 'seeinside-travel'),
                'yourDates' => __('Your dates', 'seeinside-travel'),
                'searchName' => __('Search', 'seeinside-travel'),
                'searchPh' => __('Hotel name', 'seeinside-travel'),
                'adultsL' => __('Adults', 'seeinside-travel'),
                'childrenL' => __('Children', 'seeinside-travel'),
                'showFree' => __('Show free hotels with prices', 'seeinside-travel'),
                'needDates' => __('Choose the check-in and check-out dates.', 'seeinside-travel'),
                'pickDest' => __('Choose a destination (or open a hotel).', 'seeinside-travel'),
                'destination' => __('Destination', 'seeinside-travel'),
                'catalogNote' => __('choose dates to see prices', 'seeinside-travel'),
                'pricesFor' => __('Prices for your dates', 'seeinside-travel'),
                'checkPrices' => __('Check prices', 'seeinside-travel'),
                'details' => __('Details', 'seeinside-travel'),
                'pickDates' => __('Choose the dates to see free rooms and prices.', 'seeinside-travel'),
                'loading' => __('Loading…', 'seeinside-travel'),
                'choose' => __('Choose a destination and dates to see the hotels with prices.', 'seeinside-travel'),
                'searching' => __('Searching the best prices…', 'seeinside-travel'),
                'none' => __('No free hotels for this search. Try other dates or a nearby destination.', 'seeinside-travel'),
                'noneFilter' => __('No hotel matches these filters.', 'seeinside-travel'),
                'hotels' => __('hotels', 'seeinside-travel'),
                'hotel1' => __('hotel', 'seeinside-travel'),
                'noFreeDates' => __('No free room for the chosen dates', 'seeinside-travel'),
                'busyHead' => __('No free hotels for %s. Below are all hotels – open one, or choose one of the closest free dates.', 'seeinside-travel'),
                'altLooking' => __('Looking for the closest free dates…', 'seeinside-travel'),
                'altDates' => __('Closest free dates', 'seeinside-travel'),
                'altHotels' => __('Free hotels in %s for your dates', 'seeinside-travel'),
                'altAll' => __('All free hotels in the area (%d)', 'seeinside-travel'),
                'nights' => __('nights', 'seeinside-travel'),
                'night1' => __('night', 'seeinside-travel'),
                'for' => __('for', 'seeinside-travel'),
                'from' => __('from', 'seeinside-travel'),
                'rooms' => __('Rooms', 'seeinside-travel'),
                'room1' => __('room', 'seeinside-travel'),
                'roomN' => __('rooms', 'seeinside-travel'),
                'adults' => __('adults', 'seeinside-travel'),
                'children' => __('children', 'seeinside-travel'),
                'seeRooms' => __('See rooms', 'seeinside-travel'),
                'book' => __('Book', 'seeinside-travel'),
                'back' => __('All hotels', 'seeinside-travel'),
                'freeCancel' => __('Free cancellation', 'seeinside-travel'),
                'freeUntil' => __('Free cancellation until %s', 'seeinside-travel'),
                'nonRef' => __('Non-refundable', 'seeinside-travel'),
                'payHotel' => __('Pay at the hotel', 'seeinside-travel'),
                'left' => __('Only %d left', 'seeinside-travel'),
                'filters' => __('Filters', 'seeinside-travel'),
                'name' => __('Hotel name', 'seeinside-travel'),
                'price' => __('Price', 'seeinside-travel'),
                'stars' => __('Stars', 'seeinside-travel'),
                'board' => __('Board', 'seeinside-travel'),
                'onlyFree' => __('Only with free cancellation', 'seeinside-travel'),
                'sort' => __('Sort', 'seeinside-travel'),
                'sortRec' => __('Recommended', 'seeinside-travel'),
                'sortLow' => __('Lowest price', 'seeinside-travel'),
                'sortHigh' => __('Highest price', 'seeinside-travel'),
                'sortStars' => __('Stars', 'seeinside-travel'),
                'more' => __('Show more', 'seeinside-travel'),
                'readMore' => __('Read more', 'seeinside-travel'),
                'facilities' => __('Facilities', 'seeinside-travel'),
                'map' => __('Show on map', 'seeinside-travel'),
                'roomsTitle' => __('Rooms and prices', 'seeinside-travel'),
                'fee' => __('extra charge', 'seeinside-travel'),
                'taxes' => __('Local taxes paid at the hotel', 'seeinside-travel'),
                'yourBooking' => __('Your booking', 'seeinside-travel'),
                'guests' => __('Guests', 'seeinside-travel'),
                'guestsNote' => __('Names as in the passport (Latin letters).', 'seeinside-travel'),
                'room' => __('Room', 'seeinside-travel'),
                'adult' => __('Adult', 'seeinside-travel'),
                'child' => __('Child', 'seeinside-travel'),
                'years' => __('years', 'seeinside-travel'),
                'firstName' => __('First name', 'seeinside-travel'),
                'lastName' => __('Last name', 'seeinside-travel'),
                'contact' => __('Contact', 'seeinside-travel'),
                'fullName' => __('Name and surname', 'seeinside-travel'),
                'email' => __('E-mail', 'seeinside-travel'),
                'phone' => __('Phone', 'seeinside-travel'),
                'message' => __('Message (optional)', 'seeinside-travel'),
                'send' => __('Send booking request', 'seeinside-travel'),
                'sending' => __('Checking the price with the hotel…', 'seeinside-travel'),
                'priceChanged' => __('The hotel changed the price to %s. Do you accept the new price?', 'seeinside-travel'),
                'thanks' => __('Thank you! Your booking request was received.', 'seeinside-travel'),
                'thanksNote' => __('We confirm the booking with the hotel and send you the confirmation by e-mail.', 'seeinside-travel'),
                'booked' => __('Your booking is confirmed.', 'seeinside-travel'),
                'reference' => __('Reference number', 'seeinside-travel'),
                'manage' => __('Your booking', 'seeinside-travel'),
                'total' => __('Total', 'seeinside-travel'),
                'checkIn' => __('Check-in', 'seeinside-travel'),
                'checkOut' => __('Check-out', 'seeinside-travel'),
                'close' => __('Close', 'seeinside-travel'),
                'expired' => __('The prices are older than 15 minutes – search again for the current ones.', 'seeinside-travel'),
                'searchAgain' => __('Search again', 'seeinside-travel'),
                'error' => __('Something went wrong. Please try again or call us.', 'seeinside-travel'),
                'needFields' => __('Please fill in the marked fields.', 'seeinside-travel'),
            ],
        ]);
    }

    /** A card exactly like the apartment cards, with __T__ (name), __P__ (place), __U__ (link), __I__ (photo), 5 stars, price 98765 */
    protected static function card_prototype(): string
    {
        if (! class_exists('STA_Cards') || ! class_exists('STA_Items')) {
            return '';
        }
        $it = array_merge(STA_Items::base(), [
            'key' => 'hotel-__ID__', 'kind' => 'post', 'type' => 'accommodation', 'title' => '__T__', 'url' => 'https://sta.invalid/__U__',
            'image' => 'https://sta.invalid/__I__.jpg', 'place' => '__P__', 'stars' => 5, 'price' => 98765, 'currency' => 'EUR',
        ]);

        return STA_Cards::render($it, null, 'accommodation');
    }

    /** [sta_hotels] – search on top, results with filters, hotel page and booking (all on one page) */
    public static function shortcode($atts = []): string
    {
        // the dates are chosen in the filter column ("Your dates"), like the apartments; search="1" adds the old search bar on top
        $a = shortcode_atts(['search' => '0', 'per_page' => 18, 'columns' => 3], $atts, 'sta_hotels');
        if (! self::enabled()) {
            return current_user_can('edit_posts')
                ? '<div class="sta sta-empty">'.esc_html__('Hotel search: add a hotel booking system (Hotelbeds) in the app under Booking systems (API), import destinations and hotels, then "Sync all" here.', 'seeinside-travel').'</div>'
                : '';
        }
        self::enqueue();
        // opened for a country / region / area (menu): the header of the destination pages, when the page has none of its own
        $head = class_exists('STA_Term_Page') && ! STA_Term_Page::$hotels_head ? STA_Term_Page::hotels_header([]) : '';
        $h = $head.'<div class="sta sta-hotels" data-sta-hotels data-per-page="'.(int) $a['per_page'].'" data-columns="'.max(1, (int) $a['columns']).'">';
        if ($a['search']) {
            $h .= '<div class="sta-hotels-search">'.STA_Search_Box::shortcode(['tabs' => 'hotels', 'compact' => '1']).'</div>';
        }

        return $h.'<div class="sta-hotels-body" data-sta-hotels-body aria-live="polite"></div></div>';
    }

    /* ---------------- relay to the app (when this server can reach it) ---------------- */

    public static function routes(): void
    {
        $ns = 'seeinside-travel/v1';
        foreach (['destinations', 'suggest', 'search', 'catalog', 'alternatives'] as $what) {
            register_rest_route($ns, '/hotels/'.$what, ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => fn (WP_REST_Request $r) => self::relay(STA_Api::get('hotels/'.$what, self::query($r), 60))]);
        }
        register_rest_route($ns, '/hotels/hotel', ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
            $q = self::query($r);
            $id = preg_replace('/[^0-9A-Za-z\-]/', '', (string) ($q['id'] ?? ''));
            unset($q['id']);

            return self::relay(STA_Api::get('hotels/'.$id, $q, 60));
        }]);
        register_rest_route($ns, '/hotels/book', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function (WP_REST_Request $r) {
            if ($blocked = STA_Frontend::check($r)) {
                return $blocked;
            }
            $p = (array) ($r->get_json_params() ?: $r->get_body_params());
            $data = array_intersect_key($p, array_flip(['search_id', 'hotel', 'rate_key', 'accept_price', 'query', 'customer', 'passengers', 'message', 'payment_method', 'return_url', 'terms_accepted', 'terms_url']));
            $data['locale'] = sta_lang();
            $data['message'] = trim(($data['message'] ?? '')."\n\n[".home_url('/').']');
            $res = STA_Api::post('hotels/book', $data, 90);
            if ($res[0] === 201) {
                sta_log('hotel booking '.($res[1]['data']['reference'] ?? ''));
            }

            return self::relay($res);
        }]);
    }

    protected static function query(WP_REST_Request $r): array
    {
        $q = [];
        foreach ($r->get_query_params() as $k => $v) {
            if (in_array($k, ['q', 'from', 'to', 'destination', 'hotel', 'room_count', 'adults', 'ages', 'search_id', 'id', 'stars', 'page', 'per_page', 'country', 'zone', 'type', 'board', 'feature'], true)) {
                $q[$k] = sanitize_text_field((string) $v);
            }
        }

        return $q;
    }

    protected static function relay(array $res): WP_REST_Response
    {
        [$code, $body] = $res;
        if ($code === 0) {
            return new WP_REST_Response(['message' => __('Booking system is not reachable at the moment. Please call us.', 'seeinside-travel')], 502);
        }

        return new WP_REST_Response($body, $code);
    }
}
