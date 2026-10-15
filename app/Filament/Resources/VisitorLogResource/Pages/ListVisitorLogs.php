<?php

namespace App\Filament\Resources\VisitorLogResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\VisitorLogResource;

class ListVisitorLogs extends ListRecords
{
    protected static string $resource = VisitorLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
