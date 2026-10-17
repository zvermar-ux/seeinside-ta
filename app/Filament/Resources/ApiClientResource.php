<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ApiClientResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\ApiClient;
use App\Services\Website\WebsiteNotifier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiClientResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = ApiClient::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 6;

    public static function permissionModule(): string
    {
        return 'integrations';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Website connection'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Websites (WordPress)');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('name')->label(__('Name'))->required()->placeholder('aries.mk'),
                Forms\Components\TextInput::make('site_url')->label(__('Website address'))->url()->placeholder('https://aries.mk'),
                Forms\Components\TextInput::make('webhook_url')->label(__('Webhook address'))->url()
                    ->placeholder('https://aries.mk/wp-json/seeinside/v1/webhook')
                    ->helperText(__('The plugin shows this address. Changes are sent here instantly.')),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold')->description(fn (ApiClient $r) => $r->site_url),
                Tables\Columns\TextColumn::make('key_prefix')->label(__('Key'))->formatStateUsing(fn ($state) => 'ta_'.$state.'_••••')->fontFamily('mono'),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('last_used_at')->label(__('Last request'))->since()->placeholder('—'),
                Tables\Columns\TextColumn::make('last_webhook_status')->label(__('Last webhook'))->badge()
                    ->color(fn ($state) => $state >= 200 && $state < 300 ? 'success' : ($state === null ? 'gray' : 'danger'))
                    ->formatStateUsing(fn ($state, ApiClient $r) => $state === null ? '—' : $state.' · '.$r->last_webhook_at?->diffForHumans()),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('key')->label(__('New API key'))->icon('heroicon-o-key')->color('warning')->iconButton()->tooltip(__('New API key'))
                    ->requiresConfirmation()
                    ->modalDescription(__('The old key stops working immediately.'))
                    ->action(fn (ApiClient $record) => static::showKey($record)),
                Tables\Actions\Action::make('ping')->label(__('Test webhook'))->icon('heroicon-o-signal')->color('gray')->iconButton()->tooltip(__('Test webhook'))
                    ->visible(fn (ApiClient $r) => filled($r->webhook_url))
                    ->action(function (ApiClient $record) {
                        $status = WebsiteNotifier::send($record, ['event' => 'ping', 'type' => 'ping', 'id' => 0, 'slug' => null, 'updated_at' => now()->toIso8601String()]);
                        Notification::make()->title(__('Website answered: :status', ['status' => $status ?: __('no answer')]))
                            ->color($status >= 200 && $status < 300 ? 'success' : 'danger')->send();
                    }),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ]);
    }

    public static function showKey(ApiClient $client): void
    {
        $key = $client->issueKey();

        Notification::make()
            ->title(__('API key for :name', ['name' => $client->name]))
            ->body(__('Copy it now, it is shown only once:')."\n\n".$key."\n\n".__('Webhook secret:')."\n".$client->webhook_secret)
            ->persistent()->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageApiClients::route('/')];
    }
}
