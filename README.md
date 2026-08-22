# Laravel Example
基于 Laravel，集成常用功能。

<p align="center">
    <a href="https://github.com/haoyuqi/laravel-example/actions"><img alt="Build Status" src="https://github.com/haoyuqi/laravel-example/workflows/CI/badge.svg"></a>
    <a href="https://laravel.com/"><img alt="Laravel" src="https://img.shields.io/badge/Laravel-v9.5.1-%23fc2d1f"></a>
    <a href="https://github.com/haoyuqi/laravel-example/blob/master/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/haoyuqi/laravel-example"></a>
</p>

## 安装
1. 搭建 [Laradock](https://github.com/laradock/laradock) 环境
2. 根据下方所示开启相关容器 `docker-compose up -d nginx mysql ...`
3. 在 `workspace` 中依次执行
    1. `git clone https://github.com/haoyq02/laravel-example.git`
    2. `cp .env.example .env`
    3. `composer install --prefer-dist`
    4. `npm install && npm run dev`
    5. `php artisan key:generate`
    6. `php artisan horizon:publish`   
    7. `php artisan vendor:publish --provider="Encore\Admin\AdminServiceProvider"`
    8. `php artisan storage:link`
4. 修改 `.env` 中 `redis`、`mysql` 相关配置，`php artisan migrate`
5. 服务器、域名配置
6. `php-worker`, `laravel-horizon` 配置 `supervisord`, 维护队列   
7. `php artisan admin:install` 生成后台默认账号密码 `admin` `admin`

## Laradock Container
环境使用 [Laradock](https://github.com/laradock/laradock) 搭建，已使用如下容器
* nginx
* php-fpm
* mysql
* workspace
* redis
* memcached
* php-worker
* laravel-horizon
* laravel-echo-server

## Composer Package
| 名称                                                                             | 简介                  | 备注                                                      |
|--------------------------------------------------------------------------------|---------------------|---------------------------------------------------------|
| [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar)               | 调试工具                | dev                                                     |
| [Laravel IDE Helper Generator](https://github.com/barryvdh/laravel-ide-helper) | IDE 开发工具            | dev                                                     |
| [Laravel Horizon](https://github.com/laravel/horizon)                          | 队列系统                |                                                         |
| [Laravel-lang](https://github.com/overtrue/laravel-lang)                       | 语言包                 |                                                         |
| [Laravel Telescope](https://github.com/laravel/telescope)                      | 调试工具                |                                                         |
| [Sort functions](https://github.com/haoyuqi/sort-function)                     | 排序函数                | 练习用开发的 Composer 包                                       |
| [Laravel-admin](https://github.com/z-song/laravel-admin)                       | 管理后台                |                                                         |
| [Log viewer](https://github.com/laravel-admin-extensions/log-viewer)           | 后台插件                | Laravel-admin 插件。 `php artisan admin:import log-viewer` |
| [Laravel Dusk](https://github.com/laravel/dusk)                                | 浏览器测试               | `php artisan dusk:install`                              |
| [Laravel GeoIP](https://github.com/Torann/laravel-geoip)                       | 根据 IP 获取地址          |                                                       |
| [Laravel Backup](https://github.com/spatie/laravel-backup)                     | 备份工具                |                                                       |

## Project supported by JetBrains

Thanks to JetBrains for supporting me.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/haoyuqi)
