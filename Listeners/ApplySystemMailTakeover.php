<?php

namespace App\Apps\CmsproSmtpmail\Listeners;

use App\Apps\CmsproSmtpmail\Services\TakeoverContext;
use Illuminate\Mail\Events\MessageSending;

/**
 * SMTP邮箱 · 系统邮件接管监听器
 *
 * 接管开关开启时，系统 Mail::send('emails.register-code' / 'emails.password-reset-code')
 * 渲染的视图已被 Views/Override 替换，本监听器把暂存在上下文中的模板主题与
 * 纯文本正文写回 Symfony 消息，实现「不改框架代码的可视化模板接管」。
 *
 * 应用自身发信不会写入接管上下文，故不受影响。
 */
class ApplySystemMailTakeover
{
    public function handle(MessageSending $event): void
    {
        if (! function_exists('smtpmail_config')
            || (string) smtpmail_config('takeover_system_mail', '0') !== '1') {
            return;
        }

        $render = app(TakeoverContext::class)->consume();
        if ($render === null) {
            return;
        }

        $message = $event->message;

        if (! empty($render['subject'])) {
            $message->subject((string) $render['subject']);
        }
        if (! empty($render['html'])) {
            $message->html((string) $render['html']);
        }
        if (! empty($render['text'])) {
            $message->text((string) $render['text']);
        }
    }
}
