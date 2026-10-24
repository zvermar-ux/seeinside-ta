@php
    $color = $agency->brand_color ?: '#0e7490';
    $logo = $agency->brand_logo ?: $agency->logo;
    $en = ($booking?->locale ?? 'mk') === 'en';
    // links in the text become clickable
    $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:'.e($color).'">$1</a>', nl2br(e($text)));
@endphp
<div style="background:#f3f5f8;padding:24px 12px;font-family:Arial,Helvetica,sans-serif">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">
    <div style="padding:18px 24px;border-bottom:4px solid {{ $color }}">
      @if ($logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $agency->name }}" style="max-height:48px;max-width:220px">@else<b style="font-size:20px;color:{{ $color }}">{{ $agency->name }}</b>@endif
    </div>
    <div style="padding:22px 24px;font-size:15px;line-height:1.6;color:#111">{!! $html !!}</div>
    @if ($portal)
      <div style="padding:0 24px 24px">
        <a href="{{ $portal }}" style="display:inline-block;background:{{ $color }};color:#fff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:8px">{{ $en ? 'Manage my booking' : 'Мојата резервација' }}</a>
      </div>
    @endif
    @if ($booking && ! $internal)
      <div style="padding:12px 24px;background:#f9fafb;font-size:13px;color:#555;border-top:1px solid #eee">
        {{ $en ? 'Booking' : 'Резервација' }} <b>{{ $booking->reference }}</b> · {{ $booking->itemTitle() }}
      </div>
    @endif
    <div style="padding:14px 24px;font-size:12px;color:#777;border-top:1px solid #eee">
      <b>{{ $agency->name }}</b>@if($agency->address) · {{ $agency->address }}@endif @if($agency->city) {{ $agency->city }}@endif<br>
      @if($agency->phone){{ $agency->phone }}@endif @if($agency->email) · {{ $agency->email }}@endif
    </div>
  </div>
</div>
