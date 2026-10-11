<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RecordsAdminActions;
use App\Service\AdminOperationRecorder;

abstract class CreateRecord extends \Filament\Resources\Pages\CreateRecord
{
    use RecordsAdminActions;

    public function create(bool $another = false): void
    {
        app(AdminOperationRecorder::class)->within(static::getResource().'.create', fn () => parent::create($another));
    }
}
