<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\Permissions;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SyncsRolesAndPermissions;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->primaryRole() ?? Permissions::OPERATOR;
        $data['permission_names'] = $this->record->getDirectPermissions()->pluck('name')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractRoleData($data);
    }

    protected function afterSave(): void
    {
        $this->applyRoleData($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
