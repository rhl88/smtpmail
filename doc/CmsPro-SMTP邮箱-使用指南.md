# CmsPro SMTP邮箱 · 使用指南

> 版本：1.0.2 | 更新日期：2026-11-05
> 应用版本：1.0.3 | 应用 ID：`cmspro.smtpmail`

本指南面向后台管理员，说明「SMTP邮箱」应用的安装、发信账号与模板配置、邮件发送、日志查看、队列与定时任务、以及卸载验证的完整使用流程。

---

## 一、应用概述

「SMTP邮箱」是独立 SMTP 邮件服务中台，提供多账号管理、可视化邮件模板、队列/定时/批量发送、发送日志与统计看板，并通过辅助函数、Service 层、钩子、HTTP 接口、Laravel 事件五类方式向其他应用提供发信能力；内置验证码与找回密码两套模板，可在不改动框架代码的前提下接管系统自带邮件。

- 仅提供三终端：后台管理端、外部 HTTP 接口、前台退订页，不含用户端。
- 应用自身发信使用独立 Mailer，不读写框架 `mail_*` 配置；仅「接管系统邮件」开关开启时才会运行时覆盖框架邮件配置。
- 无强/弱应用依赖（`manifest.json` `dependencies` 为空）。

---

## 二、环境要求

| 项目 | 要求 |
|------|------|
| PHP | `>= 8.3` |
| CmsPro | `>= 5.0.0` |
| Laravel | `13` |
| 数据库 | MySQL 或 MariaDB（迁移在非 sqlite 连接下写表注释；测试可用 SQLite） |
| PHP 扩展 | `openssl`（账号密码经 Laravel Crypt 加密）、`json`、`mbstring` |
| 其他 | 可用的 SMTP 服务账号（主机、端口、加密方式、用户名/密码、发件人地址）；开启队列时需运行队列 worker |

> 依赖声明以 `manifest.json` 的 `require` 为准（PHP `>=8.3`、CmsPro `>=5.0.0`）；Laravel 13 与 MySQL/MariaDB 为运行环境的配套要求。

---

## 三、安装与启用

1. 将应用目录 `code/app/Apps/CmsproSmtpmail/` 放入站点应用目录。
2. 在后台「应用管理」中安装应用，安装过程由 `Install::install()` 自动完成：
   - 执行 `Migrations/` 下 5 个迁移，创建 5 张 `app_cmspro_smtpmail_*` 业务表；
   - 以 `manifest.json` 为基准三向同步 3 个配置组、共 12 个配置项；
   - 初始化 2 套系统内置模板 `verify_code`（邮箱验证码）与 `password_reset`（找回密码验证码）；
   - 写入 1 个一级菜单 + 8 个子菜单、6 个权限点，并声明前台退订路由 `/smtpmail/unsubscribe`。
3. 安装后启用应用，到「角色 / 权限」中为相应角色勾选权限：

   `cmspro.smtpmail.send`、`cmspro.smtpmail.template`、`cmspro.smtpmail.account`、`cmspro.smtpmail.record`、`cmspro.smtpmail.batch`、`cmspro.smtpmail.setting`。

4. 启用后后台左侧新增一级菜单「SMTP邮箱」。

> **首次使用顺序建议**：先在「发信账号」添加并启用至少一个账号（首个账号自动设为默认）→ 用「测试」验证连通性 → 再配置「应用设置」与模板 → 最后发送邮件。

---

## 四、后台页面说明

后台入口统一在 `/admin/cmspro/smtpmail` 下，共 8 个功能页面。

| 序号 | 页面 | 路径 | 对应视图 |
|------|------|------|----------|
| 1 | 发送邮件 | `/admin/cmspro/smtpmail/send` | `Views/Admin/send/index.blade.php` |
| 2 | 邮件模板 | `/admin/cmspro/smtpmail/templates` | `Views/Admin/templates/{index,form}.blade.php` |
| 3 | 发信账号 | `/admin/cmspro/smtpmail/accounts` | `Views/Admin/accounts/{index,form}.blade.php` |
| 4 | 发送日志 | `/admin/cmspro/smtpmail/records` | `Views/Admin/records/{index,detail}.blade.php` |
| 5 | 批量群发 | `/admin/cmspro/smtpmail/batches` | `Views/Admin/batches/{index,form}.blade.php` |
| 6 | 黑名单 | `/admin/cmspro/smtpmail/blacklist` | `Views/Admin/blacklist/{index,form}.blade.php` |
| 7 | 统计看板 | `/admin/cmspro/smtpmail/dashboard` | `Views/Admin/dashboard/index.blade.php` |
| 8 | 应用设置 | `/admin/cmspro/smtpmail/settings` | `Views/Admin/settings/index.blade.php` |

