<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Middleware\AuthenticateApiClient;
use Illuminate\Support\Facades\Route;

/*
| Website API (WordPress plugin). All routes need the website's API key:
|   X-Api-Key: ta_xxxxxxxx_...
| Prefix: /api/v1
| Limits are per website (all visitors of a site come from the site's server); the WordPress plugin
| additionally limits each visitor.
*/

// Called from the visitor's browser (push mode): forms, prices, availability, booking status
Route::prefix('public/{site}')->middleware([\App\Http\Middleware\AuthenticatePublicSite::class, 'throttle:website-visitor'])->group(function () {
    Route::get('accommodations/{slug}/quote', [CatalogController::class, 'quote'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations/{slug}/availability', [CatalogController::class, 'availability'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations/{slug}/terms', [CatalogController::class, 'terms'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations-available', [CatalogController::class, 'available'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations')->middleware('throttle:60,1');
    Route::post('bookings', [BookingController::class, 'store'])->middleware(\App\Http\Middleware\AgencyModule::class);
    Route::post('visa-applications', [BookingController::class, 'visaApplication'])->middleware(\App\Http\Middleware\AgencyModule::class.':visas');
    Route::get('bookings/{reference}', [BookingController::class, 'status']);
    Route::get('visa-applications/{reference}', [BookingController::class, 'visaStatus']);
    Route::post('reviews/verify', [ReviewController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('reviews', [ReviewController::class, 'store'])->middleware('throttle:10,10');
    Route::post('photo-stories/verify', [\App\Http\Controllers\Api\PhotoStoryController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('photo-stories', [\App\Http\Controllers\Api\PhotoStoryController::class, 'store'])->middleware('throttle:5,10');

    // hotels from booking systems (Hotelbeds…): live prices, so own limits
    Route::get('hotels/destinations', [HotelController::class, 'destinations']);
    Route::get('hotels/suggest', [HotelController::class, 'suggest'])->middleware('throttle:120,1');
    Route::get('hotels/catalog', [HotelController::class, 'catalog'])->middleware('throttle:120,1');
    Route::get('hotels/search', [HotelController::class, 'search'])->middleware('throttle:30,1');
    Route::get('hotels/{id}', [HotelController::class, 'show'])->middleware('throttle:60,1');
    Route::post('hotels/book', [HotelController::class, 'book'])->middleware('throttle:10,1');

});

// Live chat widget (visitor's browser): own limits, chat messages are frequent
Route::prefix('public/{site}/chat')->middleware([\App\Http\Middleware\AuthenticatePublicSite::class])->group(function () {
    Route::get('config', [ChatController::class, 'config'])->middleware('throttle:chat-poll');
    Route::get('poll', [ChatController::class, 'poll'])->middleware('throttle:chat-poll');
    Route::post('start', [ChatController::class, 'start'])->middleware('throttle:chat');
    Route::post('message', [ChatController::class, 'message'])->middleware('throttle:chat');
    Route::post('questionnaire', [ChatController::class, 'questionnaire'])->middleware('throttle:chat');
    Route::post('contact', [ChatController::class, 'contact'])->middleware('throttle:chat');
    Route::post('close', [ChatController::class, 'close'])->middleware('throttle:chat');
});

Route::middleware([AuthenticateApiClient::class, 'throttle:website'])->group(function () {
    Route::get('meta', [CatalogController::class, 'meta']);
    Route::get('changes', [CatalogController::class, 'changes']);
    Route::get('last-minute', [CatalogController::class, 'lastMinute']);
    Route::get('slider', [CatalogController::class, 'slider']);
    Route::get('charters', [CatalogController::class, 'charters'])->middleware(\App\Http\Middleware\AgencyModule::class.':charters');
    Route::get('reviews', [ReviewController::class, 'index'])->middleware(\App\Http\Middleware\AgencyModule::class.':reviews');
    Route::get('photo-stories', [\App\Http\Controllers\Api\PhotoStoryController::class, 'index']);
    Route::post('photo-stories/verify', [\App\Http\Controllers\Api\PhotoStoryController::class, 'verify'])->middleware('throttle:website-forms');
    Route::post('photo-stories', [\App\Http\Controllers\Api\PhotoStoryController::class, 'store'])->middleware('throttle:website-forms');
    Route::get('chat', [CatalogController::class, 'chat']);
    Route::post('reviews/verify', [ReviewController::class, 'verify'])->middleware('throttle:website-forms');
    Route::post('reviews', [ReviewController::class, 'store'])->middleware('throttle:website-forms');
    Route::get('bookings/{reference}', [BookingController::class, 'status'])->middleware('throttle:website-forms');
    Route::get('visa-applications/{reference}', [BookingController::class, 'visaStatus'])->middleware('throttle:website-forms');

    Route::get('tours', [CatalogController::class, 'tours'])->middleware(\App\Http\Middleware\AgencyModule::class.':tours');
    Route::get('tours/{slug}', [CatalogController::class, 'tour'])->middleware(\App\Http\Middleware\AgencyModule::class.':tours');

    Route::get('events', [CatalogController::class, 'events'])->middleware(\App\Http\Middleware\AgencyModule::class.':events');
    Route::get('events/{slug}', [CatalogController::class, 'event'])->middleware(\App\Http\Middleware\AgencyModule::class.':events');

    Route::get('accommodations', [CatalogController::class, 'accommodations'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations/{slug}', [CatalogController::class, 'accommodation'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations/{slug}/quote', [CatalogController::class, 'quote'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations/{slug}/availability', [CatalogController::class, 'availability'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations/{slug}/terms', [CatalogController::class, 'terms'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations');
    Route::get('accommodations-available', [CatalogController::class, 'available'])->middleware(\App\Http\Middleware\AgencyModule::class.':accommodations')->middleware('throttle:60,1');

    Route::get('hotels/destinations', [HotelController::class, 'destinations']);
    Route::get('hotels/suggest', [HotelController::class, 'suggest']);
    Route::get('hotels/catalog', [HotelController::class, 'catalog']);
    Route::get('hotels/search', [HotelController::class, 'search'])->middleware('throttle:60,1');
    Route::get('hotels/{id}', [HotelController::class, 'show']);
    Route::post('hotels/book', [HotelController::class, 'book'])->middleware('throttle:website-forms');

    Route::get('custom-records', [CatalogController::class, 'customRecords']);
    Route::get('custom-records/{slug}', [CatalogController::class, 'customRecord']);

    Route::get('visas', [CatalogController::class, 'visas'])->middleware(\App\Http\Middleware\AgencyModule::class.':visas');
    Route::get('visas/{slug}', [CatalogController::class, 'visa'])->middleware(\App\Http\Middleware\AgencyModule::class.':visas');

    Route::post('bookings', [BookingController::class, 'store'])->middleware(\App\Http\Middleware\AgencyModule::class)->middleware('throttle:website-forms');
    Route::post('visa-applications', [BookingController::class, 'visaApplication'])->middleware(\App\Http\Middleware\AgencyModule::class.':visas')->middleware('throttle:website-forms');
});
