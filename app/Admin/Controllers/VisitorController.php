<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Visitor\AddToBlackList;
use App\Models\Visitor;
use App\Models\VisitorLog;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Table;
use Illuminate\Database\Eloquent\Collection;

class VisitorController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '访问记录';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Visitor);

        $grid->model()->withCount([
            'logs as all_logs_count',
            'logs as today_logs_count' => function ($query) {
                $query->whereDate('created_at', today());
            },
        ]);
        $grid->model()->orderBy('id', 'desc');
        $grid->model()->with('logs');

        $grid->column('id', __(Visitor::$alias['id']))->display(function ($id) {
            return '<a href="'.url('admin/visitor/'.$id).'" target="_blank" >'.$id.'</a>';
        })->sortable();

        $grid->column('ip', __(Visitor::$alias['ip']));
        $grid->column('city', __(Visitor::$alias['city']));
        $grid->column('today_logs_count', __(Visitor::$alias['today_logs_count']))->sortable();
        $grid->column('all_logs_count', __(Visitor::$alias['all_logs_count']))->sortable();
        $grid->column('blackList.id', __(Visitor::$alias['blackList-id']))->bool();
        $grid->column('urls', __(Visitor::$alias['urls']))
            ->expand(function ($model) {
                return new Table([__(VisitorLog::$alias['url']), __(VisitorLog::$alias['created_at'])],
                    $this->logs
                        ->sortByDesc('created_at')
                        ->take(10)
                        ->map(function ($item) {
                            return $item->only(['url', 'created_at']);
                        })
                        ->values()
                        ->all()
                );
            });
        $grid->column('created_at', __(Visitor::$alias['created_at']));
        $grid->column('updated_at', __(Visitor::$alias['updated_at']))->sortable();

        $grid->batchActions(function ($batch) {
            $batch->add(new AddToBlackList);
        });

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('ip', __(Visitor::$alias['ip']));
            $filter->like('city', __(Visitor::$alias['city']));
            $filter->between('created_at', __(Visitor::$alias['created_at']))->datetime();
            $filter->where(function ($query) {
                if ($this->input) {
                    return $query->has('blackList');
                } else {
                    return $query->doesntHave('blackList');
                }
            }, __(Visitor::$alias['blackList-id']))
                ->radio([1 => '是', 0 => '否']);
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param  mixed  $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Visitor::findOrFail($id));

        $show->field('id', __(Visitor::$alias['id']));
        $show->field('ip', __(Visitor::$alias['ip']));
        $show->field('city', __(Visitor::$alias['city']));
        $show->field('created_at', __(Visitor::$alias['created_at']));
        $show->field('updated_at', __(Visitor::$alias['updated_at']));

        $show->logs(__(Visitor::$alias['urls']), function ($logs) {
            $logs->resource('/admin/visitor_logs');

            $logs->model()->orderBy('id', 'desc');

            $logs->model()->collection(function (Collection $collection) {
                foreach ($collection as $key => $value) {
                    $value->number = $key + 1;
                }

                return $collection;
            });

            $logs->column('number', __(VisitorLog::$alias['number']));
            $logs->column('url', __(VisitorLog::$alias['url']));
            $logs->column('created_at', __(VisitorLog::$alias['created_at']))->sortable();

            $logs->disableActions();

            $logs->disableCreateButton();

            $logs->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('url', __(VisitorLog::$alias['url']));
                $filter->between('created_at', __(VisitorLog::$alias['created_at']))->datetime();
            });
        });

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Visitor);

        $form->ip('ip', __('Ip'));
        $form->text('city', __('City'));

        return $form;
    }
}