### 1. 发送邮件

- 从启用模板下拉选择模板编码，填写模板变量（左侧显示变量中文 label，输入框提示按变量 key 提示），可先「预览」渲染结果再「发送」。
- 抄送、密送、回信地址、优先级、定时发送、队列异步等选填项默认收起在「高级选项」中，点击展开/收起。
- 附件支持两种方式（Tab 切换，二选一，以当前所在 Tab 为准）：「上传附件」直接选择本地文件上传（单个 ≤10M，禁止服务端脚本，存储于 `storage/app/smtpmail/attachments/<日期>/`）；「附件路径」按行填写服务器路径。
- 支持指定收件人、抄送、密送、回复到、附件、自定义邮件头、优先级、发信账号、计划发送时间等可选项。
- 数据接口位于后台 `api/` 子前缀：`GET api/send/options`、`POST api/send/preview`、`POST api/send/upload`（附件上传）、`POST api/send`。

### 2. 邮件模板

- 模板以「编码」为唯一键（仅允许小写字母、数字、下划线且字母开头），供 `smtpmail_send()` 与其他应用引用。
- 支持主题、HTML 正文、纯文本正文三类内容，均可用 `{{变量}}` 占位符；HTML 正文中的 `{{变量}}` 会做 HTML 转义，`{{{变量}}}` 原样输出。
- 提供新建、编辑、复制（自动生成 `_copy` 编码且默认禁用）、启用/停用切换、预览、测试发送。
- 系统内置模板（`is_system=1`）可编辑但**禁止删除、禁止修改编码、禁止降级为自定义或关闭**；可设为禁用。
- 变量定义（`variables`）为 `[{"name":"code","label":"验证码"}]` 结构，页面可自动「解析变量」提取正文中出现的占位符。
- 模板可绑定指定发信账号，未绑定时使用默认账号。
- HTML 正文支持「代码 / 可视化编辑 / 预览」三个标签页实时切换；预览与测试发送均按模板声明渲染变量表单填写，内置变量中文名由系统统一下发。
- 分类管理：模板列表「分类管理」按钮维护分类（新增、编辑、删除，名称唯一），新建/编辑模板时「分类」改为下拉选择；编辑分类会同步更新使用该分类的模板，被模板占用的分类不可删除；模板表中已使用但未登记的历史分类自动合入下拉选项兼容老数据。

### 3. 发信账号

- 字段：账号名称、账号编码、SMTP 主机、端口、加密方式（`none` / `tls` / `ssl`）、用户名、密码、发件人地址、发件人名称、回复到地址、连接超时、账号级每分钟限流、是否默认、状态。
- 密码用 Laravel `Crypt`（绑定 `APP_KEY`）加密存储，页面只显示 `has_password` 标记，不回显密文；**编辑时密码留空表示不修改**。
- 新建账号默认值（见 `AccountService::normalize()`）：端口 `587`、加密 `tls`、超时 `30` 秒、限流 `0`（用全局限流）、状态 `1`。
- 首个账号自动设为默认；支持「设为默认」「启用/禁用」「删除」；删除默认账号后自动把下一个启用账号设为默认。
- 每个账号可点「测试」，向指定邮箱发送一封同步测试邮件，测试结果写入「最近一次测试时间/结果」。

### 4. 发送日志

- 每条发送落一条 `app_cmspro_smtpmail_records` 记录，保存渲染后正文快照与完整收件人信息，可追溯、可重试。
- 检索条件：状态（待发送/队列中/定时待发/发送中/成功/失败/已取消）、关键字（主题/收件人/模板编码/错误）、日期范围；支持导出。
- 详情可查看主题、双正文、收/抄/密送、回复到、发件人、附件、邮件头、优先级、账号、批次、操作人、失败原因。
- 支持操作：重试（失败/待发记录）、取消（未完成记录）、删除；已完成或已取消的记录不能再次取消。

### 5. 批量群发

