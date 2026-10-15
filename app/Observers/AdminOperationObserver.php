<?php

namespace App\Observers;

use App\Service\AdminOperationRecorder;
use App\Support\AdminOperationContext;
use Illuminate\Database\Eloquent\Model;

class AdminOperationObserver
{
    public function __construct(private AdminOperationContext $context, private AdminOperationRecorder $recorder) {}

    public function handle(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;
        if (! $this->context->actor || ! $model instanceof Model ||
            ! str_starts_with(get_class($model), 'App\\Models\\') ||
            in_array(get_class($model), config('admin-operation-logs.excluded', []), true)) {
            return;
        }
        $phase = substr($event, strlen('eloquent.'), strpos($event, ':') - strlen('eloquent.'));
        $key = spl_object_id($model);
        if (isset($this->context->explicitSubjects[$key])) {
            $this->recorder->assertTransaction($model);

            return;
        }
        if ($phase === 'restoring') {
            $this->recorder->assertTransaction($model);

            return;
        }
        if (in_array($phase, ['creating', 'updating', 'deleting'], true)) {
            $this->recorder->assertTransaction($model);
            $current = $phase === 'creating' ? null : $this->recorder->query($model)->lockForUpdate()->findOrFail($model->getKey());
            $this->context->pending[$key] = [
                'action' => ['creating' => 'create', 'updating' => 'update', 'deleting' => 'delete'][$phase],
                'before' => $current ? $this->recorder->snapshot($current) : null,
                'model_before' => $current,
            ];
        } elseif (in_array($phase, ['created', 'updated', 'deleted'], true)) {
            $pending = $this->context->pending[$key] ?? null;
            if (! $pending) {
                return;
            }
            unset($this->context->pending[$key]);
            $current = $this->recorder->query($model)->find($model->getKey());
            $after = $current ? $this->recorder->snapshot($current) : null;
            $action = $pending['action'];
            if ($action === 'update' && ($pending['before']['deleted'] ?? false) && isset($after['deleted']) && ! $after['deleted']) {
                $action = 'restore';
            }
            $this->recorder->changed($model, $action, $pending['before'], $after,
                $pending['model_before'] && $current && $this->recorder->attributesDiffer($pending['model_before'], $current));
        }
    }
}
