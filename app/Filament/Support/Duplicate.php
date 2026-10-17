<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * "Duplicate" row action: copies the record (and, optionally, its related data), marks the copy as
 * inactive with "(копија)" in the name, then opens the copy for editing.
 */
class Duplicate
{
    /** @param  Closure(Model $original, Model $copy): void|null  $withRelations */
    public static function action(?Closure $withRelations = null, array $except = []): Action
    {
        return Action::make('duplicate')
            ->label(__('Duplicate'))->tooltip(__('Duplicate'))
            ->icon('heroicon-o-document-duplicate')->color('gray')->iconButton()
            ->requiresConfirmation()
            ->modalHeading(__('Duplicate'))
            ->modalDescription(__('A copy is created (inactive) and opened for editing.'))
            ->action(function (Model $record, $livewire) use ($withRelations, $except) {
                $copy = static::copy($record, $withRelations, $except);
                Notification::make()->success()->title(__('Copy created'))->send();

                $resource = method_exists($livewire, 'getResource') ? $livewire::getResource() : null;
                if ($resource && $resource::hasPage('edit') && $resource::canEdit($copy)) {
                    $livewire->redirect($resource::getUrl('edit', ['record' => $copy]));
                }
            });
    }

    public static function copy(Model $record, ?Closure $withRelations = null, array $except = []): Model
    {
        $columns = Schema::getColumnListing($record->getTable());
        // Leave out non-columns (withCount / withSum aggregates) and fields that must stay unique
        $skip = array_merge(
            array_diff(array_keys($record->getAttributes()), $columns),
            array_intersect(array_merge(['slug', 'reference', 'external_id', 'external_source'], $except), $columns),
        );
        $copy = $record->replicate(array_values(array_unique($skip)));
        $suffix = fn ($locale) => $locale === 'en' ? ' (copy)' : ' (копија)';

        foreach (['title', 'name', 'company', 'region'] as $field) {
            if (! in_array($field, $columns, true)) {
                continue;
            }
            $value = $copy->getAttribute($field);
            if (is_array($value)) {
                $copy->setAttribute($field, collect($value)->map(fn ($v, $locale) => filled($v) ? $v.$suffix($locale) : $v)->all());
                break;
            }
            if (method_exists($copy, 'getTranslations') && in_array($field, $copy->translatable ?? [], true)) {
                foreach ($copy->getTranslations($field) as $locale => $v) {
                    $copy->setTranslation($field, $locale, $v.$suffix($locale));
                }
                break;
            }
            if (filled($value)) {
                $copy->setAttribute($field, $value.' (копија)');
                break;
            }
        }

        if (in_array('key', $columns, true) && filled($copy->getAttribute('key'))) {
            $copy->setAttribute('key', $copy->getAttribute('key').'_'.strtolower(\Illuminate\Support\Str::random(4)));
        }

        if (in_array('is_active', $columns, true)) {
            $copy->setAttribute('is_active', false);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($copy, $record, $withRelations) {
            $copy->save();
            if ($withRelations) {
                $withRelations($record, $copy);
            }

            return $copy;
        });
    }

    /** Copy gallery photos (same files, no re-upload) */
    public static function media(Model $from, Model $to): void
    {
        foreach ($from->media as $m) {
            $to->media()->create($m->only(['path', 'caption', 'is_main', 'sort_order']));
        }
    }
}
