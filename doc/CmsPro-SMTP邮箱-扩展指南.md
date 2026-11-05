# CmsPro SMTP邮箱 · 扩展指南

> 版本：1.0.1 | 更新日期：2026-11-05
> 应用版本：1.0.1 | 应用 ID：`cmspro.smtpmail`

本指南面向二次开发者，核心说明**其他应用如何调用本应用**：辅助函数、`MailService` 服务层、钩子点、Laravel 事件、HTTP 接口，以及「接管系统邮件」的实现原理。所有签名、参数与返回结构均取自应用内真实源码，未做任何臆造。

---

## 一、扩展面总览

| 层次 | 实现位置 | 用途 |
|------|----------|------|
| 辅助函数 | `helpers.php`（由 `ServiceProvider::register()` `require_once` 加载） | 跨应用发信主入口、配置读取、启用状态检测 |
| 服务层 | `Services/MailService.php` 及各 Service | 门面发送、直发、测试、投递、重试、模板/账号/记录/批量/黑名单能力 |
| 钩子 | `Hooks.php` + `App\Services\HookManager` | 4 个自定义钩子点（2 Filter + 2 Action） |
| 事件 | `Events/MessageSent.php`、`Events/MessageFailed.php` | Laravel 原生事件，弱依赖订阅 |
| HTTP 接口 | `Routes/api.php` | 外部系统经令牌调用 |
| 接管开关 | `ServiceProvider::registerTakeover()` | 零改动接管系统自带邮件 |

命名空间统一为 `App\Apps\CmsproSmtpmail\*`。**跨应用调用请优先使用 `smtpmail_send()` 等辅助函数**：应用未安装/未启用时返回失败数组而不抛异常。

---

## 二、前置检查

调用前必须确认应用**已安装并启用**（迁移已执行、默认账号已配置、配置已写入）。跨应用弱依赖推荐「双保险」：

```php
// 方式一（推荐）：函数存在 + 应用启用 双重判断
if (function_exists('smtpmail_send') && smtpmail_enabled()) {
    $result = smtpmail_send('verify_code', 'user@example.com', ['code' => '482913']);
    if (! $result['success']) {
        // 按 $result['message'] 记录或降级处理
    }
}

// 方式二：直接查询应用状态（不依赖辅助函数是否已加载）
use App\Models\AppModel;

$enabled = AppModel::where('app_id', 'cmspro.smtpmail')
    ->where('status', 1)
    ->exists();
```

| 检查项 | 说明 |
|--------|------|
| `function_exists('smtpmail_send')` | 应用级 `helpers.php` 是否已被加载（由 `ServiceProvider::register()` 引入）；未加载时调用会致命错误 |
| `smtpmail_enabled()` | 三重防护：ServiceProvider 类是否可加载 → `apps` 表 `status=1` → 异常兜底返回 `false` |
| 默认账号 | 发送前 `MailService::resolveAccount()` 要求存在 `status=1` 的账号；否则抛 `SmtpmailException`「未配置可用的发信账号」 |
| 模板 | 模板编码须对应 `status=1` 的模板，否则抛 `SmtpmailException`「邮件模板不存在或已禁用」 |

---

## 三、辅助函数

`helpers.php` 中所有函数均以 `smtpmail_` 前缀命名、用 `function_exists` 包裹，可全局调用。

| 函数 | 确切签名 | 说明 |
|------|----------|------|
| 按模板发送 | `smtpmail_send(string $templateCode, string\|array $to, array $vars = [], array $options = []): array` | 其他应用调用的主入口；应用未启用返回失败数组 |
| 直发正文 | `smtpmail_send_raw(string $subject, string $html, string\|array $to, array $options = []): array` | 不走模板，直接发送自定义主题与 HTML 正文 |
| 启用检测 | `smtpmail_enabled(): bool` | 判断应用是否已安装并启用 |
| 读取配置 | `smtpmail_config(string $key, mixed $default = null): mixed` | 优先读数据库 `config_items`（键 `app_cmspro_smtpmail_<key>`），回退 `config('apps.cmspro.smtpmail.<key>')`；`Cache::remember` 缓存 3600 秒 |
| 清配置缓存 | `smtpmail_forget_config_cache(array $keys = []): void` | 清除 `smtpmail:config:*` 与 `smtpmail:takeover:account`；`$keys` 为空时清全部配置键 |

