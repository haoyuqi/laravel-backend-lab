# Laravel Backend Lab

<p align="center">
  一个用于实践现代后端架构、后台管理、队列、可观测性与自动化运维的<br>
  Laravel 应用项目。
</p>

<p align="center">
  <a href="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml"><img alt="测试状态" src="https://github.com/haoyuqi/laravel-backend-lab/actions/workflows/tests.yml/badge.svg?branch=master"></a>
  <a href="https://www.php.net/"><img alt="PHP 8.4 或更高版本" src="https://img.shields.io/badge/PHP-%5E8.4-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com/"><img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com/"><img alt="Filament 5" src="https://img.shields.io/badge/Filament-5.x-FDAE4B"></a>
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

> 本项目在 AI 辅助下开发，并由维护者进行审核。

## 主要功能

- 位于 `/admin` 的 Filament 管理后台，支持访客和黑名单管理。
- 展示流量统计、应用信息和服务健康状态的后台仪表盘组件。
- 访客记录、IP 地理位置查询和黑名单拦截。
- 可配置的队列处理，以及可选的 Redis 和 Laravel Horizon 监控。
- 使用 Laravel Telescope 和 Debugbar 进行应用调试与观测。
- 定时执行备份、数据清理、访客统计汇总和 Bing 壁纸下载。
- 使用 Vite 构建 Vue 3 与 Bootstrap 5 前端资源。
- 使用独立测试数据库连接的 PHPUnit 单元与功能测试，以及 Playwright
  端到端浏览器测试。

应用健康检查地址为 `/up`。

### 后台操作日志

后台的 **系统 → 操作日志** 记录后台数据变更和成功登录、主动退出。
当前所有写入入口均已接入：黑名单新增、修改、删除（包括批量删除），以及从访客
列表批量加入和恢复黑名单。通用记录器支持不同资源类型及字符串/UUID 记录 ID，
保存操作人 ID 和姓名快照、资源/记录标识、操作类型、入口、操作关联 ID、安全的
变更前后快照和时间。查看、搜索、导出、失败或被拒绝的登录、无实际变化的操作，
以及任务或 CLI 直接写入模型不会记录；不采集请求正文、Cookie、凭据或用户邮箱。

新增资源页面需继承 `App\Filament\Pages` 的公共新增/编辑/列表页面；安全字段及
固定资源别名在 `config/admin-operation-logs.php` 中配置，审计操作内未经配置的
业务模型写入会在持久化前拒绝。快照有大小限制，并递归排除敏感字段。基础设施
模型默认排除；将其开放为后台可写资源时，必须移除排除项并配置安全字段。
自定义写入方法和批量回调必须使用业务连接上的 `AdminOperationRecorder::run()`。
批量查询、pivot 和原始 SQL 绕过模型事件，必须通过记录器显式的加锁/变更/快照接口
接入；Eloquent 无法自动审计任意 SQL。测试资源证明可复用，没有新增生产用户管理权限。

关系写入使用 `explicit($subject, 'update', $mutation, $snapshot)`：在锁定父记录后，
快照回调读取排序稳定的已持久化关系状态，其顶层字段需配置到模型策略的
`relationship_fields` 中。新增/删除关系和 pivot 更新均比较此状态，无变化不记日志；
敏感字段递归过滤，超大快照拒绝。所有写入方须使用同一连接并锁定父记录。
Eloquent 写入应使用回调传入的模型，避免重复日志。

每条数据变更与日志使用同一数据库事务，日志写入失败时该条变更回滚。
批量操作仍然逐条提交，后续记录失败不会撤销此前成功的记录。日志页面只读，
沿用已有后台访问限制；普通模型实例的修改和删除会被拒绝，但绕过模型事件的写入
（包括批量查询和静默写入）、原始 SQL 和数据库管理员仍能
修改历史，因此这不是防篡改审计系统。提交后会再次清除黑名单缓存；数据库与 Redis
之间没有分布式事务保证。

后台登录方法成功返回后才记登录日志，写入失败会撤销本次新认证并返回错误。
退出始终使本地会话失效；审计存储故障时仍允许退出并提示，此异常情况下退出日志
可能缺失。会话、Cookie 和数据库之间没有原子事务保证。

日志包含操作人姓名和 IP 等个人数据，默认保留直到人工清理。请根据部署需要制定
保留策略，并将日志表纳入备份。先预览待清理数量：

```bash
php artisan admin-operation-logs:prune --days=365
```

确认数量并备份历史后，添加 `--force` 才会删除过期日志，每批最多 1,000 条。
最短保留 30 天，不设置自动清理任务。部署前请查看 [UPGRADE.md](UPGRADE.md)。

## 技术栈

