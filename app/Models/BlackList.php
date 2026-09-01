<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class BlackList extends BaseModel
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = ['ip'];

    public static $alias = [
        'id' => 'ID',
        'ip' => 'IP',
        'created_at' => '添加时间',
        'updated_at' => '最后访问时间',
        'deleted_at' => '删除时间',
        'today_logs_count' => '今日访问数量',
        'all_logs_count' => '历史访问数量',
        'urls' => '访问记录',
    ];

    public function logs()
    {
        return $this->hasMany(BlackListLog::class);
    }

    public function city()
    {
        return $this->belongsTo(Visitor::class, 'ip', 'ip');
    }
}
