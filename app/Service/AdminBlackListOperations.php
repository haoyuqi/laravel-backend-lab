<?php

namespace App\Service;

use App\Models\BlackList;
use App\Models\User;
use App\Support\IpAddress;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminBlackListOperations
{
    public function create(array $data): BlackList
    {
        $actor = $this->actor();
        $ip = $this->validatedIp($data);

        return app(AdminOperationRecorder::class)->run((new BlackList)->getConnection(), 'black_list.create', function () use ($ip): BlackList {
            $this->assertNoEquivalentRecord($ip);
            $record = new BlackList(['ip' => $ip]);
            $this->saveRecord($record);
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
            $this->assertNoEquivalentRecord($ip, $current->getKey());
            $before = $this->snapshot($current);
            if ($current->ip !== $ip) {
                $current->ip = $ip;
                $this->saveRecord($current);
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
            $record = IpAddress::findBlacklistRecord(BlackList::withTrashed()->lockForUpdate(), $ip);
            if (! $record) {
                $record = new BlackList(['ip' => $ip]);
                $this->saveRecord($record);
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
        $ip = Validator::make($data, ['ip' => ['required', 'string', 'ip', 'max:45']])->validate()['ip'];

        return IpAddress::normalize($ip);
    }

    private function locked(BlackList $record): BlackList
    {
        return $record->newQuery()->withTrashed()->lockForUpdate()->findOrFail($record->getKey());
    }

    private function assertNoEquivalentRecord(string $ip, int|string|null $exceptId = null): void
    {
        $query = BlackList::withTrashed()->lockForUpdate();
        if ($exceptId !== null) {
            $query->where((new BlackList)->getKeyName(), '!=', $exceptId);
        }

        if (IpAddress::findBlacklistRecord($query, $ip)) {
            throw ValidationException::withMessages(['ip' => 'This IP address is already on the blacklist.']);
        }
    }

    private function saveRecord(BlackList $record): void
    {
        try {
            $this->requireSuccess($record->save());
        } catch (QueryException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));
            $isUniqueViolation = in_array($state, ['23000', '23505'], true);
            $isBlacklistIpConstraint = str_contains($message, 'black_lists.ip') ||
                str_contains($message, 'black_lists_ip_unique');

            if ($isUniqueViolation && $isBlacklistIpConstraint) {
                throw ValidationException::withMessages(['ip' => 'This IP address is already on the blacklist.']);
            }

            throw $exception;
        }
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
