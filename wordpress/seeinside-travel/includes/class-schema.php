<?php

defined('ABSPATH') || exit;

/**
 * One place that describes the content types and their fields – the same information as in the
 * SeeInside Travel system. Used for the post types, the ACF field groups and the sync.
 *
 * Text fields exist twice: "name" (Macedonian, main language) and "name_en" (English).
 */
class STA_Schema
{
    /** API type → WordPress post type */
    public const TYPES = [
        'tour' => 'sta_tour',
        'event' => 'sta_event',
        'accommodation' => 'sta_hotel',
        'visa' => 'sta_visa',
        'custom' => 'sta_offer',
    ];

    /** API list endpoints */
    public const ENDPOINTS = [
        'tour' => 'tours',
        'event' => 'events',
        'accommodation' => 'accommodations',
        'visa' => 'visas',
        'custom' => 'custom-records',
    ];

    public static function post_type(string $apiType): ?string
    {
        return self::TYPES[$apiType] ?? null;
    }

    public static function api_type(string $postType): ?string
    {
        $flip = array_flip(self::TYPES);

        return $flip[$postType] ?? null;
    }

    /** Post type labels and default URL slugs */
    public static function post_types(): array
    {
        return [
            'sta_tour' => [
                'singular' => __('Tour', 'seeinside-travel'),
                'plural' => __('Organized tours', 'seeinside-travel'),
                'slug' => 'aranzmani',
                'icon' => 'dashicons-palmtree',
                'taxonomies' => ['sta_destination', 'sta_group', 'sta_tour_type', 'sta_country'],
            ],
            'sta_event' => [
                'singular' => __('Event', 'seeinside-travel'),
                'plural' => __('Events', 'seeinside-travel'),
                'slug' => 'nastani',
                'icon' => 'dashicons-tickets-alt',
                'taxonomies' => ['sta_event_category', 'sta_country'],
            ],
            'sta_hotel' => [
                'singular' => __('Hotel / apartment', 'seeinside-travel'),
                'plural' => __('Hotels & apartments', 'seeinside-travel'),
                'slug' => 'smestuvanje',
                'icon' => 'dashicons-building',
                'taxonomies' => ['sta_destination', 'sta_hotel_type', 'sta_amenity', 'sta_country'],
            ],
            'sta_visa' => [
                'singular' => __('Visa', 'seeinside-travel'),
                'plural' => __('Visas', 'seeinside-travel'),
                'slug' => 'vizi',
                'icon' => 'dashicons-id-alt',
                'taxonomies' => ['sta_country'],
            ],
            // types defined in SeeInside by the super admin (transfers, rent-a-car …)
            'sta_offer' => [
                'singular' => __('Offer', 'seeinside-travel'),
                'plural' => __('Other offers', 'seeinside-travel'),
                'slug' => 'ponudi',
                'icon' => 'dashicons-car',
                'taxonomies' => ['sta_offer_type'],
            ],
        ];
    }

    public static function taxonomies(): array
    {
        return [
            'sta_destination' => ['singular' => __('Destination', 'seeinside-travel'), 'plural' => __('Destinations', 'seeinside-travel'), 'slug' => 'destinacija', 'hierarchical' => true],
            'sta_group' => ['singular' => __('Group', 'seeinside-travel'), 'plural' => __('Groups', 'seeinside-travel'), 'slug' => 'grupa', 'hierarchical' => true],
            'sta_tour_type' => ['singular' => __('Tour type', 'seeinside-travel'), 'plural' => __('Tour types', 'seeinside-travel'), 'slug' => 'tip-na-tura', 'hierarchical' => false],
            'sta_event_category' => ['singular' => __('Event category', 'seeinside-travel'), 'plural' => __('Event categories', 'seeinside-travel'), 'slug' => 'kategorija-nastan', 'hierarchical' => false],
            'sta_hotel_type' => ['singular' => __('Accommodation type', 'seeinside-travel'), 'plural' => __('Accommodation types', 'seeinside-travel'), 'slug' => 'tip-smestuvanje', 'hierarchical' => false],
            'sta_amenity' => ['singular' => __('Amenity', 'seeinside-travel'), 'plural' => __('Amenities', 'seeinside-travel'), 'slug' => 'sodrzina', 'hierarchical' => false],
            'sta_offer_type' => ['singular' => __('Offer type', 'seeinside-travel'), 'plural' => __('Offer types', 'seeinside-travel'), 'slug' => 'tip-ponuda', 'hierarchical' => false],
            'sta_country' => ['singular' => __('Country', 'seeinside-travel'), 'plural' => __('Countries', 'seeinside-travel'), 'slug' => 'drzava', 'hierarchical' => false],
        ];
    }

