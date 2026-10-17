<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div style="position:sticky;bottom:0;z-index:10;padding:.75rem 0;display:flex;gap:.75rem;align-items:center;background:linear-gradient(to top, rgb(var(--gray-50)) 70%, transparent)" class="ta-appearance-bar">
            <x-filament::button type="submit" icon="heroicon-m-check" size="lg">{{ __('Save and apply') }}</x-filament::button>
            <span style="font-size:.85rem;color:rgb(var(--gray-500))">{{ __('The page reloads with the new look.') }}</span>
        </div>
    </x-filament-panels::form>
    <style>.dark .ta-appearance-bar { background: linear-gradient(to top, rgb(var(--gray-950)) 70%, transparent) !important; }</style>
</x-filament-panels::page>