- 以「任务名称 + 模板编码 + 收件人列表」创建批量任务，每个收件人生成**独立发送记录**并共享同一 `batch_id`，便于单独重试与统计。
- 收件人自动转小写、去重、校验格式；开启黑名单时自动剔除命中地址。
- 支持计划开始时间、指定发信账号、是否走队列；页面展示总数/已投递/成功/失败进度与状态（待处理/进行中/已完成/已取消），可取消未完成任务。

### 6. 黑名单

- 管理被屏蔽的邮箱地址，字段含加入原因（`manual` 后台添加 / `unsubscribe` 用户退订 / `bounce` 退信）与退订令牌。
- 开启「启用黑名单」后，发送前自动过滤命中地址；收件人全部命中时本次发送跳过并记录为「已取消」。
- 前台退订页 `/smtpmail/unsubscribe` 凭令牌将邮箱加入黑名单（`reason=unsubscribe`），返回独立完整的退订结果页。

### 7. 统计看板

- 展示总量、今日发送、成功数、失败数、待处理数、成功率与近 N 日趋势（默认 7 日，见 `RecordService::stats()`）。
- 数据接口：`GET api/dashboard/stats`。

### 8. 应用设置

- 以配置组形式展示并保存全部 12 个配置项（详见下一章），保存后立即清理本应用配置缓存使其生效。
- 提供「接管状态」查询接口 `GET api/settings/takeover-status`，便于确认接管开关是否真正生效。

---

## 五、配置项说明

全部配置项取自 `manifest.json` `config_groups`，共 3 组 13 项。运行时代理读取时使用 `smtpmail_config('<键名>')`；数据库落库键名为 `app_cmspro_smtpmail_<键名>`，代码兜底默认值见 `Config/smtpmail.php`。

### 1. 发信基础设置（`general`）

| 键名 | 标题 | 类型 | 默认值 | 说明 |
|------|------|------|--------|------|
| `takeover_system_mail` | 接管系统邮件 | switch | `0` | 开启后，系统自带的注册验证码、找回密码邮件改用本应用的默认账号与内置模板发送（不修改框架代码）；关闭时系统邮件保持原生行为 |
| `use_queue` | 队列异步发送 | switch | `0` | 开启后投递到系统队列由 worker 异步发送；需保证队列 worker 已运行。关闭时为同步发送 |
| `max_retries` | 最大重试 | number | `3` | 发送失败后自动重试的次数，0 表示不重试 |
| `retry_delay` | 重试间隔（秒） | number | `60` | 每次重试之间的等待秒数 |
| `rate_limit` | 每分钟上限 | number | `60` | 超过上限时本次发送进入等待队列，0 表示不限流；账号单独配置的限流优先 |
| `log_retention_days` | 日志保留天数 | number | `90` | 发送记录保留天数，超期记录由清理命令删除，0 表示永不清理 |
| `site_name` | 站点名称 | text | `CmsPro` | 模板变量 `{{site_name}}` 的默认值 |
| `enable_blacklist` | 启用黑名单 | switch | `1` | 开启后发送前自动过滤黑名单中的邮箱地址 |
| `welcome_mail` | 欢迎邮件 | switch | `0` | 开启后新用户注册成功时自动发送「新用户欢迎邮件」（`welcome` 模板）；注册用户未填写邮箱时不发送；发送异常不影响注册流程 |

### 2. 接口访问设置（`api`）

| 键名 | 标题 | 类型 | 默认值 | 说明 |
|------|------|------|--------|------|
| `api_enabled` | 开启 HTTP 接口 | switch | `0` | 开启后允许外部系统通过 `/api/smtpmail/` 调用发信与查询接口 |
| `api_token` | 接口令牌 | password | 空 | 调用方需在请求头 `X-Smtpmail-Token` 中携带此令牌；保存后明文回显，清空提交视为不修改；留空表示禁止接口调用 |

### 3. 系统邮件模板映射（`system_templates`）

| 键名 | 标题 | 类型 | 默认值 | 说明 |
|------|------|------|--------|------|
| `template_verify_code` | 验证码模板编码 | text | `verify_code` | 接管开启时，系统注册验证码邮件使用此编码的模板 |
| `template_password_reset` | 找回密码模板编码 | text | `password_reset` | 接管开启时，系统找回密码邮件使用此编码的模板 |

> 修改配置并保存后，应用会调用 `smtpmail_forget_config_cache()` 清除 `smtpmail:config:*` 与 `smtpmail:takeover:account` 缓存，确保立即生效。

