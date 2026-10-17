@props(['center' => false])
<div class="ta-locale" style="{{ $center ? 'justify-content:center;margin-top:.5rem;' : '' }}">
    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
        <a href="{{ route('locale.switch', $code) }}" title="{{ $label }}"
           class="ta-locale-link {{ app()->getLocale() === $code ? 'is-active' : '' }}">{{ strtoupper($code) }}</a>
    @endforeach
</div>
