<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use LogicException;

class AdminOperationContext
{
    public ?User $actor = null;

    public ?string $actionKey = null;

    public ?string $operationId = null;

    public ?string $connection = null;

    public array $pending = [];

    public array $explicitSubjects = [];

    public function within(User $actor, string $actionKey, string $connection, Closure $callback): mixed
    {
        if ($this->actor) {
            if ($this->connection !== $connection || $this->actor->getKey() !== $actor->getKey()) {
                throw new LogicException('An admin operation cannot span actors or database connections.');
            }

            return $callback();
        }

        $this->actor = $actor;
        $this->actionKey = $actionKey;
        $this->operationId = (string) Str::uuid();
        $this->connection = $connection;

        try {
            return $callback();
        } finally {
            $this->actor = null;
            $this->actionKey = null;
            $this->operationId = null;
            $this->connection = null;
            $this->pending = [];
            $this->explicitSubjects = [];
        }
    }
}