---

## 六、内置模板与变量

安装时由 `Install::seedBuiltinTemplates()` 初始化四套系统内置模板（`is_system=1`、默认启用、可编辑但不可删除），均以 `{{...}}` 占位符渲染。

| 模板编码 | 名称 | 主题 | 用途 |
|----------|------|------|------|
| `verify_code` | 邮箱验证码 | `【{{site_name}}】邮箱验证码` | 注册/邮箱验证验证码；接管时对应系统视图 `emails.register-code` |
| `password_reset` | 找回密码验证码 | `【{{site_name}}】找回密码` | 找回密码验证码；接管时对应系统视图 `emails.password-reset-code` |
| `welcome` | 新用户欢迎邮件 | `【{{site_name}}】欢迎加入，{{nickname}}` | 新用户开通账号后的欢迎通知 |
| `notice` | 系统通知公告 | `【{{site_name}}】{{title}}` | 系统公告/通知类邮件 |

> `welcome` 与 `notice` 自 1.0.3 起内置（原演示模板编码 `demo_welcome` / `demo_notice` 已去除 `demo_` 前缀并升级为内置）；升级脚本只补齐缺失的内置模板，不覆盖用户已编辑的内容。

### 模板变量说明

| 变量 | 说明 | `verify_code` | `password_reset` | `welcome` | `notice` | 来源 |
|------|------|---------------|------------------|-----------|----------|------|
| `code` | 验证码 | 使用 | 使用 | — | — | 模板 `variables` 定义 + 正文占位符 |
| `minutes` | 有效分钟数 | 使用 | 使用 | — | — | 模板 `variables` 定义 + 正文占位符 |
| `nickname` | 用户昵称 | 定义（正文未使用） | 使用（正文称呼） | 使用（正文称呼） | — | 模板 `variables` 定义 |
| `title` | 通知标题 | — | — | — | 使用 | 模板 `variables` 定义 |
| `content` | 通知正文 | — | — | — | 使用 | 模板 `variables` 定义 |
| `site_name` | 站点名称 | 使用（主题与页脚） | 使用（主题与页脚） | 使用（主题与页脚） | 使用 | 内置默认变量，取自 `site_name` 配置 |
| `year` | 当前年份 | 使用（页脚版权） | 使用（页脚版权） | 使用（页脚版权） | — | 内置默认变量 |
| `date` | 当前日期 `Y-m-d` | 可直接使用 | 可直接使用 | 可直接使用 | 可直接使用 | 内置默认变量 |
| `datetime` | 当前时间 `Y-m-d H:i:s` | 可直接使用 | 可直接使用 | 可直接使用 | 可直接使用 | 内置默认变量 |

| 渲染规则 | 说明 |
|----------|------|
| 内置默认变量 | `TemplateRenderer::withDefaults()` 自动注入 `site_name` / `year` / `date` / `datetime`，**调用方传入的同名变量优先** |
| 未提供变量 | 渲染为空字符串 |
| 转义 | 主题与纯文本正文不做 HTML 转义；HTML 正文中 `{{code}}` 经 `e()` 转义防 XSS，`{{{code}}}` 原样输出可信 HTML 片段 |
| 占位符格式 | 变量名仅允许 `[A-Za-z_][A-Za-z0-9_]*` |

> 接管系统邮件时，覆盖视图 `Views/Override/emails/*.blade.php` 固定向模板传入 `code` / `minutes` / `nickname`，模板编码分别取自 `template_verify_code` / `template_password_reset` 配置项。

---

## 七、发送测试与日志查看

### 1. 发信账号连通性测试

1. 进入「SMTP邮箱 → 发信账号」，新建或编辑账号，填好 SMTP 主机、端口、加密方式、用户名、密码、发件人地址。
2. 保存后点击该账号的「测试」，输入一个可接收邮件的测试邮箱。
3. 应用以该账号**强制同步**发送一封主题为 `【SMTP邮箱】发信测试` 的邮件（不进队列），并把结果写入账号的「最近一次测试时间 / 最近一次测试结果」。
4. 收到邮件即表示账号配置正确；未收到请到「发送日志」查失败原因。

### 2. 模板测试发送

1. 进入「邮件模板」，点击目标模板的「测试」，填写收件邮箱与变量值。
2. 应用经 `MailService::test()` 强制同步发送（来源标记 `template.test`），不走队列，便于即时查看渲染效果。

