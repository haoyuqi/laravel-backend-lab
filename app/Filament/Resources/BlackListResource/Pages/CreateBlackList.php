<?php

namespace App\Filament\Resources\BlackListResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\BlackListResource;
use App\Service\AdminBlackListOperations;
use Illuminate\Database\Eloquent\Model;

class CreateBlackList extends CreateRecord
{
    protected static string $resource = BlackListResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(AdminBlackListOperations::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
