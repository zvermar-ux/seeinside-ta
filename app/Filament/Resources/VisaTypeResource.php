<?php

namespace App\Filament\Resources;

use App\Enums\VisaCategory;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\VisaTypeResource\Pages;
use App\Filament\Support\AgencyField;
use App\Filament\Support\CatalogFields;
use App\Filament\Support\Lang;
use App\Models\VisaType;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VisaTypeResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = VisaType::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'country';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 4;

    public static function permissionModule(): string
    {
        return 'visas';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Visa service'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Visa services');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? $record->country.' — '.$record->tr('name') : '';
    }

    public const FIELD_TYPES = [
        'text' => 'Text', 'textarea' => 'Long text', 'email' => 'E-mail', 'phone' => 'Phone', 'date' => 'Date',
        'number' => 'Number', 'select' => 'Choice (list)', 'radio' => 'Yes / No', 'checkbox' => 'Checkbox', 'country' => 'Country',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('visa')->persistTabInQueryString()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make(__('Basic information'))->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Section::make()->columnSpan(2)->columns(3)->schema([
                            AgencyField::select(),
                            Forms\Components\TextInput::make('country')->label(__('Country'))->required()->placeholder('Канада'),
                            Forms\Components\TextInput::make('country_code')->label(__('Country code'))->maxLength(2)->placeholder('CA'),
                            Forms\Components\Select::make('category')->label(__('Visa type'))->options(VisaCategory::class)
                                ->default(VisaCategory::Tourist->value)->required(),
                            Lang::tabs(fn (string $l) => [
                                Lang::text('name', __('Name'), $l, true),
                                Lang::area('short_description', __('Short description'), $l, 2),
                                Lang::editor('description', __('Description'), $l),
                                Lang::lines('requirements', __('Conditions / requirements'), $l),
                                Forms\Components\Grid::make(2)->schema([
                                    Lang::text('processing_time', __('Processing time'), $l),
                                    Lang::text('validity', __('Validity'), $l),
                                ]),
                            ]),
                        ]),
                        Forms\Components\Group::make()->columnSpan(1)->schema([
                            Forms\Components\Section::make(__('Price'))->columns(2)->schema([
                                Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR'),
                                Forms\Components\TextInput::make('service_fee')->label(__('Agency service fee'))->numeric(),
                                Forms\Components\TextInput::make('consular_fee')->label(__('Consular / embassy fee'))->numeric(),
                                Forms\Components\TextInput::make('express_fee')->label(__('Express processing'))->numeric(),
                                Forms\Components\Toggle::make('online_payment')->label(__('Online payment'))->columnSpanFull(),
                            ]),
                            CatalogFields::photos('visas'),
                            CatalogFields::publishing(false),
                        ]),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Questionnaire'))->icon('heroicon-o-queue-list')
                    ->badge(fn (Get $get) => count((array) $get('questionnaire')) ?: null)
                    ->schema([
                        Forms\Components\Placeholder::make('q_hint')->hiddenLabel()
                            ->content(__('Questions the applicant answers on the website. Each visa type has its own questionnaire.')),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('basic_questions')->label(__('Insert standard questions'))->icon('heroicon-o-sparkles')->color('gray')
                                ->action(fn (Forms\Set $set, Get $get) => $set('questionnaire', array_merge((array) $get('questionnaire'), static::standardQuestions()))),
                        ]),
                        Forms\Components\Repeater::make('questionnaire')->hiddenLabel()->defaultItems(0)->collapsible()->cloneable()->reorderable()
                            ->itemLabel(fn (array $state) => ($state['label']['mk'] ?? '').(! empty($state['required']) ? ' *' : ''))
                            ->addActionLabel(__('Add question'))
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make('label.mk')->label(__('Question').' (МК)')->required()->columnSpan(2)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Get $get) => blank($get('key')) ? $set('key', Str::slug(Str::limit((string) $state, 40, ''), '_')) : null),
                                Forms\Components\TextInput::make('label.en')->label(__('Question').' (EN)')->columnSpan(2),
                                Forms\Components\Select::make('type')->label(__('Answer type'))->required()->default('text')->live()
                                    ->options(collect(self::FIELD_TYPES)->map(fn ($l) => __($l))),
                                Forms\Components\TextInput::make('key')->label(__('Field key'))->required()->alphaDash(),
                                Forms\Components\Toggle::make('required')->label(__('Required'))->inline(false),
                                Forms\Components\TextInput::make('section')->label(__('Section'))->placeholder('Лични податоци'),
                                Forms\Components\TagsInput::make('options')->label(__('Options'))->columnSpanFull()
                                    ->visible(fn (Get $get) => $get('type') === 'select'),
                                Forms\Components\TextInput::make('help.mk')->label(__('Help text'))->columnSpanFull(),
                            ]),
                    ]),
                Forms\Components\Tabs\Tab::make(__('Documents'))->icon('heroicon-o-paper-clip')
                    ->badge(fn (Get $get) => count((array) $get('required_documents')) ?: null)
                    ->schema([
                        Forms\Components\Repeater::make('required_documents')->hiddenLabel()->defaultItems(0)->reorderable()
                            ->itemLabel(fn (array $state) => ($state['label']['mk'] ?? '').(! empty($state['required']) ? ' *' : ''))
                            ->addActionLabel(__('Add document'))
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make('label.mk')->label(__('Document').' (МК)')->required()->columnSpan(2)->placeholder('Скенирана копија од пасош')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, Forms\Set $set, Get $get) => blank($get('key')) ? $set('key', Str::slug(Str::limit((string) $state, 40, ''), '_')) : null),
                                Forms\Components\TextInput::make('label.en')->label(__('Document').' (EN)'),
                                Forms\Components\TextInput::make('key')->label(__('Field key'))->required()->alphaDash(),
                                Forms\Components\Toggle::make('required')->label(__('Required'))->default(true)->inline(false),
                                Forms\Components\Toggle::make('multiple')->label(__('Several files'))->inline(false),
                                Forms\Components\TextInput::make('help.mk')->label(__('Help text'))->columnSpan(2),
                            ]),
                    ]),
                Forms\Components\Tabs\Tab::make(__('Promotions'))->icon('heroicon-o-bolt')->schema([
                    CatalogFields::promotions(),
                    CatalogFields::seo(),
                ]),
            ]),
        ]);
    }

    public static function standardQuestions(): array
    {
        $q = fn ($key, $mk, $en, $type = 'text', $required = true, $section = 'Лични податоци') => compact('key', 'type', 'required', 'section') + ['label' => ['mk' => $mk, 'en' => $en]];

        return [
            $q('first_name', 'Име', 'First name'),
            $q('last_name', 'Презиме', 'Last name'),
            $q('date_of_birth', 'Датум на раѓање', 'Date of birth', 'date'),
            $q('place_of_birth', 'Место на раѓање', 'Place of birth'),
            $q('passport_number', 'Број на пасош', 'Passport number'),
            $q('passport_expiry', 'Пасошот важи до', 'Passport expiry date', 'date'),
            $q('address', 'Адреса на живеење', 'Home address', 'textarea'),
            $q('email', 'Е-пошта', 'E-mail', 'email'),
            $q('phone', 'Телефон', 'Phone', 'phone'),
            $q('employer', 'Работодавач и позиција', 'Employer and position', 'text', false, 'Работа'),
            $q('travel_date', 'Планиран датум на патување', 'Planned travel date', 'date', true, 'Патување'),
            $q('travel_purpose', 'Цел на патувањето', 'Purpose of travel', 'textarea', true, 'Патување'),
            $q('previous_visa', 'Дали сте имале виза за оваа земја?', 'Have you had a visa for this country before?', 'radio', true, 'Патување'),
            $q('refused_before', 'Дали ви била одбиена виза?', 'Have you ever been refused a visa?', 'radio', true, 'Патување'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                CatalogFields::coverColumn(),
                Tables\Columns\TextColumn::make('country')->label(__('Country'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (VisaType $r) => $r->tr('name')),
                Tables\Columns\TextColumn::make('category')->label(__('Visa type'))->badge(),
                Tables\Columns\TextColumn::make('price')->label(__('Price'))
                    ->state(fn (VisaType $r) => number_format($r->totalPrice(), 0, ',', '.').' '.$r->currency),
                Tables\Columns\TextColumn::make('questions')->label(__('Questions'))->badge()->color('gray')
                    ->state(fn (VisaType $r) => count($r->questionnaire ?? [])),
                Tables\Columns\TextColumn::make('applications_count')->counts('applications')->label(__('Applications'))->badge()->color('info'),
                CatalogFields::lastMinuteColumn(),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([AgencyField::filter(), Tables\Filters\SelectFilter::make('category')->label(__('Visa type'))->options(VisaCategory::class)])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action(fn (VisaType $from, VisaType $to) => Duplicate::media($from, $to))])
            ->defaultSort('country');
    }

    public static function getRelations(): array
    {
        return [\App\Filament\RelationManagers\MediaRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisaTypes::route('/'),
            'create' => Pages\CreateVisaType::route('/create'),
            'edit' => Pages\EditVisaType::route('/{record}/edit'),
        ];
    }
}
