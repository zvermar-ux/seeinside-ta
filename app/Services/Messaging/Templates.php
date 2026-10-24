<?php

namespace App\Services\Messaging;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\Invoice;

/** Notification events, their default texts (MK / EN) and the {placeholders} */
class Templates
{
    /** event => [label, for (client|agency), sent automatically?] */
    public static function events(): array
    {
        return [
            'booking_received' => [__('Booking request received'), 'client'],
            'booking_confirmed' => [__('Booking confirmed'), 'client'],
            'payment_received' => [__('Payment received'), 'client'],
            'payment_reminder' => [__('Reminder: payment due tomorrow'), 'client'],
            'invoice_reminder' => [__('Reminder: invoice due tomorrow'), 'client'],
            'option_expiring' => [__('Reminder: reservation (option) expires tomorrow'), 'client'],
            'trip_reminder' => [__('Day before the trip'), 'client'],
            'review_request' => [__('After the trip: ask for a review'), 'client'],
            'booking_cancelled' => [__('Booking cancelled'), 'client'],
            'agency_reply' => [__('Agency replied in the client portal'), 'client'],
            'new_booking' => [__('New booking (to the agency)'), 'agency'],
            'client_message' => [__('New message from a client (to the agency)'), 'agency'],
            'reminders_digest' => [__('Daily reminder of unpaid bookings & invoices (to the agency)'), 'agency'],
        ];
    }

    public static function placeholders(): array
    {
        return ['{name}', '{reference}', '{offer}', '{dates}', '{passengers}', '{total}', '{paid}', '{balance}', '{due_date}', '{option_until}', '{invoice}', '{link}', '{agency}', '{agency_phone}', '{agency_email}'];
    }

    /** [subject, text] */
    public static function defaults(string $event, string $locale = 'mk'): array
    {
        $mk = [
            'booking_received' => ['Барање за резервација {reference}', "Почитувани {name},\nго примивме вашето барање за {offer} ({dates}).\nБрој на резервација: {reference}\nВкупно: {total}\nНаскоро ќе ве контактираме со потврда.\nВашата резервација: {link}\n\n{agency} · {agency_phone}"],
            'booking_confirmed' => ['Потврдена резервација {reference}', "Почитувани {name},\nвашата резервација {reference} за {offer} ({dates}) е ПОТВРДЕНА.\nВкупно: {total} · платено: {paid} · за доплата: {balance}\nДоговор, ваучер и уплати: {link}\n\n{agency} · {agency_phone}"],
            'payment_received' => ['Примена уплата – {reference}', "Почитувани {name},\nја примивме вашата уплата за резервација {reference}.\nПлатено вкупно: {paid} · преостанува: {balance}\nВи благодариме!\n{link}\n\n{agency}"],
            'payment_reminder' => ['Потсетник за уплата – {reference}', "Почитувани {name},\nве потсетуваме дека рокот за уплата за {offer} ({reference}) е утре, {due_date}.\nЗа доплата: {balance}\nДетали: {link}\n\n{agency} · {agency_phone}"],
            'invoice_reminder' => ['Потсетник за фактура {invoice}', "Почитувани {name},\nрокот за плаќање на фактурата {invoice} е утре, {due_date}.\nЗа плаќање: {balance}\n{link}\n\n{agency} · {agency_phone}"],
            'option_expiring' => ['Резервацијата {reference} истекува утре', "Почитувани {name},\nопцијата за вашата резервација {reference} ({offer}) истекува утре, {option_until}.\nЗа да ја задржите, ве молиме уплатете или јавете ни се.\n{link}\n\n{agency} · {agency_phone}"],
            'trip_reminder' => ['Среќен пат! – {reference}', "Почитувани {name},\nутре е вашето патување: {offer} ({dates}).\nВаучерот и деталите се тука: {link}\nСреќен пат!\n\n{agency} · {agency_phone}"],
            'review_request' => ['Како беше патувањето?', "Почитувани {name},\nсе надеваме дека уживавте во {offer}.\nЌе ни значи ако оставите кратка рецензија: {link}\n\n{agency}"],
            'booking_cancelled' => ['Откажана резервација {reference}', "Почитувани {name},\nрезервацијата {reference} ({offer}) е откажана.\nЗа прашања јавете ни се на {agency_phone}.\n\n{agency}"],
            'agency_reply' => ['Нова порака за резервација {reference}', "Почитувани {name},\nимате нова порака од {agency} за резервацијата {reference}:\n\n{message}\n\nОдговорете тука: {link}"],
            'new_booking' => ['Нова резервација {reference}', "Нова резервација {reference}\n{offer} · {dates}\nКлиент: {name} · {phone} · {email}\nПатници: {passengers}\nВкупно: {total}\n{admin_link}"],
            'client_message' => ['Порака од клиент – {reference}', "{name} напиша за резервацијата {reference}:\n\n{message}\n\n{admin_link}"],
            'reminders_digest' => ['Потсетник: рокови за уплата', "{message}"],
        ];
        $en = [
            'booking_received' => ['Booking request {reference}', "Dear {name},\nwe have received your request for {offer} ({dates}).\nBooking number: {reference}\nTotal: {total}\nWe will contact you shortly with a confirmation.\nYour booking: {link}\n\n{agency} · {agency_phone}"],
            'booking_confirmed' => ['Booking confirmed {reference}', "Dear {name},\nyour booking {reference} for {offer} ({dates}) is CONFIRMED.\nTotal: {total} · paid: {paid} · balance: {balance}\nContract, voucher and payments: {link}\n\n{agency} · {agency_phone}"],
            'payment_received' => ['Payment received – {reference}', "Dear {name},\nwe have received your payment for booking {reference}.\nPaid in total: {paid} · balance: {balance}\nThank you!\n{link}\n\n{agency}"],
            'payment_reminder' => ['Payment reminder – {reference}', "Dear {name},\nthis is a reminder that the payment for {offer} ({reference}) is due tomorrow, {due_date}.\nBalance: {balance}\nDetails: {link}\n\n{agency} · {agency_phone}"],
            'invoice_reminder' => ['Invoice reminder {invoice}', "Dear {name},\ninvoice {invoice} is due tomorrow, {due_date}.\nAmount due: {balance}\n{link}\n\n{agency} · {agency_phone}"],
            'option_expiring' => ['Your reservation {reference} expires tomorrow', "Dear {name},\nthe option for your reservation {reference} ({offer}) expires tomorrow, {option_until}.\nTo keep it, please pay or call us.\n{link}\n\n{agency} · {agency_phone}"],
            'trip_reminder' => ['Have a nice trip! – {reference}', "Dear {name},\nyour trip starts tomorrow: {offer} ({dates}).\nVoucher and details: {link}\nHave a great trip!\n\n{agency} · {agency_phone}"],
            'review_request' => ['How was your trip?', "Dear {name},\nwe hope you enjoyed {offer}.\nA short review would mean a lot to us: {link}\n\n{agency}"],
            'booking_cancelled' => ['Booking cancelled {reference}', "Dear {name},\nbooking {reference} ({offer}) has been cancelled.\nQuestions? Call us at {agency_phone}.\n\n{agency}"],
            'agency_reply' => ['New message about booking {reference}', "Dear {name},\nyou have a new message from {agency} about booking {reference}:\n\n{message}\n\nReply here: {link}"],
        ];
        $set = $locale === 'en' ? ($en + $mk) : $mk;

        return $set[$event] ?? ['', ''];
    }

