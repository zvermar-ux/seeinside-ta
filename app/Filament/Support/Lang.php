<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Two-language form fields. Values are stored in JSON columns as {"mk": "...", "en": "..."};
 * the form shows one tab per language so both are visible side by side.
 */
class Lang
{
    public const LOCALES = ['mk' => 'МК', 'en' => 'EN'];

    /** @param Closure(string $locale): array $schema */
    public static function tabs(Closure $schema, ?string $id = null): Tabs
    {
        $tabs = [];
        foreach (self::LOCALES as $locale => $label) {
            $tabs[] = Tab::make($label)->schema($schema($locale));
        }

        return Tabs::make($id ?? 'lang-'.uniqid())->tabs($tabs)->columnSpanFull()->contained(false);
    }

    public static function text(string $name, string $label, string $locale, bool $requiredInMk = false): TextInput
    {
        return TextInput::make("{$name}.{$locale}")
            ->label($label)
            ->required($requiredInMk && $locale === 'mk')
            ->maxLength(255);
    }

    public static function area(string $name, string $label, string $locale, int $rows = 3): Textarea
    {
        return Textarea::make("{$name}.{$locale}")->label($label)->rows($rows)->autosize();
    }

    /** Textarea where every line is one bullet on the website */
    public static function lines(string $name, string $label, string $locale): Textarea
    {
        return Textarea::make("{$name}.{$locale}")->label($label)->rows(5)->autosize()
            ->helperText(__('One item per line.'));
    }

    public static function editor(string $name, string $label, string $locale): RichEditor
    {
        return RichEditor::make("{$name}.{$locale}")->label($label)
            ->toolbarButtons(['bold', 'italic', 'underline', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
            ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('content');
    }
}
