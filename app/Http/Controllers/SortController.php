<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckCountRequest;
use Illuminate\Http\JsonResponse;

class SortController extends Controller
{
    private function getShuffleArr(CheckCountRequest $request): array
    {
        $arr = range(1, (int) $request->input('count'));
        shuffle($arr);

        return $arr;
    }

    /**
     * 冒泡排序
     */
    public function bubbleSort(CheckCountRequest $request): JsonResponse
    {
        return response()->json(bubble_sort($this->getShuffleArr($request)));
    }

    /**
     * 快速排序
     */
    public function quickSort(CheckCountRequest $request): JsonResponse
    {
        return response()->json(quick_sort($this->getShuffleArr($request)));
    }

    /**
     * 选择排序
     */
    public function selectSort(CheckCountRequest $request): JsonResponse
    {
        return response()->json(select_sort($this->getShuffleArr($request)));
    }

    /**
     * 插入排序
     */
    public function insertSort(CheckCountRequest $request): JsonResponse
    {
        return response()->json(insert_sort($this->getShuffleArr($request)));
    }
}
