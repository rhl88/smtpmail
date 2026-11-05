<?php

namespace App\Apps\CmsproSmtpmail\Controllers;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * SMTP邮箱 · 后台控制器基类
 *
 * 统一权限校验、JSON 返回格式与异常处理：
 * - 普通接口：{code:0, message:'...', data:...}；错误 code 非 0
 * - Layui 表格：{code:0, msg:'', count:总数, data:行数组}
 */
abstract class BaseAdminController extends Controller
{
    /**
     * 后台按钮/接口权限校验（super_admin 由 hasPermission 内部放行）
     */
    protected function hasPermission(string $code): bool
    {
        $user = auth('admin')->user();

        return $user !== null && $user->hasPermission($code);
    }

    protected function permissionDenied(string $message = '无权执行该操作'): JsonResponse
    {
        return response()->json(['code' => 40301, 'message' => $message, 'data' => null]);
    }

    /**
     * @param mixed $data
     */
    protected function success(mixed $data = null, string $message = '操作成功'): JsonResponse
    {
        return response()->json(['code' => 0, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $code = 40001): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => null]);
    }

    /**
     * Layui 表格分页响应
     *
     * @param callable $mapper 行转换回调
     */
    protected function layui(LengthAwarePaginator $paginator, callable $mapper): JsonResponse
    {
        $rows = collect($paginator->items())->map($mapper)->values()->toArray();

        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $paginator->total(),
            'data' => $rows,
        ]);
    }

    /**
     * 统一异常包装：业务异常返回友好提示，系统异常记录日志
     */
    protected function run(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (SmtpmailException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('SMTP邮箱后台操作失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fail('操作失败：' . $e->getMessage(), 50001);
        }
    }

    /**
     * 分页大小（限制 1-100）
     */
    protected function pageSize(mixed $value): int
    {
        return max(1, min(100, (int) $value ?: 15));
    }
}
