<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Filament\Pages\MessagingSettings;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNotificationLog extends ListRecords
{
    protected static string $resource = NotificationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('settings')->label(__('Notifications (e-mail, Viber, WhatsApp)'))->icon('heroicon-o-cog-6-tooth')->color('gray')
                ->url(MessagingSettings::getUrl()),
        ];
    }

    /** Today / this week / this month / all – with how many were sent and how many failed */
    public function getTabs(): array
    {
        $base = fn () => NotificationLogResource::getEloquentQuery();
        $tab = function (string $label, ?\Closure $scope) use ($base) {
            $q = $base();
            if ($scope) {
                $scope($q);
            }
            $sent = (clone $q)->where('status', 'sent')->count();
            $failed = (clone $q)->where('status', 'failed')->count();

            return Tab::make($label)->badge($sent.($failed ? ' / ✕'.$failed : ''))->badgeColor($failed ? 'danger' : 'success')
                ->modifyQueryUsing(fn (Builder $query) => $scope ? $scope($query) : $query);
        };

        return [
            'today' => $tab(__('Today'), fn ($q) => $q->whereDate('created_at', today())),
            'week' => $tab(__('This week'), fn ($q) => $q->where('created_at', '>=', now()->startOfWeek())),
            'month' => $tab(__('This month'), fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())),
            'all' => $tab(__('All'), null),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'week';
    }
}
