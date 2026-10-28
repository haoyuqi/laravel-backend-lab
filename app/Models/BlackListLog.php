<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class BlackListLog extends BaseModel
{
    use SoftDeletes;

    protected $fillable = ['url', 'event_id'];

    // 更新父级时间戳
    protected $touches = ['blackList'];

    public function blackList()
    {
        return $this->belongsTo(BlackList::class)->withTrashed();
    }
}