### 3. 手动发送邮件

1. 进入「发送邮件」，选择模板 → 填写变量 → 「预览」确认 → 填写收件人（选填项在「高级选项」中）→ 附件可在「上传附件」/「附件路径」Tab 中二选一 → 「发送」。
2. 同步模式下页面直接返回发送结果；开启队列时返回「已投递到发送队列」。

### 4. 日志查看

1. 进入「发送日志」，按状态 / 关键字 / 日期范围筛选。
2. 点击「详情」查看渲染后的正文快照、收件人、账号、批次、失败原因。
3. 对失败记录点「重试」；对定时/待发记录可「取消」；必要时「删除」。
4. 日志保留天数由 `log_retention_days` 控制，超期记录由 `smtpmail:clean-records` 每日清理。

---

## 八、队列与定时任务

### 1. 队列发送

- 在「应用设置」开启 `use_queue` 后，新记录状态为 `queued`，由 `Jobs/SendMailJob.php` 投递到系统队列；**必须运行队列 worker**（如 `php artisan queue:work`）才会真正发送，否则记录一直停留在「队列中」。
- 关闭 `use_queue` 时为同步发送，不依赖 worker。
- `SendMailJob` 设置 `tries=1`、`timeout=120`，**不做队列框架级重试**；失败重试统一由 `MailService::fail()` 依据 `max_retries` / `retry_delay` 安排为待重试记录，再由定时命令派发，避免双重重试导致重复投递。
- 超过 `rate_limit`（或账号级 `rate_limit`）时，本次发送自动顺延到下一分钟，同样由定时命令派发。

### 2. 定时任务（命令 + 调度）

| 命令 | 作用 | 调度频率 |
|------|------|----------|
| `php artisan smtpmail:dispatch-due` | 派发到期/待重试邮件；回收超过 10 分钟仍无结果的 `queued` 记录。可选 `--limit=100`（范围 1~500） | 每分钟（`everyMinute()`） |
| `php artisan smtpmail:clean-records` | 按 `log_retention_days` 清理过期的成功/失败/取消记录 | 每日 03:20（`dailyAt('03:20')`） |

- 两个命令由应用 `ServiceProvider::registerSchedule()` 通过 `Schedule` 注册，均带 `withoutOverlapping()`，**不侵入框架 `routes/console.php`**。
- 调度**依赖运维环境既有的 `php artisan schedule:run`**（系统 cron 每分钟调用一次）。若站点未配置该 cron，定时/重试/限流顺延的记录将不会被自动派发，可手工执行命令兜底：

```bash
php artisan smtpmail:dispatch-due
php artisan smtpmail:dispatch-due --limit=200
php artisan smtpmail:clean-records
```

> 定时命令只处理库内到期记录；`enable_blacklist`、`rate_limit`、`max_retries` 等配置均通过 `smtpmail_config()` 实时读取，修改并清缓存后即时生效。

---

## 九、卸载验证

在后台「应用管理」中卸载应用，`Install::uninstall()` 将：

1. 逆序执行 5 个迁移的 `down()`，删除 5 张 `app_cmspro_smtpmail_*` 表并清理 `migrations` 表记录；
2. 删除本应用的配置组与配置项，清除 `smtpmail:config:*` 与 `smtpmail:takeover:account` 缓存；
3. 由框架移除菜单、权限点与按 `app_id` 注册的钩子。

卸载后验证：

- 后台菜单不再出现「SMTP邮箱」，8 个后台页面与 `/smtpmail/unsubscribe` 均不可访问；
- 数据库中 5 张业务表与 `app_cmspro_smtpmail_*` 配置项已清空；
- 接管开关随配置清理失效，系统注册验证码/找回密码邮件回退到框架原生 `Mail::send` 行为，站点邮件功能不受影响。

---

## 十、目录结构

以下为应用根目录 `code/app/Apps/CmsproSmtpmail/` 的真实磁盘结构（含空目录标注）。

