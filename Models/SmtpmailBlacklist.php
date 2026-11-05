<?php

namespace App\Apps\CmsproSmtpmail\Models;

use App\Models\BaseModel;

/**
 * SMTP邮箱 · 退订黑名单模型
 */
class SmtpmailBlacklist extends BaseModel
{
    protected $table = 'app_cmspro_smtpmail_blacklist';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = ['email', 'reason', 'token', 'status'];

    protected $casts = [
        'status' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return array<string,mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => (int) $this->id,
            'email' => (string) $this->email,
            'reason' => (string) $this->reason,
            'token' => (string) $this->token,
            'status' => (int) $this->status,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
