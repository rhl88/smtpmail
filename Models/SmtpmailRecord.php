<?php

namespace App\Apps\CmsproSmtpmail\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * SMTP邮箱 · 发送记录模型
 *
 * 状态机：pending → queued/scheduled → sending → success / failed → (重试) → failed
 */
class SmtpmailRecord extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    protected $table = 'app_cmspro_smtpmail_records';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'template_id', 'template_code', 'account_id', 'batch_id',
        'subject', 'body_html', 'body_text',
        'to_addresses', 'cc', 'bcc', 'reply_to', 'from_address', 'from_name',
        'attachments', 'headers', 'priority',
        'status', 'scheduled_at', 'sent_at', 'retry_count', 'error',
        'source', 'operator', 'ip',
    ];

    protected $casts = [
        'to_addresses' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'reply_to' => 'array',
        'attachments' => 'array',
        'headers' => 'array',
        'template_id' => 'integer',
        'account_id' => 'integer',
        'batch_id' => 'integer',
        'priority' => 'integer',
        'retry_count' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'sent_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        $status = trim((string) $status);

        return $status === '' ? $query : $query->where('status', $status);
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($keyword) {
            $sub->where('subject', 'like', '%' . $keyword . '%')
                ->orWhere('to_addresses', 'like', '%' . $keyword . '%')
                ->orWhere('template_code', 'like', '%' . $keyword . '%')
                ->orWhere('error', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * @return array<string,string>
     */
    public static function statusMap(): array
    {
        return [
            self::STATUS_PENDING => '待发送',
            self::STATUS_QUEUED => '队列中',
            self::STATUS_SCHEDULED => '定时待发',
            self::STATUS_SENDING => '发送中',
            self::STATUS_SUCCESS => '发送成功',
            self::STATUS_FAILED => '发送失败',
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
            'template_code' => (string) $this->template_code,
            'subject' => (string) $this->subject,
            'to_addresses' => $this->flattenEmailList($this->to_addresses),
            'from_address' => (string) $this->from_address,
            'status' => (string) $this->status,
            'status_text' => self::statusMap()[$this->status] ?? $this->status,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s') ?? '',
            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s') ?? '',
            'retry_count' => (int) $this->retry_count,
            'error' => (string) $this->error,
            'source' => (string) $this->source,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toDetailArray(): array
    {
        return array_merge($this->toSummaryArray(), [
            'body_html' => (string) $this->body_html,
            'body_text' => (string) $this->body_text,
            'cc' => $this->flattenEmailList($this->cc),
            'bcc' => $this->flattenEmailList($this->bcc),
            'reply_to' => $this->flattenEmailList($this->reply_to),
            'from_name' => (string) $this->from_name,
            'attachments' => $this->attachments ?? [],
            'headers' => $this->headers ?? [],
            'priority' => (int) $this->priority,
            'account_id' => $this->account_id !== null ? (int) $this->account_id : null,
            'batch_id' => $this->batch_id !== null ? (int) $this->batch_id : null,
            'operator' => (string) $this->operator,
        ]);
    }

    /**
     * 归一化邮箱列表：兼容历史双重 JSON 编码产生的嵌套数组，统一输出一层字符串数组
     *
     * @return list<string>
     */
    protected function flattenEmailList(mixed $list): array
    {
        $flat = [];
        foreach ((array) ($list ?? []) as $item) {
            if (is_array($item)) {
                foreach ($item as $sub) {
                    if (is_scalar($sub)) {
                        $flat[] = (string) $sub;
                    }
                }
            } elseif (is_scalar($item)) {
                $flat[] = (string) $item;
            }
        }

        return array_values(array_unique($flat));
    }
}
