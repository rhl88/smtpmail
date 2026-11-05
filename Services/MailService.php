<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Events\MessageFailed;
use App\Apps\CmsproSmtpmail\Events\MessageSent;
use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Jobs\SendMailJob;
use App\Apps\CmsproSmtpmail\Mail\SmtpmailMailable;
use App\Apps\CmsproSmtpmail\Models\SmtpmailAccount;
use App\Apps\CmsproSmtpmail\Models\SmtpmailBatch;
use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Services\HookManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * SMTP邮箱 · 对外主服务（门面）
 *
 * 其他应用请通过 app(MailService::class) 或 smtpmail_send() 调用，
 * 禁止直接操作模型。发送前经过 Filter 钩子与黑名单，发送后触发 Action 钩子
 * 与 Laravel 事件；所有结果都落在「发送记录」中，可追溯、可重试。
 */
class MailService
{
    /**
     * 按模板编码发送
     *
     * @param string|array<int,string> $to      收件人
     * @param array<string,mixed>      $vars    模板变量
     * @param array<string,mixed>      $options 可选项
     * @return array{success:bool,record_id:int,status:string,message:string}
     */
    public function send(string $templateCode, string|array $to, array $vars = [], array $options = []): array
    {
        $rendered = app(TemplateRenderer::class)->renderByCode($templateCode, $vars);

        $options['template_id'] = $rendered['template_id'];
        $options['template_code'] = $rendered['template_code'];
        $options['subject'] = $rendered['subject'];
        $options['html'] = $rendered['html'];
        $options['text'] = $rendered['text'];

        if (empty($options['account_code']) && $rendered['account_id'] !== null) {
            $options['account_id'] = $rendered['account_id'];
        }

        // 模板已渲染完成，避免 sendRaw 对正文中的 {{}} 做二次替换
        $options['_no_render'] = true;

        return $this->sendRaw($to, $options);
    }

    /**
     * 直发自定义正文（不走模板）
     *
     * $options 必填：subject、html；可选：text、cc、bcc、reply_to、attachments、
     * headers、priority、account_code、account_id、from_address、from_name、
     * scheduled_at、queue、source、operator、batch_id、template_id、template_code。
     *
     * @param string|array<int,string> $to
     * @param array<string,mixed>      $options
     * @return array{success:bool,record_id:int,status:string,message:string}
     */
    public function sendRaw(string|array $to, array $options = []): array
    {
        if (! empty($options['_no_render'])) {
            $rendered = [
                'subject' => (string) ($options['subject'] ?? ''),
                'html' => (string) ($options['html'] ?? ''),
                'text' => (string) ($options['text'] ?? ''),
            ];
        } else {
            $rendered = app(TemplateRenderer::class)->renderRaw(
                (string) ($options['subject'] ?? ''),
                (string) ($options['html'] ?? ''),
                (string) ($options['text'] ?? ''),
                (array) ($options['vars'] ?? [])
            );
        }

        if (trim((string) $rendered['subject']) === '') {
            throw new SmtpmailException('邮件主题不能为空');
        }
        if (trim((string) $rendered['html']) === '' && trim((string) $rendered['text']) === '') {
            throw new SmtpmailException('邮件正文不能为空');
        }

        $account = $this->resolveAccount($options);
        $payload = $this->buildPayload($to, $rendered, $options, $account);

        // Filter 钩子：其他应用可改写载荷（收件人、正文、附件等）
        try {
            $filtered = app(HookManager::class)->applyFilter('cmspro.smtpmail.before_send', $payload, $options);
            if (is_array($filtered)) {
                $payload = $filtered;
            }

            $allowed = app(HookManager::class)->applyFilter('cmspro.smtpmail.should_send', true, $payload);
            if ($allowed === false) {
                return $this->result(false, 0, SmtpmailRecord::STATUS_CANCELED, '发送已被钩子拦截');
            }
        } catch (\Throwable $e) {
            // 单个钩子异常不应阻断发信
        }

        // 黑名单过滤
        if ((string) smtpmail_config('enable_blacklist', '1') === '1') {
            $blocked = app(BlacklistService::class)->blockedEmails();
            $payload['to_addresses'] = array_values(array_filter(
                $payload['to_addresses'],
                fn ($email) => ! in_array(strtolower((string) $email), $blocked, true)
            ));

            if ($payload['to_addresses'] === []) {
                return $this->result(false, 0, SmtpmailRecord::STATUS_CANCELED, '收件人全部在黑名单中，已跳过发送');
            }
        }

        $record = SmtpmailRecord::create($payload);

        return $this->dispatch($record);
    }

    /**
     * 发送测试邮件（强制同步，便于即时看到结果）
     *
     * @param array<string,mixed> $vars
     */
    public function test(string $templateCode, string $to, array $vars = []): array
    {
        return $this->send($templateCode, $to, $vars, [
            'queue' => false,
            'source' => 'template.test',
        ]);
    }

