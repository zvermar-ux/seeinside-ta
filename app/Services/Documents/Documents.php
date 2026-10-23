<?php

namespace App\Services\Documents;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\CashEntry;
use App\Models\Invoice;
use App\Services\Mail\AgencyMail;
use Illuminate\Support\Facades\Storage;

/** Printable documents (HTML, print → PDF) and sending them by e-mail */
class Documents
{
    public const TYPES = ['contract', 'voucher', 'invoice', 'cash', 'rooming', 'journal'];

    protected static function common(Agency $agency, array $extra = []): array
    {
        $logo = $agency->brand_logo ?: $agency->logo;
        $stamp = $agency->doc('stamp');

        return $extra + [
            'agency' => $agency,
            'logoUrl' => $logo ? Storage::disk('public')->url($logo) : null,
            'stampUrl' => $stamp ? Storage::disk('public')->url($stamp) : null,
        ];
    }

    public static function contract(Booking $b, bool $forEmail = false): string
    {
        $b->loadMissing(['bookable', 'room', 'departure', 'departurePlace', 'payments', 'invoices', 'assignedUser']);

        return view('docs.contract', self::common($b->agency, ['booking' => $b, 'forEmail' => $forEmail, 'title' => __('Travel contract').' '.$b->reference]))->render();
    }

    public static function voucher(Booking $b, bool $forEmail = false): string
    {
        $b->loadMissing(['bookable', 'room']);

        return view('docs.voucher', self::common($b->agency, ['booking' => $b, 'forEmail' => $forEmail, 'title' => 'Voucher '.$b->reference]))->render();
    }

    public static function invoice(Invoice $i, bool $forEmail = false): string
    {
        return view('docs.invoice', self::common(Agency::findOrFail($i->agency_id), ['invoice' => $i, 'forEmail' => $forEmail, 'title' => $i->title()]))->render();
    }

    public static function cash(CashEntry $e): string
    {
        return view('docs.cash', self::common(Agency::findOrFail($e->agency_id), ['entry' => $e, 'title' => $e->title()]))->render();
    }

    public static function rooming(Agency $agency, $bookings, string $heading): string
    {
        return view('docs.rooming', self::common($agency, ['bookings' => $bookings, 'heading' => $heading, 'title' => __('Rooming list')]))->render();
    }

    public static function journal(Agency $agency, array $data, string $register, string $from, string $to): string
    {
        return view('docs.journal', self::common($agency, $data + ['register' => $register, 'from' => $from, 'to' => $to, 'title' => __('Cash journal')]))->render();
    }

    public static function url(string $type, int $id, array $query = []): string
    {
        return route('docs.show', ['type' => $type, 'id' => $id] + $query);
    }

    /** Send a document as an e-mail (the document is also attached as an .html file that prints as PDF) */
    public static function email(string $type, $model, string $to, ?string $message = null, array $cc = []): ?string
    {
        $agency = Agency::findOrFail($model->agency_id);
        [$subject, $html, $file] = match ($type) {
            'contract' => [__('Travel contract').' '.$model->reference.' – '.$agency->name, self::contract($model, true), 'Dogovor-'.$model->reference.'.html'],
            'voucher' => ['Voucher '.$model->reference.' – '.$agency->name, self::voucher($model, true), 'Voucher-'.$model->reference.'.html'],
            'invoice' => [$model->title().' – '.$agency->name, self::invoice($model, true), str_replace('/', '-', ($model->type === 'proforma' ? 'Profaktura-' : 'Faktura-').$model->number).'.html'],
        };
        $body = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5">'.($message ? nl2br(e($message)).'<hr style="border:0;border-top:1px solid #ddd;margin:16px 0">' : '').'</div>'.$html;
        $err = AgencyMail::send($agency, $to, $subject, $body, $cc, [[$html, $file, 'text/html']]);
        if (! $err && $type === 'invoice') {
            $model->forceFill(['sent_at' => now()])->saveQuietly();
        }

        return $err;
    }
}
