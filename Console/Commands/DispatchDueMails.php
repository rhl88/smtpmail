<?php

namespace App\Apps\CmsproSmtpmail\Console\Commands;

use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Apps\CmsproSmtpmail\Services\MailService;
use Illuminate\Console\Command;

/**
 * SMTP邮箱 · 派发到期邮件
 *
 * 由应用 ServiceProvider 通过 Schedule 每分钟注册，也可手工执行：
 *   php artisan smtpmail:dispatch-due
 *
 * 处理范围：
 * 1. pending / scheduled 且 scheduled_at 为空或已到期（含失败重试）
 * 2. queued 超过 10 分钟仍无结果的记录（队列任务丢失时兜底回收）
 */
class DispatchDueMails extends Command
{
    protected $signature = 'smtpmail:dispatch-due {--limit=100 : 单次最多处理条数}';

    protected $description = '派发 SMTP邮箱 到期与待重试的邮件';

    public function handle(MailService $mailService): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $now = date('Y-m-d H:i:s');
        $stale = date('Y-m-d H:i:s', time() - 600);

        $records = SmtpmailRecord::query()
            ->where(function ($outer) use ($now, $stale) {
                $outer->where(function ($due) use ($now) {
                    $due->whereIn('status', [
                        SmtpmailRecord::STATUS_PENDING,
                        SmtpmailRecord::STATUS_SCHEDULED,
                    ])->where(function ($time) use ($now) {
                        $time->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now);
                    });
                })->orWhere(function ($lost) use ($stale) {
                    $lost->where('status', SmtpmailRecord::STATUS_QUEUED)
                        ->where('create_time', '<', $stale);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($records as $record) {
            $mailService->deliver((int) $record->id);
        }

        $this->info('已派发 ' . $records->count() . ' 条邮件');

        return self::SUCCESS;
    }
}
