<?php

namespace App\Filament\Resources\BlackListResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\BlackListResource;
use Filament\Actions\CreateAction;

class ListBlackLists extends ListRecords
{
    protected static string $resource = BlackListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
