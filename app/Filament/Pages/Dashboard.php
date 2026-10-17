<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    public function getColumns(): int | string | array
    {
        return ['md' => 2, 'xl' => 3];
    }
}
