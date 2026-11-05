<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * SMTP邮箱 · 发送记录服务
 */
class RecordService
{
    /**
     * @return LengthAwarePaginator
     */
    public function paginate(
        ?string $status = null,
        ?string $keyword = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $pageSize = 15
    ): LengthAwarePaginator {
        $query = SmtpmailRecord::query()->ofStatus($status)->keyword($keyword);

        $dateFrom = trim((string) $dateFrom);
        $dateTo = trim((string) $dateTo);
        if ($dateFrom !== '') {
            $query->where('create_time', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== '') {
            $query->where('create_time', '<=', $dateTo . ' 23:59:59');
        }

        return $query->orderByDesc('id')->paginate($pageSize);
    }

    public function find(int $id): SmtpmailRecord
    {
        $record = SmtpmailRecord::find($id);
        if ($record === null) {
            throw new SmtpmailException('发送记录不存在');
        }

        return $record;
    }

    public function cancel(int $id): SmtpmailRecord
    {
        $record = $this->find($id);
        if (in_array($record->status, [
            SmtpmailRecord::STATUS_SUCCESS, SmtpmailRecord::STATUS_CANCELED,
        ], true)) {
            throw new SmtpmailException('已完成或已取消的记录不能取消');
        }

        $record->update(['status' => SmtpmailRecord::STATUS_CANCELED]);

        return $record->fresh();
    }

    public function delete(int $id): void
    {
        $this->find($id)->delete();
    }

    /**
     * 统计看板数据
     *
     * @return array<string,mixed>
     */
    public function stats(int $days = 7): array
    {
        $today = date('Y-m-d');
        $todayCount = SmtpmailRecord::query()->where('create_time', '>=', $today . ' 00:00:00')->count();
        $successCount = SmtpmailRecord::query()->where('status', SmtpmailRecord::STATUS_SUCCESS)->count();
        $failedCount = SmtpmailRecord::query()->where('status', SmtpmailRecord::STATUS_FAILED)->count();
        $total = SmtpmailRecord::query()->count();

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $trend[] = [
                'date' => $day,
                'total' => SmtpmailRecord::query()
                    ->where('create_time', '>=', $day . ' 00:00:00')
                    ->where('create_time', '<=', $day . ' 23:59:59')
                    ->count(),
                'success' => SmtpmailRecord::query()
                    ->where('status', SmtpmailRecord::STATUS_SUCCESS)
                    ->where('create_time', '>=', $day . ' 00:00:00')
                    ->where('create_time', '<=', $day . ' 23:59:59')
                    ->count(),
            ];
        }

        return [
            'total' => $total,
            'today' => $todayCount,
            'success' => $successCount,
            'failed' => $failedCount,
            'pending' => SmtpmailRecord::query()->whereIn('status', [
                SmtpmailRecord::STATUS_PENDING,
                SmtpmailRecord::STATUS_QUEUED,
                SmtpmailRecord::STATUS_SCHEDULED,
            ])->count(),
            'success_rate' => $total > 0 ? round($successCount / $total * 100, 2) : 0.0,
            'trend' => $trend,
        ];
    }

    /**
     * 清理过期日志，返回删除条数
     */
    public function cleanup(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        $deadline = date('Y-m-d H:i:s', time() - $days * 86400);

        return SmtpmailRecord::query()
            ->where('create_time', '<', $deadline)
            ->whereIn('status', [SmtpmailRecord::STATUS_SUCCESS, SmtpmailRecord::STATUS_FAILED, SmtpmailRecord::STATUS_CANCELED])
            ->delete();
    }
}
