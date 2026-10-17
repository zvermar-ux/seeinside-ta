<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(array_key_exists($locale, SetLocale::LOCALES), 404);

    $request->session()->put('locale', $locale);

    if ($user = $request->user()) {
        $user->forceFill(['locale' => $locale])->save();
    }

    return redirect()->back(fallback: '/admin');
})->name('locale.switch');
