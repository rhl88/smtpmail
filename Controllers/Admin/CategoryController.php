<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplateCategory;
use App\Apps\CmsproSmtpmail\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 模板分类管理（模板列表页「分类管理」弹窗 + 模板表单分类下拉）
 */
class CategoryController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.template';

    public function list(): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(app(CategoryService::class)->list()));
    }

    public function options(): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(app(CategoryService::class)->options()));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(CategoryService::class)->add((string) $request->input('name', ''))->toSummaryArray(),
            '分类已创建'
        ));
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(fn () => $this->success(
            app(CategoryService::class)->rename((int) $id, (string) $request->input('name', ''))->toSummaryArray(),
            '分类已更新'
        ));
    }

    public function destroy($id): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($id) {
            app(CategoryService::class)->remove((int) $id);

            return $this->success(null, '分类已删除');
        });
    }
}
