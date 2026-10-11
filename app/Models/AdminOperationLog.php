<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AdminOperationLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Admin operation logs cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Admin operation logs cannot be deleted.'));
    }
}
