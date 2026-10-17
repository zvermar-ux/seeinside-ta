<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SyncsRolesAndPermissions;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractRoleData($data);
        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->applyRoleData($this->record);
    }
}
