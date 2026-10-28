<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class BlackList extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['ip'];

    public function logs()
    {
        return $this->hasMany(BlackListLog::class);
    }

    public function city()
    {
        return $this->belongsTo(Visitor::class, 'ip', 'ip')->withTrashed();
    }
}
