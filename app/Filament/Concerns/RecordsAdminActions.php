<?php

namespace App\Filament\Concerns;

use App\Service\AdminOperationRecorder;
use Filament\Actions\BulkAction;

trait RecordsAdminActions
{
    public function callMountedAction(array $arguments = []): mixed
    {
        $action = $this->getMountedAction();
        // Bulk callbacks own their per-record transactions and failure handling.
        $action?->databaseTransaction(! $action instanceof BulkAction);

        return app(AdminOperationRecorder::class)->within(
            static::getResource().'.'.($action?->getName() ?? 'action'),
            fn () => parent::callMountedAction($arguments),
        );
    }
}