    /**
     * 真正投递一条记录（由 Job / 定时命令 / 同步调用）
     */
    public function deliver(int $recordId): void
    {
        $record = SmtpmailRecord::find($recordId);
        if ($record === null || in_array($record->status, [
            SmtpmailRecord::STATUS_SUCCESS, SmtpmailRecord::STATUS_CANCELED,
        ], true)) {
            return;
        }

        $account = $record->account_id !== null
            ? SmtpmailAccount::find($record->account_id)
            : app(AccountService::class)->defaultAccount();

        if ($account === null || (int) $account->status !== 1) {
            $this->fail($record, '发信账号不存在或已禁用');

            return;
        }

        $record->update(['status' => SmtpmailRecord::STATUS_SENDING]);

        try {
            $mailer = app(MailerFactory::class)->make($account);
            $mailer->to($record->to_addresses)->send(new SmtpmailMailable($record));

            $record->update([
                'status' => SmtpmailRecord::STATUS_SUCCESS,
                'sent_at' => date('Y-m-d H:i:s'),
                'error' => null,
            ]);

            event(new MessageSent($record));
            $this->fireAction('cmspro.smtpmail.sent', [$record->id, $record]);
            $this->syncBatch($record, true);
        } catch (\Throwable $e) {
            $this->fail($record, $e->getMessage());
        }
    }

    /**
     * 手动重试一条失败记录
     */
    public function retry(int $recordId): array
    {
        $record = SmtpmailRecord::findOrFail($recordId);
        if ($record->status === SmtpmailRecord::STATUS_CANCELED) {
            throw new SmtpmailException('已取消的记录不能重试');
        }

        $record->update([
            'status' => SmtpmailRecord::STATUS_PENDING,
            'retry_count' => 0,
            'scheduled_at' => null,
            'error' => null,
        ]);

        $this->deliver($recordId);
        $record->refresh();

        return $this->result(
            $record->status === SmtpmailRecord::STATUS_SUCCESS,
            (int) $record->id,
            (string) $record->status,
            $record->status === SmtpmailRecord::STATUS_SUCCESS ? '重试成功' : (string) $record->error
        );
    }

    /**
     * 解析发信账号（指定编码 > 模板绑定 > 默认账号）
     *
     * @param array<string,mixed> $options
     */
    protected function resolveAccount(array $options): SmtpmailAccount
    {
        $accountService = app(AccountService::class);

        if (! empty($options['account_code'])) {
            $account = $accountService->findByCode((string) $options['account_code']);
        } elseif (! empty($options['account_id'])) {
            $account = SmtpmailAccount::find((int) $options['account_id']);
        } else {
            $account = $accountService->defaultAccount();
        }

        if ($account === null || (int) $account->status !== 1) {
            throw new SmtpmailException('未配置可用的发信账号，请先在「发信账号」中添加并启用账号');
        }

        return $account;
    }

    /**
     * 组装发送记录载荷
     *
     * @param string|array<int,string> $to
     * @param array<string,mixed>      $rendered
     * @param array<string,mixed>      $options
     * @return array<string,mixed>
     */
    protected function buildPayload(
        string|array $to,
        array $rendered,
        array $options,
        SmtpmailAccount $account
    ): array {
        $to = $this->normalizeAddresses($to);
        if ($to === []) {
            throw new SmtpmailException('收件人不能为空');
        }

        $scheduledAt = null;
        if (! empty($options['scheduled_at'])) {
            try {
                $scheduledAt = Carbon::parse((string) $options['scheduled_at'])->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                throw new SmtpmailException('计划发送时间格式不正确');
            }

            if ($scheduledAt <= date('Y-m-d H:i:s')) {
                $scheduledAt = null; // 过去的时间视为立即发送
            }
        }

        $queue = array_key_exists('queue', $options)
            ? (bool) $options['queue']
            : ((string) smtpmail_config('use_queue', '0') === '1');

        $status = $scheduledAt !== null
            ? SmtpmailRecord::STATUS_SCHEDULED
            : ($queue ? SmtpmailRecord::STATUS_QUEUED : SmtpmailRecord::STATUS_PENDING);

        // 限流：超限顺延到下一分钟，由定时命令派发
        if ($scheduledAt === null && ! $this->withinRateLimit($account)) {
            $scheduledAt = date('Y-m-d H:i:s', time() + 60);
            $status = SmtpmailRecord::STATUS_SCHEDULED;
            $queue = false;
        }

        return [
            'template_id' => $options['template_id'] ?? null,
            'template_code' => (string) ($options['template_code'] ?? ''),
            'account_id' => (int) $account->id,
            'batch_id' => $options['batch_id'] ?? null,
            'subject' => (string) $rendered['subject'],
            'body_html' => (string) $rendered['html'],
            'body_text' => (string) $rendered['text'],
            'to_addresses' => $to,
            'cc' => $this->normalizeAddresses($options['cc'] ?? []),
            'bcc' => $this->normalizeAddresses($options['bcc'] ?? []),
            'reply_to' => $this->normalizeAddresses(
                $options['reply_to'] ?? ($account->reply_to !== '' ? [$account->reply_to] : [])
            ),
            'from_address' => (string) ($options['from_address'] ?? $account->from_address),
            'from_name' => (string) ($options['from_name'] ?? $account->from_name),
            'attachments' => (array) ($options['attachments'] ?? []),
            'headers' => (array) ($options['headers'] ?? []),
            'priority' => max(1, min(5, (int) ($options['priority'] ?? 3))),
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'retry_count' => 0,
            'error' => null,
            'source' => (string) ($options['source'] ?? ''),
            'operator' => (string) ($options['operator'] ?? ''),
            'ip' => (string) ($options['ip'] ?? request()?->ip() ?? ''),
        ];
    }

