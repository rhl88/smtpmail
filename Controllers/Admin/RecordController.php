<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Services\RecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * 发送日志（查询 / 详情 / 重试 / 取消 / 导出 / 清理后删除）
 */
class RecordController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.record';

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.records.index');
    }

    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->layui(
            app(RecordService::class)->paginate(
                $request->input('status'),
                $request->input('keyword'),
                $request->input('dateFrom'),
                $request->input('dateTo'),
                $this->pageSize($request->input('per_page'))
            ),
            fn (SmtpmailRecord $r) => $r->toSummaryArray()
        ));
    }

    public function show($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(app(RecordService::class)->find($id)->toDetailArray()));
    }

    public function retry($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            $result = app(MailService::class)->retry($id);

            return $this->success($result, $result['message']);
        });
    }

    public function cancel($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(RecordService::class)->cancel($id)->toSummaryArray(),
            '记录已取消'
        ));
    }

    public function destroy($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            app(RecordService::class)->delete($id);

            return $this->success(null, '记录已删除');
        });
    }

    /**
     * 导出 CSV（最多 10000 条，Excel 中文需 BOM 前缀）
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        $filters = [
            $request->input('status'),
            $request->input('keyword'),
            $request->input('dateFrom'),
            $request->input('dateTo'),
        ];

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', '主题', '收件人', '状态', '模板', '重试次数', '错误信息', '来源', '创建时间']);

            SmtpmailRecord::query()
                ->ofStatus($filters[0])
                ->keyword($filters[1])
                ->when($filters[2], fn ($q) => $q->where('create_time', '>=', $filters[2] . ' 00:00:00'))
                ->when($filters[3], fn ($q) => $q->where('create_time', '<=', $filters[3] . ' 23:59:59'))
                ->orderByDesc('id')
                ->limit(10000)
                ->each(function (SmtpmailRecord $r) use ($handle): void {
                    fputcsv($handle, [
                        $r->id,
                        $r->subject,
                        implode(';', $r->to_addresses ?? []),
                        SmtpmailRecord::statusMap()[$r->status] ?? $r->status,
                        $r->template_code,
                        $r->retry_count,
                        $r->error,
                        $r->source,
                        $r->create_time?->format('Y-m-d H:i:s') ?? '',
                    ]);
                });

            fclose($handle);
        }, 'smtpmail_records_' . date('YmdHis') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
