<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Models\SmtpmailBatch;
use App\Apps\CmsproSmtpmail\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 批量群发
 */
class BatchController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.batch';

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.batches.index');
    }

    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->layui(
            app(BatchService::class)->paginate(
                $request->input('status'),
                $request->input('keyword'),
                $this->pageSize($request->input('per_page'))
            ),
            fn (SmtpmailBatch $b) => $b->toSummaryArray()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $recipients = $request->input('recipients', []);
            if (is_string($recipients)) {
                $recipients = preg_split('/[\r\n,;]+/', $recipients, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }

            $vars = $request->input('vars', []);
            if (is_string($vars)) {
                $decoded = json_decode($vars, true);
                $vars = is_array($decoded) ? $decoded : [];
            }

            $batch = app(BatchService::class)->create(
                (string) $request->input('name', ''),
                (string) $request->input('template_code', ''),
                (array) $recipients,
                (array) $vars,
                [
                    'scheduled_at' => $request->input('scheduled_at'),
                    'account_code' => $request->input('account_code'),
                    'remark' => (string) $request->input('remark', ''),
                ]
            );

            return $this->success($batch->toSummaryArray(), '批量任务已创建并开始投递');
        });
    }

    public function cancel($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(BatchService::class)->cancel($id)->toSummaryArray(),
            '批量任务已取消'
        ));
    }
}
