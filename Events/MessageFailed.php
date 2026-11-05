<?php

namespace App\Apps\CmsproSmtpmail\Events;

use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SMTP邮箱 · 发送失败事件（含每次失败，含可重试的失败）
 */
class MessageFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public SmtpmailRecord $record, public string $error)
    {
    }
}
