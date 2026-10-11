<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RecordsAdminActions;
use App\Service\AdminOperationRecorder;
use Filament\Schemas\Components\Component;

abstract class EditRecord extends \Filament\Resources\Pages\EditRecord
{
    use RecordsAdminActions;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        app(AdminOperationRecorder::class)->within(static::getResource().'.save', fn () => parent::save($shouldRedirect, $shouldSendSavedNotification));
    }

    public function saveFormComponentOnly(Component $component): void
    {
        app(AdminOperationRecorder::class)->within(static::getResource().'.save_component', fn () => parent::saveFormComponentOnly($component));
    }
}
