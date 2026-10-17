<?php

namespace App\Http\Middleware;

use App\Support\Branding;
use Closure;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Per-request appearance settings that Filament reads outside the page layout (also on Livewire updates) */
class ApplyBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::user();   // resolve the user so the agency's own look is used

        [$vertical, $horizontal] = explode('-', (string) Branding::get('notifications_position', 'top-right')) + [1 => 'right'];
        Notifications::verticalAlignment($vertical === 'bottom' ? VerticalAlignment::End : VerticalAlignment::Start);
        Notifications::alignment(match ($horizontal) {
            'left' => Alignment::Start,
            'center' => Alignment::Center,
            default => Alignment::End,
        });

        return $next($request);
    }
}
