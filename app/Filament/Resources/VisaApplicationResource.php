<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Enums\VisaApplicationStatus;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\VisaApplicationResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class VisaApplicationResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = VisaApplication::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function permissionModule(): string
    {
        return 'visa_applications';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Visa application'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Visa applications');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::where('status', VisaApplicationStatus::New->value)->count();

        return $n ? (string) $n : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'first_name', 'last_name', 'email', 'phone'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Section::make(__('Applicant'))->columnSpan(2)->columns(2)->schema([
                    AgencyField::select(),
                    Forms\Components\Select::make('visa_type_id')->label(__('Visa service'))->required()->live()
                        ->options(fn (Get $get) => VisaType::query()->when(AgencyField::current($get), fn ($q, $a) => $q->where('agency_id', $a))
                            ->get()->mapWithKeys(fn ($v) => [$v->id => $v->country.' — '.$v->tr('name')]))->searchable(),
                    Forms\Components\TextInput::make('first_name')->label(__('First name'))->required(),
                    Forms\Components\TextInput::make('last_name')->label(__('Last name'))->required(),
                    Forms\Components\TextInput::make('email')->label(__('E-mail'))->email(),
                    Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
                    Forms\Components\TextInput::make('nationality')->label(__('Nationality')),
                    Forms\Components\DatePicker::make('travel_date')->label(__('Travel date'))->native(false)->displayFormat('d.m.Y'),
                    Forms\Components\Toggle::make('is_express')->label(__('Express processing')),
                    Forms\Components\KeyValue::make('answers')->label(__('Answers'))->columnSpanFull()->keyLabel(__('Question'))->valueLabel(__('Answer')),
                    Forms\Components\Repeater::make('documents')->label(__('Documents'))->columnSpanFull()->columns(3)->defaultItems(0)
                        ->schema([
                            Forms\Components\TextInput::make('key')->label(__('Document')),
                            Forms\Components\TextInput::make('name')->label(__('File name')),
                            Forms\Components\FileUpload::make('path')->label(__('File'))->disk('local')->directory('visa-documents')->downloadable()->openable(),
                        ]),
                ]),
                Forms\Components\Section::make(__('Processing'))->columnSpan(1)->schema([
                    Forms\Components\Select::make('status')->label(__('Status'))->options(VisaApplicationStatus::class)->required()
                        ->default(VisaApplicationStatus::New->value),
                    Forms\Components\Select::make('payment_status')->label(__('Payment'))->options(PaymentStatus::class)->required()
                        ->default(PaymentStatus::Unpaid->value),
                    Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric(),
                    Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR'),
                    Forms\Components\Select::make('assigned_user_id')->label(__('Assigned to'))
                        ->options(fn () => User::query()->when(! Auth::user()->isSuperAdmin(), fn ($q) => $q->where('agency_id', Auth::user()->agency_id))->pluck('name', 'id'))
                        ->default(fn () => Auth::id()),
                    Forms\Components\Textarea::make('internal_notes')->label(__('Internal notes'))->rows(4),
                ]),
            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Applicant'))->columns(4)->schema([
                Infolists\Components\TextEntry::make('reference')->label(__('Reference'))->copyable()->weight('bold'),
                Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge(),
                Infolists\Components\TextEntry::make('payment_status')->label(__('Payment'))->badge(),
                Infolists\Components\TextEntry::make('created_at')->label(__('Received'))->dateTime('d.m.Y H:i'),
                Infolists\Components\TextEntry::make('full_name')->label(__('Name'))->state(fn (VisaApplication $r) => $r->fullName()),
                Infolists\Components\TextEntry::make('email')->label(__('E-mail'))->copyable(),
                Infolists\Components\TextEntry::make('phone')->label(__('Phone'))->copyable(),
                Infolists\Components\TextEntry::make('visaType.country')->label(__('Visa service'))
                    ->state(fn (VisaApplication $r) => $r->visaType ? $r->visaType->country.' — '.$r->visaType->tr('name') : '—'),
            ]),
            Infolists\Components\Section::make(__('Questionnaire'))->schema([
                Infolists\Components\TextEntry::make('answers_view')->hiddenLabel()->html()
                    ->state(fn (VisaApplication $r) => static::answersHtml($r)),
            ]),
            Infolists\Components\Section::make(__('Documents'))->schema([
                Infolists\Components\TextEntry::make('documents_view')->hiddenLabel()->html()
                    ->state(fn (VisaApplication $r) => static::documentsHtml($r)),
            ]),
        ]);
    }

    protected static function answersHtml(VisaApplication $r): HtmlString
    {
        $labels = collect($r->visaType?->questionnaire ?? [])->mapWithKeys(fn ($q) => [$q['key'] => $q['label']['mk'] ?? $q['key']]);
        $rows = collect($r->answers ?? [])->map(fn ($v, $k) => '<tr><td style="padding:4px 12px 4px 0;color:rgb(var(--gray-500))">'.e($labels[$k] ?? $k).'</td><td style="padding:4px 0">'.e(is_array($v) ? implode(', ', $v) : (string) $v).'</td></tr>')->implode('');

        return new HtmlString($rows ? '<table>'.$rows.'</table>' : '—');
    }

    protected static function documentsHtml(VisaApplication $r): HtmlString
    {
        $items = collect($r->documents ?? [])->map(function ($d) use ($r) {
            $url = route('admin.visa-document', ['application' => $r->id, 'index' => array_search($d, $r->documents, true)]);

            return '<li><a href="'.e($url).'" target="_blank" style="color:rgb(var(--primary-600));text-decoration:underline">'.e(($d['key'] ?? '').' — '.($d['name'] ?? basename($d['path'] ?? ''))).'</a></li>';
        })->implode('');

        return new HtmlString($items ? '<ul style="list-style:disc;padding-left:1.25rem">'.$items.'</ul>' : '—');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('visaType'))
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label(__('Reference'))->searchable()->weight('semibold')->copyable(),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->state(fn (VisaApplication $r) => $r->fullName())
                    ->description(fn (VisaApplication $r) => $r->email)
                    ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('visaType.country')->label(__('Visa service'))
                    ->description(fn (VisaApplication $r) => $r->visaType?->tr('name')),
                Tables\Columns\IconColumn::make('is_express')->label(__('Express'))->boolean()->trueIcon('heroicon-s-bolt')->falseIcon('heroicon-o-minus')->trueColor('danger'),
                Tables\Columns\TextColumn::make('documents')->label(__('Documents'))->badge()->color('gray')->state(fn (VisaApplication $r) => count($r->documents ?? [])),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge(),
                Tables\Columns\TextColumn::make('payment_status')->label(__('Payment'))->badge(),
                Tables\Columns\TextColumn::make('created_at')->label(__('Received'))->since()->sortable(),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(VisaApplicationStatus::class)->multiple(),
                Tables\Filters\SelectFilter::make('payment_status')->label(__('Payment'))->options(PaymentStatus::class),
                Tables\Filters\SelectFilter::make('visa_type_id')->label(__('Visa service'))->relationship('visaType', 'country'),
            ])
            ->actions([Tables\Actions\ViewAction::make()->iconButton(), Tables\Actions\EditAction::make()->iconButton()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisaApplications::route('/'),
            'create' => Pages\CreateVisaApplication::route('/create'),
            'view' => Pages\ViewVisaApplication::route('/{record}'),
            'edit' => Pages\EditVisaApplication::route('/{record}/edit'),
        ];
    }
}
