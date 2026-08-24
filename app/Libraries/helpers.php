<?php

/**
 * Helpers.php
 * 辅助函数
 * Date: 2019/11/3
 */

use Illuminate\Support\Facades\Validator;

if (! function_exists('is_ip')) {
    function is_ip($ip)
    {
        return Validator::make(['ip' => $ip], [
            'ip' => 'required|ip',
        ])->passes();
    }
}