| 组件 | 版本 | 用途 |
| --- | --- | --- |
| [PHP](https://www.php.net/) | 8.4+ | 应用运行环境 |
| [Laravel](https://laravel.com/) | 13.x | Web 应用框架 |
| [Filament](https://filamentphp.com/) | 5.x | 管理后台 |
| [Laravel Horizon](https://laravel.com/docs/horizon) | 5.x | Redis 队列监控 |
| [Laravel Telescope](https://laravel.com/docs/telescope) | 5.x | 本地应用观测 |
| [Laravel Lang](https://laravel-lang.com/) | 6.x | 应用语言包 |
| [Laravel Backup](https://github.com/spatie/laravel-backup) | 10.x | 数据库与文件备份 |
| [Vite](https://vite.dev/) | 6.x | 前端开发与生产构建 |
| [Vue](https://vuejs.org/) | 3.x | 交互式前端组件 |
| [Bootstrap](https://getbootstrap.com/) | 5.x | 前端样式 |

## 环境要求

应用需要：

- PHP 8.4 或更高版本，以及 `composer.lock` 所要求的 PHP 扩展；
- Composer 2；
- Node.js 24 LTS 和 npm；
- SQLite、MySQL 或 PostgreSQL，用于持久化应用数据；
- Redis，用于访客统计和相关定时任务；
- 生产环境中需要 Laravel 支持的 Web 服务器。

Laravel 管理的缓存、会话、队列和广播不一定要使用 Redis。但是当前应用的访客计数、
仪表盘统计和维护命令会直接访问 Redis，因此 Redis 服务本身仍然是必需的。
如果要让 Redis 完全可选，需要修改应用代码，仅修改 `.env` 并不足够。

## 服务与配置

| 服务 | 是否必需 | 配置与替代方案 |
| --- | --- | --- |
| 数据库 | 是 | 设置 `DB_CONNECTION` 和对应的 `DB_*`。支持 SQLite、MySQL 和 PostgreSQL。 |
| Redis | 是 | 访客统计、仪表盘组件和定时清理会直接使用。黑名单拦截直接读取数据库，避免旧缓存改变判断。可以通过 `REDIS_*` 修改主机、端口、凭据、客户端和数据库编号。 |
| 队列 Worker | 否 | 仅异步队列连接需要。`sync` 驱动会在当前请求中直接处理任务。 |
| Horizon | 否 | 必须同时使用 Redis 和 Redis 队列；使用 `sync` 或非 Redis 队列时不需要。 |
| 调度器 | 按功能需要 | 每分钟运行 `php artisan schedule:run`，可启用定时备份、清理、统计和维护任务。 |
| 邮件服务器 | 否 | 开发环境可使用 `MAIL_MAILER=log`；需要发送邮件时再配置 SMTP。 |
| 对象存储 | 否 | 默认本地文件系统不需要 S3；需要外部存储时再配置 `FILESYSTEM_DRIVER`、`FILESYSTEM_CLOUD` 和 `AWS_*`。 |
| 实时事件服务器 | 否 | 不使用实时事件时保持 `BROADCAST_CONNECTION=log` 并留空 `REVERB_APP_KEY`。启用 Laravel Reverb 后可实时更新时间，无需 Redis 广播服务器。 |
| Telescope 与 Debugbar | 否 | 不希望这些调试工具收集数据或暴露界面时，设置 `TELESCOPE_ENABLED=false` 和 `DEBUGBAR_ENABLED=false`。 |

常用环境变量包括：

- `APP_URL`：应用公开地址；
- `ADMIN_EMAILS`：生产环境管理员邮箱白名单，多个值使用英文逗号分隔；
- `DB_*`：数据库连接；
- `REDIS_CLIENT`、`REDIS_HOST`、`REDIS_PASSWORD` 和 `REDIS_PORT`：Redis 连接；
- `CACHE_STORE`、`SESSION_DRIVER` 和 `QUEUE_CONNECTION`：状态与任务存储；
- `BROADCAST_CONNECTION`、`REVERB_*` 和 `VITE_REVERB_*`：实时事件；
- `MAIL_MAILER` 和 `MAIL_*`：邮件发送；
- `FILESYSTEM_DRIVER`、`FILESYSTEM_CLOUD` 和 `AWS_*`：文件存储；
- `TELESCOPE_ENABLED` 和 `DEBUGBAR_ENABLED`：可选的应用调试工具；
- `TEST_DB_*`：独立的非默认测试数据库。

队列和排序演示接口默认关闭，确需公开时设置 `DEMO_ROUTES_ENABLED=true`。
接口按 IP 共用每分钟 10 次的限制，单次最多处理 1,000 项。创建演示任务改用
`POST /queue/create`，并提交正常的 Web CSRF token。

部署在反向代理后时，将 `TRUSTED_PROXIES` 设置为代理地址或 CIDR 范围，多个值用
英文逗号分隔。只信任自己控制的代理，其他地址发送的转发客户端头不会被采信。

备份使用 `DB_CONNECTION` 指定的连接。配置单个有效的 `BACKUP_NOTIFICATION_EMAIL`
和邮件发送服务后，才会启用备份失败通知。默认备份仍保存在本地；应定期将备份恢复到
隔离数据库，验证恢复能力。任务锁防止同一命令重复运行；多服务器部署需使用共享锁
存储，并指定一台服务器运行调度器。

新的访客与拦截日志仅保存请求路径，移除查询参数和片段，最长 2,048 字节。
历史日志不会自动重写。Telescope 在本地环境也会隐藏 Livewire 组件请求和响应内容，
避免登录信息随组件快照保存。

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

这些配置可以减少 Redis 的用途，但无法消除访客统计功能对 Redis 的直接调用。

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

升级现有安装时，请先阅读 [UPGRADE.md](UPGRADE.md) 中的 v3.0.2 升级流程。
此补丁移除未使用的 Laravel Admin 资源，没有新增数据库迁移。使用文件覆盖
部署时，还须删除旧的 `public/vendor/laravel-admin/` 目录并清除其缓存副本。
升级后将现有环境中的 `APP_VERSION` 更新为 `3.0.2`。更早版本的安装还须完成
适用的 v3.0.0、v3.0.1 升级流程及其必要的数据库迁移。

### 本地实时更新时间

`/time` 使用 [Laravel Reverb](https://laravel.com/docs/13.x/reverb) 和 Laravel
Echo。不开启广播也可以正常显示初始时间。启用实时更新时，先生成本地凭据：

```bash
php artisan reverb:install
```

设置 `BROADCAST_CONNECTION=reverb`、`REVERB_HOST=localhost`、
`REVERB_PORT=8080`、`REVERB_SCHEME=http`，单进程保持
`REVERB_SCALING_ENABLED=false`。`.env.example` 中的 `VITE_REVERB_*` 引用相应
配置；不要把 `REVERB_APP_SECRET` 放进任何 `VITE_*` 变量。修改前端连接配置后
执行 `npm run build`。

在不同终端运行：

```bash
php artisan reverb:start
php artisan schedule:work
# 仅使用异步队列时需要：
php artisan queue:work --queue=push-time
```

通过 HTTP 打开 `/time`，等到下一分钟，应无需刷新就新增一行时间。
`REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` 控制监听地址，
`REVERB_HOST` / `REVERB_PORT` 控制 Laravel 发送事件的目标。
`VITE_REVERB_HOST` 必须能被浏览器访问，容器环境中它可能与服务端地址不同。

`npm run test:reverb` 在端口 8000 和 8080 启动独立应用及 Reverb 进程，构建
测试资源，在 Chromium 中验证真实 WebSocket 推送和刷新后重新订阅。运行前
应停止占用这些端口的其他进程。测试使用内存数据库和临时 Reverb 凭据。
测试后运行 `npm run build` 恢复正常前端资源。

## 测试

默认测试套件使用独立的 `testing` 连接和内存 SQLite 数据库，不会使用主要开发数据库。

`app/` 的行覆盖率为 **91.55%**，由 SQLite PHPUnit 任务在
[2026-10-22 的 CI 运行](https://github.com/haoyuqi/laravel-backend-lab/actions/runs/39708654277)
中测得（最低要求：85%）。

GitHub Actions 会在拉取请求和推送到 `master` 时运行测试。每日任务安排在
北京时间 03:46，只在默认分支 `master` 上运行。每次运行的摘要展示实测的
测试与覆盖率结果、安装的版本，以及实际测试的数据库。

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

在独立测试数据库中运行覆盖桌面与移动端浏览器的 Playwright 端到端测试：

```bash
php artisan migrate --database=testing
php artisan db:seed --class=E2EDataSeeder --database=testing
npm run test:e2e
```

## 运维

生产环境启用定时功能时，需要每分钟运行 Laravel 调度器。使用异步队列连接时，
需要通过进程管理器持续运行队列 Worker；Horizon 仅适用于 Redis 队列。调度器负责
备份、清理、访客统计汇总、Telescope 数据裁剪、GeoIP 维护和 Bing 壁纸下载。

## 参与贡献

提交 Issue 或 Pull Request 前，请阅读 [CONTRIBUTING.md](CONTRIBUTING.md)。其中定义了
本仓库使用的分支命名、Commit Message、测试和目标分支规范。

## 许可证

Laravel Backend Lab 使用 [MIT 许可证](LICENSE) 开源。

## 致谢

感谢 [JetBrains](https://www.jetbrains.com/) 对本项目的支持。
