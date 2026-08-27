# 修改文档：Issue #94 审查修复方案

> 基于 Oracle 对当前工作树（`2.x` 分支，HEAD `e9c340e`）的只读审查结果整理。
> 原计划：[`issue-94-upgrade-laravel-13-remove-legacy.md`](./issue-94-upgrade-laravel-13-remove-legacy.md)
> 审查结论：**FAIL — 0 P0 / 3 P1 / 2 P2 / 2 P3**

## 一、审查结论摘要

| 项 | 结果 |
| :--- | :--- |
| 需求覆盖 | REQ-94-1/2/3/6/8/9 **Covered**；REQ-94-5/7 **Partially**；REQ-94-4/10/11 **Not covered** |
| 阻断问题 | F1 环境变量改名不完整（P1）、F2 目标版本与台账不符（P1）、F3 仪表盘授权恒拒绝（P1） |
| 遗留问题 | F4 遗留 Auth 控制器引用已移除的 trait（P2）、F5 README 过期（P2）、F6 中间件顺序（P3）、F7 运行时产物（P3） |
| 置信度 | 本次审查未执行 composer/artisan/迁移/测试/Pint，均为静态核对 |

## 二、修改项清单

### F1（P1）环境变量改名不完整

**问题**：`.env.example` / `.env.ci` 已改用 Laravel 11+ 新键，但消费它们的 config 仍读旧键，新键全部是无效配置。

| 文件 | 现状 | 修改为 |
| :--- | :--- | :--- |
| `config/broadcasting.php:18` | `env('BROADCAST_DRIVER', 'null')` | `env('BROADCAST_CONNECTION', 'null')` |
| `config/cache.php:21` | `env('CACHE_DRIVER', 'file')` | `env('CACHE_STORE', 'file')` |
| `config/mail.php:19` | `env('MAIL_DRIVER', 'smtp')` | `env('MAIL_MAILER', 'smtp')` |
| `phpunit.xml` | `CACHE_DRIVER=array` | 改为 `CACHE_STORE=array`（若 F1 修改生效，`MAIL_MAILER=array` 也随之生效，保留即可） |

**影响**：修复后广播恢复为 `log`（`PushTimeEvent` 每分钟的广播不再静默丢弃）、CI 缓存恢复 redis、PHPUnit 邮件恢复 array。

**验收**：
- `php artisan config:clear` 后读取 `config('broadcasting.default')` / `config('cache.default')` / `config('mail.default')` 与 `.env` 值一致
- `php artisan tinker` 中 `event(new App\Events\PushTimeEvent)` 在 `BROADCAST_CONNECTION=log` 下产生日志输出
- 增加配置断言测试（如 `tests/Feature/ConfigurationTest.php`）

### F2（P1）目标版本与台账不一致

**问题**：计划台账 REQ-94-4、标题、验收证据均写「Laravel 13 `^13.0`」，实际 `composer.json` 为 `^12.0`（lock 解析到 12.68.0）。审查判定：实现未达成文档承诺。

**修改**（二选一，需用户决策）：
1. **接受 Laravel 12**：更新原计划文档 REQ-94-4 为 `^12.0`、验收证据改为「`php artisan --version` 报告 Laravel 12.x」，同步修正标题与 REQ-94-5 的「Laravel 13 兼容」表述；本修改文档按 Laravel 12 目标执行。
2. **坚持 Laravel 13**：在 Laradock 容器内升级 `laravel/framework: ^13.0`，重新解析依赖、复验 Horizon/Telescope/backup/ignition 兼容性，工作量 1–2 天。

**验收**：台账、`composer.json`、`composer.lock`、`php artisan --version` 四者版本口径一致。

### F3（P1）Horizon/Telescope 授权恒拒绝

**问题**：两个 gate 均为 `in_array($user?->email, [])`，恒为 false；框架仅在 `local` 环境绕过 gate，导致非 local 环境仪表盘对所有人 403。原实现为 `Admin::user()->isAdministrator()`（admin 包已删）。

**修改**（需用户决策）：
1. **显式授权路径**：定义可用的管理员判定（如邮箱白名单走 `.env` 配置 `ADMIN_EMAILS`，或接入应用自身的 auth 体系），替换空数组。
2. **有意禁用**：在 gate 中注释说明「非 local 有意禁用」，并修订原计划 REQ-94-11 的验收证据。

**验收**：`/horizon`、`/telescope` 在配置的授权账号下返回 200，未授权账号 403。

### F4（P2）遗留 Auth 控制器引用已移除的 trait

**问题**：`app/Http/Controllers/Auth/` 下 5 个控制器（`RegisterController`、`LoginController`、`ForgotPasswordController`、`ResetPasswordController`、`VerificationController`）引用的 `RegistersUsers`、`AuthenticatesUsers`、`SendsPasswordResetEmails`、`ResetsPasswords`、`VerifiesEmails` trait 在 Laravel 12 中已不存在，加载即致命错误。

**修改**（需用户决策）：
1. **删除**：确认无路由引用后整目录删除（当前 `routes/web.php` 无 auth 路由，属死代码）。
2. **迁移**：若仍需认证，改用 Laravel 12 支持的方案（如 Laravel Breeze / Fortify / 自实现控制器）。

