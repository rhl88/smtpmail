<?php

namespace App\Apps\CmsproSmtpmail;

use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Services\HookManager;
use Illuminate\Support\Facades\Log;

/**
 * SMTP邮箱 · 钩子注册
 *
 * 第 4 参数必须传 app_id，应用禁用/卸载时框架据此批量摘除钩子。
 * 本应用同时「提供」4 个自定义钩子点（在 MailService 中触发），供其他应用扩展：
 *   - cmspro.smtpmail.before_send  Filter：改写发送载荷
 *   - cmspro.smtpmail.should_send  Filter：返回 false 拦截发送
 *   - cmspro.smtpmail.sent         Action：发送成功
 *   - cmspro.smtpmail.failed       Action：发送失败
 *
 * 并监听框架钩子：
 *   - user.after_register  Action：新用户注册成功，按开关发送欢迎邮件
 */
class Hooks
{
    public const APP_ID = 'cmspro.smtpmail';

    /**
     * 注册钩子（由 manifest.json 的 hooks 字段与 ServiceProvider 双通道调用）
     */
    public function register(HookManager $hooks): void
    {
        // 应用被禁用时清理配置缓存，避免接管开关残留
        $hooks->registerAction('app.disabled', [$this, 'onAppDisabled'], 10, self::APP_ID);

        // 内置监听：发送失败写入系统日志，便于运维排查
        $hooks->registerAction('cmspro.smtpmail.failed', [$this, 'onSendFailed'], 10, self::APP_ID);

        // 框架监听：新用户注册成功后按「欢迎邮件」开关发送 welcome 模板
        $hooks->registerAction('user.after_register', [$this, 'onUserRegistered'], 10, self::APP_ID);
    }

    /**
     * 应用禁用回调
     *
     * @param mixed $app 框架传入的应用对象或标识，内容不依赖
     */
    public function onAppDisabled(mixed $app = null): void
    {
        if (function_exists('smtpmail_forget_config_cache')) {
            smtpmail_forget_config_cache();
        }
    }

    /**
     * 发送失败回调（参数为记录 ID 与错误信息）
     */
    public function onSendFailed(int $recordId = 0, string $error = ''): void
    {
        Log::warning('SMTP邮箱发送失败', [
            'app' => self::APP_ID,
            'record_id' => $recordId,
            'error' => $error,
        ]);
    }

    /**
     * 新用户注册回调：按「欢迎邮件」开关发送 welcome 模板
     *
     * 注册用户未填写邮箱时不发送；模板缺失/禁用、发送异常等一律捕获写日志，
     * 不阻断注册主流程（欢迎邮件失败不应影响注册结果）。
     */
    public function onUserRegistered(mixed $user = null): void
    {
        try {
            if (! $user || ! is_object($user)) {
                return;
            }

            if ((string) smtpmail_config('welcome_mail', '0') !== '1') {
                return;
            }

            $email = trim((string) ($user->email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $nickname = trim((string) ($user->nickname ?? $user->username ?? ''));
            app(MailService::class)->send('welcome', $email, ['nickname' => $nickname], [
                'source' => 'hook',
                'operator' => 'system',
            ]);
        } catch (\Throwable $e) {
            Log::error('SMTP邮箱发送欢迎邮件失败', [
                'app' => self::APP_ID,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