> 应用内部另有 `smtpmail_takeover_context(): TakeoverContext` 与 `smtpmail_render_system(string $templateCode, array $vars): string`，仅供接管系统邮件的覆盖视图使用，其他应用无需调用。

### 返回值结构

`smtpmail_send()` / `smtpmail_send_raw()` / `MailService` 的发送类方法统一返回如下数组（由 `MailService::result()` 生成）：

| 字段 | 类型 | 说明 |
|------|------|------|
| `success` | `bool` | 是否发送成功（同步模式）/ 是否成功受理（定时、队列模式） |
| `record_id` | `int` | 发送记录 ID；应用不可用、被钩子拦截或收件人全在黑名单时为 `0` |
| `status` | `string` | 记录状态：`pending` / `queued` / `scheduled` / `sending` / `success` / `failed` / `canceled`；应用未启用时为 `unavailable`；捕获异常时为 `failed` |
| `message` | `string` | 结果说明或错误信息 |

```php
// 应用未安装/未启用时 smtpmail_send() 的返回
// ['success' => false, 'record_id' => 0, 'status' => 'unavailable', 'message' => 'SMTP邮箱应用未安装或未启用']

// 捕获到异常时
// ['success' => false, 'record_id' => 0, 'status' => 'failed', 'message' => $e->getMessage()]
```

### 调用示例

```php
// 1. 按内置模板发送验证码
$result = smtpmail_send('verify_code', 'user@example.com', [
    'code'    => '482913',
    'minutes' => 10,
    'nickname' => '张三',
], [
    'account_code' => 'notify',
    'source'       => 'my.app.id',
    'priority'     => 2,
]);

// 2. 多收件人 + 抄送 + 定时发送
$result = smtpmail_send('notice', ['a@example.com', 'b@example.com'], [
    'title' => '系统通知',
], [
    'cc'           => 'boss@example.com',
    'scheduled_at' => '2026-11-06 09:00:00',
    'queue'        => true,
]);

// 3. 不使用模板，直发自定义正文
$result = smtpmail_send_raw(
    '【{{site_name}}】账户开通通知',
    '<p>{{nickname}} 您好，您的账户已开通。</p>',
    'user@example.com',
    ['vars' => ['nickname' => '张三']]
);

// 4. 读取配置 / 清缓存
$maxRetries = smtpmail_config('max_retries', 3);
$useQueue   = smtpmail_config('use_queue', '0') === '1';
smtpmail_forget_config_cache(['takeover_system_mail', 'use_queue']);
```

---

## 四、Service 层调用

服务类位于 `App\Apps\CmsproSmtpmail\Services`，通过容器解析：`app(MailService::class)`。

```php
use App\Apps\CmsproSmtpmail\Services\MailService;

$mail = app(MailService::class);
```

| 方法 | 确切签名 | 返回值 | 说明 |
|------|----------|--------|------|
| 按模板发送 | `send(string $templateCode, string\|array $to, array $vars = [], array $options = []): array` | `array{success,record_id,status,message}` | 渲染模板后注入 `subject/html/text/template_id/template_code/account_id` 并调用 `sendRaw()` |
| 直发正文 | `sendRaw(string\|array $to, array $options = []): array` | `array{success,record_id,status,message}` | 不走模板；`$options` 需含 `subject` 与 `html`/`text`；经 Filter 钩子、黑名单后落记录并派发 |
| 测试发送 | `test(string $templateCode, string $to, array $vars = []): array` | `array{success,record_id,status,message}` | 强制同步（`queue=false`）、`source=template.test` |
| 投递记录 | `deliver(int $recordId): void` | **无返回值（`void`）** | 真正投递一条已存在的记录；成功触发 `MessageSent` 与 `cmspro.smtpmail.sent`，失败走 `fail()` 触发 `MessageFailed` 与 `cmspro.smtpmail.failed` |
| 手动重试 | `retry(int $recordId): array` | `array{success,record_id,status,message}` | 重置记录为 `pending`、`retry_count=0`、清空 `scheduled_at`/`error` 后调用 `deliver()`；已取消记录抛 `SmtpmailException` |

