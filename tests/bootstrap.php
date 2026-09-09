<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| PHPUnit loads this file (see `bootstrap` in phpunit.xml) before any test
| case boots the application. Its job is to guarantee a clean, reproducible
| test environment:
|
|   1. Load Composer's autoloader.
|   2. Remove any cached configuration/events. If a developer has run
|      `php artisan config:cache` (a common deploy/optimization step), Laravel
|      would otherwise boot every test application from the cached config and
|      silently bypass the environment overrides declared in phpunit.xml —
|      including `DB_CONNECTION=testing`, which is what keeps the suite away
|      from the primary development database.
|
*/

require __DIR__.'/../vendor/autoload.php';

$cacheDir = __DIR__.'/../bootstrap/cache';

foreach (['config.php', 'events.php', 'routes-v7.php', 'routes.php'] as $cached) {
    $file = $cacheDir.'/'.$cached;

    if (is_file($file)) {
        @unlink($file);
    }
}