    /* ------------------------------------------------------------------
     * Field definitions: [name, type, label, extra]
     * types: text, textarea, wysiwyg, number, true_false, date, datetime, url, image, gallery, repeater, select
     * ------------------------------------------------------------------ */

    protected static function f(string $name, string $type, string $label, array $extra = []): array
    {
        return ['name' => $name, 'type' => $type, 'label' => $label] + $extra;
    }

    /** text field in both languages */
    protected static function both(string $name, string $type, string $label): array
    {
        return [self::f($name, $type, $label.' (МК)'), self::f($name.'_en', $type, $label.' (EN)')];
    }

    protected static function tab(string $label): array
    {
        return ['name' => 'tab_'.sanitize_title($label), 'type' => 'tab', 'label' => $label];
    }

    /** Fields every type has */
    protected static function common_start(): array
    {
        return array_merge(
            [self::tab(__('Basic', 'seeinside-travel'))],
            [self::f('title_en', 'text', __('Title (EN)', 'seeinside-travel'))],
            self::both('short_description', 'textarea', __('Short description', 'seeinside-travel')),
            [self::f('description_en', 'wysiwyg', __('Description (EN) – the Macedonian description is the post content', 'seeinside-travel'))],
        );
    }

    protected static function common_end(): array
    {
        return array_merge(
            [self::tab(__('Photos & video', 'seeinside-travel'))],
            [
                self::f('gallery', 'gallery', __('Gallery', 'seeinside-travel')),
                self::f('videos', 'repeater', __('Videos', 'seeinside-travel'), ['sub_fields' => [self::f('url', 'url', 'URL')]]),
            ],
            [self::tab(__('Last minute / First minute', 'seeinside-travel'))],
            [
                self::f('is_last_minute', 'true_false', 'Last minute'),
                self::f('last_minute_price', 'number', __('Last minute price', 'seeinside-travel')),
                self::f('last_minute_discount', 'number', __('Last minute discount %', 'seeinside-travel')),
                self::f('last_minute_until', 'date', __('Last minute until', 'seeinside-travel')),
                self::f('first_minute_discount', 'number', __('First minute discount', 'seeinside-travel')),
                self::f('first_minute_type', 'text', __('First minute discount type (amount / percent)', 'seeinside-travel')),
                self::f('first_minute_until', 'date', __('First minute until', 'seeinside-travel')),
                self::f('is_featured', 'true_false', __('Featured', 'seeinside-travel')),
            ],
            [self::tab('SEO')],
            self::both('seo_title', 'text', __('SEO title', 'seeinside-travel')),
            self::both('seo_description', 'textarea', __('SEO description', 'seeinside-travel')),
            [self::tab(__('Sync', 'seeinside-travel'))],
            [
                self::f('sta_id', 'number', __('ID in SeeInside', 'seeinside-travel'), ['readonly' => 1]),
                self::f('sta_updated_at', 'text', __('Last change in SeeInside', 'seeinside-travel'), ['readonly' => 1]),
                self::f('booking_enabled', 'true_false', __('Online booking enabled', 'seeinside-travel')),
                self::f('booking_mode', 'text', __('Booking mode', 'seeinside-travel')),
            ],
        );
    }

    protected static function lines(string $name, string $label): array
    {
        // one item per line
        return self::both($name, 'textarea', $label.' – '.__('one per line', 'seeinside-travel'));
    }

