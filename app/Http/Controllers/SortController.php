<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckCountRequest;
use Illuminate\Http\JsonResponse;

class SortController extends Controller
{
    private $shuffleArr;

    public function __construct(CheckCountRequest $request)
    {
        $arr = range(1, $request->input('count'));
        shuffle($arr);

        $this->shuffleArr = $arr;
    }

    /**
     * 冒泡排序
     *
     * @return JsonResponse
     */
    public function bubbleSort()
    {
        return response()->json(bubble_sort($this->shuffleArr));
    }

    /**
     * 快速排序
     *
     * @return JsonResponse
     */
    public function quickSort()
    {
        return response()->json(quick_sort($this->shuffleArr));
    }

    /**
     * 选择排序
     *
     * @return JsonResponse
     */
    public function selectSort()
    {
        return response()->json(select_sort($this->shuffleArr));
    }

    /**
     * 插入排序
     *
     * @return JsonResponse
     */
    public function insertSort()
    {
        return response()->json(insert_sort($this->shuffleArr));
    }
}
