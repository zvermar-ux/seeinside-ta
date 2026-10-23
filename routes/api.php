<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ChatController;
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
    Route::get('accommodations/{slug}/quote', [CatalogController::class, 'quote']);
    Route::get('accommodations/{slug}/availability', [CatalogController::class, 'availability']);
    Route::get('accommodations/{slug}/terms', [CatalogController::class, 'terms']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::post('visa-applications', [BookingController::class, 'visaApplication']);
    Route::get('bookings/{reference}', [BookingController::class, 'status']);
    Route::get('visa-applications/{reference}', [BookingController::class, 'visaStatus']);
    Route::post('reviews', [ReviewController::class, 'store']);

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
    Route::get('charters', [CatalogController::class, 'charters']);
    Route::get('reviews', [ReviewController::class, 'index']);
    Route::get('chat', [CatalogController::class, 'chat']);
    Route::post('reviews', [ReviewController::class, 'store'])->middleware('throttle:website-forms');
    Route::get('bookings/{reference}', [BookingController::class, 'status'])->middleware('throttle:website-forms');
    Route::get('visa-applications/{reference}', [BookingController::class, 'visaStatus'])->middleware('throttle:website-forms');

    Route::get('tours', [CatalogController::class, 'tours']);
    Route::get('tours/{slug}', [CatalogController::class, 'tour']);

    Route::get('events', [CatalogController::class, 'events']);
    Route::get('events/{slug}', [CatalogController::class, 'event']);

    Route::get('accommodations', [CatalogController::class, 'accommodations']);
    Route::get('accommodations/{slug}', [CatalogController::class, 'accommodation']);
    Route::get('accommodations/{slug}/quote', [CatalogController::class, 'quote']);
    Route::get('accommodations/{slug}/availability', [CatalogController::class, 'availability']);
    Route::get('accommodations/{slug}/terms', [CatalogController::class, 'terms']);

    Route::get('visas', [CatalogController::class, 'visas']);
    Route::get('visas/{slug}', [CatalogController::class, 'visa']);

    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:website-forms');
    Route::post('visa-applications', [BookingController::class, 'visaApplication'])->middleware('throttle:website-forms');
});
