<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgencyResource\Pages;
use App\Models\Agency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AgencyResource extends Resource
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Agency::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Agency'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Agencies');
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    /** Shared with the "My agency" page */
    public static function formSchema(bool $withAdminFields = true): array
    {
        return [
            Forms\Components\Section::make(__('Agency details'))
                ->columns(3)
                ->schema([
                    Forms\Components\FileUpload::make('logo')
                        ->label(__('Logo'))
                        ->image()->imageEditor()
                        ->disk('public')->directory('agency-logos')
                        ->avatar()
                        ->columnSpan(1),
                    Forms\Components\Grid::make(2)->columnSpan(2)->schema([
                        Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                        Forms\Components\TextInput::make('email')->label(__('E-mail'))->email(),
                        Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
                        Forms\Components\TextInput::make('website')->label(__('Website'))->url(),
                    ]),
                    Forms\Components\TextInput::make('address')->label(__('Address')),
                    Forms\Components\TextInput::make('city')->label(__('City')),
                    Forms\Components\Select::make('country')->label(__('Country'))
                        ->options(['MK' => __('North Macedonia'), 'RS' => __('Serbia'), 'AL' => __('Albania'), 'BG' => __('Bulgaria'), 'GR' => __('Greece'), 'XK' => __('Kosovo')])
                        ->default('MK')->searchable(),
                    Forms\Components\TextInput::make('tax_number')->label(__('Tax number')),
                    Forms\Components\TextInput::make('license_number')->label(__('License number')),
                    Forms\Components\Select::make('default_currency')->label(__('Currency'))
                        ->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR')->required(),
                    Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)
                        ->visible($withAdminFields)->inline(false),
                    Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull()->visible($withAdminFields),
                ]),
            Forms\Components\Section::make(__('Look of the panel for this agency'))
                ->description(__('Users of this agency see their own logo and main colour.'))
                ->icon('heroicon-o-paint-brush')
                ->visible(fn () => \App\Support\Branding::get('allow_agency_branding'))
                ->collapsible()->collapsed()
                ->columns(3)
                ->schema([
                    Forms\Components\ColorPicker::make('brand_color')->label(__('Main colour'))
                        ->helperText(__('Empty = the colour of the application.')),
                    Forms\Components\FileUpload::make('brand_logo')->label(__('Logo (light mode)'))
                        ->image()->disk('public')->directory('agency-logos')->maxSize(4096)
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
                    Forms\Components\FileUpload::make('brand_logo_dark')->label(__('Logo (dark mode)'))
                        ->image()->disk('public')->directory('agency-logos')->maxSize(4096)
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
                ]),
            Forms\Components\Section::make(__('Website, flights & payments'))
                ->collapsible()->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('booking_notify_email')->label(__('E-mail for new bookings'))->email()
                        ->helperText(__('New bookings and visa applications from the website are sent here.')),
                    Forms\Components\TextInput::make('flight_search_url')->label(__('Flight search (GOL IBE) address'))->url()
                        ->placeholder('https://aviokarti.golibe.com/'),
                    Forms\Components\Textarea::make('flight_widget_code')->label(__('Flight search box code'))->rows(3)->columnSpanFull()
                        ->helperText(__('Embed code from the flight booking partner; the website shows it in the search box.')),
                    Forms\Components\Select::make('payment_provider')->label(__('Online payment'))
                        ->options(['casys' => 'CaSys (cPay)', 'nestpay' => 'NestPay / Halkbank', 'stripe' => 'Stripe', 'paypal' => 'PayPal'])
                        ->placeholder(__('Not enabled')),
                    Forms\Components\KeyValue::make('payment_settings')->label(__('Payment access keys'))->columnSpanFull()
                        ->keyLabel(__('Name'))->valueLabel(__('Value'))->helperText(__('Stored encrypted.')),
                ]),
            Forms\Components\Section::make(__('Outgoing e-mail (SMTP)'))
                ->description(__('Used for e-mails sent on behalf of the agency.'))
                ->collapsible()->collapsed()
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('mail_from_address')->label(__('Sender e-mail'))->email(),
                    Forms\Components\TextInput::make('mail_host')->label(__('SMTP server')),
                    Forms\Components\TextInput::make('mail_port')->label(__('Port'))->numeric(),
                    Forms\Components\Select::make('mail_encryption')->label(__('Encryption'))
                        ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => __('None')]),
                    Forms\Components\TextInput::make('mail_username')->label(__('Username')),
                    Forms\Components\TextInput::make('mail_password')->label(__('Password'))
                        ->password()->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText(__('Leave empty to keep the current password.')),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')->label('')->disk('public')->circular()->defaultImageUrl(fn (Agency $r) => 'https://ui-avatars.com/api/?background=0ea5e9&color=fff&name='.urlencode($r->name)),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (Agency $r) => $r->city),
                Tables\Columns\TextColumn::make('email')->label(__('E-mail'))->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->label(__('Phone'))->toggleable(),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label(__('Users'))->badge()->color('gray')->sortable(),
                Tables\Columns\TextColumn::make('travel_objects_count')->counts('travelObjects')->label(__('Arrangements & hotels'))->badge()->color('info')->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
                Tables\Columns\TextColumn::make('created_at')->label(__('Created'))->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->actions([
                Tables\Actions\Action::make('users')
                    ->label(__('Users'))->icon('heroicon-o-users')->color('gray')->iconButton()->tooltip(__('Users'))
                    ->url(fn (Agency $r) => UserResource::getUrl('index', ['tableFilters' => ['agency_id' => ['value' => $r->id]]])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgencies::route('/'),
            'create' => Pages\CreateAgency::route('/create'),
            'edit' => Pages\EditAgency::route('/{record}/edit'),
        ];
    }
}
