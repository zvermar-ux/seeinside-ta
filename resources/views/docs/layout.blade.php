{{-- Printable A4 document (contract, voucher, invoice, cash receipt). Opens in the browser: Print → Save as PDF. --}}
<!doctype html>
<html lang="mk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<style>
    @page { size: A4; margin: 12mm 12mm 14mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; font: 12.5px/1.45 "DejaVu Sans", "Segoe UI", Arial, sans-serif; color: #111; }
    .bar { position: sticky; top: 0; z-index: 5; display: flex; gap: .5rem; justify-content: center; padding: .6rem; background: #1f2937; }
    .bar button, .bar a { font: 600 13px/1 Arial, sans-serif; padding: .6rem 1rem; border-radius: 6px; border: 0; cursor: pointer; background: #fff; color: #111; text-decoration: none; }
    .bar button.pri { background: #0ea5e9; color: #fff; }
    .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); position: relative; }
    .head { display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center; margin-bottom: 12px; }
    .head .lic { font-size: 20px; font-weight: 700; }
    .head .doc { margin-top: 6px; font-weight: 700; font-size: 14px; letter-spacing: .02em; }
    .head .num { display: inline-block; margin-top: 6px; padding: 4px 30px; border: 2px solid #111; font-size: 17px; font-weight: 700; }
    .head img { max-height: 90px; max-width: 220px; }
    .head .addr { text-align: right; font-weight: 600; font-size: 12px; line-height: 1.55; }
    h3 { margin: 12px 0 4px; font-size: 13px; text-transform: uppercase; letter-spacing: .02em; }
    table { width: 100%; border-collapse: collapse; }
    .box { border: 1.5px solid #111; }
    .box td, .box th { padding: 4px 6px; vertical-align: top; }
    .grid td { border: 1px solid #111; padding: 4px 6px; }
    .cap { display: block; font-size: 10.5px; color: #333; border-top: 1px solid #111; margin-top: 2px; padding-top: 1px; text-align: center; }
    .val { display: block; min-height: 16px; text-align: center; font-weight: 600; }
    .r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: 700; }
    .muted { color: #555; } .small { font-size: 11px; }
    .lines td { padding: 2px 4px; } .lines .sep td { border-top: 1px solid #111; }
    .chk { display: inline-block; width: 13px; height: 13px; border: 1.5px solid #111; vertical-align: -2px; margin-right: 4px; text-align: center; line-height: 11px; font-size: 11px; font-weight: 700; }
    .sign { display: grid; grid-template-columns: 1fr 160px 1fr; gap: 10px; align-items: end; margin-top: 10px; }
    .sign .line { border-bottom: 1px solid #111; min-height: 22px; }
    .stamp { max-height: 110px; max-width: 160px; opacity: .9; justify-self: center; }
    .note { min-height: 64px; white-space: pre-line; }
    .two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .tot td { padding: 3px 6px; } .tot .big td { font-size: 14px; font-weight: 700; border-top: 1.5px solid #111; }
    .foot { margin-top: 14px; font-size: 10.5px; color: #444; text-align: center; }
    .break { page-break-before: always; }
    @media print { body { background: #fff; } .bar { display: none; } .page { margin: 0; width: auto; min-height: 0; padding: 0; box-shadow: none; } }
</style>
</head>
<body>
@unless ($forEmail ?? false)
    <div class="bar"><button class="pri" onclick="window.print()">🖨 {{ __('Print / Save as PDF') }}</button><button onclick="window.close()">{{ __('Close') }}</button></div>
@endunless
<div class="page">
    @yield('content')
</div>
</body>
</html>
