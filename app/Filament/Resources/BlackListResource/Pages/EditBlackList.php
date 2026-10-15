<?php

namespace App\Filament\Resources\BlackListResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\BlackListResource;
use App\Models\BlackList;
use App\Service\AdminBlackListOperations;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class EditBlackList extends EditRecord
{
    protected static string $resource = BlackListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(fn (BlackList $record): bool => app(AdminBlackListOperations::class)->delete($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->record = app(AdminBlackListOperations::class)->update($record, $data);

        return $this->record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
