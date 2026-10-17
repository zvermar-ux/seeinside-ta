<x-filament-panels::page>
    @if (count($agencies = $this->getAgencyOptions()))
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem">
            <span style="font-weight:600;font-size:.9rem">{{ __('You are editing the look of') }}:</span>
            <div style="min-width:18rem">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="agencyId">
                        <option value="">{{ __('Super admin (my login) and login page') }}</option>
                        @foreach ($agencies as $id => $name)
                            <option value="{{ $id }}">{{ __('Agency') }}: {{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            @if (! \App\Support\Branding::global()['allow_agency_branding'])
                <span style="font-size:.85rem;color:rgb(var(--warning-600))">{{ __('Agency looks are switched off (tab Advanced).') }}</span>
            @endif
        </div>
    @endif
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div style="position:sticky;bottom:0;z-index:10;padding:.75rem 0;display:flex;gap:.75rem;align-items:center;background:linear-gradient(to top, rgb(var(--gray-50)) 70%, transparent)" class="ta-appearance-bar">
            <x-filament::button type="submit" icon="heroicon-m-check" size="lg">{{ __('Save and apply') }}</x-filament::button>
            <span style="font-size:.85rem;color:rgb(var(--gray-500))">{{ __('The page reloads with the new look.') }}</span>
        </div>
    </x-filament-panels::form>
    <style>.dark .ta-appearance-bar { background: linear-gradient(to top, rgb(var(--gray-950)) 70%, transparent) !important; }</style>
</x-filament-panels::page>
