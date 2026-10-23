<?php

namespace App\Services\Finance;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Invoice;

/** Builds proformas / invoices from a booking (“Ве задолжуваме за …”) and converts a proforma into an invoice */
class InvoiceBuilder
{
    /** Description in the style of the old software: offer, accommodation type, service, term, names */
    public static function description(Booking $b): string
    {
        $agency = Agency::find($b->agency_id);
        $parts = [trim(($agency?->doc('invoice_text') ?? 'Ве задолжуваме за').' '.$b->itemTitle())];
        $item = $b->bookable;
        if ($b->bookable_type === 'tour' && filled($item?->location)) {
            $parts[0] .= ' ('.$item->location.')';
        }
        if ($b->room) {
            $parts[] = __('Accommodation type').': '.$b->room->tr('name');
            if ($b->room->board) {
                $parts[] = __('Service').': '.$b->room->board->value;
            }
        }
        if ($b->date_from) {
            $term = $b->date_from->format('d.m.Y').($b->date_to ? ' – '.$b->date_to->format('d.m.Y') : '');
            $parts[] = __('Term').': '.$term.($b->nights() ? ' ('.$b->nights().' '.__('nights').')' : '');
        }
        $names = collect($b->passengerList())->map(fn ($p) => trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? '')))->filter()->implode(', ');
        if ($names) {
            $parts[] = __('In the name of').': '.$names;
        }

        return implode(', ', $parts);
    }

    /**
     * Attributes for a new invoice from a booking. Invoices are issued in the base currency (MKD) –
     * the booking total is converted with the booking rate. $amount = only part of it (e.g. a deposit).
     */
    public static function fromBooking(Booking $b, string $type = 'proforma', ?float $amount = null, ?string $currency = null): array
    {
        $agency = Agency::findOrFail($b->agency_id);
        $base = $agency->doc('base_currency', 'MKD');
        $currency ??= $base;
        $value = $amount ?? (float) $b->total;
        $rate = (float) ($b->exchange_rate ?: $agency->eurRate());
        if ($currency === $base && $b->currency !== $base) {
            $value = $b->inBase($value);
        } elseif ($currency !== $base && $b->currency === $base) {
            $value = $rate ? $value / $rate : $value;
        }
        $payer = $b->company_id ? Client::find($b->company_id) : ($b->client_id ? Client::find($b->client_id) : null);
        $vat = (float) $agency->doc('vat_rate', 0);
        // price incl. VAT → net line price
        $net = $vat > 0 ? round($value / (1 + $vat / 100), 2) : round($value, 2);

        return [
            'agency_id' => $b->agency_id, 'type' => $type, 'booking_id' => $b->id,
            'client_id' => $payer?->id, 'client_name' => $payer?->display_name ?? $b->customer_name,
            'client_address' => $payer ? trim(($payer->address ?? '').' '.($payer->city ?? '')) : $b->customer_city,
            'client_tax_number' => $payer?->tax_number,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays((int) $agency->doc('invoice_days', 8))->toDateString(),
            'currency' => $currency, 'exchange_rate' => $rate,
            'lines' => [['description' => self::description($b), 'qty' => 1, 'price' => $net, 'vat' => $vat]],
            'status' => 'issued',
        ];
    }

    public static function createFromBooking(Booking $b, string $type = 'proforma', ?float $amount = null, ?string $currency = null): Invoice
    {
        return Invoice::create(self::fromBooking($b, $type, $amount, $currency));
    }

    /** Proforma → final invoice (new number); payments of the proforma move to the invoice */
    public static function convert(Invoice $proforma): Invoice
    {
        $data = collect($proforma->getAttributes())->except(['id', 'seq', 'year', 'number', 'created_at', 'updated_at', 'sent_at', 'paid_amount', 'created_by', 'status'])->all();
        $data['lines'] = $proforma->lines;
        $data['type'] = 'invoice';
        $data['proforma_id'] = $proforma->id;
        $data['issue_date'] = now()->toDateString();
        $data['status'] = 'issued';
        $invoice = Invoice::create($data);
        \App\Models\BookingPayment::withoutGlobalScopes()->where('invoice_id', $proforma->id)->update(['invoice_id' => $invoice->id]);
        \App\Models\CashEntry::withoutGlobalScopes()->where('invoice_id', $proforma->id)->update(['invoice_id' => $invoice->id]);
        $invoice->syncPaid();
        $proforma->forceFill(['status' => 'paid'])->saveQuietly();

        return $invoice->fresh();
    }
}
