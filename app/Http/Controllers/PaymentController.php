<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\OnlinePayment;
use App\Services\Payments\Halk;
use App\Services\Payments\OnlinePayments;
use App\Services\Payments\PaymentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/** Card payment: our page → the bank's page (Halk) → back here → result with links to the booking and the website */
class PaymentController extends Controller
{
    protected function find(string $token): OnlinePayment
    {
        abort_unless(strlen($token) >= 40, 404);

        return OnlinePayment::withoutGlobalScopes()->where('token', $token)->firstOrFail();
    }

    protected function lang(Request $r, OnlinePayment $p): string
    {
        $l = $r->query('lang') ?: ($p->payable()->withoutGlobalScopes()->value('locale') ?? 'mk');
        $l = $l === 'en' ? 'en' : 'mk';
        app()->setLocale($l);

        return $l;
    }

    /** Posts the payment form to the bank by itself */
    public function show(Request $request, string $token): Response
    {
        $p = $this->find($token);
        if ($p->status !== 'pending') {
            return redirect()->route('pay.result', $token);
        }
        $agency = Agency::findOrFail($p->agency_id);
        $lang = $this->lang($request, $p);
        abort_unless(Halk::enabled($agency), 404);

        return response()->view('pay.redirect', ['p' => $p, 'agency' => $agency, 'form' => Halk::form($p, $agency, $lang)])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
    }

    /** okUrl / failUrl of the bank */
    public function back(Request $request, string $token): RedirectResponse
    {
        $p = $this->find($token);
        $post = $request->request->all();
        if ($post) {
            $p = OnlinePayments::handle($p, $post);
        }

        return redirect()->route('pay.result', $token);
    }

    public function result(Request $request, string $token): Response
    {
        $p = $this->find($token);
        $lang = $this->lang($request, $p);
        $agency = Agency::findOrFail($p->agency_id);
        $what = $p->payable()->withoutGlobalScopes()->first();

        return response()->view('pay.result', [
            'p' => $p, 'agency' => $agency, 'what' => $what, 'lang' => $lang,
            'portal' => $what instanceof Booking ? PortalController::url($what) : null,
            'back' => $p->return_url,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
    }

    /** Another attempt with the same amount (declined card, closed page …) */
    public function retry(Request $request, string $token): RedirectResponse
    {
        $p = $this->find($token);
        $what = $p->payable()->withoutGlobalScopes()->first();
        abort_if(! $what || $p->status === 'approved', 404);
        $new = PaymentPolicy::start($what, (float) $p->booking_amount, $p->return_url);

        return redirect()->route('pay.show', $new->token);
    }
}
