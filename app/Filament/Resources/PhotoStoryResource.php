<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\PhotoStoryResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\PhotoStory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** "Фотоприказни": travel photos of clients – approved ones are shown on the website */
class PhotoStoryResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = PhotoStory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'photo-stories';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 7;

    public static function permissionModule(): string
    {
        return 'reviews';
    }

    public static function defaultNavigationLabel(): string
    {
        return __('Photo stories');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Photo story'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Photo stories');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('status', PhotoStory::PENDING)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('name')->label(__('Name and surname'))->required()->maxLength(120),
                Forms\Components\TextInput::make('destination')->label(__('Where they travelled / stayed'))->maxLength(200),
                Forms\Components\TextInput::make('trip_date')->label(__('When'))->placeholder('Август 2026')->maxLength(40),
                Forms\Components\TextInput::make('email')->label(__('E-mail'))->email()->maxLength(150)->helperText(__('Never shown on the website.')),
                Forms\Components\Placeholder::make('bk')->label(__('Booking'))
                    ->content(fn (?PhotoStory $record) => $record?->booking ? new \Illuminate\Support\HtmlString('<a style="color:rgb(var(--primary-600));font-weight:600" href="'.BookingResource::getUrl('edit', ['record' => $record->booking]).'">'.e($record->booking->reference).'</a> · '.e($record->booking->itemTitle())) : '—'),
                Forms\Components\Textarea::make('story')->label(__('Their story'))->rows(4)->autosize()->columnSpanFull(),
                Forms\Components\FileUpload::make('photo_paths')->label(__('Photos'))->columnSpanFull()
                    ->disk('public')->directory('photo-stories')->image()->multiple()->reorderable()->panelLayout('grid')->maxFiles(30)->maxSize(15360)
                    ->helperText(__('Drag to change the order – the first ones are the big photos of the slider. Remove the ones that should not be shown.'))
                    ->afterStateHydrated(fn (Forms\Components\FileUpload $c, ?PhotoStory $record) => $c->state($record ? $record->photoPaths() : [])),
            ]),
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\ToggleButtons::make('status')->label(__('Status'))->inline()->required()->default(PhotoStory::APPROVED)
                    ->options(PhotoStory::statuses())
                    ->colors([PhotoStory::PENDING => 'warning', PhotoStory::APPROVED => 'success', PhotoStory::REJECTED => 'danger'])
                    ->icons([PhotoStory::PENDING => 'heroicon-o-clock', PhotoStory::APPROVED => 'heroicon-o-check-circle', PhotoStory::REJECTED => 'heroicon-o-x-circle'])
                    ->columnSpan(2),
                Forms\Components\Toggle::make('is_featured')->label(__('On the homepage slider'))->inline(false)
                    ->helperText(__('Up to 5 – otherwise the newest ones.')),
            ]),
        ]);
    }

    /** The photos of the form (order, removed, added) → the photos column (thumbnails kept for the ones that stay) */
    public static function photosFrom(array $paths, ?PhotoStory $old): array
    {
        $byPath = collect((array) $old?->photos)->filter(fn ($p) => is_array($p))->keyBy('path');
        $out = [];
        foreach (array_values($paths) as $p) {
            $out[] = $byPath[$p] ?? (function () use ($p) {
                $size = @getimagesize(Storage::disk('public')->path($p)) ?: [0, 0];

                return ['path' => $p, 'thumb' => $p, 'w' => (int) $size[0], 'h' => (int) $size[1]];
            })();
        }
        // removed photos: files go too
        foreach ($byPath as $path => $p) {
            if (! in_array($path, $paths, true)) {
                Storage::disk('public')->delete(array_filter([$p['path'] ?? null, ($p['thumb'] ?? null) !== ($p['path'] ?? null) ? ($p['thumb'] ?? null) : null]));
            }
        }

        return $out;
    }

    public static function approve(PhotoStory $s): void
    {
        $s->forceFill(['status' => PhotoStory::APPROVED, 'approved_at' => $s->approved_at ?? now(), 'approved_by' => Auth::id()])->save();
    }

    public static function table(Table $table): Table
    {
        $thumbs = fn (PhotoStory $r) => collect((array) $r->photos)->take(5)->map(fn ($p) => Storage::disk('public')->url($p['thumb'] ?? $p['path'] ?? ''))->all();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbs')->label(__('Photos'))->state($thumbs)->stacked()->limit(5)->circular(false)->height(48)->width(64)
                    ->limitedRemainingText(),
                Tables\Columns\TextColumn::make('name')->label(__('Client'))->searchable(['name', 'destination', 'email'])->weight('semibold')
                    ->description(fn (PhotoStory $r) => trim(($r->destination ?: '').($r->trip_date ? ' · '.$r->trip_date : ''))),
                Tables\Columns\TextColumn::make('n')->label(__('Number'))->state(fn (PhotoStory $r) => count((array) $r->photos))->badge()->color('gray'),
                Tables\Columns\TextColumn::make('booking.reference')->label(__('Booking'))
                    ->url(fn (PhotoStory $r) => $r->booking_id ? BookingResource::getUrl('edit', ['record' => $r->booking_id]) : null),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state) => PhotoStory::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => [PhotoStory::PENDING => 'warning', PhotoStory::APPROVED => 'success', PhotoStory::REJECTED => 'danger'][$state] ?? 'gray'),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Homepage')),
                Tables\Columns\TextColumn::make('created_at')->label(__('Sent'))->dateTime('d.m.Y H:i')->sortable()->toggleable(),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(PhotoStory::statuses()),
                AgencyField::filter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\Action::make('approve')->label(__('Approve'))->tooltip(__('Approve – show on the website'))
                    ->icon('heroicon-o-check-circle')->color('success')->iconButton()
                    ->visible(fn (PhotoStory $r) => $r->status !== PhotoStory::APPROVED)
                    ->action(fn (PhotoStory $r) => static::approve($r)),
                Tables\Actions\Action::make('reject')->label(__('Reject'))->tooltip(__('Reject – hide from the website'))
                    ->icon('heroicon-o-x-circle')->color('danger')->iconButton()
                    ->visible(fn (PhotoStory $r) => $r->status !== PhotoStory::REJECTED)
                    ->action(fn (PhotoStory $r) => $r->forceFill(['status' => PhotoStory::REJECTED])->save()),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')->label(__('Approve'))->icon('heroicon-o-check-circle')->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (PhotoStory $r) => static::approve($r)))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('reject')->label(__('Reject'))->icon('heroicon-o-x-circle')->color('danger')
                        ->action(fn (Collection $records) => $records->each(fn (PhotoStory $r) => $r->forceFill(['status' => PhotoStory::REJECTED])->save()))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPhotoStories::route('/'),
            'create' => Pages\CreatePhotoStory::route('/create'),
            'edit' => Pages\EditPhotoStory::route('/{record}/edit'),
        ];
    }
}