> **注意**：`deliver(int $recordId): void` 的真实返回类型是 `void`（见 `Services/MailService.php`），它只负责投递并更新记录状态；需要结果结构时请使用 `send()` / `sendRaw()` / `retry()`，或投递后用 `RecordService::find($id)` 查询记录。

```php
use App\Apps\CmsproSmtpmail\Services\MailService;

$mail = app(MailService::class);

// 按模板发送
$result = $mail->send('verify_code', 'user@example.com', ['code' => '482913']);

// 直发
$result = $mail->sendRaw('user@example.com', [
    'subject' => '测试主题',
    'html'    => '<p>正文 {{name}}</p>',
    'text'    => '正文 {{name}}',
    'vars'    => ['name' => '张三'],
    'queue'   => false,
]);

// 测试发送（模板后台预览用）
$result = $mail->test('verify_code', 'admin@example.com', ['code' => '000000']);

// 重试一条失败记录
$result = $mail->retry(1024);

// 直接投递一条待发记录（无返回值）
$mail->deliver(1024);
```

发送主流程（`sendRaw()`）：渲染 → 解析账号 → 组装载荷 → `cmspro.smtpmail.before_send` 过滤 → `cmspro.smtpmail.should_send` 判定 → 黑名单过滤 → 落 `SmtpmailRecord` → 依状态定时/入队/同步投递。

---

## 五、`$options` 完整参数表

`smtpmail_send()` 第 4 参数、`send()` 第 4 参数、`sendRaw()` 第 2 参数、`smtpmail_send_raw()` 第 4 参数均为同一套 `$options` 数组。下表覆盖全部对外可选项（取自 `MailService::buildPayload()` / `resolveAccount()` / `sendRaw()` / `send()`）。

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `cc` | `string\|array` | `[]` | 抄送地址；字符串按 `,;空白` 切分，逐项校验邮箱格式 |
| `bcc` | `string\|array` | `[]` | 密送地址，处理同 `cc` |
| `reply_to` | `string\|array` | 账号 `reply_to`（非空时） | 回复到地址 |
| `attachments` | `array` | `[]` | 附件数组，元素形如 `{"path":"相对路径","name":"显示名"}` |
| `headers` | `array` | `[]` | 自定义邮件头键值对 |
| `priority` | `int` | `3` | 优先级，自动约束到 `1`（最高）~`5`（最低） |
| `account_code` | `string` | 空 | 指定发信账号编码（`AccountService::findByCode()`，须 `status=1`）；**优先级最高** |
| `account_id` | `int` | 空 | 指定发信账号 ID；`send()` 在模板绑定账号且未指定 `account_code` 时自动注入 |
| `from_address` | `string` | 账号 `from_address` | 覆盖发件人地址 |
| `from_name` | `string` | 账号 `from_name` | 覆盖发件人名称 |
| `scheduled_at` | `string` | `null` | 计划发送时间，经 `Carbon::parse()` 归一为 `Y-m-d H:i:s`；过去时间视为立即发送；非 null 时记录为 `scheduled` |
| `queue` | `bool` | 取 `use_queue` 配置（`'1'` 为 `true`） | 是否入队；显式传值优先于全局配置 |
| `source` | `string` | `''` | 调用来源标识，落库到记录 `source`，便于按应用统计/排查 |
| `operator` | `string` | `''` | 操作人标识 |
| `batch_id` | `int\|null` | `null` | 所属批量任务 ID（由 `BatchService` 注入） |
| `subject` | `string` | `''` | 邮件主题；`send()` 由模板渲染覆盖，直发时必填（不能为空白） |
| `html` | `string` | `''` | HTML 正文；`send()` 由模板渲染覆盖，直发时与 `text` 至少一项非空 |
| `text` | `string` | `''` | 纯文本正文 |
| `vars` | `array` | `[]` | 直发正文（`subject`/`html`/`text`）占位符替换变量；按模板发送时请改用 `send()` 的 `$vars` 参数 |

