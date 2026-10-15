<?php

namespace App\Filament\Resources\VisitorResource\Pages;

use App\Filament\Pages\ListRecords;
use App\Filament\Resources\VisitorResource;

class ListVisitors extends ListRecords
{
    protected static string $resource = VisitorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
