<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * SMTP邮箱 · 发信账号服务
 *
 * 密码使用 Laravel Crypt（与 APP_KEY 绑定）加密存储，读取统一走
 * decryptPassword()，响应中只输出 has_password 标记，不输出密文。
 */
class AccountService
{
    public function paginate(?string $keyword, int $pageSize = 15): LengthAwarePaginator
    {
        return SmtpmailAccount::query()
            ->keyword($keyword)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->paginate($pageSize);
    }

    /**
     * 账号下拉选项
     *
     * @return array<int,array<string,mixed>>
     */
    public function options(): array
    {
        return SmtpmailAccount::query()->enabled()->orderByDesc('is_default')->orderBy('id')
            ->get()
            ->map(fn (SmtpmailAccount $a) => [
                'id' => (int) $a->id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
                'is_default' => (int) $a->is_default,
            ])->all();
    }

    public function find(int $id): SmtpmailAccount
    {
        $account = SmtpmailAccount::find($id);
        if ($account === null) {
            throw new SmtpmailException('发信账号不存在');
        }

        return $account;
    }

    public function findByCode(?string $code): ?SmtpmailAccount
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        return SmtpmailAccount::query()->where('code', $code)->where('status', 1)->first();
    }

    /**
     * 获取默认账号（带短缓存，账号变更时由 forgetCache 清理）
     */
    public function defaultAccount(): ?SmtpmailAccount
    {
        try {
            return Cache::remember('smtpmail:takeover:account', 300, function (): ?SmtpmailAccount {
                return SmtpmailAccount::query()->enabled()->orderByDesc('is_default')->orderBy('id')->first();
            });
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): SmtpmailAccount
    {
        $payload = $this->normalize($data, true);
        $this->assertCodeAvailable($payload['code']);

        if ((int) ($payload['is_default'] ?? 0) === 1) {
            SmtpmailAccount::query()->update(['is_default' => 0]);
        }

        // 首个账号自动设为默认，确保安装后即可发信
        if (SmtpmailAccount::query()->count() === 0) {
            $payload['is_default'] = 1;
        }

        $account = SmtpmailAccount::create($payload);
        $this->forgetCache();

        return $account;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): SmtpmailAccount
    {
        $account = $this->find($id);
        $payload = $this->normalize($data, false);

        if (isset($payload['code'])) {
            $this->assertCodeAvailable($payload['code'], $id);
        }

        if ((int) ($payload['is_default'] ?? 0) === 1) {
            SmtpmailAccount::query()->where('id', '!=', $id)->update(['is_default' => 0]);
        }

        $account->update($payload);
        $this->forgetCache();

        return $account->fresh();
    }

    public function delete(int $id): void
    {
        $account = $this->find($id);
        $wasDefault = (int) $account->is_default === 1;
        $account->delete();

        if ($wasDefault) {
            $next = SmtpmailAccount::query()->enabled()->orderBy('id')->first();
            if ($next !== null) {
                $next->update(['is_default' => 1]);
            }
        }

        $this->forgetCache();
    }

    public function setDefault(int $id): void
    {
        $account = $this->find($id);

        DB::transaction(function () use ($account): void {
            SmtpmailAccount::query()->update(['is_default' => 0]);
            $account->update(['is_default' => 1, 'status' => 1]);
        });

        $this->forgetCache();
    }

    /**
     * 解密账号密码（密文损坏或 APP_KEY 变更时返回空串）
     */
    public function decryptPassword(SmtpmailAccount $account): string
    {
        $encrypted = (string) $account->password;
        if ($encrypted === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * 发送测试邮件（同步，不进队列），并记录测试结果
     */
    public function test(int $id, string $to): array
    {
        $account = $this->find($id);
        $to = trim($to);

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new SmtpmailException('测试收件人邮箱格式不正确');
        }

        $result = app(MailService::class)->sendRaw($to, [
            'subject' => '【SMTP邮箱】发信测试',
            'html' => '<div style="font-family:Arial,sans-serif;padding:24px">'
                . '<h3>SMTP 发信测试</h3><p>这是一封来自「' . e($account->name) . '」的测试邮件，收到即表示账号配置正确。</p>'
                . '<p style="color:#999">发送时间：{{datetime}}</p></div>',
            'text' => 'SMTP 发信测试：收到即表示账号配置正确。发送时间：{{datetime}}',
            'account_code' => $account->code,
            'queue' => false,
            'source' => 'account.test',
        ]);

        $account->update([
            'last_test_at' => date('Y-m-d H:i:s'),
            'last_test_result' => ($result['success'] ? '成功' : '失败') . '：' . ($result['message'] ?? ''),
        ]);

        return $result;
    }

    public function forgetCache(): void
    {
        Cache::forget('smtpmail:takeover:account');
    }

    /**
     * 整理并校验写入数据
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function normalize(array $data, bool $creating): array
    {
        $fields = [
            'name', 'code', 'host', 'scheme', 'username', 'from_address',
            'from_name', 'reply_to', 'timeout', 'rate_limit', 'is_default', 'status', 'port',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        if (isset($payload['code'])) {
            $payload['code'] = (string) $payload['code'];
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $payload['code'])) {
                throw new SmtpmailException('账号编码仅允许小写字母、数字与下划线，且必须以字母开头');
            }
        }

        if (isset($payload['scheme']) && ! in_array($payload['scheme'], [
            SmtpmailAccount::SCHEME_NONE, SmtpmailAccount::SCHEME_TLS, SmtpmailAccount::SCHEME_SSL,
        ], true)) {
            throw new SmtpmailException('加密方式仅支持 none / tls / ssl');
        }

        foreach (['port', 'timeout', 'rate_limit', 'is_default', 'status'] as $intField) {
            if (isset($payload[$intField])) {
                $payload[$intField] = (int) $payload[$intField];
            }
        }

        // 密码留空表示「不修改」；新建时留空则不使用认证
        $password = trim((string) ($data['password'] ?? ''));
        if ($password !== '') {
            $payload['password'] = Crypt::encryptString($password);
        } elseif ($creating) {
            $payload['password'] = null;
        }

        if ($creating) {
            foreach (['name', 'code', 'host', 'from_address'] as $required) {
                if (empty($payload[$required])) {
                    throw new SmtpmailException('请完整填写账号名称、编码、SMTP 主机与发件人地址');
                }
            }

            if (! filter_var($payload['from_address'], FILTER_VALIDATE_EMAIL)) {
                throw new SmtpmailException('发件人邮箱格式不正确');
            }

            $payload += [
                'port' => 587,
                'scheme' => SmtpmailAccount::SCHEME_TLS,
                'timeout' => 30,
                'rate_limit' => 0,
                'is_default' => 0,
                'status' => 1,
                'from_name' => '',
                'reply_to' => '',
                'username' => '',
            ];
        } elseif (isset($payload['from_address']) && ! filter_var($payload['from_address'], FILTER_VALIDATE_EMAIL)) {
            throw new SmtpmailException('发件人邮箱格式不正确');
        }

        return $payload;
    }

    protected function assertCodeAvailable(string $code, ?int $excludeId = null): void
    {
        $query = SmtpmailAccount::query()->where('code', $code);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new SmtpmailException('账号编码已存在：' . $code);
        }
    }
}
