<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorLog extends BaseModel
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    // 更新父级时间戳
    protected $touches = ['visitor'];

    public static $alias = [
        'id' => 'ID',
        'url' => 'URL',
        'created_at' => '访问时间',
        'updated_at' => '更新时间',
        'number' => '序号',
    ];

    public function visitor()
    {
        return $this->belongsTo(Visitor::class)->withTrashed();
    }
}
