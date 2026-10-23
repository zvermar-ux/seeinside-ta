<?php

namespace App\Http\Controllers\Admin;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\CashEntry;
use App\Models\Invoice;
use App\Models\TourDeparture;
use App\Models\StayTerm;
use App\Services\Documents\Documents;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/** Printable documents in the admin: /admin/docs/{type}/{id} */
class DocumentController extends Controller
{
    public function show(Request $request, string $type, int $id): Response
    {
        $u = $request->user();
        abort_unless($u, 403);
        // agency scope of the models keeps other agencies' documents out of reach
        $html = match ($type) {
            'contract' => Documents::contract(Booking::query()->findOrFail($id)),
            'voucher' => Documents::voucher(Booking::query()->findOrFail($id)),
            'invoice' => Documents::invoice(Invoice::query()->findOrFail($id)),
            'cash' => Documents::cash(CashEntry::query()->findOrFail($id)),
            'rooming' => $this->rooming($request, $id),
            'journal' => $this->journal($request, $id),
            default => abort(404),
        };
        abort_unless($u->isSuperAdmin() || $u->can(in_array($type, ['invoice', 'cash', 'journal'], true) ? 'finance.view' : 'bookings.view'), 403);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** Cash journal (Дневник на благајна): id = agency, ?register=&from=&to= */
    protected function journal(Request $request, int $id): string
    {
        $u = $request->user();
        abort_unless($u->isSuperAdmin() || (int) $u->agency_id === $id, 403);
        $agency = Agency::findOrFail($id);
        $from = $request->query('from', today()->toDateString());
        $to = $request->query('to', $from);
        $register = (string) $request->query('register', \App\Services\Finance\Finance::registerFor('cash', $agency));
        $data = \App\Services\Finance\Finance::journal($agency->id, $register, $from, $to);

        return Documents::journal($agency, $data, \App\Services\Finance\Finance::registers($agency)[$register] ?? $register, $from, $to);
    }

    /** Rooming list: id = departure (tour) or term (accommodation); ?kind=departure|term */
    protected function rooming(Request $request, int $id): string
    {
        $statuses = ['pending', 'confirmed', 'completed'];
        if ($request->query('kind') === 'term') {
            $t = StayTerm::query()->with('accommodation')->findOrFail($id);
            $bookings = Booking::query()->where('stay_term_id', $t->id)->whereIn('status', $statuses)->with('room')->orderBy('room_number')->get();
            $agency = Agency::findOrFail($t->accommodation->agency_id);
            $heading = $t->accommodation->name.' · '.$t->label();
        } else {
            $d = TourDeparture::query()->with('tour')->findOrFail($id);
            $bookings = Booking::query()->where('tour_departure_id', $d->id)->whereIn('status', $statuses)->with('room')->orderBy('customer_name')->get();
            $agency = Agency::findOrFail($d->tour->agency_id);
            $heading = $d->tour->tr('title').' · '.$d->start_date->format('d.m.Y').($d->end_date ? ' – '.$d->end_date->format('d.m.Y') : '');
        }
        abort_unless($request->user()->isSuperAdmin() || (int) $request->user()->agency_id === (int) $agency->id, 403);

        return Documents::rooming($agency, $bookings, $heading);
    }
}
