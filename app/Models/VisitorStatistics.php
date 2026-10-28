<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorStatistics extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['type', 'date', 'count'];
}
