<?php

namespace App\Filament\Support;

use App\Enums\GroupType;
use App\Filament\Resources\GroupResource;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Tables;
use Illuminate\Support\HtmlString;

/** Building blocks shared by tours, events, accommodations and visas */
class CatalogFields
{
    /** Main photo (WordPress featured image) + gallery upload while creating */
    public static function photos(string $directory): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Photos'))->icon('heroicon-o-photo')->schema([
            Forms\Components\FileUpload::make('cover_image')->label(__('Main photo'))
                ->helperText(__('Shown in lists and as the featured image on the website.'))
                ->image()->imageEditor()->imageEditorAspectRatios(['16:9', '4:3', '1:1', null])
                ->disk('public')->directory($directory)->maxSize(10240),
            Forms\Components\FileUpload::make('gallery_upload')->label(__('Gallery'))
                ->helperText(__('You can add more photos later in the "Gallery" tab.'))
                ->image()->multiple()->reorderable()->maxFiles(60)->maxSize(10240)
                ->disk('public')->directory('gallery')
                ->visibleOn('create'),
        ]);
    }

    /**
     * Gallery edited directly in the form (used in modals, e.g. hotel rooms): the uploaded photos
     * are kept in the shared media table, in the order shown, the first one marked as main.
     */
    public static function galleryField(string $directory = 'gallery'): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('gallery')->label(__('Gallery'))
            ->image()->multiple()->reorderable()->appendFiles()->maxFiles(60)->maxSize(10240)
            ->disk('public')->directory($directory)->panelLayout('grid')
            ->dehydrated(false)
            ->loadStateFromRelationshipsUsing(function (Forms\Components\FileUpload $component, ?\Illuminate\Database\Eloquent\Model $record) {
                $component->state($record?->exists ? $record->media()->pluck('path')->all() : []);
            })
            ->saveRelationshipsUsing(function (\Illuminate\Database\Eloquent\Model $record, $state) {
                $paths = array_values(array_filter((array) $state, 'is_string'));
                $record->media()->whereNotIn('path', $paths)->get()->each->delete();
                $existing = $record->media()->get()->keyBy('path');
                foreach ($paths as $i => $path) {
                    $values = ['sort_order' => $i + 1, 'is_main' => $i === 0];
                    if ($m = $existing->get($path)) {
                        $m->update($values);
                    } else {
                        $record->media()->create(['path' => $path] + $values);
                    }
                }
            });
    }

    public static function promotions(): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Last minute / First minute'))
            ->icon('heroicon-o-bolt')
            ->columns(3)
            ->schema([
                Forms\Components\Toggle::make('is_last_minute')->label(__('Last minute offer'))->live()->columnSpanFull(),
                Forms\Components\TextInput::make('last_minute_price')->label(__('Last minute price'))->numeric()
                    ->visible(fn (Get $get) => $get('is_last_minute')),
                Forms\Components\TextInput::make('last_minute_discount_percent')->label(__('or discount'))->numeric()->suffix('%')
                    ->visible(fn (Get $get) => $get('is_last_minute')),
                Forms\Components\DatePicker::make('last_minute_until')->label(__('Valid until'))->native(false)->displayFormat('d.m.Y')
                    ->visible(fn (Get $get) => $get('is_last_minute')),
                Forms\Components\TextInput::make('first_minute_discount')->label(__('First minute discount'))->numeric(),
                Forms\Components\Select::make('first_minute_discount_type')->label(__('Discount type'))
                    ->options(['amount' => __('Amount per person'), 'percent' => '%'])->default('amount')->selectablePlaceholder(false),
                Forms\Components\DatePicker::make('first_minute_until')->label(__('For bookings until'))->native(false)->displayFormat('d.m.Y'),
            ]);
    }

    public static function publishing(bool $withBooking = true): Forms\Components\Section
    {
        return Forms\Components\Section::make(__('Publishing'))
            ->schema(array_values(array_filter([
                Forms\Components\Toggle::make('is_active')->label(__('Active on website'))->default(true),
                Forms\Components\Toggle::make('is_featured')->label(__('Featured')),
                $withBooking ? Forms\Components\Toggle::make('booking_enabled')->label(__('Online booking form'))->default(true) : null,
                Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
                Forms\Components\TextInput::make('slug')->label(__('URL (slug)'))->helperText(__('Created automatically from the title.'))->maxLength(255),
            ])));
    }

    public static function seo(): Forms\Components\Section
    {
        return Forms\Components\Section::make('SEO')->collapsible()->collapsed()->schema([
            Lang::tabs(fn (string $l) => [
                Lang::text('seo_title', __('SEO title'), $l),
                Lang::area('seo_description', __('SEO description'), $l, 2),
            ]),
        ]);
    }

    public static function location(): array
    {
        return [
            Forms\Components\TextInput::make('map_location')->label(__('Map (lat,lng or address)'))
                ->placeholder('40.845640,25.869948')->live(onBlur: true)->columnSpanFull(),
            Forms\Components\Placeholder::make('map_preview')->hiddenLabel()->columnSpanFull()
                ->visible(fn (Get $get) => filled($get('map_location')))
                ->content(fn (Get $get) => new HtmlString(
                    '<iframe class="ta-map" loading="lazy" src="https://maps.google.com/maps?q='.urlencode((string) $get('map_location')).'&z=13&output=embed"></iframe>'
                )),
        ];
    }

    public static function videos(): Forms\Components\TagsInput
    {
        return Forms\Components\TagsInput::make('video_urls')->label(__('Video links (YouTube / Vimeo)'))
            ->placeholder(__('Paste a link and press Enter'))->columnSpanFull();
    }

    public static function groupSelect(string $field, GroupType $type, string $label): Forms\Components\Select
    {
        return Forms\Components\Select::make($field)->label($label)
            ->options(fn (Get $get) => GroupResource::optionsFor($type, AgencyField::current($get)))
            ->searchable();
    }

    /* ---------- table helpers ---------- */

    public static function titleColumn(string $attr = 'title'): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($attr)->label(__('Title'))->weight('semibold')->wrap()
            ->state(fn ($record) => $record->tr($attr))
            ->searchable(query: fn ($query, string $search) => $query->where($attr, 'like', '%'.$search.'%'))
            ->extraAttributes(['style' => 'min-width:16rem']);
    }

    public static function coverColumn(): Tables\Columns\ImageColumn
    {
        return Tables\Columns\ImageColumn::make('cover_image')->label('')->disk('public')->square()->size(52)
            ->defaultImageUrl('data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 16l-5-5-8 8"/></svg>'));
    }

    public static function lastMinuteColumn(): Tables\Columns\IconColumn
    {
        return Tables\Columns\IconColumn::make('is_last_minute')->label('LM')->tooltip('Last minute')
            ->boolean()->trueIcon('heroicon-s-bolt')->falseIcon('heroicon-o-minus')->trueColor('danger')->falseColor('gray');
    }

    public static function lastMinuteFilter(): Tables\Filters\TernaryFilter
    {
        return Tables\Filters\TernaryFilter::make('is_last_minute')->label('Last minute');
    }
}
