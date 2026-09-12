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
定时维护、备份和可观测性，并通过 Laradock 提供一致的开发环境。

本仓库是一个完整应用，不是可复用的 Laravel 扩展包，也不是通用项目模板。

## 主要功能

- 位于 `/admin` 的 Filament 管理后台，支持访客和黑名单管理。
- 展示流量统计、应用信息和服务健康状态的后台仪表盘组件。
- 访客记录、IP 地理位置查询和黑名单拦截。
- 基于 Redis 的队列及 Laravel Horizon 监控。
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

项目支持的开发环境是 [Laradock](https://github.com/laradock/laradock)，
需要具备：

- `workspace`、`php-fpm` 和 `nginx` 容器；
- `workspace` 中的 PHP 8.3 和 Composer 2；
- `workspace` 中的 Node.js 20 和 npm；
- MySQL 与 Redis 服务。

下文假定两个仓库位于：

```text
~/Developer/laradock
~/Developer/www/laravel-backend-lab
```

Laradock 会将 `~/Developer/www` 挂载到 `/var/www`，因此容器中的项目路径为
`/var/www/laravel-backend-lab`。

## 安装

先在宿主机克隆项目：

```bash
cd ~/Developer/www
git clone git@github.com:haoyuqi/laravel-backend-lab.git
cd laravel-backend-lab
cp .env.example .env
```

启动需要的 Laradock 服务：

```bash
cd ~/Developer/laradock
docker compose up -d nginx mysql redis workspace
```

在 `workspace` 容器中安装 PHP 与 JavaScript 依赖：

```bash
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace composer install --prefer-dist --no-interaction
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace npm ci
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace npm run build
```

执行数据库迁移前，按照 Laradock 网络修改 `.env`。至少需要检查以下配置：

```dotenv
APP_URL=http://laravel-backend-lab.test

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=root

REDIS_HOST=redis
```

数据库名称和凭据必须与 Laradock 配置一致。然后在 `workspace` 中完成应用初始化：

```bash
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan key:generate
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan storage:link
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan migrate
docker compose exec -it -u laradock -w /var/www/laravel-backend-lab workspace php artisan make:filament-user
```

在 Laradock 的 nginx 站点配置和宿主机 hosts 文件中加入本地域名，然后访问应用。
使用上一步创建的 Filament 用户登录 `/admin`。

生产环境中，`ADMIN_EMAILS` 必须设置为允许访问后台的管理员邮箱列表，多个邮箱用
英文逗号分隔。生产环境中留空会拒绝所有用户访问后台。

## 本地开发

在 `workspace` 中启动 Vite 开发服务器：

```bash
cd ~/Developer/laradock
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace npm run dev
```

使用 `npm run build` 生成优化后的前端资源。请勿继续使用早期版本中已经移除的
Laravel Mix 命令。

升级现有安装时，请在部署新次版本前阅读 [UPGRADE.md](UPGRADE.md)。

## 测试

默认测试套件使用独立的 `testing` 连接和内存 SQLite 数据库，不会使用主要开发数据库。

```bash
cd ~/Developer/laradock
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace php artisan test
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab workspace ./vendor/bin/pint --test
```

如需使用 MySQL 或 PostgreSQL 运行测试，请提供专用的 `TEST_DB_*` 变量。如果指定的
测试数据库尚不存在，相应账号必须拥有创建该数据库的权限：

```bash
docker compose exec -T -u laradock -w /var/www/laravel-backend-lab \
  -e TEST_DB_CONNECTION=mysql \
  -e TEST_DB_HOST=mysql \
  -e TEST_DB_PORT=3306 \
  -e TEST_DB_DATABASE=laravel_test \
  -e TEST_DB_USERNAME=root \
  -e TEST_DB_PASSWORD=root \
  workspace php artisan test
```

## 运维

生产部署需要每分钟运行 Laravel 调度器，并使用进程管理器持续运行队列 worker 或
Horizon。调度器负责备份、清理、访客统计汇总、Telescope 数据裁剪、GeoIP 维护和
Bing 壁纸下载。启用相关功能时，需要配置 Laradock 的 `php-worker`、
`laravel-horizon` 和 `laravel-echo-server` 服务。

## 参与贡献

提交 Issue 或 Pull Request 前，请阅读 [CONTRIBUTING.md](CONTRIBUTING.md)。其中定义了
本仓库使用的分支命名、Commit Message、测试和目标分支规范。

## 许可证

Laravel Backend Lab 使用 [MIT 许可证](LICENSE) 开源。

## 致谢

感谢 [JetBrains](https://www.jetbrains.com/) 对本项目的支持。
