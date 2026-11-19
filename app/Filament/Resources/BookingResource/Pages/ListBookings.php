<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
            // test period: every booking of one agency or of all agencies at once (super admin only)
            Actions\Action::make('purge')->label(__('Delete bookings'))->icon('heroicon-o-trash')->color('danger')->outlined()
                ->visible(fn () => \Illuminate\Support\Facades\Auth::user()?->isSuperAdmin())
                ->modalHeading(__('Delete bookings'))
                ->modalDescription(__('Deletes the bookings with their payments and passengers. Invoices and other documents stay, without the link to the booking. This cannot be undone.'))
                ->form([
                    \Filament\Forms\Components\Select::make('agency_id')->label(__('Agency'))
                        ->options(fn () => \App\Models\Agency::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder(__('All agencies'))->live(),
                    \Filament\Forms\Components\DatePicker::make('until')->label(__('Created until (empty = all)'))->native(false)->displayFormat('d.m.Y')->live(),
                    \Filament\Forms\Components\Placeholder::make('count')->label(__('Bookings to delete'))
                        ->content(fn (\Filament\Forms\Get $get) => (string) self::purgeQuery($get('agency_id'), $get('until'))->count()),
                    \Filament\Forms\Components\TextInput::make('confirm')->label(__('Type DELETE to confirm'))->required()
                        ->rule(fn () => fn ($attr, $value, $fail) => strtoupper(trim((string) $value)) === 'DELETE' || mb_strtoupper(trim((string) $value)) === 'ИЗБРИШИ' ? null : $fail(__('Type DELETE to confirm'))),
                ])
                ->action(function (array $data) {
                    $n = 0;
                    self::purgeQuery($data['agency_id'] ?? null, $data['until'] ?? null)->chunkById(200, function ($rows) use (&$n) {
                        foreach ($rows as $b) {
                            $b->delete();
                            $n++;
                        }
                    });
                    \Filament\Notifications\Notification::make()->title(__('Deleted bookings: :n', ['n' => $n]))->success()->send();
                }),
        ];
    }

    protected static function purgeQuery($agencyId, $until)
    {
        return \App\Models\Booking::withoutGlobalScopes()
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($until, fn ($q) => $q->whereDate('created_at', '<=', $until));
    }
}
