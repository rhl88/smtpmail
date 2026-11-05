<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailBlacklist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * SMTP邮箱 · 黑名单 / 退订服务
 */
class BlacklistService
{
    public function paginate(?string $keyword, int $pageSize = 15): LengthAwarePaginator
    {
        $query = SmtpmailBlacklist::query()->orderByDesc('id');

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where('email', 'like', '%' . $keyword . '%');
        }

        return $query->paginate($pageSize);
    }

    /**
     * 当前生效的屏蔽邮箱列表
     *
     * @return array<int,string>
     */
    public function blockedEmails(): array
    {
        return SmtpmailBlacklist::query()->where('status', 1)->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))->all();
    }

    public function isBlocked(string $email): bool
    {
        return SmtpmailBlacklist::query()
            ->where('email', strtolower(trim($email)))
            ->where('status', 1)
            ->exists();
    }

    public function add(string $email, string $reason = 'manual'): SmtpmailBlacklist
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SmtpmailException('邮箱地址格式不正确');
        }

        return SmtpmailBlacklist::updateOrCreate(
            ['email' => $email],
            ['reason' => $reason, 'status' => 1, 'token' => bin2hex(random_bytes(16))]
        );
    }

    public function remove(int $id): void
    {
        $item = SmtpmailBlacklist::find($id);
        if ($item === null) {
            throw new SmtpmailException('黑名单记录不存在');
        }

        $item->update(['status' => 0]);
    }

    /**
     * 通过退订令牌加入黑名单
     */
    public function unsubscribe(string $token): SmtpmailBlacklist
    {
        $token = trim($token);
        if ($token === '') {
            throw new SmtpmailException('退订链接无效');
        }

        $item = SmtpmailBlacklist::query()->where('token', $token)->first();
        if ($item !== null) {
            $item->update(['status' => 1, 'reason' => 'unsubscribe']);

            return $item;
        }

        // 令牌未预置时（外部系统自行拼接链接），按令牌无法反查邮箱，提示无效
        throw new SmtpmailException('退订链接无效或已过期');
    }

    /**
     * 为邮箱预生成退订令牌（供模板插入 {{unsubscribe_token}} 变量）
     */
    public function tokenFor(string $email): string
    {
        $email = strtolower(trim($email));
        $item = SmtpmailBlacklist::query()->where('email', $email)->first();

        return (string) ($item?->token ?? '');
    }
}