```text
CmsproSmtpmail/
├── Assets/                         # 静态资源目录（css/ js/，当前为空）
│   ├── css/
│   └── js/
├── Config/
│   └── smtpmail.php                # 应用默认配置（12 项兜底值）
├── Console/
│   └── Commands/
│       ├── CleanRecords.php        # smtpmail:clean-records
│       └── DispatchDueMails.php    # smtpmail:dispatch-due
├── Controllers/
│   ├── Admin/                      # 后台控制器（8 个模块）
│   │   ├── AccountController.php
│   │   ├── BatchController.php
│   │   ├── BlacklistController.php
│   │   ├── DashboardController.php
│   │   ├── RecordController.php
│   │   ├── SendController.php
│   │   ├── SettingController.php
│   │   └── TemplateController.php
│   ├── Api/
│   │   └── ApiController.php       # 外部 HTTP 接口（开关 + 令牌校验）
│   ├── Home/
│   │   └── UnsubscribeController.php
│   └── BaseAdminController.php     # 后台控制器基类
├── Events/
│   ├── MessageFailed.php
│   └── MessageSent.php
├── Exceptions/
│   └── SmtpmailException.php
├── Jobs/
│   └── SendMailJob.php
├── Listeners/
│   └── ApplySystemMailTakeover.php
├── Mail/
│   └── SmtpmailMailable.php
├── Migrations/
│   ├── 2026_11_05_000001_create_app_cmspro_smtpmail_accounts_table.php
│   ├── 2026_11_05_000002_create_app_cmspro_smtpmail_templates_table.php
│   ├── 2026_11_05_000003_create_app_cmspro_smtpmail_records_table.php
│   ├── 2026_11_05_000004_create_app_cmspro_smtpmail_batches_table.php
│   └── 2026_11_05_000005_create_app_cmspro_smtpmail_blacklist_table.php
├── Models/
│   ├── SmtpmailAccount.php
│   ├── SmtpmailBatch.php
│   ├── SmtpmailBlacklist.php
│   ├── SmtpmailRecord.php
│   └── SmtpmailTemplate.php
├── Routes/
│   ├── admin.php                   # 后台页面 + 数据接口
│   ├── api.php                     # 外部 HTTP 接口
│   └── home.php                    # 前台退订页
├── Services/
│   ├── AccountService.php
│   ├── BatchService.php
│   ├── BlacklistService.php
│   ├── MailService.php
│   ├── MailerFactory.php
│   ├── RecordService.php
│   ├── TakeoverContext.php
│   ├── TemplateRenderer.php
│   └── TemplateService.php
├── Tests/                          # 应用测试（55 个用例，sqlite :memory: 隔离）
│   ├── SmtpmailTestCase.php        # 测试基类
│   ├── Feature/
│   │   ├── ApiAndTakeoverTest.php
│   │   ├── HookAndEventTest.php
│   │   ├── InstallTest.php
│   │   └── MailServiceTest.php
│   └── Unit/
│       ├── AccountServiceTest.php
│       └── TemplateRendererTest.php
├── Views/
│   ├── Admin/
│   │   ├── accounts/{index,form}.blade.php
│   │   ├── batches/{index,form}.blade.php
│   │   ├── blacklist/{index,form}.blade.php
│   │   ├── dashboard/index.blade.php
│   │   ├── records/{index,detail}.blade.php
│   │   ├── send/index.blade.php
│   │   ├── settings/index.blade.php
│   │   └── templates/{index,form}.blade.php
│   ├── Home/
│   │   └── unsubscribe.blade.php
│   └── Override/
│       └── emails/
│           ├── password-reset-code.blade.php
│           └── register-code.blade.php
├── doc/
│   ├── CmsPro-SMTP邮箱-特性清单.md
│   ├── CmsPro-SMTP邮箱-使用指南.md
│   └── CmsPro-SMTP邮箱-扩展指南.md
├── Hooks.php
├── Install.php
├── ServiceProvider.php
├── helpers.php
├── manifest.json
├── README.md
├── icon.svg
├── .exportignore
└── .gitignore
```

> 说明：以上为交付完成后的真实磁盘结构。`Routes/api.php` 的四个端点由 `Controllers/Api/ApiController.php` 实现，请求体字段、响应结构与调用示例详见《扩展指南》「HTTP API」章节。

---

## 十一、常见问题（FAQ）

