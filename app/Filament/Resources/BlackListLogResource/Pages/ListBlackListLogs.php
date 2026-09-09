<?php

namespace App\Filament\Resources\BlackListLogResource\Pages;

use App\Filament\Resources\BlackListLogResource;
use Filament\Resources\Pages\ListRecords;

class ListBlackListLogs extends ListRecords
{
    protected static string $resource = BlackListLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
