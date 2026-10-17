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
                Tables\Actions\Action::make('secret')->label(__('Webhook secret'))->icon('heroicon-o-lock-closed')->color('gray')->iconButton()->tooltip(__('Webhook secret'))
                    ->modalHeading(fn (ApiClient $r) => __('Webhook secret').' – '.$r->name)
                    ->modalDescription(__('Paste it in WordPress → SeeInside Travel → Connection → Webhook secret.'))
                    ->modalContent(fn (ApiClient $r) => static::copyBox(__('Webhook secret'), (string) $r->webhook_secret))
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('Close')),
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
            ->body(new \Illuminate\Support\HtmlString(
                '<p>'.e(__('Copy it now, it is shown only once:')).'</p>'
                .'<p style="margin:.4rem 0 .8rem"><code style="user-select:all;word-break:break-all;font-size:.8rem;padding:.3rem .45rem;border-radius:.4rem;background:rgba(0,0,0,.06);display:block">'.e($key).'</code></p>'
                .'<p>'.e(__('Webhook secret:')).'</p>'
                .'<p style="margin:.4rem 0 0"><code style="user-select:all;word-break:break-all;font-size:.8rem;padding:.3rem .45rem;border-radius:.4rem;background:rgba(0,0,0,.06);display:block">'.e((string) $client->webhook_secret).'</code></p>'
            ))
            ->persistent()->success()->send();
    }

    /** Value shown in a modal, selectable with one click, with a copy button */
    public static function copyBox(string $label, string $value): \Illuminate\Support\HtmlString
    {
        return new \Illuminate\Support\HtmlString(
            '<div x-data="{ copied: false }" style="display:flex;gap:.5rem;align-items:center">'
            .'<code style="flex:1;user-select:all;word-break:break-all;font-size:.9rem;padding:.6rem .75rem;border-radius:.5rem;background:rgba(var(--gray-500),.1)" aria-label="'.e($label).'">'.e($value).'</code>'
            .'<button type="button" class="fi-btn fi-btn-size-md fi-color-gray" style="padding:.5rem .8rem;border-radius:.5rem;border:1px solid rgba(var(--gray-300),1);font-weight:600" '
            .'x-on:click="navigator.clipboard.writeText('.e(json_encode($value)).'); copied = true; setTimeout(() => copied = false, 2000)" '
            .'x-text="copied ? '.e(json_encode(__('Copied'))).' : '.e(json_encode(__('Copy'))).'"></button></div>'
        );
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageApiClients::route('/')];
    }
}