账号解析优先级：`account_code` > `account_id`（含模板绑定自动注入） > 默认账号（`AccountService::defaultAccount()`）。无可用账号时抛 `SmtpmailException`。

以下为应用内部自动写入、调用方一般无需关心的附加键，一并列明以免误解：`template_id`、`template_code`（`send()` 注入）、`ip`（默认 `request()?->ip()`）、`_no_render`（`send()` 注入，避免模板已渲染正文被二次替换）。

---

## 六、钩子扩展点（4 个）

框架钩子管理器为 `App\Services\HookManager`：

| 方法 | 确切签名 |
|------|----------|
| 注册 Action | `registerAction(string $hook, callable $callback, int $priority = 10, ?string $appId = null): void` |
| 注册 Filter | `registerFilter(string $hook, callable $callback, int $priority = 10, ?string $appId = null): void` |
| 触发 Action | `doAction(string $hook, mixed ...$args): void` |
| 应用 Filter | `applyFilter(string $hook, mixed $value, mixed ...$args): mixed` |

**第 4 参数必须传自己的 `app_id`**：应用禁用/卸载时框架据 `removeHooksByApp(appId)` 批量摘除钩子，不传会导致钩子残留。

| 钩子点 | 类型 | 触发位置 | 值与参数 | 返回约定 |
|--------|------|----------|----------|----------|
| `cmspro.smtpmail.before_send` | Filter | `MailService::sendRaw()` | 值为 `$payload` 数组（`buildPayload()` 结果），附传 `$options` | **必须返回数组**；返回非数组时被忽略 |
| `cmspro.smtpmail.should_send` | Filter | `MailService::sendRaw()` | 值为 `bool`（初始 `true`），附传 `$payload` | 返回 `false` 拦截发送，记录状态 `canceled` |
| `cmspro.smtpmail.sent` | Action | `MailService::deliver()` | `int $recordId, SmtpmailRecord $record` | 无返回值 |
| `cmspro.smtpmail.failed` | Action | `MailService::fail()` | `int $recordId, string $error` | 无返回值（每次失败都会触发，含可重试失败） |

`$payload` 关键键（来自 `MailService::buildPayload()`）：`template_id`、`template_code`、`account_id`、`batch_id`、`subject`、`body_html`、`body_text`、`to_addresses`、`cc`、`bcc`、`reply_to`、`from_address`、`from_name`、`attachments`、`headers`、`priority`、`status`、`scheduled_at`、`retry_count`、`error`、`source`、`operator`、`ip`。

```php
use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Services\HookManager;

$hooks = app(HookManager::class);
$myAppId = 'my.app.id';   // 第 4 参数必须传自己的 app_id

// 1. Filter：改写发送载荷（必须返回数组）
$hooks->registerFilter(
    'cmspro.smtpmail.before_send',
    function (array $payload, array $options): array {
        $payload['subject'] = '【我的应用】' . $payload['subject'];
        return $payload;
    },
    10,
    $myAppId
);

// 2. Filter：返回 false 拦截
$hooks->registerFilter(
    'cmspro.smtpmail.should_send',
    function (bool $allowed, array $payload): bool {
        return $allowed && ! in_array('blocked@example.com', $payload['to_addresses'] ?? [], true);
    },
    10,
    $myAppId
);

// 3. Action：发送成功
$hooks->registerAction(
    'cmspro.smtpmail.sent',
    function (int $recordId, SmtpmailRecord $record): void {
        // 例如写业务通知表、推送站内信
    },
    10,
    $myAppId
);

// 4. Action：发送失败
$hooks->registerAction(
    'cmspro.smtpmail.failed',
    function (int $recordId, string $error): void {
        // 例如告警；应用内置 Hooks::onSendFailed() 已写系统日志
    },
    10,
    $myAppId
);
```

> 单个钩子回调抛出的异常会被 `HookManager` 与 `MailService` 双重隔离，不阻断发信与后续回调；`before_send` 抛异常时 `$payload` 保持进入该回调之前的值。

