<?php

namespace App\Admin\Actions\Visitor;

use App\Models\BlackList;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;

class AddToBlackList extends BatchAction
{
    public $name = '添加黑名单';

    public function handle(Collection $collection)
    {
        foreach ($collection as $model) {
            $black_list = $model->blackList()->withTrashed()->first();

            if ($black_list) {
                $black_list->deleted_at = null;
            } else {
                $black_list = new BlackList;
            }

            $model->blackList()->save($black_list);
        }

        return $this->response()->success('添加成功！')->refresh();
    }
}