    /** Subject and text for an event: agency's own texts win over the defaults */
    public static function for(Agency $agency, string $event, string $locale = 'mk'): array
    {
        [$s, $t] = self::defaults($event, $locale);
        $own = (array) $agency->msg('templates.'.$event, []);
        $suffix = $locale === 'en' ? '_en' : '';

        return [filled($own['subject'.$suffix] ?? null) ? $own['subject'.$suffix] : $s, filled($own['text'.$suffix] ?? null) ? $own['text'.$suffix] : $t];
    }

    public static function money($v, ?string $cur): string
    {
        return number_format((float) $v, 2, ',', '.').' '.($cur ?: 'EUR');
    }

    /** Values of the {placeholders} for a booking (and optionally an invoice) */
    public static function vars(Agency $agency, ?Booking $b = null, ?Invoice $i = null, array $extra = []): array
    {
        $v = [
            '{agency}' => $agency->name, '{agency_phone}' => (string) $agency->phone, '{agency_email}' => (string) $agency->email,
        ];
        if ($b) {
            $dates = $b->date_from ? $b->date_from->format('d.m.Y').($b->date_to ? ' – '.$b->date_to->format('d.m.Y') : '') : ($b->departure?->start_date?->format('d.m.Y') ?? '');
            $v += [
                '{name}' => trim((string) ($b->customer_name ?: $b->client?->display_name)),
                '{phone}' => (string) $b->customer_phone, '{email}' => (string) $b->customer_email,
                '{reference}' => $b->reference, '{offer}' => $b->itemTitle(), '{dates}' => $dates,
                '{passengers}' => collect($b->passengerList())->map(fn ($p) => trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? '')))->filter()->implode(', '),
                '{total}' => $b->total !== null ? self::money($b->total, $b->currency) : '—',
                '{paid}' => self::money($b->paid_amount, $b->currency), '{balance}' => self::money($b->balance(), $b->currency),
                '{due_date}' => $b->payment_due?->format('d.m.Y') ?? '', '{option_until}' => $b->option_until?->format('d.m.Y') ?? '',
                '{link}' => \App\Http\Controllers\PortalController::url($b),
                '{admin_link}' => url('/admin/bookings/'.$b->id.'/edit'),
            ];
        }
        if ($i) {
            $v['{invoice}'] = (string) $i->number;
            $v['{due_date}'] = $i->due_date?->format('d.m.Y') ?? '';
            $v['{balance}'] = self::money($i->balance(), $i->currency);
            $v['{total}'] = self::money($i->total, $i->currency);
            $v['{name}'] ??= (string) $i->client_name;
            $v['{name}'] = $v['{name}'] ?: (string) $i->client_name;
        }

        return $extra + $v + ['{name}' => '', '{link}' => '', '{message}' => ''];
    }

    public static function render(string $text, array $vars): string
    {
        return trim(strtr($text, $vars));
    }
}
