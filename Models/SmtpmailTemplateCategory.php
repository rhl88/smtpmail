<?php

namespace App\Apps\CmsproSmtpmail\Models;

use App\Models\BaseModel;

/**
 * SMTP邮箱 · 模板分类模型
 *
 * 名称应用内唯一；删除前需确认无模板正在使用该分类。
 */
class SmtpmailTemplateCategory extends BaseModel
{
    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $table = 'app_cmspro_smtpmail_template_categories';

    protected $fillable = [
        'name', 'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
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
            'name' => (string) $this->name,
            'sort' => (int) $this->sort,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
