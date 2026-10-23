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

// Private visa documents (only for logged-in staff of the same agency)
Route::get('/admin/visa-documents/{application}/{index}', function (\App\Models\VisaApplication $application, int $index) {
    if (! auth()->check()) {
        return redirect('/admin/login');
    }
    abort_unless(auth()->user()->can('visa_applications.view'), 403);
    $doc = $application->documents[$index] ?? abort(404);
    abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($doc['path'] ?? ''), 404);

    return \Illuminate\Support\Facades\Storage::disk('local')->download($doc['path'], $doc['name'] ?? basename($doc['path']));
})->name('admin.visa-document');

// "Install as an app" on phones / desktop (name, colour and icon from Settings → Appearance)
Route::get('/manifest.webmanifest', function () {
    $b = \App\Support\Branding::class;
    $icon = $b::url($b::get('app_icon'));

    return response()->json(array_filter([
        'name' => $b::get('app_name') ?: config('app.name'),
        'short_name' => \Illuminate\Support\Str::limit($b::get('app_name') ?: config('app.name'), 12, ''),
        'start_url' => '/admin',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => $b::primaryHex(),
        'icons' => $icon ? [
            ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ] : null,
    ]), 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
})->name('app.manifest');

// Media library picker (JSON for the "From library" button)
Route::get('/admin/library/browse', [\App\Http\Controllers\Admin\LibraryController::class, 'browse'])
    ->middleware(['web', 'auth'])->name('library.browse');

// Printable documents: contract, voucher, invoice, cash receipt, rooming list
Route::get('/admin/docs/{type}/{id}', [\App\Http\Controllers\Admin\DocumentController::class, 'show'])
    ->middleware(['web', 'auth'])->whereNumber('id')->name('docs.show');
