<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RecordsAdminActions;

abstract class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    use RecordsAdminActions;
}
