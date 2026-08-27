<?php

namespace App\Libraries\GetCityByIp;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class GeoIP extends GetCityByIpAbstract
{
    public function getCity($ip)
    {
        if (! $this->checkIp($ip)) {
            Log::error('Ip 地址错误。');
            Log::error($ip);

            return '';
        }

        $result = geoip($ip)->toArray();
        if (! Arr::has($result, 'city') || blank(Arr::get($result, 'city'))) {
            Log::error('获取数据错误。');
            Log::error($ip);
            Log::error($result);

            return '';
        }

        return Arr::get($result, 'city');
    }
}
