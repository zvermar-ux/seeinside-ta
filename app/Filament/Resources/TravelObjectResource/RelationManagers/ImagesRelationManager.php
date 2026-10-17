<?php

namespace App\Filament\Resources\TravelObjectResource\RelationManagers;

use App\Models\ObjectImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $icon = 'heroicon-o-photo';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Images').' ('.$ownerRecord->images()->count().')';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('path')->label(__('Image'))->image()->imageEditor()
                ->disk('public')->directory('objects')->required(),
            Forms\Components\TextInput::make('caption')->label(__('Caption')),
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
                    Tables\Columns\TextInputColumn::make('caption')->placeholder(__('Caption')),
                    Tables\Columns\ToggleColumn::make('is_main')->label(__('Main image'))
                        ->afterStateUpdated(fn () => $this->dispatch('$refresh')),
                ])->space(2),
            ])
            ->headerActions([
                Tables\Actions\Action::make('upload')->label(__('Upload images'))->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('paths')->label(__('Images'))->image()->multiple()->reorderable()
                            ->disk('public')->directory('objects')->maxSize(8192)->required(),
                    ])
                    ->action(function (array $data) {
                        $owner = $this->getOwnerRecord();
                        $sort = (int) $owner->images()->max('sort_order');
                        $hasMain = $owner->images()->where('is_main', true)->exists();
                        foreach ($data['paths'] as $i => $path) {
                            ObjectImage::create([
                                'travel_object_id' => $owner->id,
                                'path' => $path,
                                'sort_order' => ++$sort,
                                'is_main' => ! $hasMain && $i === 0,
                            ]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->paginated([20, 50, 100]);
    }
}