    /**
     * 根据记录状态决定：定时等待 / 入队 / 同步发送
     */
    protected function dispatch(SmtpmailRecord $record): array
    {
        if ($record->status === SmtpmailRecord::STATUS_SCHEDULED) {
            return $this->result(true, (int) $record->id, $record->status, '已加入定时发送队列');
        }

        if ($record->status === SmtpmailRecord::STATUS_QUEUED) {
            SendMailJob::dispatch((int) $record->id);

            return $this->result(true, (int) $record->id, SmtpmailRecord::STATUS_QUEUED, '已投递到发送队列');
        }

        $this->deliver((int) $record->id);
        $record->refresh();

        return $this->result(
            $record->status === SmtpmailRecord::STATUS_SUCCESS,
            (int) $record->id,
            (string) $record->status,
            $record->status === SmtpmailRecord::STATUS_SUCCESS ? '发送成功' : (string) $record->error
        );
    }

    /**
     * 失败处理：按配置的次数与间隔安排重试，超出后标记 failed
     */
    protected function fail(SmtpmailRecord $record, string $error): void
    {
        $maxRetries = max(0, (int) smtpmail_config('max_retries', 3));
        $delay = max(1, (int) smtpmail_config('retry_delay', 60));
        $retry = (int) $record->retry_count < $maxRetries;

        $record->update([
            'status' => $retry ? SmtpmailRecord::STATUS_PENDING : SmtpmailRecord::STATUS_FAILED,
            'retry_count' => $retry ? (int) $record->retry_count + 1 : (int) $record->retry_count,
            'scheduled_at' => $retry ? date('Y-m-d H:i:s', time() + $delay) : $record->scheduled_at,
            'error' => mb_substr($error, 0, 1000),
        ]);

        event(new MessageFailed($record, $error));
        $this->fireAction('cmspro.smtpmail.failed', [$record->id, $error]);

        if (! $retry) {
            $this->syncBatch($record, false);
        }
    }

    /**
     * 每分钟限流计数（账号单独配置优先于全局）
     */
    protected function withinRateLimit(SmtpmailAccount $account): bool
    {
        $limit = (int) $account->rate_limit > 0
            ? (int) $account->rate_limit
            : (int) smtpmail_config('rate_limit', 60);

        if ($limit <= 0) {
            return true;
        }

        $key = 'smtpmail:rate:' . date('YmdHi') . ':' . $account->id;
        if (! Cache::has($key)) {
            Cache::add($key, 0, 70);
        }

        return Cache::increment($key) <= $limit;
    }

    /**
     * 更新批量任务进度
     */
    protected function syncBatch(SmtpmailRecord $record, bool $success): void
    {
        if ($record->batch_id === null) {
            return;
        }

        $batch = SmtpmailBatch::find($record->batch_id);
        if ($batch === null) {
            return;
        }

        $batch->increment('sent_count');
        $batch->increment($success ? 'success_count' : 'failed_count');
        $batch->refresh();

        if ((int) $batch->sent_count >= (int) $batch->total_count) {
            $batch->update([
                'status' => SmtpmailBatch::STATUS_FINISHED,
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * 触发 Action 钩子（钩子链内部已隔离单回调异常）
     */
    protected function fireAction(string $hook, array $args): void
    {
        try {
            app(HookManager::class)->doAction($hook, ...$args);
        } catch (\Throwable $e) {
            // 钩子异常不阻断发送流程
        }
    }

    /**
     * 规范化并校验邮箱地址列表
     *
     * @param mixed $value
     * @return array<int,string>
     */
    protected function normalizeAddresses(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\s]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $result = [];
        foreach ((array) $value as $address) {
            $address = trim((string) $address);
            if ($address === '') {
                continue;
            }
            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new SmtpmailException('邮箱地址格式不正确：' . $address);
            }
            $result[] = $address;
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array{success:bool,record_id:int,status:string,message:string}
     */
    protected function result(bool $success, int $recordId, string $status, string $message): array
    {
        return [
            'success' => $success,
            'record_id' => $recordId,
            'status' => $status,
            'message' => $message,
        ];
    }
}