---

## 七、Laravel 事件

| 事件类 | 构造签名 | 派发时机 |
|--------|----------|----------|
| `App\Apps\CmsproSmtpmail\Events\MessageSent` | `__construct(public SmtpmailRecord $record)` | `MailService::deliver()` 投递成功后 `event(new MessageSent($record))` |
| `App\Apps\CmsproSmtpmail\Events\MessageFailed` | `__construct(public SmtpmailRecord $record, public string $error)` | `MailService::fail()` 中（含可重试失败）`event(new MessageFailed($record, $error))` |

两者均使用 `Dispatchable` 与 `SerializesModels` trait，可安全用于队列监听器。

```php
use App\Apps\CmsproSmtpmail\Events\MessageFailed;
use App\Apps\CmsproSmtpmail\Events\MessageSent;
use Illuminate\Support\Facades\Event;

Event::listen(MessageSent::class, function (MessageSent $event): void {
    $record = $event->record;              // SmtpmailRecord
    // $record->id / $record->to_addresses / $record->status ...
});

Event::listen(MessageFailed::class, function (MessageFailed $event): void {
    $record = $event->record;              // SmtpmailRecord
    $error  = $event->error;               // string
});
```

也可注册为监听器类：`Event::listen(MessageSent::class, MySentListener::class)`。事件与 Action 钩子可二选一：事件适合纯 Laravel 生态订阅，Action 钩子适合需要 `app_id` 生命周期管理的应用插件。

---

## 八、HTTP API

### 1. 开关与令牌

| 项目 | 取值 | 来源 |
|------|------|------|
| 开关 | `api_enabled`（默认 `0`） | `manifest.json` `config_groups.api` |
| 令牌 | `api_token`（默认空，留空表示禁止接口调用） | `manifest.json` `config_groups.api` |
| 请求头 | `X-Smtpmail-Token: <接口令牌>` | `Routes/api.php` 文件头注释 |
| 路由前缀 | `/api/smtpmail` | `ServiceProvider::registerRoutes()` |
| 中间件 | `throttle:60,1`（每分钟 60 次） | `ServiceProvider::registerRoutes()` |
| 鉴权 | 不走后台登录态，由 `ApiController::authorizeRequest()` 校验开关与令牌 | `Controllers/Api/ApiController.php` |

> **必须同时开启 `api_enabled` 且配置非空 `api_token`**，并在每次请求头携带 `X-Smtpmail-Token`，接口才可用。

### 2. 接口清单

| 方法 | 路径 | 路由名 | 用途 |
|------|------|--------|------|
| `POST` | `/api/smtpmail/send` | `cmspro.smtpmail.api.send` | 发送邮件 |
| `GET` | `/api/smtpmail/templates` | `cmspro.smtpmail.api.templates` | 模板列表 |
| `GET` | `/api/smtpmail/records` | `cmspro.smtpmail.api.records` | 发送记录 |
| `GET` | `/api/smtpmail/status` | `cmspro.smtpmail.api.status` | 状态查询 |

上述路由定义于 `Routes/api.php`，指向 `App\Apps\CmsproSmtpmail\Controllers\Api\ApiController`（`send` / `templates` / `records` / `status` 四个方法）。统一响应结构为 `{code:0, message, data, timestamp}`（由 `App\Http\Responses\ApiResponse` 生成），`code` 非 0 表示失败；错误码：`40301` 接口未开启、`40101` 令牌无效、`40001` 参数/业务错误、`50001` 系统异常。

| 端点 | 请求体 / 查询参数 | `data` 结构 |
|------|------------------|-------------|
| `POST /send` | 模板模式 `{template_code, to, vars?, options?}`；直发模式 `{subject, html, text?, to, options?}`；`options` 可含 `cc/bcc/reply_to/attachments/headers/priority/account_code/scheduled_at/queue/source` | `{success, record_id, status, message}` |
| `GET /templates` | 无 | 模板选项数组 `[{code,name,subject,variables,extracted_variables,is_system}]` |
| `GET /records` | `status/keyword/date_from/date_to/per_page` | `{total, page, data: 记录摘要数组}` |
| `GET /status` | 无 | `{app_id, stats, takeover_enabled, default_account}` |

