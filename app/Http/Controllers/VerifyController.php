<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/** Page behind the QR code of a voucher: is the booking valid? (for hotels, guides, bus) */
class VerifyController extends Controller
{
    protected function booking(string $reference, string $sig): Booking
    {
        abort_unless(hash_equals(Booking::verifySig($reference), $sig), 404);

        return Booking::withoutGlobalScopes()->where('reference', $reference)->with(['bookable', 'room', 'departure'])->firstOrFail();
    }

    public function show(Request $request, string $reference, string $sig): Response
    {
        $b = $this->booking($reference, $sig);
        $agency = Agency::findOrFail($b->agency_id);
        $u = $request->user();
        $staff = $u && ($u->isSuperAdmin() || (int) $u->agency_id === (int) $b->agency_id);
        app()->setLocale($request->query('lang') === 'en' || $b->locale === 'en' ? 'en' : 'mk');
        $valid = in_array($b->status, [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::Pending], true);

        return response()->view('portal.verify', compact('b', 'agency', 'staff', 'valid'))
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function png(string $reference, string $sig): Response
    {
        $b = $this->booking($reference, $sig);

        return response(QrCode::png($b->verifyUrl(), 6))->header('Content-Type', 'image/png')->header('Cache-Control', 'public, max-age=604800');
    }
}
