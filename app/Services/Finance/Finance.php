<?php

namespace App\Services\Finance;

use App\Models\Agency;
use App\Models\BookingPayment;
use App\Models\CashEntry;
use App\Models\Invoice;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;

/** Money movements: payments of invoices, payments to suppliers, cash registers */
class Finance
{
    /** Cash registers of the agency + the bank account */
    public static function registers(?Agency $agency): array
    {
        $regs = (array) ($agency?->doc('registers') ?? ['main' => 'Главна благајна']);

        return $regs + ['bank' => __('Bank account')];
    }

    public static function registerFor(string $method, ?Agency $agency): string
    {
        if ($method === 'bank') {
            return 'bank';
        }

        return (string) array_key_first((array) ($agency?->doc('registers') ?? ['main' => 1]));
    }

    /**
     * The client paid (part of) an invoice. Invoice of a booking → a booking payment (which makes the cash document);
     * otherwise only a cash / bank document is written.
     */
    public static function payInvoice(Invoice $invoice, float $amount, string $date, string $method, ?string $register = null, ?string $note = null): void
    {
        DB::transaction(function () use ($invoice, $amount, $date, $method, $register, $note) {
            $agency = Agency::find($invoice->agency_id);
            if ($invoice->booking) {
                BookingPayment::$register = $register;
                BookingPayment::create([
                    'agency_id' => $invoice->agency_id, 'booking_id' => $invoice->booking_id, 'invoice_id' => $invoice->id,
                    'paid_at' => $date, 'method' => $method, 'currency' => $invoice->currency, 'original_amount' => $amount,
                    'exchange_rate' => $invoice->exchange_rate,
                    // same currency as the booking: no conversion needed
                    'amount' => $invoice->currency === $invoice->booking->currency ? $amount : 0,
                    'reference' => $invoice->number, 'note' => $note,
                ]);
                BookingPayment::$register = null;
            } else {
                self::cash($invoice, $amount, $date, $method, $register ?? self::registerFor($method, $agency), $note);
            }
            $invoice->refresh()->syncPaid();
        });
    }

    protected static function cash(Invoice $invoice, float $amount, string $date, string $method, string $register, ?string $note): CashEntry
    {
        return CashEntry::create([
            'agency_id' => $invoice->agency_id, 'direction' => 'in', 'register' => $register, 'entry_at' => $date.' '.now()->format('H:i:s'),
            'amount' => $amount, 'currency' => $invoice->currency, 'exchange_rate' => $invoice->exchange_rate, 'method' => $method,
            'category' => $invoice->booking_id ? 'arrangement' : 'other', 'party_name' => $invoice->client_name, 'client_id' => $invoice->client_id,
            'booking_id' => $invoice->booking_id, 'invoice_id' => $invoice->id,
            'purpose' => trim($invoice->title().($note ? ' · '.$note : '')),
        ]);
    }

    /** The agency paid a supplier invoice → cash payout (Каса исплати) or bank payment */
    public static function paySupplier(SupplierInvoice $si, float $amount, string $date, string $method, ?string $register = null, ?string $note = null): CashEntry
    {
        return DB::transaction(function () use ($si, $amount, $date, $method, $register, $note) {
            $agency = Agency::find($si->agency_id);
            $entry = CashEntry::create([
                'agency_id' => $si->agency_id, 'direction' => 'out', 'register' => $register ?? self::registerFor($method, $agency),
                'entry_at' => $date.' '.now()->format('H:i:s'), 'amount' => $amount, 'currency' => $si->currency, 'exchange_rate' => $si->exchange_rate,
                'method' => $method, 'category' => 'supplier', 'party_name' => $si->supplier_name, 'client_id' => $si->client_id,
                'booking_id' => $si->booking_id, 'supplier_invoice_id' => $si->id,
                'purpose' => trim(__('Incoming invoice').' '.$si->number.($note ? ' · '.$note : '')),
            ]);
            self::syncSupplier($si);

            return $entry;
        });
    }

    public static function syncSupplier(SupplierInvoice $si): void
    {
        $si->paid_amount = round((float) CashEntry::withoutGlobalScopes()->where('supplier_invoice_id', $si->id)->where('direction', 'out')->sum('amount'), 2);
        $si->saveQuietly();
    }

    /**
     * Cash journal (Дневник на благајна) of one register: opening balance, every document of the period, closing balance.
     *
     * @return array{opening: float, in: float, out: float, closing: float, rows: \Illuminate\Support\Collection}
     */
    public static function journal(int $agencyId, string $register, string $from, string $to): array
    {
        $q = fn () => CashEntry::withoutGlobalScopes()->where('agency_id', $agencyId)->where('register', $register);
        $opening = (float) $q()->where('entry_at', '<', $from.' 00:00:00')->selectRaw("COALESCE(SUM(CASE WHEN direction='in' THEN amount_base ELSE -amount_base END),0) s")->value('s');
        $rows = $q()->whereBetween('entry_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('entry_at')->orderBy('id')->get();
        $in = (float) $rows->where('direction', 'in')->sum('amount_base');
        $out = (float) $rows->where('direction', 'out')->sum('amount_base');
        $running = $opening;
        foreach ($rows as $r) {
            $running += $r->direction === 'in' ? (float) $r->amount_base : -(float) $r->amount_base;
            $r->running = round($running, 2);
        }

        return ['opening' => round($opening, 2), 'in' => round($in, 2), 'out' => round($out, 2), 'closing' => round($opening + $in - $out, 2), 'rows' => $rows];
    }
}
