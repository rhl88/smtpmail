<?php

namespace App\Apps\CmsproSmtpmail\Events;

use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SMTP邮箱 · 发送成功事件
 *
 * 其他应用可通过 Event::listen(MessageSent::class, ...) 弱依赖订阅。
 */
class MessageSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SmtpmailRecord $record)
    {
    }
}