    public static function fields(string $postType): array
    {
        return match ($postType) {
            'sta_tour' => array_merge(
                self::common_start(),
                self::both('subtitle', 'text', __('Subtitle', 'seeinside-travel')),
                [
                    self::f('tour_type', 'text', __('Tour type', 'seeinside-travel')),
                    self::f('location', 'text', __('Location', 'seeinside-travel')),
                    self::f('country', 'text', __('Country', 'seeinside-travel')),
                    self::f('duration_days', 'number', __('Duration (days)', 'seeinside-travel')),
                    self::f('duration_nights', 'number', __('Nights', 'seeinside-travel')),
                    self::f('group_size_min', 'number', __('Group size from', 'seeinside-travel')),
                    self::f('group_size_max', 'number', __('Group size to', 'seeinside-travel')),
                    self::f('map', 'text', __('Map (lat,lng or address)', 'seeinside-travel')),
                    self::f('aries_quality', 'true_false', __('Aries quality', 'seeinside-travel')),
                    self::f('has_guide', 'true_false', __('Tour leader', 'seeinside-travel')),
                    self::f('is_flight', 'true_false', __('Flight', 'seeinside-travel')),
                    self::f('airline_name', 'text', __('Airline', 'seeinside-travel')),
                    self::f('airline_iata', 'text', 'IATA'),
                    self::f('airline_logo', 'url', __('Airline logo', 'seeinside-travel')),
                    self::tab(__('Prices & dates', 'seeinside-travel')),
                    self::f('price_from', 'number', __('Price from', 'seeinside-travel')),
                    self::f('currency', 'text', __('Currency', 'seeinside-travel')),
                    self::f('price_adult', 'number', __('Price per adult', 'seeinside-travel')),
                    self::f('price_child', 'number', __('Price per child', 'seeinside-travel')),
                    self::f('child_age_from', 'number', __('Child age from', 'seeinside-travel')),
                    self::f('child_age_to', 'number', __('Child age to', 'seeinside-travel')),
                    self::f('single_supplement', 'number', __('Single room supplement', 'seeinside-travel')),
                    self::f('deposit_percent', 'number', __('Deposit %', 'seeinside-travel')),
                    self::f('next_departure', 'date', __('Next departure', 'seeinside-travel')),
                    self::f('departures', 'repeater', __('Departure dates', 'seeinside-travel'), ['sub_fields' => [
                        self::f('departure_id', 'number', 'ID'),
                        self::f('start_date', 'date', __('Departure', 'seeinside-travel')),
                        self::f('end_date', 'date', __('Return', 'seeinside-travel')),
                        self::f('price_adult', 'number', __('Adult', 'seeinside-travel')),
                        self::f('price_child', 'number', __('Child', 'seeinside-travel')),
                        self::f('seats_left', 'number', __('Seats left', 'seeinside-travel')),
                        self::f('status', 'text', __('Status', 'seeinside-travel')),
                        self::f('status_label', 'text', __('Status (MK)', 'seeinside-travel')),
                        self::f('status_label_en', 'text', __('Status (EN)', 'seeinside-travel')),
                        self::f('is_last_minute', 'true_false', 'Last minute'),
                        self::f('note', 'text', __('Note', 'seeinside-travel')),
                    ]]),
                    self::f('departure_places', 'repeater', __('Departure places', 'seeinside-travel'), ['sub_fields' => [
                        self::f('place_id', 'number', 'ID'),
                        self::f('name', 'text', __('Place (MK)', 'seeinside-travel')),
                        self::f('name_en', 'text', __('Place (EN)', 'seeinside-travel')),
                        self::f('time', 'text', __('Time', 'seeinside-travel')),
                        self::f('surcharge', 'number', __('Surcharge', 'seeinside-travel')),
                    ]]),
                    self::f('extras', 'repeater', __('Extras & excursions', 'seeinside-travel'), ['sub_fields' => [
                        self::f('extra_id', 'number', 'ID'),
                        self::f('name', 'text', __('Name (MK)', 'seeinside-travel')),
                        self::f('name_en', 'text', __('Name (EN)', 'seeinside-travel')),
                        self::f('description', 'textarea', __('Description (MK)', 'seeinside-travel')),
                        self::f('description_en', 'textarea', __('Description (EN)', 'seeinside-travel')),
                        self::f('price', 'number', __('Price', 'seeinside-travel')),
                        self::f('price_type', 'text', __('Price type', 'seeinside-travel')),
                        self::f('kind', 'text', __('Kind', 'seeinside-travel')),
                        self::f('optional', 'true_false', __('Optional', 'seeinside-travel')),
                    ]]),
                    self::tab(__('Program', 'seeinside-travel')),
                    self::f('program', 'repeater', __('Program by days', 'seeinside-travel'), ['sub_fields' => [
                        self::f('day', 'number', __('Day', 'seeinside-travel')),
                        self::f('title', 'text', __('Title (MK)', 'seeinside-travel')),
                        self::f('title_en', 'text', __('Title (EN)', 'seeinside-travel')),
                        self::f('description', 'wysiwyg', __('Description (MK)', 'seeinside-travel')),
                        self::f('description_en', 'wysiwyg', __('Description (EN)', 'seeinside-travel')),
                        self::f('meals', 'text', __('Meals', 'seeinside-travel')),
                        self::f('overnight', 'text', __('Overnight', 'seeinside-travel')),
                    ]]),
                    self::f('program_pdf', 'url', __('Program (PDF)', 'seeinside-travel')),
                ],
                self::lines('highlights', __('Highlights', 'seeinside-travel')),
                self::both('agenda', 'wysiwyg', __('Agenda', 'seeinside-travel')),
                [self::tab(__('Included & info', 'seeinside-travel'))],
                self::lines('included', __('Included', 'seeinside-travel')),
                self::lines('not_included', __('Not included', 'seeinside-travel')),
                self::both('notes', 'wysiwyg', __('Note', 'seeinside-travel')),
                self::both('important_info', 'wysiwyg', __('Important information', 'seeinside-travel')),
                self::both('baggage_info', 'wysiwyg', __('Baggage', 'seeinside-travel')),
                self::both('payment_terms', 'wysiwyg', __('Payment terms', 'seeinside-travel')),
                self::both('cancellation_policy', 'wysiwyg', __('Cancellation policy', 'seeinside-travel')),
                [self::f('links', 'repeater', __('Links', 'seeinside-travel'), ['sub_fields' => [self::f('label', 'text', __('Label', 'seeinside-travel')), self::f('url', 'url', 'URL')]])],
                self::common_end(),
            ),
            'sta_event' => array_merge(
                self::common_start(),
                [
                    self::f('category', 'text', __('Category', 'seeinside-travel')),
                    self::f('event_status', 'text', __('Status', 'seeinside-travel')),
                    self::f('performer', 'text', __('Performer / teams', 'seeinside-travel')),
                    self::f('starts_at', 'datetime', __('Starts', 'seeinside-travel')),
                    self::f('ends_at', 'datetime', __('Ends', 'seeinside-travel')),
                    self::f('doors_open', 'text', __('Doors open', 'seeinside-travel')),
                    self::f('venue', 'text', __('Venue', 'seeinside-travel')),
                    self::f('city', 'text', __('City', 'seeinside-travel')),
                    self::f('country', 'text', __('Country', 'seeinside-travel')),
                    self::f('address', 'text', __('Address', 'seeinside-travel')),
                    self::f('map', 'text', __('Map (lat,lng or address)', 'seeinside-travel')),
                    self::f('organizer', 'text', __('Organizer', 'seeinside-travel')),
                    self::f('age_restriction', 'text', __('Age restriction', 'seeinside-travel')),
                    self::tab(__('Tickets', 'seeinside-travel')),
                    self::f('price_from', 'number', __('Price from', 'seeinside-travel')),
                    self::f('currency', 'text', __('Currency', 'seeinside-travel')),
                    self::f('tickets', 'repeater', __('Tickets', 'seeinside-travel'), ['sub_fields' => [
                        self::f('ticket_id', 'number', 'ID'),
                        self::f('name', 'text', __('Name (MK)', 'seeinside-travel')),
                        self::f('name_en', 'text', __('Name (EN)', 'seeinside-travel')),
                        self::f('description', 'textarea', __('Description (MK)', 'seeinside-travel')),
                        self::f('description_en', 'textarea', __('Description (EN)', 'seeinside-travel')),
                        self::f('price', 'number', __('Price', 'seeinside-travel')),
                        self::f('available', 'number', __('Available', 'seeinside-travel')),
                    ]]),
                    self::f('external_ticket_url', 'url', __('External ticket link', 'seeinside-travel')),
                    self::f('related_tour', 'text', __('Related tour (slug)', 'seeinside-travel')),
                ],
                [self::tab(__('Included & info', 'seeinside-travel'))],
                self::lines('included', __('Included', 'seeinside-travel')),
                self::lines('not_included', __('Not included', 'seeinside-travel')),
                self::both('notes', 'wysiwyg', __('Note', 'seeinside-travel')),
                self::common_end(),
            ),
            'sta_hotel' => array_merge(
                self::common_start(),
                [
                    self::f('accommodation_type', 'text', __('Type', 'seeinside-travel')),
                    self::f('stars', 'number', __('Stars', 'seeinside-travel')),
                    self::f('place', 'text', __('Place', 'seeinside-travel')),
                    self::f('region', 'text', __('Region', 'seeinside-travel')),
                    self::f('country', 'text', __('Country', 'seeinside-travel')),
                    self::f('address', 'text', __('Address', 'seeinside-travel')),
                    self::f('map', 'text', __('Map (lat,lng or address)', 'seeinside-travel')),
                    self::f('check_in', 'text', __('Check-in', 'seeinside-travel')),
                    self::f('check_out', 'text', __('Check-out', 'seeinside-travel')),
                    self::f('distance_beach_m', 'number', __('Distance to beach (m)', 'seeinside-travel')),
                    self::f('distance_center_m', 'number', __('Distance to centre (m)', 'seeinside-travel')),
                    self::f('distance_ski_lift_m', 'number', __('Distance to ski lift (m)', 'seeinside-travel')),
                    self::f('distance_airport_km', 'number', __('Distance to airport (km)', 'seeinside-travel')),
                    self::f('board_types', 'text', __('Board types', 'seeinside-travel')),
                    self::tab(__('Rooms & prices', 'seeinside-travel')),
                    self::f('price_from', 'number', __('Price from', 'seeinside-travel')),
                    self::f('currency', 'text', __('Currency', 'seeinside-travel')),
                    self::f('rooms', 'repeater', __('Rooms', 'seeinside-travel'), ['sub_fields' => [
                        self::f('room_id', 'number', 'ID'),
                        self::f('name', 'text', __('Name (MK)', 'seeinside-travel')),
                        self::f('name_en', 'text', __('Name (EN)', 'seeinside-travel')),
                        self::f('room_type', 'text', __('Room type', 'seeinside-travel')),
                        self::f('board', 'text', __('Board', 'seeinside-travel')),
                        self::f('max_adults', 'number', __('Max adults', 'seeinside-travel')),
                        self::f('max_children', 'number', __('Max children', 'seeinside-travel')),
                        self::f('beds', 'text', __('Beds', 'seeinside-travel')),
                        self::f('size_m2', 'number', 'm²'),
                        self::f('description', 'textarea', __('Description (MK)', 'seeinside-travel')),
                        self::f('description_en', 'textarea', __('Description (EN)', 'seeinside-travel')),
                        self::f('room_amenities', 'text', __('Room amenities', 'seeinside-travel')),
                        self::f('price', 'number', __('Price per night', 'seeinside-travel')),
                        self::f('price_type', 'text', __('Price is', 'seeinside-travel')),
                        self::f('image', 'image', __('Main photo', 'seeinside-travel')),
                        self::f('room_gallery', 'gallery', __('Gallery', 'seeinside-travel')),
                        self::f('rates', 'repeater', __('Seasonal prices', 'seeinside-travel'), ['sub_fields' => [
                            self::f('from', 'date', __('From', 'seeinside-travel')),
                            self::f('to', 'date', __('To', 'seeinside-travel')),
                            self::f('price', 'number', __('Price', 'seeinside-travel')),
                            self::f('price_type', 'text', __('Price is', 'seeinside-travel')),
                            self::f('min_nights', 'number', __('Min. nights', 'seeinside-travel')),
                            self::f('is_last_minute', 'true_false', 'Last minute'),
                        ]]),
                    ]]),
                    self::f('price_list_pdf', 'url', __('Price list (PDF)', 'seeinside-travel')),
                    self::tab(__('Rules & info', 'seeinside-travel')),
                ],
                self::both('house_rules', 'wysiwyg', __('House rules', 'seeinside-travel')),
                self::both('notes', 'wysiwyg', __('Note', 'seeinside-travel')),
                self::both('cancellation_policy', 'wysiwyg', __('Cancellation policy', 'seeinside-travel')),
                self::common_end(),
            ),
            'sta_visa' => array_merge(
                self::common_start(),
                [
                    self::f('country', 'text', __('Country', 'seeinside-travel')),
                    self::f('country_code', 'text', __('Country code', 'seeinside-travel')),
                    self::f('category', 'text', __('Visa type', 'seeinside-travel')),
                    self::f('price', 'number', __('Total price', 'seeinside-travel')),
                    self::f('currency', 'text', __('Currency', 'seeinside-travel')),
                    self::f('fee_service', 'number', __('Service fee', 'seeinside-travel')),
                    self::f('fee_consular', 'number', __('Consular fee', 'seeinside-travel')),
                    self::f('fee_express', 'number', __('Express fee', 'seeinside-travel')),
                    self::f('online_payment', 'true_false', __('Online payment', 'seeinside-travel')),
                ],
                self::both('processing_time', 'text', __('Processing time', 'seeinside-travel')),
                self::both('validity', 'text', __('Validity', 'seeinside-travel')),
                self::lines('requirements', __('Requirements', 'seeinside-travel')),
                [
                    self::tab(__('Form', 'seeinside-travel')),
                    self::f('questionnaire_json', 'textarea', __('Questionnaire (managed in SeeInside)', 'seeinside-travel'), ['readonly' => 1]),
                    self::f('documents_json', 'textarea', __('Required documents (managed in SeeInside)', 'seeinside-travel'), ['readonly' => 1]),
                ],
                self::common_end(),
            ),
            'sta_offer' => array_merge(
                self::common_start(),
                [
                    self::f('record_type', 'text', __('Offer type', 'seeinside-travel')),
                    self::f('price_from', 'number', __('Price from', 'seeinside-travel')),
                    self::f('currency', 'text', __('Currency', 'seeinside-travel')),
                ],
                self::common_end(),
            ),
            default => [],
        };
    }
}
