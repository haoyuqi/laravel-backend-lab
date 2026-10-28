<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorLog extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['url', 'event_id'];

    // 更新父级时间戳
    protected $touches = ['visitor'];

    public function visitor()
    {
        return $this->belongsTo(Visitor::class)->withTrashed();
    }
}
