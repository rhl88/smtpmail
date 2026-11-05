<?php

namespace App\Apps\CmsproSmtpmail\Console\Commands;

use App\Apps\CmsproSmtpmail\Services\RecordService;
use Illuminate\Console\Command;

/**
 * SMTP邮箱 · 清理过期发送日志
 *
 * 由 ServiceProvider 通过 Schedule 每日 03:20 注册，也可手工执行：
 *   php artisan smtpmail:clean-records
 */
class CleanRecords extends Command
{
    protected $signature = 'smtpmail:clean-records';

    protected $description = '按配置保留天数清理 SMTP邮箱 发送日志';

    public function handle(RecordService $recordService): int
    {
        $days = (int) (function_exists('smtpmail_config')
            ? smtpmail_config('log_retention_days', 90)
            : 90);

        $count = $recordService->cleanup($days);
        $this->info("已清理 {$count} 条过期发送记录（保留 {$days} 天）");

        return self::SUCCESS;
    }
}
