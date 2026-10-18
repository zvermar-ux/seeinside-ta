<?php

namespace App\Filament\Support;

use App\Services\Media\MediaLibrary;
use Closure;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Media library in every image field:
 *  - a "From library" button next to the label opens the library and puts the chosen image(s) into the field
 *  - every image uploaded from the computer goes into the library automatically
 */
class LibraryPicker
{
    public static function isImageField(BaseFileUpload $c): bool
    {
        $types = (array) ($c->getAcceptedFileTypes() ?? []);

        return $c->getDiskName() === 'public' && (in_array('image/*', $types, true) || (bool) array_filter($types, fn ($t) => str_starts_with((string) $t, 'image/')));
    }

    public static function boot(): void
    {
        FileUpload::configureUsing(function (FileUpload $component) {
            $component->hintAction(static::action());

            // register uploads in the library (keeps Filament's own saving)
            $original = (fn () => $this->saveUploadedFileUsing)->call($component);
            if ($original instanceof Closure) {
                $component->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($original) {
                    $path = $component->evaluate($original, ['file' => $file]);
                    if (is_string($path) && static::isImageField($component)) {
                        try {
                            MediaLibrary::register($path, MediaLibrary::agencyFor($component), 'upload', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }

                    return $path;
                });
            }
        });
    }

    public static function action(): Action
    {
        return Action::make('fromLibrary')
            ->label(__('From library'))
            ->icon('heroicon-m-photo')
            ->color('primary')
            ->visible(fn (BaseFileUpload $component) => static::isImageField($component) && ! $component->isDisabled() && Auth::check())
            ->modalHeading(fn (BaseFileUpload $component) => $component->isMultiple() ? __('Choose images from the library') : __('Choose an image from the library'))
            ->modalWidth('6xl')
            ->modalSubmitActionLabel(__('Insert'))
            ->form(fn (BaseFileUpload $component) => [
                ViewField::make('picked')->hiddenLabel()->default([])
                    ->view('filament.library.picker')
                    ->viewData(['multiple' => $component->isMultiple(), 'agency' => MediaLibrary::agencyFor($component)]),
            ])
            ->action(function (array $data, BaseFileUpload $component) {
                $picked = array_values(array_filter((array) ($data['picked'] ?? []), 'is_string'));
                if (! $picked) {
                    return;
                }
                if ($component->isMultiple()) {
                    $state = (array) ($component->getState() ?? []);
                    $have = array_values($state);
                    foreach ($picked as $p) {
                        if (! in_array($p, $have, true)) {
                            $state[(string) Str::uuid()] = $p;
                        }
                    }
                } else {
                    $state = [(string) Str::uuid() => $picked[0]];
                }
                $component->state($state);
                $component->callAfterStateUpdated();
            });
    }
}
