<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Models\SmtpmailBlacklist;
use App\Apps\CmsproSmtpmail\Services\BlacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 退订黑名单
 */
class BlacklistController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.setting';

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.blacklist.index');
    }

    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->layui(
            app(BlacklistService::class)->paginate($request->input('keyword'), $this->pageSize($request->input('per_page'))),
            fn (SmtpmailBlacklist $b) => $b->toSummaryArray()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(BlacklistService::class)->add(
                (string) $request->input('email', ''),
                (string) ($request->input('reason') ?: 'manual')
            )->toSummaryArray(),
            '已加入黑名单'
        ));
    }

    public function destroy($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            app(BlacklistService::class)->remove($id);

            return $this->success(null, '已从黑名单移除');
        });
    }
}
