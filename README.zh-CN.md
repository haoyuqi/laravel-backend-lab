# Laravel Backend Lab

<p align="center">
  一个用于探索现代后端架构、后台工作流、队列、可观测性和自动化运维的<br>
  Laravel 实战应用。
</p>

<p align="center">
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml"><img alt="Tests" src="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml?query=branch%3Amaster"><img alt="Coverage" src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com/haoyuqi%2Flaravel-backend-lab%2Fcoverage-badge%2Fcoverage.json"></a>
  <a href="https://www.php.net/"><img alt="PHP 8.4.27+ or 8.5.12+" src="https://img.shields.io/badge/PHP-8.4.27%2B%20%7C%208.5.12%2B-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com/"><img alt="Filament 5" src="https://img.shields.io/badge/Filament-5.x-FDAE4B"></a>
  <a href="LICENSE"><img alt="MIT License" src="https://img.shields.io/github/license/haoyuqi/laravel-backend-lab"></a>
</p>

<p align="center">
  <a href="README.md">English</a> | 简体中文
</p>

## 项目简介

Laravel Backend Lab 是一个以生产环境后端为目标的参考应用，把后台管理、访客统计、
队列、定时维护、备份和可观测性放在同一个代码库里。它是应用，不是可复用包，也不是
通用项目脚手架。

## 环境要求

- PHP 8.4.27 或更高版本，或 PHP 8.5.12 或更高版本，并安装 `composer.lock`
  要求的扩展；
- Composer 2；
- SQLite、MySQL 或 PostgreSQL；
- Redis，访客统计、仪表盘和定时维护会直接访问；
- 仅在从源码构建前端资源时需要 Node.js 24 和 npm；
- 生产环境需要 Laravel 支持的 Web 服务器。

## 安装

### 从源码安装

```bash
git clone git@github.com:haoyuqi/laravel-backend-lab.git
cd laravel-backend-lab
cp .env.example .env
composer install --prefer-dist --no-interaction
npm ci
npm run build
```

运行迁移前先在 `.env` 中选择数据库，例如：

```dotenv
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

Laravel 的缓存、会话、队列和广播可以不使用 Redis，但访客统计等功能仍会直接
访问 Redis。可用的存储和服务见[配置说明](docs/configuration.md)。

完成初始化：

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate
php artisan make:filament-user
```

将 Web 服务器的文档根目录指向项目的 `public` 目录，本地体验也可以使用
`php artisan serve`，然后登录 `/admin`。生产环境中 `ADMIN_EMAILS` 必须填写
管理员邮箱列表；留空会拒绝所有后台访问。

### 使用 Docker

`linux/amd64` 和 `linux/arm64` 的单镜像发布在
`ghcr.io/haoyuqi/laravel-backend-lab`。镜像默认提供 HTTP 访问，也可以通过覆盖
命令运行队列、调度器和 Reverb。启动时不会生成 `APP_KEY`、执行迁移或创建
管理员。

快速启动、必填环境变量和持久化卷见[容器安装](docs/container.md)。

## 文档

| 文档 | 内容 |
| --- | --- |
| [配置](docs/configuration.md) | 环境变量、服务和运行时选项 |
| [运维](docs/operations.md) | 队列、调度器、日志和备份 |
| [实时更新](docs/realtime.md) | Reverb 和 Echo |
| [测试](docs/testing.md) | PHPUnit、覆盖率和浏览器测试 |
| [架构](docs/architecture.md) | 功能映射和运行时契约 |
| [代码风格](docs/code-style.md) | PHP、Filament 和前端约定 |
| [升级](UPGRADE.md) | 版本之间需要人工操作的变更 |
| [贡献](CONTRIBUTING.md) | 本地构建、验证和 Pull Request |

## 许可证

Laravel Backend Lab 基于 [MIT license](LICENSE) 开源。

## 致谢

感谢 [JetBrains](https://www.jetbrains.com/) 对项目的支持。
