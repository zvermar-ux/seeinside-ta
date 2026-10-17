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
