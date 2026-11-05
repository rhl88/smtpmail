<?php

namespace App\Apps\CmsproSmtpmail\Jobs;

use App\Apps\CmsproSmtpmail\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SMTP邮箱 · 队列发送任务
 *
 * 任务本身只执行一次（tries=1），失败重试由 MailService::fail() 按应用配置
 * 统一安排，避免队列框架与应用双重重试导致重复投递。
 */
class SendMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $recordId)
    {
    }

    public function handle(MailService $mailService): void
    {
        $mailService->deliver($this->recordId);
    }
}
