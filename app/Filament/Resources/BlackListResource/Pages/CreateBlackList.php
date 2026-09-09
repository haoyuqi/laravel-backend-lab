<?php

namespace App\Filament\Resources\BlackListResource\Pages;

use App\Filament\Resources\BlackListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlackList extends CreateRecord
{
    protected static string $resource = BlackListResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
