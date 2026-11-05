<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Models\SmtpmailAccount;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 发信账号管理
 */
class AccountController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.account';

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.accounts.index');
    }

    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->layui(
            app(AccountService::class)->paginate($request->input('keyword'), $this->pageSize($request->input('limit'))),
            fn (SmtpmailAccount $a) => $a->toSummaryArray()
        ));
    }

    public function options(): JsonResponse
    {
        return $this->run(fn () => $this->success(app(AccountService::class)->options()));
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(app(AccountService::class)->find($id)->toSummaryArray()));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $account = app(AccountService::class)->create($request->all());

            return $this->success(['id' => (int) $account->id], '发信账号已创建');
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request, $id) {
            app(AccountService::class)->update($id, $request->all());

            return $this->success(null, '发信账号已保存');
        });
    }

    public function destroy(int $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            app(AccountService::class)->delete($id);

            return $this->success(null, '发信账号已删除');
        });
    }

    public function setDefault(int $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            app(AccountService::class)->setDefault($id);

            return $this->success(null, '已设为默认发信账号');
        });
    }

    /**
     * 发送测试邮件（同步，立即返回结果）
     */
    public function test(Request $request, int $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        $request->validate(['to' => 'required|email'], [], ['to' => '测试收件人']);

        return $this->run(fn () => $this->success(
            app(AccountService::class)->test($id, (string) $request->input('to')),
            '测试邮件已发送'
        ));
    }
}
