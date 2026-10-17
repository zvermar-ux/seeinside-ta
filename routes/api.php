<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Middleware\AuthenticateApiClient;
use Illuminate\Support\Facades\Route;

/*
| Website API (WordPress plugin). All routes need the website's API key:
|   X-Api-Key: ta_xxxxxxxx_...
| Prefix: /api/v1
*/

Route::middleware([AuthenticateApiClient::class, 'throttle:240,1'])->group(function () {
    Route::get('meta', [CatalogController::class, 'meta']);
    Route::get('changes', [CatalogController::class, 'changes']);
    Route::get('last-minute', [CatalogController::class, 'lastMinute']);

    Route::get('tours', [CatalogController::class, 'tours']);
    Route::get('tours/{slug}', [CatalogController::class, 'tour']);

    Route::get('events', [CatalogController::class, 'events']);
    Route::get('events/{slug}', [CatalogController::class, 'event']);

    Route::get('accommodations', [CatalogController::class, 'accommodations']);
    Route::get('accommodations/{slug}', [CatalogController::class, 'accommodation']);
    Route::get('accommodations/{slug}/quote', [CatalogController::class, 'quote']);

    Route::get('visas', [CatalogController::class, 'visas']);
    Route::get('visas/{slug}', [CatalogController::class, 'visa']);

    Route::post('bookings', [BookingController::class, 'store'])->middleware('throttle:20,1');
    Route::post('visa-applications', [BookingController::class, 'visaApplication'])->middleware('throttle:10,1');
});
