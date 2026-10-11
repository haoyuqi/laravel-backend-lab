<?php

namespace App\Service;

use App\Models\BlackList;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AdminBlackListOperations
{
    public function create(array $data): BlackList
    {
        $actor = $this->actor();
        $ip = $this->validatedIp($data);

        return app(AdminOperationRecorder::class)->run((new BlackList)->getConnection(), 'black_list.create', function () use ($ip): BlackList {
            $record = new BlackList(['ip' => $ip]);
            $this->requireSuccess($record->save());
            $this->invalidate($record, null);

            return $record;
        });
    }

    public function update(BlackList $record, array $data): BlackList
    {
        $actor = $this->actor();
        $ip = $this->validatedIp($data);

        return app(AdminOperationRecorder::class)->run($record->getConnection(), 'black_list.update', function () use ($record, $ip): BlackList {
            $current = $this->locked($record);
            if ($current->trashed()) {
                throw (new ModelNotFoundException)->setModel(BlackList::class, [$record->getKey()]);
            }
            $before = $this->snapshot($current);
            if ($current->ip !== $ip) {
                $current->ip = $ip;
                $this->requireSuccess($current->save());
                $this->invalidate($current, $before);
            }

            return $current;
        });
    }

    public function delete(BlackList $record): bool
    {
        $actor = $this->actor();

        return app(AdminOperationRecorder::class)->run($record->getConnection(), 'black_list.delete', function () use ($record): bool {
            $current = $this->locked($record);
            if ($current->trashed()) {
                return true;
            }
            $before = $this->snapshot($current);
            $this->requireSuccess($current->delete());
            $this->invalidate($current, $before);

            return true;
        });
    }

    public function addIp(string $ip): BlackList
    {
        $actor = $this->actor();
        $ip = $this->validatedIp(['ip' => $ip]);

        return app(AdminOperationRecorder::class)->run((new BlackList)->getConnection(), 'black_list.add_ip', function () use ($ip): BlackList {
            $record = BlackList::withTrashed()->where('ip', $ip)->lockForUpdate()->first();
            if (! $record) {
                $record = new BlackList(['ip' => $ip]);
                $this->requireSuccess($record->save());
                $this->invalidate($record, null);
            } elseif ($record->trashed()) {
                $before = $this->snapshot($record);
                $this->requireSuccess($record->restore());
                $this->invalidate($record, $before);
            }

            return $record;
        });
    }

    private function actor(): User
    {
        $panel = Filament::getPanel('admin');
        $actor = $panel->auth()->user();
        if (! $actor instanceof User || ! $actor->canAccessPanel($panel)) {
            throw new AuthorizationException('Admin access is required.');
        }

        return $actor;
    }

    private function validatedIp(array $data): string
    {
        return Validator::make($data, ['ip' => ['required', 'string', 'ip', 'max:45']])->validate()['ip'];
    }

    private function locked(BlackList $record): BlackList
    {
        return $record->newQuery()->withTrashed()->lockForUpdate()->findOrFail($record->getKey());
    }

    private function snapshot(BlackList $record): array
    {
        return ['ip' => $record->ip, 'deleted' => $record->trashed()];
    }

    private function invalidate(BlackList $record, ?array $before): void
    {
        $after = $this->snapshot($record);

        // Observers run before commit. Remove any old state refilled during the transaction.
        $ips = array_unique(array_filter([$before['ip'] ?? null, $after['ip']]));
        $record->getConnection()->afterCommit(function () use ($ips): void {
            foreach ($ips as $ip) {
                app(BlackListService::class)->forgetIp($ip);
            }
        });
    }

    private function requireSuccess(?bool $success): void
    {
        if (! $success) {
            throw new RuntimeException('Admin operation was canceled; no changes were committed.');
        }
    }
}
