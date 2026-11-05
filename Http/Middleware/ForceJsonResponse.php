<?php

namespace App\Apps\CmsproSmtpmail\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * SMTP邮箱 · 强制 JSON 响应中间件
 *
 * 后台数据接口统一显式设置 Accept: application/json，保证：
 * 1. validate() 校验失败返回 422 JSON，而不是被 ValidationException 转成 302 重定向；
 * 2. 前端无论以 $.ajax 还是普通 post 形态调用，拿到的都是可 JSON 解析的响应体。
 *
 * 依据：CMSPRO-v5 应用开发常见问题 第 1 章「前端 fetch 调用应用 API 返回 302 重定向」。
 */
class ForceJsonResponse
{
    /**
     * @param \Closure(\Illuminate\Http\Request): mixed $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
