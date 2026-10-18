<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ReviewResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ReviewResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Review::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 6;

    public static function permissionModule(): string
    {
        return 'reviews';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Review'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Reviews');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('status', Review::PENDING)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Waiting for approval');
    }

    /** Offers a review can be about: "tour:5" => "Тура – Рим" */
    public static function itemOptions(?int $agencyId): array
    {
        $out = [];
        $labels = ['tour' => __('Tour'), 'event' => __('Event'), 'accommodation' => __('Hotel'), 'visa' => __('Visa')];
        foreach (Review::TYPES as $type => $class) {
            $q = $class::query()->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))->latest('id')->limit(400);
            foreach ($q->get() as $m) {
                $t = $type === 'visa' ? trim($m->country.' – '.($m->name['mk'] ?? '')) : ($m->title['mk'] ?? $m->title ?? '');
                $out[$labels[$type]][$type.':'.$m->id] = is_array($t) ? reset($t) : $t;
            }
        }

        return $out;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                AgencyField::select(),
                Forms\Components\Select::make('item')->label(__('About'))->placeholder(__('The agency in general'))
                    ->options(fn (Get $get) => static::itemOptions(AgencyField::current($get)))
                    ->searchable()->columnSpan(2)
                    ->afterStateHydrated(fn (Forms\Components\Select $c, ?Review $record) => $c->state($record?->reviewable_id ? $record->websiteItemType().':'.$record->reviewable_id : null))
                    ->dehydrated(false),
                Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(120),
                Forms\Components\TextInput::make('city')->label(__('City'))->maxLength(100),
                Forms\Components\TextInput::make('email')->label(__('E-mail'))->email()->maxLength(150)
                    ->helperText(__('Never shown on the website.')),
                Forms\Components\Select::make('rating')->label(__('Rating'))->required()->default(5)
                    ->options([5 => '★★★★★', 4 => '★★★★☆', 3 => '★★★☆☆', 2 => '★★☆☆☆', 1 => '★☆☆☆☆']),
                Forms\Components\TextInput::make('trip_date')->label(__('When did they travel'))->placeholder('Август 2026')->maxLength(40),
                Forms\Components\Select::make('locale')->label(__('Language'))->options(['mk' => 'МК', 'en' => 'EN'])->default('mk'),
                Forms\Components\TextInput::make('title')->label(__('Title'))->maxLength(200)->columnSpanFull(),
                Forms\Components\Textarea::make('body')->label(__('Review'))->required()->rows(5)->autosize()->columnSpanFull(),
                Forms\Components\Textarea::make('reply')->label(__('Reply from the agency'))->rows(3)->autosize()->columnSpanFull()
                    ->helperText(__('Shown under the review on the website.')),
            ]),
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\ToggleButtons::make('status')->label(__('Status'))->inline()->required()->default(Review::APPROVED)
                    ->options(Review::statuses())
                    ->colors([Review::PENDING => 'warning', Review::APPROVED => 'success', Review::REJECTED => 'danger'])
                    ->icons([Review::PENDING => 'heroicon-o-clock', Review::APPROVED => 'heroicon-o-check-circle', Review::REJECTED => 'heroicon-o-x-circle'])
                    ->columnSpan(2),
                Forms\Components\Toggle::make('is_featured')->label(__('Featured (shown first)'))->inline(false),
            ]),
        ]);
    }

    /** "tour:5" from the form → morph columns */
    public static function applyItem(array $data, ?string $item): array
    {
        $data['reviewable_type'] = null;
        $data['reviewable_id'] = null;
        if ($item && str_contains($item, ':')) {
            [$type, $id] = explode(':', $item, 2);
            if (isset(Review::TYPES[$type])) {
                $data['reviewable_type'] = (new (Review::TYPES[$type]))->getMorphClass();
                $data['reviewable_id'] = (int) $id;
            }
        }
        if (($data['status'] ?? null) === Review::APPROVED) {
            $data['approved_by'] ??= Auth::id();
        }

        return $data;
    }

    public static function approve(Review $r): void
    {
        $r->forceFill(['status' => Review::APPROVED, 'approved_at' => $r->approved_at ?? now(), 'approved_by' => Auth::id()])->save();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('reviewable'))
            ->columns([
                Tables\Columns\TextColumn::make('rating')->label(__('Rating'))->sortable()
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state) => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('body')->label(__('Review'))->wrap()->limit(160)->searchable(['body', 'title', 'name'])
                    ->description(fn (Review $r) => trim($r->name.($r->city ? ', '.$r->city : '').' · '.$r->created_at->format('d.m.Y')), 'above'),
                Tables\Columns\TextColumn::make('item')->label(__('About'))->state(fn (Review $r) => $r->itemTitle() ?? __('Agency'))
                    ->badge()->color('gray')->wrap(),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state) => Review::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => [Review::PENDING => 'warning', Review::APPROVED => 'success', Review::REJECTED => 'danger'][$state] ?? 'gray'),
                Tables\Columns\IconColumn::make('reply')->label(__('Reply'))->boolean()->falseIcon('heroicon-o-minus')->falseColor('gray')->state(fn (Review $r) => filled($r->reply))->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')->label(__('Featured'))->boolean()->toggleable(isToggledHiddenByDefault: true),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(Review::statuses()),
                Tables\Filters\SelectFilter::make('rating')->label(__('Rating'))->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']),
                AgencyField::filter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\Action::make('approve')->label(__('Approve'))->tooltip(__('Approve – show on the website'))
                    ->icon('heroicon-o-check-circle')->color('success')->iconButton()
                    ->visible(fn (Review $r) => $r->status !== Review::APPROVED && static::can('edit', $r))
                    ->action(fn (Review $r) => static::approve($r)),
                Tables\Actions\Action::make('reject')->label(__('Reject'))->tooltip(__('Reject – hide from the website'))
                    ->icon('heroicon-o-x-circle')->color('danger')->iconButton()
                    ->visible(fn (Review $r) => $r->status !== Review::REJECTED && static::can('edit', $r))
                    ->action(fn (Review $r) => $r->forceFill(['status' => Review::REJECTED])->save()),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')->label(__('Approve'))->icon('heroicon-o-check-circle')->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (Review $r) => static::approve($r)))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('reject')->label(__('Reject'))->icon('heroicon-o-x-circle')->color('danger')
                        ->action(fn (Collection $records) => $records->each(fn (Review $r) => $r->forceFill(['status' => Review::REJECTED])->save()))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
