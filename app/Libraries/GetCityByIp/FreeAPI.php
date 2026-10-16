<?php

namespace App\Libraries\GetCityByIp;

use Illuminate\Support\Facades\Log;

class FreeAPI extends GetCityByIpAbstract
{
    private $APIUrl = 'http://ip-api.com/json/';

    public function getCity($ip)
    {
        if (! $this->checkIp($ip)) {
            Log::error('Ip 地址错误。');
            Log::error($ip);

            return '';
        }

        $response = $this->client->request('GET', $this->APIUrl.$ip.'?lang=zh-CN', [
            'timeout' => 3,
            'connect_timeout' => 1,
        ]);
        $response_collect = collect(json_decode($response->getBody()->getContents()));

        if ($response->getStatusCode() != 200 || $response_collect->get('status') != 'success') {
            Log::error('获取数据错误。');
            Log::error($ip);
            Log::error($response_collect->toArray());

            return '';
        }

        return $response_collect->get('city');
    }
}