### 3. curl 生产调用示例

> **重要**：以下为**给使用者看的生产环境调用示例**，仅用于说明请求路径与令牌头，**不是本项目的接口验证方式**。项目内接口验证必须使用 `php artisan test`（如 `php artisan test app/Apps/CmsproSmtpmail/Tests`），**禁止使用 curl 拼接域名端口进行手工测试**。

```bash
# 1. 发送邮件（POST /api/smtpmail/send）
curl -X POST 'https://your-domain.com/api/smtpmail/send' \
  -H 'Content-Type: application/json' \
  -H 'X-Smtpmail-Token: YOUR_API_TOKEN' \
  -d '{
        "template_code": "verify_code",
        "to": "user@example.com",
        "vars": {"code": "482913", "minutes": 10}
      }'

# 2. 模板列表（GET /api/smtpmail/templates）
curl -X GET 'https://your-domain.com/api/smtpmail/templates' \
  -H 'X-Smtpmail-Token: YOUR_API_TOKEN'

# 3. 发送记录（GET /api/smtpmail/records）
curl -X GET 'https://your-domain.com/api/smtpmail/records' \
  -H 'X-Smtpmail-Token: YOUR_API_TOKEN'

# 4. 状态查询（GET /api/smtpmail/status）
curl -X GET 'https://your-domain.com/api/smtpmail/status' \
  -H 'X-Smtpmail-Token: YOUR_API_TOKEN'
```

---

## 九、注意事项

1. **不直接操作模型**：其他应用一律通过 `smtpmail_send()` / `smtpmail_send_raw()` 或 `app(MailService::class)` 调用，禁止直接 `SmtpmailRecord::create()`、直接改 `SmtpmailAccount` 等；模型仅供应用内部使用，且 `SmtpmailAccount::$hidden` 已隐藏密码密文。
2. **事务边界**：发送包含外部 SMTP IO，不具备数据库事务回滚能力。`MailService::sendRaw()` 先落记录再投递，调用方**勿把发信放进需要回滚的业务事务里**（事务回滚不会撤销已发出的邮件）；建议事务提交后再发信，或改用 `queue` / `scheduled_at` 异步化。应用内部仅 `BatchService::create()`（建批次）、`AccountService::setDefault()`、`TemplateService::duplicate()` 在事务内操作自己的数据。
3. **配置缓存**：`smtpmail_config()` 带 3600 秒缓存，后台保存配置后须调用 `smtpmail_forget_config_cache()`；`Hooks::onAppDisabled()` 在应用禁用时也会自动清缓存。**禁止**用 `config('apps.cmspro.smtpmail.*')` 代替读取，数据库用户配置会读不到。
4. **密码加密与 APP_KEY**：账号密码由 `AccountService::normalize()` 用 `Crypt::encryptString()` 加密存储，读取统一走 `AccountService::decryptPassword()`；更换 `APP_KEY` 或迁移站点后密文无法解密（返回空串），**必须重新录入所有账号密码**。扩展中不得硬编码或明文输出账号密码。
5. **黑名单与限流**：`enable_blacklist=1` 时发送前过滤 `BlacklistService::blockedEmails()`，全部命中返回 `status=canceled`；`rate_limit` 全局限流与账号级 `rate_limit`（账号级优先）超限时自动顺延到下一分钟，由 `smtpmail:dispatch-due` 派发；`cmspro.smtpmail.should_send` 钩子也可拦截。
6. **日期格式**：统一 `Y-m-d H:i:s`。`scheduled_at` 传入后经 `Carbon::parse()->format('Y-m-d H:i:s')` 归一；模型 `scheduled_at` / `sent_at` / `create_time` / `update_time` 的 `$casts` 均为 `datetime:Y-m-d H:i:s`。
7. **异常约定**：应用业务校验失败抛 `App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException`（中文提示）；辅助函数已捕获 `Throwable` 并转为失败数组，Service 层直接调用时需自行 `try/catch`。
8. **钩子第 4 参数**：注册钩子务必传自己的 `app_id`，否则应用禁用/卸载后钩子残留，可能引发跨应用副作用。
9. **改动同步**：新增/修改扩展能力后，须同步更新 `doc/` 下三类文档（规范第 21 章强制）。

