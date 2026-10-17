<?php

return [
    // Base URL of the Aries administration (where login.php and operacii.php live)
    'url' => env('ARIES_URL', 'http://aries.travel/konfiguracija/'),
    'username' => env('ARIES_USERNAME'),
    'password' => env('ARIES_PASSWORD'),
    'timeout' => (int) env('ARIES_TIMEOUT', 60),
];
