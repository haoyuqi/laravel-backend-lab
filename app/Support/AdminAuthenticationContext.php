<?php

namespace App\Support;

use App\Models\User;
use Closure;

class AdminAuthenticationContext
{
    public ?string $kind = null;

    public ?User $pending = null;

    public bool $failed = false;

    public function within(string $kind, Closure $callback): mixed
    {
        $this->kind = $kind;
        $this->pending = null;
        $this->failed = false;
        try {
            return $callback();
        } finally {
            $this->kind = null;
            $this->pending = null;
            $this->failed = false;
        }
    }
}
