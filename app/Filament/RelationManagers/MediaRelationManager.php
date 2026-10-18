<?php

namespace App\Filament\RelationManagers;

use App\Models\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Photo gallery for any catalog item (tours, events, accommodations) */
class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $icon = 'heroicon-o-photo';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Gallery').' ('.$ownerRecord->media()->count().')';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('path')->label(__('Image'))->image()->imageEditor()
                ->disk('public')->directory('gallery')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('caption.mk')->label(__('Caption').' (МК)'),
            Forms\Components\TextInput::make('caption.en')->label(__('Caption').' (EN)'),
            Forms\Components\Toggle::make('is_main')->label(__('Main image')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->contentGrid(['md' => 3, 'xl' => 5])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ImageColumn::make('path')->disk('public')->height(150)->width('100%')
                        ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:.75rem;width:100%']),
                    Tables\Columns\TextColumn::make('caption')->state(fn (Media $r) => $r->caption['mk'] ?? null)->color('gray')->size('sm'),
                    Tables\Columns\ToggleColumn::make('is_main')->label(__('Main image')),
                ])->space(2),
            ])
            ->headerActions([
                Tables\Actions\Action::make('upload')->label(__('Upload images'))->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('paths')->label(__('Images'))->image()->multiple()->reorderable()
                            ->disk('public')->directory('gallery')->maxSize(10240)->maxFiles(60)->required(),
                    ])
                    ->action(function (array $data) {
                        $owner = $this->getOwnerRecord();
                        $sort = (int) $owner->media()->max('sort_order');
                        $hasMain = $owner->media()->where('is_main', true)->exists();
                        foreach ($data['paths'] as $i => $path) {
                            $owner->media()->create([
                                'path' => $path,
                                'sort_order' => ++$sort,
                                'is_main' => ! $hasMain && $i === 0,
                            ]);
                        }
                    }),
                Tables\Actions\Action::make('fromLibrary')->label(__('Add from library'))->icon('heroicon-o-photo')->color('gray')
                    ->modalHeading(__('Choose images from the library'))->modalWidth('6xl')->modalSubmitActionLabel(__('Add'))
                    ->form(fn () => [
                        Forms\Components\ViewField::make('picked')->hiddenLabel()->default([])->view('filament.library.picker')
                            ->viewData(['multiple' => true, 'agency' => $this->getOwnerRecord()->getAttribute('agency_id') ?? $this->getOwnerRecord()->accommodation?->agency_id]),
                    ])
                    ->action(function (array $data) {
                        $owner = $this->getOwnerRecord();
                        $sort = (int) $owner->media()->max('sort_order');
                        $hasMain = $owner->media()->where('is_main', true)->exists();
                        $have = $owner->media()->pluck('path')->all();
                        $i = 0;
                        foreach (array_values(array_filter((array) ($data['picked'] ?? []), 'is_string')) as $path) {
                            if (in_array($path, $have, true)) {
                                continue;
                            }
                            $owner->media()->create(['path' => $path, 'sort_order' => ++$sort, 'is_main' => ! $hasMain && $i++ === 0]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->paginated([20, 60]);
    }
}
