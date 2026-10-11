<?php

namespace App\Filament\Resources\BlackListLogResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\BlackListLogResource;

class ListBlackListLogs extends ListRecords
{
    protected static string $resource = BlackListLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
