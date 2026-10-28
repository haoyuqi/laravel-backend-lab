<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['ip', 'city'];

    public function logs()
    {
        return $this->hasMany(VisitorLog::class);
    }

    public function blackList()
    {
        return $this->hasOne(BlackList::class, 'ip', 'ip');
    }
}