---

## 十、接管系统邮件

### 1. 开关

配置项 `takeover_system_mail`（`general` 组，默认 `0`）：开启后系统自带的注册验证码、找回密码邮件改用本应用默认账号与内置模板发送；关闭时系统邮件保持框架原生行为。

### 2. 实现原理（`ServiceProvider::registerTakeover()`）

| 步骤 | 实现 | 位置 |
|------|------|------|
| ① 开关判断 | `smtpmail_config('takeover_system_mail','0') !== '1'` 时直接返回 | `ServiceProvider.php` |
| ② 默认账号检查 | `AccountService::defaultAccount()` 返回 `null` 时直接返回（**无默认账号则不接管**） | `ServiceProvider.php`、`Services/AccountService.php` |
| ③ 运行时注入 mail 配置 | `config([...])` 在内存覆盖 `mail.default`、`mail.mailers.smtp.transport/host/port/username/password/scheme/timeout`、`mail.from.address/name`；密码经 `decryptPassword()` 解密；**不修改任何配置文件** | `ServiceProvider.php` |
| ④ 覆盖系统视图 | `app('view')->getFinder()->prependLocation(.../Views/Override)`，令 `emails.register-code` 与 `emails.password-reset-code` 优先命中本应用覆盖视图 | `ServiceProvider.php`、`Views/Override/emails/*.blade.php` |
| ⑤ 改写主题与正文 | 覆盖视图调用 `smtpmail_render_system()` 渲染模板并把结果暂存 `TakeoverContext`；`MessageSending` 事件监听器 `ApplySystemMailTakeover::handle()` 用 `consume()` 取出结果，写回 Symfony 消息的 `subject()` / `html()` / `text()` | `ServiceProvider::registerEvents()`、`Listeners/ApplySystemMailTakeover.php`、`Services/TakeoverContext.php`、`helpers.php` |

- 模板编码映射：`template_verify_code`（默认 `verify_code`）→ 注册验证码；`template_password_reset`（默认 `password_reset`）→ 找回密码；覆盖视图固定传入 `code` / `minutes` / `nickname`。
- `TakeoverContext` 由 `ServiceProvider::register()` 注册为 `singleton`，逐请求隔离，`consume()` 读后即清空，避免常驻进程（Octane）跨请求污染。
- 应用自身发信走 `MailerFactory::make()` 构造的独立 `Mailer`（直接构造 Symfony `EsmtpTransport`），**不读写框架 `mail_*` 配置**，既不受接管开关影响，也不污染框架全局邮箱配置。

### 3. 零保护区改动、关闭即完全回退

- **零保护区改动**：全过程只做运行时配置注入、视图查找路径注入、事件监听，不修改框架控制器、不修改系统邮件视图源文件、不修改框架 `config/mail.php`，也不在 `app/Apps/CmsproSmtpmail/` 之外写任何文件。
- **关闭即完全回退**：关闭 `takeover_system_mail`、或应用被禁用/卸载后，不再注入任何配置与视图覆盖，系统邮件完全回归框架原生 `Mail::send` 行为；`app.disabled` 钩子与应用卸载流程还会清理配置缓存，避免接管状态残留。
- 切换开关后建议调用一次 `smtpmail_forget_config_cache()`（后台保存设置时已自动调用），确保新值立即生效。

---

## 版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.1 | 2026-11-05 | 验收整改：API 统一 `App\Http\Responses\ApiResponse`（响应新增 `timestamp` 字段）；`GET /records` 分页参数 `limit` 更名为 `per_page` |
| 1.0.0 | 2026-11-05 | 首个版本：辅助函数与 MailService 服务层、完整 `$options` 参数、4 个钩子点、2 个 Laravel 事件、HTTP 接口、跨应用弱依赖前置检查、系统邮件运行时代理接管 |
