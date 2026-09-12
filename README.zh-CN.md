# Laravel Backend Lab

<p align="center">
  一个用于实践现代后端架构、后台管理、队列、可观测性与自动化运维的<br>
  Laravel 应用项目。
</p>

<p align="center">
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml"><img alt="测试状态" src="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://www.php.net/"><img alt="PHP 8.3 或更高版本" src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com/"><img alt="Filament 3" src="https://img.shields.io/badge/Filament-3.x-FDAE4B"></a>
  <a href="LICENSE"><img alt="MIT 许可证" src="https://img.shields.io/github/license/haoyuqi/laravel-backend-lab"></a>
</p>

<p align="center">
  <a href="README.md">English</a> | 简体中文
</p>

## 项目简介

Laravel Backend Lab 是一个参考应用和学习环境，围绕接近生产实践的
Laravel 后端构建。项目在同一代码库中整合了管理后台、访客分析、队列、
定时维护、备份和可观测性。

本仓库是一个完整应用，不是可复用的 Laravel 扩展包，也不是通用项目模板。

本项目在开发过程中使用 AI 编程工具辅助。参与范围和审核原则参见
[AI 辅助开发](#ai-辅助开发)。

## 主要功能

- 位于 `/admin` 的 Filament 管理后台，支持访客和黑名单管理。
- 展示流量统计、应用信息和服务健康状态的后台仪表盘组件。
- 访客记录、IP 地理位置查询和黑名单拦截。
- 可配置的队列处理，以及可选的 Redis 和 Laravel Horizon 监控。
- 使用 Laravel Telescope 和 Debugbar 进行应用调试与观测。
- 定时执行备份、数据清理、访客统计汇总和 Bing 壁纸下载。
- 使用 Vite 构建 Vue 3 与 Bootstrap 5 前端资源。
- 使用独立测试数据库连接的 PHPUnit 单元与功能测试，以及 Laravel Dusk
  浏览器测试。

应用健康检查地址为 `/up`。

## 技术栈

| 组件 | 版本 | 用途 |
| --- | --- | --- |
| [PHP](https://www.php.net/) | 8.3+ | 应用运行环境 |
| [Laravel](https://laravel.com/) | 13.x | Web 应用框架 |
| [Filament](https://filamentphp.com/) | 3.x | 管理后台 |
| [Laravel Horizon](https://laravel.com/docs/horizon) | 5.x | Redis 队列监控 |
| [Laravel Telescope](https://laravel.com/docs/telescope) | 5.x | 本地应用观测 |
| [Laravel Lang](https://laravel-lang.com/) | 6.x | 应用语言包 |
| [Laravel Backup](https://github.com/spatie/laravel-backup) | 10.x | 数据库与文件备份 |
| [Vite](https://vite.dev/) | 6.x | 前端开发与生产构建 |
| [Vue](https://vuejs.org/) | 3.x | 交互式前端组件 |
| [Bootstrap](https://getbootstrap.com/) | 5.x | 前端样式 |

## 环境要求

应用需要：

- PHP 8.3 或更高版本，以及 `composer.lock` 所要求的 PHP 扩展；
- Composer 2；
- Node.js 20 和 npm；
- SQLite、MySQL 或 PostgreSQL，用于持久化应用数据；
- Redis，用于访客统计、黑名单缓存和相关定时任务；
- 生产环境中需要 Laravel 支持的 Web 服务器。

Laravel 管理的缓存、会话、队列和广播不一定要使用 Redis。但是当前应用的访客计数、
黑名单检查、仪表盘统计和维护命令会直接访问 Redis，因此 Redis 服务本身仍然是必需的。
如果要让 Redis 完全可选，需要修改应用代码，仅修改 `.env` 并不足够。

## 服务与配置

| 服务 | 是否必需 | 配置与替代方案 |
| --- | --- | --- |
| 数据库 | 是 | 设置 `DB_CONNECTION` 和对应的 `DB_*`。支持 SQLite、MySQL 和 PostgreSQL。 |
| Redis | 是 | 访客统计、黑名单检查、仪表盘组件和定时清理会直接使用。可以通过 `REDIS_*` 修改主机、端口、凭据、客户端和数据库编号。 |
| 队列 Worker | 否 | 仅异步队列连接需要。`sync` 驱动会在当前请求中直接处理任务。 |
| Horizon | 否 | 必须同时使用 Redis 和 Redis 队列；使用 `sync` 或非 Redis 队列时不需要。 |
| 调度器 | 按功能需要 | 每分钟运行 `php artisan schedule:run`，可启用定时备份、清理、统计和维护任务。 |
| 邮件服务器 | 否 | 开发环境可使用 `MAIL_MAILER=log`；需要发送邮件时再配置 SMTP。 |
| 对象存储 | 否 | 默认本地文件系统不需要 S3；需要外部存储时再配置 `FILESYSTEM_DRIVER`、`FILESYSTEM_CLOUD` 和 `AWS_*`。 |
| 实时事件服务器 | 否 | 不使用实时事件时保持 `BROADCAST_CONNECTION=log`；内置 Echo 集成需要 Redis 广播、兼容的 Socket.IO 服务器和匹配的 `VITE_REDIS_PREFIX`。 |
| Telescope 与 Debugbar | 否 | 不希望这些调试工具收集数据或暴露界面时，设置 `TELESCOPE_ENABLED=false` 和 `DEBUGBAR_ENABLED=false`。 |

常用环境变量包括：

- `APP_URL`：应用公开地址；
- `ADMIN_EMAILS`：生产环境管理员邮箱白名单，多个值使用英文逗号分隔；
- `DB_*`：数据库连接；
- `REDIS_CLIENT`、`REDIS_HOST`、`REDIS_PASSWORD` 和 `REDIS_PORT`：Redis 连接；
- `CACHE_STORE`、`SESSION_DRIVER` 和 `QUEUE_CONNECTION`：状态与任务存储；
- `BROADCAST_CONNECTION` 和 `VITE_REDIS_PREFIX`：实时事件；
- `MAIL_MAILER` 和 `MAIL_*`：邮件发送；
- `FILESYSTEM_DRIVER`、`FILESYSTEM_CLOUD` 和 `AWS_*`：文件存储；
- `TELESCOPE_ENABLED` 和 `DEBUGBAR_ENABLED`：可选的应用调试工具；
- `TEST_DB_*`：独立的非默认测试数据库。

## 安装

克隆项目并安装锁定的依赖：

```bash
git clone git@github.com:haoyuqi/laravel-backend-lab.git
cd laravel-backend-lab
cp .env.example .env
composer install --prefer-dist --no-interaction
npm ci
npm run build
```

运行迁移前选择数据库并修改 `.env`。例如 MySQL 可以配置为：

```dotenv
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

Laravel 的缓存、会话、队列和广播可以不存入 Redis。如果只让当前必须依赖 Redis 的
应用功能使用它，可以设置：

```dotenv
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
```

这些配置可以减少 Redis 的用途，但无法消除访客统计和黑名单功能对 Redis 的直接调用。

完成应用初始化：

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate
php artisan make:filament-user
```

将 Web 服务器的文档根目录指向项目的 `public` 目录。本地体验时可以使用
`php artisan serve` 启动 Laravel 开发服务器，然后使用上一步创建的 Filament
用户登录 `/admin`。

生产环境中，`ADMIN_EMAILS` 必须设置为允许访问后台的管理员邮箱列表，多个邮箱用
英文逗号分隔。生产环境中留空会拒绝所有用户访问后台。

## 本地开发

启动 Vite 开发服务器：

```bash
npm run dev
```

使用 `npm run build` 生成优化后的前端资源。请勿继续使用早期版本中已经移除的
Laravel Mix 命令。

升级现有安装时，请在部署新次版本前阅读 [UPGRADE.md](UPGRADE.md)。

## 测试

默认测试套件使用独立的 `testing` 连接和内存 SQLite 数据库，不会使用主要开发数据库。

```bash
php artisan test
./vendor/bin/pint --test
```

如需使用 MySQL 或 PostgreSQL 运行测试，请提供专用的 `TEST_DB_*` 变量。如果指定的
测试数据库尚不存在，相应账号必须拥有创建该数据库的权限：

```bash
TEST_DB_CONNECTION=mysql \
TEST_DB_HOST=127.0.0.1 \
TEST_DB_PORT=3306 \
TEST_DB_DATABASE=laravel_test \
TEST_DB_USERNAME=root \
TEST_DB_PASSWORD='' \
php artisan test
```

## 运维

生产环境启用定时功能时，需要每分钟运行 Laravel 调度器。使用异步队列连接时，
需要通过进程管理器持续运行队列 Worker；Horizon 仅适用于 Redis 队列。调度器负责
备份、清理、访客统计汇总、Telescope 数据裁剪、GeoIP 维护和 Bing 壁纸下载。

## 参与贡献

提交 Issue 或 Pull Request 前，请阅读 [CONTRIBUTING.md](CONTRIBUTING.md)。其中定义了
本仓库使用的分支命名、Commit Message、测试和目标分支规范。

## AI 辅助开发

本项目在开发过程中使用 AI 编程工具辅助，包括 OpenAI Codex。AI 可能参与资料研究、
方案规划、代码实现、测试编写、文档整理、问题调试和代码审查。

项目维护者会审核和验证最终接受的改动，并对其正确性、安全性和后续维护负责。
AI 仅参与开发流程：运行本应用不依赖 AI 服务，应用默认也不会把用户数据或应用数据
发送给 AI 服务提供商。

## 许可证

Laravel Backend Lab 使用 [MIT 许可证](LICENSE) 开源。

## 致谢

感谢 [JetBrains](https://www.jetbrains.com/) 对本项目的支持。