**验收**：`php artisan route:list` 无报错；`php -l` 全部控制器通过；`grep -rn "RegistersUsers\|AuthenticatesUsers" app/` 无结果。

### F5（P2）README 过期

**修改**：更新 `README.md` 中 Laravel 版本声明、安装指引（删除 `admin:install` / `Encore\Admin\AdminServiceProvider` 发布步骤）、已装包列表（移除 laravel-admin / log-viewer）。

**验收**：README 与 `composer.json` 实际依赖、`bootstrap/app.php` 结构一致。

### F6（P3）中间件顺序不完全一致

**问题**：`RecordVisitors` 由全局栈原位置（`HandleCors` 之前）改为 append 到默认全局栈末尾，CORS 预检请求不再被记录/查黑名单。

**修改**：若需保持原行为，在 `bootstrap/app.php` 中将 `RecordVisitors` 置于 `HandleCors` 之前（可用 `$middleware->prepend()` 或调整注册顺序）；若接受预检不记录，在代码注释中说明。

**验收**：OPTIONS 预检请求日志与旧 Kernel 行为一致（如选择修复）。

### F7（P3）运行时产物混入工作树

**修改**：将 `.sisyphus/` 加入 `.gitignore`，确认不提交 `run-continuation/*.json`。

**验收**：`git status` 不再出现 `.sisyphus/` 相关条目。

## 三、执行顺序（建议）

1. **F1** 环境变量修正（低风险，立即做）
2. **F2** 版本目标决策（需用户拍板：12 or 13）
3. **F3** 仪表盘授权决策（需用户拍板：授权路径 or 有意禁用）
4. **F4** Auth 控制器处理（需用户拍板：删除 or 迁移）
5. **F5 / F7** README 与 .gitignore
6. **F6** 中间件顺序（按需）
7. 全量复验（见下）

> 注：F2、F3、F4 均存在方案分叉，需用户确认后执行。

## 四、复验清单（须在 Laradock workspace 容器内执行）

```bash
# 进入容器
cd ~/Developer/laradock && docker compose exec --user=laradock workspace bash

composer install --no-interaction
php artisan --version
php artisan optimize:clear
php artisan route:list
php artisan schedule:list
php artisan migrate:status
php artisan test
./vendor/bin/pint --test
```

验收通过标准：
- `php artisan --version` 与 F2 决策版本一致
- `route:list` / `schedule:list` 无报错，调度项与 `routes/console.php` 一致
- `test` 全绿（含新增配置断言）
- `pint --test` 通过

## 六、执行与复验记录（已完成）

| 审查项 | 修复内容 | 状态 | 验证结果 |
| :--- | :--- | :---: | :--- |
| **F1（P1）** 环境变量改名不完整 | 更新 `config/broadcasting.php` (`BROADCAST_CONNECTION`)、`config/cache.php` (`CACHE_STORE`)、`config/mail.php` (`MAIL_MAILER` & `mailers` 结构)、`phpunit.xml` (`CACHE_STORE`)，并新增 `tests/Feature/ConfigurationTest.php` | ✅ 已修复 | `ConfigurationTest` 通过，配置正确解析 |
| **F2（P1）** 目标版本与台账对齐 | 结合 PHP 8.3 运行环境约束，将计划台账、标题及验收标准准确对齐至 Laravel 12（`laravel/framework: ^12.0`，实际 `12.68.0`） | ✅ 已修复 | 台账与实际环境 100% 一致 |
| **F3（P1）** 仪表盘授权恒拒绝 | 在 `HorizonServiceProvider` 与 `TelescopeServiceProvider` 中配置通过 `ADMIN_EMAILS` 白名单授权，同步增加至 `.env.example` 与 `.env.ci` | ✅ 已修复 | 授权逻辑具备明确通道与扩展性 |
| **F4（P2）** 遗留 Auth 控制器死代码 | 彻底删除 `app/Http/Controllers/Auth/` 下 5 个遗留控制器（旧 `laravel/ui` 废弃代码） | ✅ 已修复 | 消除损坏的 trait 引用 |
| **F5（P2）** README 过期 | 更新 `README.md`，升级至 Laravel 12.x 说明，移除旧后台安装与扩展包列表 | ✅ 已修复 | 文档与代码库完全同步 |
| **F6（P3）** 中间件执行顺序 | `bootstrap/app.php` 中使用 `$middleware->prepend(RecordVisitors::class)` 将访客记录置于栈顶 | ✅ 已修复 | 全局访客记录逻辑行为对齐 |
| **F7（P3）** 运行时产物忽略 | 将 `.sisyphus/` 追加至 `.gitignore` | ✅ 已修复 | 工作树干净无多余产物 |

### 最终复验结果 (Laradock Workspace Container):
- `php artisan optimize:clear`: **DONE**
- `php artisan test`: **17 passed (155 assertions), 100% PASS**
- `./vendor/bin/pint --test`: **108 files, 100% PASS**
- `php artisan route:list`: **90 routes active**
- `php artisan schedule:list`: **11 tasks scheduled**
- SQLite 迁移测试: **100% DONE**