| 现象 | 可能原因 | 处理方式 |
|------|----------|----------|
| 已开启「接管系统邮件」，但系统验证码/找回密码邮件仍是老样子 | 未配置**启用的默认账号**：`ServiceProvider::registerTakeover()` 在 `defaultAccount()` 返回 `null` 时直接跳过接管 | 先在「发信账号」添加并启用账号、设为默认，再到「应用设置 → 接管状态」确认生效 |
| 更换 `APP_KEY` 或迁移站点后，账号发信报认证失败/密码失效 | 账号密码用 Laravel `Crypt`（绑定 `APP_KEY`）加密存储，`APP_KEY` 变化后 `AccountService::decryptPassword()` 解密失败会返回空串 | 到「发信账号」重新录入所有账号的 SMTP 密码并保存；迁移站点时保持 `APP_KEY` 不变可避免 |
| 开启「队列异步发送」后邮件一直停留在「队列中」 | 未运行队列 worker，或运维未配置 `artisan schedule:run` 导致定时兜底未触发 | 运行 `php artisan queue:work`；确认系统 cron 已每分钟执行 `php artisan schedule:run`；也可手工执行 `php artisan smtpmail:dispatch-due` |
| 系统自带邮件原本怎么发？开启接后会改框架代码吗？ | 关闭接管时系统邮件走框架原生 `Mail::send`；开启接管后**不改任何框架代码**，改用运行时代理：运行时注入 `mail.*` 配置 + `prependLocation` 覆盖 `emails.register-code` / `emails.password-reset-code` 视图 + `MessageSending` 事件改写主题与纯文本正文 | 无需改代码；关闭开关即完全回退到原生行为 |
| 外部 `/api/smtpmail/` 接口调用不通或提示禁止 | 需**同时**开启 `api_enabled` 且 `api_token` 非空；请求头须带 `X-Smtpmail-Token`；接口还受 `throttle:60,1` 限流 | 在「应用设置」开启 HTTP 接口并填写令牌；调用方在请求头携带令牌；控制调用频率 |
| 发送后提示「未配置可用的发信账号」 | 无启用状态的账号，或指定 `account_code` / `account_id` 对应的账号被禁用 | 添加/启用账号，或修正调用参数 |
| 收件人被跳过、记录显示「已取消」 | 命中黑名单，或 `cmspro.smtpmail.should_send` 钩子返回 `false` 拦截 | 检查「黑名单」与各应用注册的钩子 |
| 邮件发送被延后、不是立即到达 | 命中 `rate_limit` 限流、设置了未来 `scheduled_at`，或失败后等待重试 | 查看「发送日志」状态与计划发送时间；调大限流或等待 `smtpmail:dispatch-due` 派发 |
| 模板中 `{{site_name}}` 显示不对 | `site_name` 配置项未设置，或调用方传入同名变量覆盖了默认值 | 在「应用设置」修改站点名称；注意调用方 `$vars` 优先级更高 |
| 日志被自动删除 | `log_retention_days` 到期，由 `smtpmail:clean-records` 每日 03:20 清理 | 调大保留天数，或设为 `0` 永不清理 |

---

## 版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.3 | 2026-11-06 | 模板分类管理：新增分类表与「分类管理」弹窗（新增/编辑/删除），模板表单分类改下拉选择并合并历史分类；模板正文三态编辑器（代码/可视化/预览）；预览与测试改为变量表单；内置变量中文名由后端统一下发；发送页附件支持从附件管理选择（新增 `POST api/send/attachments/resolve`）；欢迎邮件与系统通知公告升级为内置模板（编码去除 `demo_` 前缀为 `welcome`/`notice`，安装时默认内置）；新增「欢迎邮件」开关（`welcome_mail`，经框架 `user.after_register` 钩子在注册成功后自动发送，无邮箱不发送、异常不阻断注册）；设置页按类型 Tab 分类并新增「接口文档」Tab，系统邮件模板映射改下拉选择，配置项标题精简；修复记录页收件人列、详情 iframe 留白、多处时间列字段错位 |
| 1.0.2 | 2026-11-06 | 发送页完善：模板变量显示中文 label、选填项「高级选项」折叠、附件支持上传与路径二选一（新增 `POST api/send/upload`）|
| 1.0.1 | 2026-11-05 | 验收整改：设置接口 `ALLOWED_KEYS` 白名单；接口令牌按规范明文回显；后台接口强制 JSON 响应（校验失败返回 422，禁止 302）；提交防重复点击与统一错误提示；分页参数统一 `per_page`；应用级测试 41→55 个用例 |
| 1.0.0 | 2026-11-05 | 首个版本：多账号管理、可视化模板、发送/定时/队列/批量群发、发送日志与统计看板、黑名单与退订、12 项配置、系统邮件运行时代理接管、队列与定时调度 |
