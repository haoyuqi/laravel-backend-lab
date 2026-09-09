<?php

namespace App\Filament\Resources\VisitorLogResource\Pages;

use App\Filament\Resources\VisitorLogResource;
use Filament\Resources\Pages\ListRecords;

class ListVisitorLogs extends ListRecords
{
    protected static string $resource = VisitorLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
