<?php

namespace App\Apps\CmsproSmtpmail\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * SMTP邮箱 · 发信账号模型
 *
 * password 字段为 Crypt 加密后的密文，读取须经 AccountService::decryptPassword()，
 * 禁止直接输出或用于响应。
 */
class SmtpmailAccount extends BaseModel
{
    public const SCHEME_NONE = 'none';

    public const SCHEME_TLS = 'tls';

    public const SCHEME_SSL = 'ssl';

    protected $table = 'app_cmspro_smtpmail_accounts';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'name', 'code', 'host', 'port', 'scheme', 'username', 'password',
        'from_address', 'from_name', 'reply_to', 'timeout', 'rate_limit',
        'is_default', 'status', 'last_test_at', 'last_test_result',
    ];

    protected $casts = [
        'port' => 'integer',
        'timeout' => 'integer',
        'rate_limit' => 'integer',
        'is_default' => 'integer',
        'status' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
        'last_test_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $hidden = ['password'];

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
                ->orWhere('host', 'like', '%' . $keyword . '%');
        });
    }

    /**
     * @return array<string,string>
     */
    public static function schemeMap(): array
    {
        return [
            self::SCHEME_NONE => '不加密',
            self::SCHEME_TLS => 'TLS / STARTTLS',
            self::SCHEME_SSL => 'SSL（隐式 TLS）',
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
            'code' => (string) $this->code,
            'host' => (string) $this->host,
            'port' => (int) $this->port,
            'scheme' => (string) $this->scheme,
            'username' => (string) $this->username,
            'has_password' => ! empty($this->password),
            'from_address' => (string) $this->from_address,
            'from_name' => (string) $this->from_name,
            'reply_to' => (string) $this->reply_to,
            'timeout' => (int) $this->timeout,
            'rate_limit' => (int) $this->rate_limit,
            'is_default' => (int) $this->is_default,
            'status' => (int) $this->status,
            'last_test_at' => $this->last_test_at?->format('Y-m-d H:i:s') ?? '',
            'last_test_result' => (string) $this->last_test_result,
            'create_time' => $this->create_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
