<?php

namespace App\Service;

use App\Models\AdminOperationLog;
use App\Models\User;
use App\Support\AdminOperationContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class AdminOperationRecorder
{
    public function __construct(private AdminOperationContext $context) {}

    public function actor(): User
    {
        $panel = Filament::getPanel('admin');
        $actor = $panel->auth()->user();
        if (! $actor instanceof User || ! $actor->canAccessPanel($panel)) {
            throw new AuthorizationException('Admin access is required.');
        }

        return $actor;
    }

    public function within(string $action, Closure $callback, ?Connection $connection = null): mixed
    {
        return $this->context->within($this->actor(), $action, ($connection ?? DB::connection())->getName(), $callback);
    }

    public function run(Connection $connection, string $action, Closure $callback): mixed
    {
        return $this->within($action, fn () => $connection->transaction($callback), $connection);
    }

    public function policy(Model $model): array
    {
        $policy = config('admin-operation-logs.models.'.get_class($model));
        if (! is_array($policy) || ! isset($policy['type'], $policy['fields'])) {
            throw new LogicException('Writable admin models require a safe audit policy.');
        }

        return $policy;
    }

    public function snapshot(Model $model): array
    {
        $snapshot = [];
        foreach ($this->policy($model)['fields'] as $field) {
            if (! $this->sensitive($field) && ! in_array($field, $model->getHidden(), true)) {
                $snapshot[$field] = $this->safeValue($model->getAttribute($field));
            }
        }
        if (method_exists($model, 'trashed')) {
            $snapshot['deleted'] = $model->trashed();
        }

        return $this->bounded($snapshot);
    }

    public function changed(Model $model, string $action, ?array $before, ?array $after, bool $attributesChanged = false): void
    {
        if ($action === 'update' && $before === $after && ! $attributesChanged) {
            return;
        }
        $this->assertTransaction($model);
        $this->insert($model->getConnection(), $this->context->actor, [
            'action' => $action,
            'action_key' => $this->context->actionKey,
            'operation_id' => $this->context->operationId,
            'subject_type' => $this->policy($model)['type'],
            'subject_id' => (string) $model->getKey(),
            'before' => $before,
            'after' => $after,
        ]);
    }

    // Builder/pivot writes must provide locked, safe snapshots explicitly.
    public function explicit(Model $subject, string $action, Closure $mutation, ?Closure $relationshipSnapshot = null): mixed
    {
        return $this->run($subject->getConnection(), $action, function () use ($subject, $action, $mutation, $relationshipSnapshot) {
            $current = $this->query($subject)->lockForUpdate()->findOrFail($subject->getKey());
            $before = $this->explicitSnapshot($current, $relationshipSnapshot);
            $beforeModel = clone $current;
            $key = spl_object_id($current);
            $this->context->explicitSubjects[$key] = true;
            try {
                $result = $mutation($current);
            } finally {
                unset($this->context->explicitSubjects[$key]);
            }
            if ($result === false) {
                throw new RuntimeException('Admin operation was canceled.');
            }
            $after = $this->query($subject)->find($subject->getKey());
            $this->changed($subject, $action, $before, $after ? $this->explicitSnapshot($after, $relationshipSnapshot) : null,
                $after && $this->attributesDiffer($beforeModel, $after));

            return $result;
        });
    }

    // Compare persisted values without adding excluded values to the log.
    public function attributesDiffer(Model $before, Model $after): bool
    {
        $ignored = [$before->getCreatedAtColumn(), $before->getUpdatedAtColumn()];
        $old = $before->getAttributes();
        $new = $after->getAttributes();
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            if (! in_array($field, $ignored, true) && ($old[$field] ?? null) !== ($new[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function explicitSnapshot(Model $model, ?Closure $relationships): array
    {
        $snapshot = $this->snapshot($model);
        if ($relationships === null) {
            return $snapshot;
        }
        $values = $relationships($model);
        $fields = $this->policy($model)['relationship_fields'] ?? [];
        if (! is_array($values) || $fields === [] || array_diff(array_keys($values), $fields)) {
            throw new LogicException('Relationship snapshots require explicit safe field policies.');
        }
        foreach ($values as $field => $value) {
            if ($this->sensitive((string) $field) || array_key_exists($field, $snapshot)) {
                throw new LogicException('Relationship snapshots cannot contain sensitive or duplicate fields.');
            }
            $snapshot[$field] = $this->safeValue($value);
        }

        return $this->bounded($snapshot);
    }

    private function bounded(array $snapshot): array
    {
        if (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) > 16384) {
            throw new LogicException('Admin audit snapshots exceed the safe size limit.');
        }

        return $snapshot;
    }

    public function authentication(User $actor, string $action): void
    {
        $this->insert($actor->getConnection(), $actor, [
            'action' => $action, 'action_key' => $action,
            'operation_id' => (string) Str::uuid(),
            'subject_type' => null, 'subject_id' => null, 'before' => null, 'after' => null,
        ]);
    }

    public function assertTransaction(Model $model): void
    {
        $this->policy($model);
        if (! $this->context->actor || $model->getConnection()->getName() !== $this->context->connection ||
            $model->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Admin writes require the same-connection audit transaction.');
        }
    }

    public function query(Model $model): Builder
    {
        $query = $model->newQuery();

        return method_exists($model, 'trashed') ? $query->withTrashed() : $query;
    }

    private function insert(Connection $connection, User $actor, array $data): void
    {
        $log = new AdminOperationLog;
        $log->setConnection($connection->getName());
        $log->forceFill($data + [
            'actor_id' => $actor->getKey(), 'actor_name' => Str::limit($actor->name, 255, ''),
            'panel' => 'admin', 'occurred_at' => now(),
        ]);
        if (! $log->save()) {
            throw new RuntimeException('Admin operation was canceled; the audit log was not saved.');
        }
    }

    private function sensitive(string $field): bool
    {
        return preg_match('/password|token|secret|credential|cookie|authorization|email/i', $field) === 1;
    }

    private function safeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 4) {
            throw new LogicException('Admin audit snapshots exceed the safe nesting limit.');
        }
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $key => $item) {
                if (! $this->sensitive((string) $key)) {
                    $safe[$key] = $this->safeValue($item, $depth + 1);
                }
            }

            return $safe;
        }
        if (is_object($value) || is_resource($value)) {
            throw new LogicException('Admin audit snapshots require scalar or array values.');
        }

        return $value;
    }
}
