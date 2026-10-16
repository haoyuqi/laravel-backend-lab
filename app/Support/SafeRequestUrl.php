<?php

namespace App\Support;

class SafeRequestUrl
{
    public static function sanitize(string $url, int $maxLength = 2048): string
    {
        $maxLength = max(1, min($maxLength, 2048));
        $url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url) ?? '';
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        // Keep query or fragment data out of log entries.
        $path = '/'.ltrim($path, '/');

        return mb_strcut($path, 0, $maxLength, 'UTF-8');
    }
}
