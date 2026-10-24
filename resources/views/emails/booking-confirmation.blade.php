@php
    $b = $booking; $cur = $b->currency ?: 'EUR'; $n = fn ($v) => number_format((float) $v, 2, ',', '.');
    $en = ($b->locale ?? 'mk') === 'en';
    $t = fn ($mk, $enText) => $en ? $enText : $mk;
@endphp
<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#111;max-width:640px">
    <p>{{ $t('Почитувани', 'Dear') }} {{ $b->customer_name }},</p>
    <p>{{ $t('Ви благодариме за барањето. Го примивме и ќе ве контактираме за потврда.', 'Thank you for your request. We have received it and will contact you to confirm.') }}</p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;border:1px solid #ddd">
        <tr style="background:#f5f7fa"><td style="width:38%"><b>{{ $t('Број на резервација', 'Booking number') }}</b></td><td><b style="font-size:16px">{{ $b->reference }}</b></td></tr>
        <tr><td>{{ $t('Понуда', 'Offer') }}</td><td>{{ $b->itemTitle() }}</td></tr>
        @if ($b->date_from)<tr><td>{{ $t('Датум', 'Dates') }}</td><td>{{ $b->date_from->format('d.m.Y') }}@if($b->date_to) – {{ $b->date_to->format('d.m.Y') }}@endif</td></tr>@endif
        @if ($b->room)<tr><td>{{ $t('Соба / единица', 'Room / unit') }}</td><td>{{ $b->room->tr('name', $en ? 'en' : 'mk') }}</td></tr>@endif
        <tr><td>{{ $t('Лица', 'Persons') }}</td><td>{{ $b->adults }} {{ $t('возрасни', 'adults') }}@if($b->children) + {{ $b->children }} {{ $t('деца', 'children') }}@endif</td></tr>
        @if ($b->total !== null)<tr><td>{{ $t('Вкупно', 'Total') }}</td><td><b>{{ $n($b->total) }} {{ $cur }}</b></td></tr>@endif
    </table>
    @if ($pax = $b->passengerList())
        <p style="margin:16px 0 6px"><b>{{ $t('Патници', 'Passengers') }}</b></p>
        <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;border:1px solid #ddd;font-size:13px">
            <tr style="background:#f5f7fa"><td>#</td><td>{{ $t('Име и презиме', 'Name') }}</td><td>{{ $t('Датум на раѓање', 'Date of birth') }}</td><td>{{ $t('Пасош', 'Passport') }}</td><td>{{ $t('Телефон', 'Phone') }}</td></tr>
            @foreach ($pax as $i => $p)
                <tr style="border-top:1px solid #eee"><td>{{ $i + 1 }}</td><td>{{ trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? '')) }}</td><td>{{ ! empty($p['birth_date']) ? \Carbon\Carbon::parse($p['birth_date'])->format('d.m.Y') : '' }}</td><td>{{ $p['passport'] ?? '' }}</td><td>{{ $p['phone'] ?? '' }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($b->message)<p style="margin-top:14px"><b>{{ $t('Ваша порака', 'Your message') }}:</b><br>{!! nl2br(e($b->message)) !!}</p>@endif
    <table cellpadding="0" cellspacing="0" style="margin:18px 0"><tr>
        <td style="padding-right:14px"><img src="{{ $b->qrPngUrl() }}" width="120" height="120" alt="QR {{ $b->reference }}" style="display:block;border:1px solid #eee"></td>
        <td style="font-size:13px;color:#555">{{ $t('QR код на резервацијата – покажете го во хотелот, на водичот или во автобусот.', 'QR code of your booking – show it at the hotel, to the guide or on the bus.') }}</td>
    </tr></table>
    @if (! empty($portal))
        <p style="margin:20px 0"><a href="{{ $portal }}" style="display:inline-block;background:{{ $agency->brand_color ?: '#0e7490' }};color:#fff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:8px">{{ $t('Мојата резервација', 'Manage my booking') }}</a><br>
        <span style="font-size:12px;color:#666">{{ $t('Статус, документи, уплати и пораки со агенцијата.', 'Status, documents, payments and messages with the agency.') }}</span></p>
    @endif
    <p style="margin-top:18px">{{ $t('Поздрав', 'Kind regards') }},<br><b>{{ $agency->name }}</b><br>{{ $agency->phone }} · {{ $agency->email }}@if($agency->website)<br>{{ $agency->website }}@endif</p>
</div>
