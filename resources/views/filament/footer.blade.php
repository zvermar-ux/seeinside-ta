@php($text = \App\Support\Branding::text('footer_text'))
@if ($text)
    <footer class="ta-footer">{{ str_replace('{year}', date('Y'), $text) }}</footer>
@endif
