<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * SMTP邮箱 · 批量群发服务
 *
 * 每个收件人生成独立的发送记录（便于单独重试与统计），共享同一个批次号。
 */
class BatchService
{
    public function paginate(?string $status, ?string $keyword, int $pageSize = 15): LengthAwarePaginator
    {
        $query = SmtpmailBatch::query()->orderByDesc('id');

        $status = trim((string) $status);
        if ($status !== '') {
            $query->where('status', $status);
        }
        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('template_code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->paginate($pageSize);
    }

    public function find(int $id): SmtpmailBatch
    {
        $batch = SmtpmailBatch::find($id);
        if ($batch === null) {
            throw new SmtpmailException('批量任务不存在');
        }

        return $batch;
    }

    /**
     * 创建并执行批量任务
     *
     * @param array<int,string>   $recipients 收件人列表
     * @param array<string,mixed> $vars       模板变量（所有收件人共享）
     * @param array<string,mixed> $options    发送选项，可含 scheduled_at / account_code / queue
     */
    public function create(
        string $name,
        string $templateCode,
        array $recipients,
        array $vars = [],
        array $options = []
    ): SmtpmailBatch {
        $template = app(TemplateService::class)->findByCode($templateCode);
        if ($template === null) {
            throw new SmtpmailException('邮件模板不存在或已禁用：' . $templateCode);
        }

        $emails = [];
        foreach ($recipients as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new SmtpmailException('收件人邮箱格式不正确：' . $email);
            }
            $emails[] = $email;
        }
        $emails = array_values(array_unique($emails));

        if ($emails === []) {
            throw new SmtpmailException('收件人列表不能为空');
        }

        if ((string) smtpmail_config('enable_blacklist', '1') === '1') {
            $blocked = app(BlacklistService::class)->blockedEmails();
            $emails = array_values(array_diff($emails, $blocked));
            if ($emails === []) {
                throw new SmtpmailException('收件人全部在黑名单中');
            }
        }

        $batch = DB::transaction(function () use ($name, $templateCode, $emails, $options): SmtpmailBatch {
            return SmtpmailBatch::create([
                'name' => trim($name) !== '' ? trim($name) : '批量任务 ' . date('Y-m-d H:i:s'),
                'template_code' => $templateCode,
                'total_count' => count($emails),
                'status' => SmtpmailBatch::STATUS_RUNNING,
                'scheduled_at' => $options['scheduled_at'] ?? null,
                'remark' => (string) ($options['remark'] ?? ''),
            ]);
        });

        $mail = app(MailService::class);
        foreach ($emails as $email) {
            $mail->send($templateCode, $email, $vars, array_merge($options, [
                'batch_id' => $batch->id,
                'source' => 'batch',
            ]));
        }

        return $batch->fresh();
    }

    public function cancel(int $id): SmtpmailBatch
    {
        $batch = $this->find($id);
        if ($batch->status === SmtpmailBatch::STATUS_FINISHED) {
            throw new SmtpmailException('已完成的批量任务不能取消');
        }

        $batch->update([
            'status' => SmtpmailBatch::STATUS_CANCELED,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);

        return $batch->fresh();
    }
}
