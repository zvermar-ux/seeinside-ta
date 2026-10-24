<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Actions;
use App\Models\RecordType;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

class ListCustomRecords extends ListRecords
{
    protected static string $resource = CustomRecordResource::class;

    #[Url]
    public ?string $type = null;

    public function mount(): void
    {
        parent::mount();
        // no type chosen: the first one
        if (! $this->type && ($first = CustomRecordResource::types()->first())) {
            $this->redirect(CustomRecordResource::getUrl('index', ['type' => $first->slug]));
        }
    }

    public function recordType(): ?RecordType
    {
        return $this->type ? RecordType::where('slug', $this->type)->first() : null;
    }

    public function table(Table $table): Table
    {
        return CustomRecordResource::tableFor($table, $this->recordType());
    }

    public function getTitle(): string
    {
        return $this->recordType()?->label() ?? CustomRecordResource::getPluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $t = $this->recordType();

        return [Actions\CreateAction::make()->label(__('New').($t ? ': '.mb_strtolower($t->singularLabel()) : ''))
            ->url(CustomRecordResource::getUrl('create', array_filter(['type' => $t?->slug])))];
    }
}
