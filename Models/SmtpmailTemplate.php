<?php

namespace App\Apps\CmsproSmtpmail\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * SMTP邮箱 · 邮件模板模型
 */
class SmtpmailTemplate extends BaseModel
{
    protected $table = 'app_cmspro_smtpmail_templates';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'code', 'name', 'category', 'subject', 'body_html', 'body_text',
        'variables', 'account_id', 'status', 'is_system', 'remark',
    ];

    protected $casts = [
        'variables' => 'array',
        'account_id' => 'integer',
        'status' => 'integer',
        'is_system' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($keyword) {
            $sub->where('name', 'like', '%' . $keyword . '%')
                ->orWhere('code', 'like', '%' . $keyword . '%')
                ->orWhere('subject', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * 提取主题与两类正文中出现的 {{变量}} 名（按首次出现顺序去重）
     *
     * @return array<int,string>
     */
    public function extractVariables(): array
    {
        $content = (string) $this->subject . (string) $this->body_html . (string) $this->body_text;
        if (! preg_match_all('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', $content, $matches)) {
            return [];
        }

        $result = [];
        foreach ($matches[1] as $name) {
            if (! in_array($name, $result, true)) {
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => (int) $this->id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'category' => (string) $this->category,
            'subject' => (string) $this->subject,
            'body_html' => (string) $this->body_html,
            'body_text' => (string) $this->body_text,
            'variables' => $this->variables ?? [],
            'extracted_variables' => $this->extractVariables(),
            'account_id' => $this->account_id !== null ? (int) $this->account_id : null,
            'status' => (int) $this->status,
            'is_system' => (int) $this->is_system,
            'remark' => (string) $this->remark,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
            'update_time' => $this->update_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
