<x-filament-panels::page>
    @if (count($agencies = $this->getAgencyOptions()))
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem">
            <span style="font-weight:600;font-size:.9rem">{{ __('Agency') }}:</span>
            <div style="min-width:18rem">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="agencyId">
                        
                        @foreach ($agencies as $id => $name)
                            <option value="{{ $id }}">{{ __('Agency') }}: {{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    @endif
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-m-check" size="lg">{{ __('Save') }}</x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
