<?php

namespace App\Filament\Resources\CustomRecordResource\Pages;

use App\Filament\Resources\CustomRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomRecord extends CreateRecord
{
    protected static string $resource = CustomRecordResource::class;

    #[\Livewire\Attributes\Url]
    public ?string $type = null;

    public function getTitle(): string
    {
        $t = $this->type ? \App\Models\RecordType::where('slug', $this->type)->first() : null;

        return $t ? __('New').': '.mb_strtolower($t->singularLabel()) : parent::getTitle();
    }

    protected function getRedirectUrl(): string
    {
        return CustomRecordResource::getUrl('index', ['type' => $this->record->recordType?->slug]);
    }
}
