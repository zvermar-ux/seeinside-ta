<?php

namespace App\Filament\Resources\LibraryFileResource\Pages;

use App\Filament\Resources\LibraryFileResource;
use App\Services\Media\MediaLibrary;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageLibraryFiles extends ManageRecords
{
    protected static string $resource = LibraryFileResource::class;

    protected function getHeaderActions(): array
    {
        $super = (bool) Auth::user()?->isSuperAdmin();

        return [
            Actions\Action::make('upload')->label(__('Upload images'))->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => LibraryFileResource::can('create'))
                ->form([
                    Forms\Components\Select::make('agency_id')->label(__('Agency'))->options(fn () => \App\Models\Agency::query()->orderBy('name')->pluck('name', 'id'))
                        ->placeholder(__('Shared (all agencies)'))->visible($super),
                    Forms\Components\TextInput::make('folder')->label(__('Folder'))->default('library')->maxLength(80)
                        ->helperText(__('Optional – helps you find images later (e.g. "greece", "ski")')),
                    // stored by hand below, so the automatic registration does not run twice
                    Forms\Components\FileUpload::make('files')->label(__('Images'))->image()->multiple()->maxFiles(100)->maxSize(15360)
                        ->disk('local')->directory('library-tmp')->required()->storeFileNamesIn('names')
                        ->panelLayout('grid')->imagePreviewHeight('140'),
                ])
                ->action(function (array $data) use ($super) {
                    $agency = $super ? ($data['agency_id'] ?? null) : Auth::user()?->agency_id;
                    $folder = trim((string) ($data['folder'] ?? ''), '/ ') ?: 'library';
                    $n = 0;
                    foreach ((array) $data['files'] as $tmp) {
                        $name = $data['names'][$tmp] ?? basename($tmp);
                        $ext = strtolower(pathinfo($tmp, PATHINFO_EXTENSION)) ?: 'jpg';
                        $path = \Illuminate\Support\Str::slug($folder).'/'.\Illuminate\Support\Str::random(32).'.'.$ext;
                        \Illuminate\Support\Facades\Storage::disk('public')->put($path, \Illuminate\Support\Facades\Storage::disk('local')->get($tmp));
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($tmp);
                        if ($f = MediaLibrary::register($path, $agency ? (int) $agency : null, 'upload', pathinfo($name, PATHINFO_FILENAME))) {
                            $f->update(['folder' => $folder]);
                            $n++;
                        }
                    }
                    Notification::make()->success()->title(__(':n images added to the library', ['n' => $n]))->send();
                }),
            Actions\Action::make('scan')->label(__('Scan existing images'))->icon('heroicon-o-magnifying-glass')->color('gray')
                ->visible(fn () => LibraryFileResource::can('create'))
                ->requiresConfirmation()->modalDescription(__('All photos already used in offers, galleries, the slider, groups and visas are added to the library (each to its own agency).'))
                ->action(function () {
                    $n = MediaLibrary::syncExisting();
                    Notification::make()->success()->title(__(':n images added to the library', ['n' => $n]))->send();
                }),
        ];
    }
}
