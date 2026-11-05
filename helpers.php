<?php

/**
 * SMTP邮箱 · 应用级辅助函数
 *
 * 由 ServiceProvider::register() 通过 require_once 加载（composer.json 只自动加载
 * 框架级 app/helpers.php）。所有函数用 function_exists 包裹并带 smtpmail_ 前缀，
 * 保证重复加载幂等、不与其他应用冲突。
 *
 * 跨应用调用请优先使用 smtpmail_send()：应用未安装/未启用时返回失败数组而不抛异常。
 */

use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Services\TakeoverContext;
use App\Apps\CmsproSmtpmail\Services\TemplateRenderer;
use App\Models\ConfigItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('smtpmail_config')) {
    /**
     * 读取本应用配置值（优先数据库 config_items，回退 Config/smtpmail.php）
     *
     * @param string $key     配置键名（不含前缀），如 max_retries
     * @param mixed  $default 兜底默认值
     */
    function smtpmail_config(string $key, mixed $default = null): mixed
    {
        return Cache::remember('smtpmail:config:' . $key, 3600, function () use ($key, $default) {
            try {
                $value = ConfigItem::where('code', 'app_cmspro_smtpmail_' . $key)->value('value');
                if ($value !== null && $value !== '') {
                    return $value;
                }
            } catch (\Throwable $e) {
                // 数据库不可用（安装前、迁移中）时静默降级到配置文件
            }

            return config('apps.cmspro.smtpmail.' . $key, $default);
        });
    }
}

if (! function_exists('smtpmail_forget_config_cache')) {
    /**
     * 清除本应用配置缓存（后台设置保存后调用，确保立即生效）
     *
     * @param array<int,string> $keys 配置键名列表
     */
    function smtpmail_forget_config_cache(array $keys = []): void
    {
        $keys = $keys !== [] ? $keys : array_keys((array) config('apps.cmspro.smtpmail', []));

        foreach ($keys as $key) {
            Cache::forget('smtpmail:config:' . $key);
        }

        Cache::forget('smtpmail:takeover:account');
    }
}

if (! function_exists('smtpmail_enabled')) {
    /**
     * 判断本应用是否已安装并启用（跨应用弱依赖检测）
     *
     * 三重防护：ServiceProvider 类是否可加载 → apps 表状态 → 异常兜底。
     */
    function smtpmail_enabled(): bool
    {
        try {
            if (! class_exists(\App\Apps\CmsproSmtpmail\ServiceProvider::class)) {
                return false;
            }

            return DB::table('apps')
                ->where('app_id', 'cmspro.smtpmail')
                ->where('status', 1)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (! function_exists('smtpmail_send')) {
    /**
     * 按模板编码发送邮件（其他应用调用的主入口）
     *
     * @param string              $templateCode 模板编码，如 verify_code
     * @param string|array<int,string> $to      收件人，支持单个地址或地址数组
     * @param array<string,mixed> $vars         模板变量
     * @param array<string,mixed> $options      可选项：cc/bcc/reply_to/attachments/priority/
     *                                         account_code/scheduled_at/queue/source/headers
     * @return array{success:bool,record_id:int,status:string,message:string}
     */
    function smtpmail_send(string $templateCode, string|array $to, array $vars = [], array $options = []): array
    {
        if (! smtpmail_enabled()) {
            return ['success' => false, 'record_id' => 0, 'status' => 'unavailable', 'message' => 'SMTP邮箱应用未安装或未启用'];
        }

        try {
            return app(MailService::class)->send($templateCode, $to, $vars, $options);
        } catch (\Throwable $e) {
            return ['success' => false, 'record_id' => 0, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}

if (! function_exists('smtpmail_send_raw')) {
    /**
     * 不使用模板，直接发送自定义正文
     *
     * @param string              $subject 主题
     * @param string              $html    HTML 正文
     * @param string|array<int,string> $to 收件人
     * @param array<string,mixed> $options 同 smtpmail_send()
     */
    function smtpmail_send_raw(string $subject, string $html, string|array $to, array $options = []): array
    {
        if (! smtpmail_enabled()) {
            return ['success' => false, 'record_id' => 0, 'status' => 'unavailable', 'message' => 'SMTP邮箱应用未安装或未启用'];
        }

        try {
            $options['subject'] = $subject;
            $options['html'] = $html;

            return app(MailService::class)->sendRaw($to, $options);
        } catch (\Throwable $e) {
            return ['success' => false, 'record_id' => 0, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}

if (! function_exists('smtpmail_takeover_context')) {
    /**
     * 获取系统邮件接管上下文（仅应用内部使用）
     */
    function smtpmail_takeover_context(): TakeoverContext
    {
        return app(TakeoverContext::class);
    }
}

if (! function_exists('smtpmail_render_system')) {
    /**
     * 渲染系统邮件模板，并把主题/纯文本正文暂存到接管上下文
     *
     * 供 Views/Override/emails/*.blade.php 调用：系统 Mail::send() 渲染视图时
     * 得到 HTML 正文，随后 MessageSending 监听器据此改写主题与纯文本正文。
     * 渲染异常时返回兜底 HTML，保证邮件内容不为空。
     *
     * @param string               $templateCode 模板编码
     * @param array<string,mixed>  $vars         模板变量
     */
    function smtpmail_render_system(string $templateCode, array $vars): string
    {
        try {
            $rendered = app(TemplateRenderer::class)->renderByCode($templateCode, $vars);
            smtpmail_takeover_context()->setRender($rendered);

            return (string) ($rendered['html'] ?? '');
        } catch (\Throwable $e) {
            $code = (string) ($vars['code'] ?? '');
            $minutes = (string) ($vars['minutes'] ?? '10');

            return '<div style="font-family:Arial,sans-serif;padding:24px">'
                . '<h3>验证码</h3><p>您的验证码是 <strong>' . e($code) . '</strong>，'
                . e($minutes) . ' 分钟内有效。</p></div>';
        }
    }
}
