<?php

namespace App\Filament\Resources\BlackListResource\Pages;

use App\Filament\Resources\BlackListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlackList extends EditRecord
{
    protected static string $resource = BlackListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
