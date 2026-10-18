<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\LibraryFileResource\Pages;
use App\Models\LibraryFile;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class LibraryFileResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation {
        GuardedByPermission::can as guardedCan;
    }

    protected static ?string $model = LibraryFile::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 90;

    public static function permissionModule(): string
    {
        return 'media';
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        // shared images (no agency) are changed only by the super admin
        if ($record && $record->getAttribute('agency_id') === null && ! in_array($action, ['viewAny', 'view'], true)) {
            return (bool) Auth::user()?->isSuperAdmin();
        }

        return static::guardedCan($action, $record);
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Image'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Media library');
    }

    public static function getNavigationLabel(): string
    {
        return __('Media library');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visible();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('preview')->hiddenLabel()->columnSpanFull()
                ->content(fn (?LibraryFile $record) => $record ? new HtmlString('<img src="'.e($record->url()).'" style="max-height:260px;border-radius:.75rem;margin:auto">') : null),
            Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(180),
            Forms\Components\TextInput::make('folder')->label(__('Folder'))->maxLength(80)->datalist(fn () => LibraryFile::query()->visible()->whereNotNull('folder')->distinct()->pluck('folder')->all()),
            Forms\Components\TextInput::make('alt')->label(__('Alt text (SEO)'))->maxLength(250)->columnSpanFull(),
            Forms\Components\Select::make('agency_id')->label(__('Agency'))->relationship('agency', 'name')->placeholder(__('Shared (all agencies)'))
                ->visible(fn () => Auth::user()?->isSuperAdmin()),
            Forms\Components\Placeholder::make('usage')->label(__('Used in'))->columnSpanFull()
                ->content(fn (?LibraryFile $record) => $record ? new HtmlString(($u = MediaLibrary::usages($record->path)) ? '<ul style="list-style:disc;padding-left:1.2rem">'.collect($u)->map(fn ($l) => '<li>'.e($l).'</li>')->implode('').'</ul>' : '<span style="color:gray">'.e(__('Not used anywhere')).'</span>') : null),
            Forms\Components\Placeholder::make('info')->label(__('File'))->columnSpanFull()
                ->content(fn (?LibraryFile $record) => $record ? new HtmlString('<code style="font-size:.75rem">'.e($record->path).'</code> · '.e(trim(($record->width ? $record->width.'×'.$record->height.' · ' : '').$record->sizeLabel())).' · <a href="'.e($record->url()).'" target="_blank" style="text-decoration:underline">'.e(__('Open')).'</a>') : null),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->contentGrid(['default' => 1, 'sm' => 2, 'md' => 3, 'xl' => 5])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ViewColumn::make('name')->view('filament.library.card')->searchable(['name', 'alt', 'path']),
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('folder')->label(__('Folder'))
                    ->options(fn () => LibraryFile::query()->visible()->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder', 'folder')->all()),
                Tables\Filters\SelectFilter::make('source')->label(__('Source'))
                    ->options(['upload' => __('Uploaded'), 'scan' => __('Existing image'), 'ai' => __('AI image')]),
                Tables\Filters\SelectFilter::make('agency_id')->label(__('Agency'))->relationship('agency', 'name')
                    ->visible(fn () => Auth::user()?->isSuperAdmin()),
            ])
            ->recordAction('edit')
            ->actions([
                Tables\Actions\EditAction::make()->iconButton()->modalWidth('2xl'),
                Tables\Actions\Action::make('copy')->iconButton()->icon('heroicon-o-link')->tooltip(__('Copy link'))
                    ->action(fn () => null)
                    ->extraAttributes(fn (LibraryFile $r) => ['x-on:click.prevent.stop' => 'navigator.clipboard.writeText('.json_encode($r->url(), JSON_UNESCAPED_SLASHES | JSON_HEX_APOS).'); $tooltip('.json_encode(__('Copied'), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS).', { timeout: 1500 })']),
                Tables\Actions\DeleteAction::make()->iconButton()
                    ->modalDescription(fn (LibraryFile $r) => ($u = MediaLibrary::usages($r->path)) ? __('The image is used in: :list. It will be removed only from the library; the file stays.', ['list' => implode(', ', $u)]) : __('The image will be deleted permanently.'))
                    ->after(fn (LibraryFile $r) => static::deleteFileIfUnused($r)),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('move')->label(__('Move to folder'))->icon('heroicon-o-folder')
                    ->form([Forms\Components\TextInput::make('folder')->label(__('Folder'))->maxLength(80)])
                    ->action(fn (Collection $records, array $data) => $records->filter(fn ($r) => static::can('update', $r))->each->update(['folder' => $data['folder'] ?: null]))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\DeleteBulkAction::make()
                    ->modalDescription(__('Images that are still used somewhere are only removed from the library; their files stay.'))
                    ->using(function (Collection $records) {
                        foreach ($records as $r) {
                            if (static::can('delete', $r)) {
                                $r->delete();
                                static::deleteFileIfUnused($r);
                            }
                        }
                    }),
            ])
            ->paginated([24, 48, 96])
            ->defaultPaginationPageOption(48);
    }

    /** Delete the file from disk unless something in the app still uses it */
    public static function deleteFileIfUnused(LibraryFile $r): void
    {
        if (! MediaLibrary::usages($r->path) && ! isset(MediaLibrary::referenced()[$r->path])) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($r->path);
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLibraryFiles::route('/')];
    }
}
