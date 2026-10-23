<div class="head" style="grid-template-columns:auto 1fr">
    <div>@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else<b style="font-size:20px">{{ $agency->name }}</b>@endif</div>
    <div class="addr">{{ $agency->name }}@if($agency->license_number) · {{ $agency->doc('license') }} {{ $agency->license_number }}@endif<br>{{ $agency->address }}, {{ $agency->city }}
        @if($agency->phone)<br>{{ __('tel.') }} {{ $agency->phone }}@endif @if($agency->email) · {{ $agency->email }}@endif
        @if($agency->tax_number)<br>{{ __('Tax no.') }} {{ $agency->tax_number }}@endif @if($agency->doc('bank_account')) · {{ __('Bank account') }} {{ $agency->doc('bank_account') }}@endif @if($agency->doc('bank_name')) {{ $agency->doc('bank_name') }}@endif</div>
</div>
