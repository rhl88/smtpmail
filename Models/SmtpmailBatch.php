<?php

namespace App\Apps\CmsproSmtpmail\Models;

use App\Models\BaseModel;

/**
 * SMTP邮箱 · 批量群发任务模型
 */
class SmtpmailBatch extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELED = 'canceled';

    protected $table = 'app_cmspro_smtpmail_batches';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'name', 'template_code', 'total_count', 'sent_count', 'success_count',
        'failed_count', 'status', 'scheduled_at', 'finished_at', 'remark',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'sent_count' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'finished_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return array<string,string>
     */
    public static function statusMap(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_RUNNING => '进行中',
            self::STATUS_FINISHED => '已完成',
            self::STATUS_CANCELED => '已取消',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'template_code' => (string) $this->template_code,
            'total_count' => (int) $this->total_count,
            'sent_count' => (int) $this->sent_count,
            'success_count' => (int) $this->success_count,
            'failed_count' => (int) $this->failed_count,
            'status' => (string) $this->status,
            'status_text' => self::statusMap()[$this->status] ?? $this->status,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s') ?? '',
            'finished_at' => $this->finished_at?->format('Y-m-d H:i:s') ?? '',
            'remark' => (string) $this->remark,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
