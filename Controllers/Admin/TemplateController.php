<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use App\Apps\CmsproSmtpmail\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 邮件模板管理（可视化编辑 + 预览 + 发测试信）
 */
class TemplateController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.template';

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.templates.index');
    }

    public function list(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->layui(
            app(TemplateService::class)->paginate(
                $request->input('keyword'),
                $request->input('category'),
                $this->pageSize($request->input('per_page'))
            ),
            fn (SmtpmailTemplate $t) => $t->toSummaryArray()
        ));
    }

    public function options(): JsonResponse
    {
        return $this->run(fn () => $this->success(app(TemplateService::class)->options()));
    }

    public function show($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(app(TemplateService::class)->find($id)->toSummaryArray()));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $template = app(TemplateService::class)->create($request->all());

            return $this->success(['id' => (int) $template->id], '邮件模板已创建');
        });
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request, $id) {
            app(TemplateService::class)->update($id, $request->all());

            return $this->success(null, '邮件模板已保存');
        });
    }

    public function destroy($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            app(TemplateService::class)->delete($id);

            return $this->success(null, '邮件模板已删除');
        });
    }

    public function duplicate($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(TemplateService::class)->duplicate($id)->toSummaryArray(),
            '模板已复制，副本默认禁用'
        ));
    }

    public function toggle($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(TemplateService::class)->toggle($id)->toSummaryArray(),
            '模板状态已切换'
        ));
    }

    /**
     * 预览渲染结果（不发送、不落库）
     */
    public function preview(Request $request, $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(TemplateService::class)->preview($id, (array) $request->input('vars', []))
        ));
    }

    /**
     * 用该模板向指定邮箱发一封测试信（同步）
     */
    public function test(Request $request, $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        $request->validate(['to' => 'required|email'], [], ['to' => '测试收件人']);

        return $this->run(function () use ($request, $id) {
            $template = app(TemplateService::class)->find($id);
            $result = app(\App\Apps\CmsproSmtpmail\Services\MailService::class)->test(
                (string) $template->code,
                (string) $request->input('to'),
                (array) $request->input('vars', [])
            );

            return $this->success($result, $result['message']);
        });
    }
}
