@php($multiple = $multiple ?? false)
<div x-data="staLibraryPicker({ state: $wire.$entangle('{{ $getStatePath() }}'), multiple: @js($multiple), url: @js(route('library.browse')), agency: @js($agency ?? null) })" x-init="load(true)" class="sta-lib">
    <div class="sta-lib-bar">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="sta-lib-search">
            <x-filament::input type="search" x-model.debounce.400ms="q" @input.debounce.400ms="load(true)" placeholder="{{ __('Search by name…') }}" />
        </x-filament::input.wrapper>
        <x-filament::input.wrapper class="sta-lib-folder">
            <x-filament::input.select x-model="folder" @change="load(true)">
                <option value="">{{ __('All folders') }}</option>
                <template x-for="f in folders" :key="f"><option :value="f" x-text="f"></option></template>
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <span class="sta-lib-count" x-text="(selected().length ? selected().length + ' {{ __('selected') }} · ' : '') + total + ' {{ __('images') }}'"></span>
    </div>
    <div class="sta-lib-grid">
        <template x-for="it in items" :key="it.path">
            <button type="button" class="sta-lib-item" :class="{ 'is-on': isOn(it.path) }" @click="toggle(it.path)" :title="it.name">
                <img :src="it.url" alt="" loading="lazy">
                <span class="sta-lib-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z"/></svg></span>
                <span class="sta-lib-meta"><b x-text="it.name"></b><small x-text="(it.w ? it.w + '×' + it.h + ' · ' : '') + it.size"></small></span>
            </button>
        </template>
    </div>
    <p class="sta-lib-empty" x-show="!loading && !items.length">{{ __('No images. Upload them in Offer → Media library or with the upload field.') }}</p>
    <div class="sta-lib-more" x-show="more"><x-filament::button color="gray" size="sm" @click="load(false)">{{ __('Show more') }}</x-filament::button></div>
    <div class="sta-lib-loading" x-show="loading"><x-filament::loading-indicator class="h-6 w-6" /></div>
</div>
