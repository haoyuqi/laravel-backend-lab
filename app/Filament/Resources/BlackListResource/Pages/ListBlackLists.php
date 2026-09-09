<?php

namespace App\Filament\Resources\BlackListResource\Pages;

use App\Filament\Resources\BlackListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlackLists extends ListRecords
{
    protected static string $resource = BlackListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
