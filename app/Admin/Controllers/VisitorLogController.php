<?php

namespace App\Admin\Controllers;

use App\Models\VisitorLog;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class VisitorLogController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'VisitorLog';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new VisitorLog);

        $grid->column('id', __('Id'));
        $grid->column('visitor_id', __('Visitor id'));
        $grid->column('url', __('Url'));
        $grid->column('deleted_at', __('Deleted at'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));

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
        $show = new Show(VisitorLog::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('visitor_id', __('Visitor id'));
        $show->field('url', __('Url'));
        $show->field('deleted_at', __('Deleted at'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new VisitorLog);

        $form->number('visitor_id', __('Visitor id'));
        $form->url('url', __('Url'));

        return $form;
    }
}
