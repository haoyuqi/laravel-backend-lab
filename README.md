# Laravel Backend Lab
基于 Laravel，集成常用功能与现代化后端架构实践。

<p align="center">
    <a href="https://github.com/haoyuqi/laravel-backend-lab/actions"><img alt="Build Status" src="https://github.com/haoyuqi/laravel-backend-lab/workflows/CI/badge.svg"></a>
    <a href="https://laravel.com/"><img alt="Laravel" src="https://img.shields.io/badge/Laravel-v13.x-%23fc2d1f"></a>
    <a href="https://github.com/haoyuqi/laravel-backend-lab/blob/master/LICENSE"><img alt="GitHub license" src="https://img.shields.io/github/license/haoyuqi/laravel-backend-lab"></a>
</p>

## 安装
1. 搭建 [Laradock](https://github.com/laradock/laradock) 环境
2. 根据下方所示开启相关容器 `docker-compose up -d nginx mysql ...`
3. 在 `workspace` 中依次执行
    1. `git clone git@github.com:haoyuqi/laravel-backend-lab.git`
    2. `cp .env.example .env`
    3. `composer install --prefer-dist`
    4. `npm install && npm run dev`
    5. `php artisan key:generate`
    6. `php artisan storage:link`
4. 修改 `.env` 中 `redis`、`mysql` 等相关配置后执行 `php artisan migrate`
5. 服务器、域名配置
6. `php-worker`, `laravel-horizon` 配置 `supervisord`, 维护队列

## Laradock Container
环境使用 [Laradock](https://github.com/laradock/laradock) 搭建，已使用如下容器：
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
| 名称 | 简介 | 备注 |
|---|---|---|
| [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) | 调试工具 | dev |
| [Laravel IDE Helper Generator](https://github.com/barryvdh/laravel-ide-helper) | IDE 开发工具 | dev |
| [Laravel Horizon](https://github.com/laravel/horizon) | 队列系统 | |
| [Laravel-Lang](https://github.com/Laravel-Lang/common) | 语言包 | |
| [Laravel Telescope](https://github.com/laravel/telescope) | 调试工具 | |
| [Sort functions](https://github.com/haoyuqi/sort-function) | 排序函数 | 练习用开发的 Composer 包 |
| [Laravel Dusk](https://github.com/laravel/dusk) | 浏览器测试 | `php artisan dusk:install` |
| [Laravel GeoIP](https://github.com/Torann/laravel-geoip) | 根据 IP 获取地址 | |
| [Laravel Backup](https://github.com/spatie/laravel-backup) | 备份工具 | |

## Running Tests

The test suite runs against a dedicated `testing` database connection so it
**never touches your primary development database**.

```bash
php artisan test
```

Out of the box this uses an in-memory SQLite database — a fresh checkout runs
with zero database provisioning and no database server required.

To run the suite against a real database (same driver/version as your local
development), override the dedicated connection:

```bash
# PostgreSQL (e.g. Laradock: user must have CREATEDB, or use the postgres superuser)
TEST_DB_CONNECTION=pgsql TEST_DB_HOST=postgres TEST_DB_PORT=5432 \
TEST_DB_DATABASE=web_test TEST_DB_USERNAME=postgres TEST_DB_PASSWORD=postgres \
php artisan test

# MySQL
TEST_DB_CONNECTION=mysql TEST_DB_HOST=mysql TEST_DB_PORT=3306 \
TEST_DB_DATABASE=laravel_test TEST_DB_USERNAME=root TEST_DB_PASSWORD=root \
php artisan test
```

The named test database is auto-created when missing (SQLite needs no setup).
CI runs the same suite against MySQL 8.0 / 8.4 and PostgreSQL via GitHub
Actions service containers.

## Project supported by JetBrains

Thanks to JetBrains for supporting me.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/haoyuqi/laravel-backend-lab)
