<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Api;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Services\RecordService;
use App\Apps\CmsproSmtpmail\Services\TemplateService;
use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SMTP邮箱 · 外部 HTTP 接口
 *
 * 不使用后台登录态：必须同时开启「开启 HTTP 接口」配置并配置令牌，
 * 调用方在 X-Smtpmail-Token 请求头携带令牌。仅在应用设置中显式开启后可用。
 */
class ApiController extends Controller
{
    /**
     * 发送邮件
     *
     * 模板模式：{template_code, to, vars, options?}
     * 直发模式：{subject, html, to, text?, options?}
     */
    public function send(Request $request): JsonResponse
    {
        if (($deny = $this->authorizeRequest($request)) !== null) {
            return $deny;
        }

        $to = $request->input('to');
        if (empty($to)) {
            return $this->error('收件人不能为空');
        }

        $options = (array) $request->input('options', []);
        $options['source'] = (string) ($options['source'] ?? 'api');

        try {
            $mail = app(MailService::class);
            $templateCode = trim((string) $request->input('template_code', ''));

            $result = $templateCode !== ''
                ? $mail->send($templateCode, $to, (array) $request->input('vars', []), $options)
                : $mail->sendRaw($to, array_merge($options, [
                    'subject' => (string) $request->input('subject', ''),
                    'html' => (string) $request->input('html', ''),
                    'text' => (string) $request->input('text', ''),
                ]));

            return $this->ok($result, $result['message']);
        } catch (SmtpmailException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('SMTP邮箱接口发送失败', ['error' => $e->getMessage()]);

            return $this->error('发送失败：' . $e->getMessage(), 50001);
        }
    }

    /**
     * 可用模板列表
     */
    public function templates(Request $request): JsonResponse
    {
        if (($deny = $this->authorizeRequest($request)) !== null) {
            return $deny;
        }

        return $this->ok(app(TemplateService::class)->options());
    }

    /**
     * 发送记录查询
     */
    public function records(Request $request): JsonResponse
    {
        if (($deny = $this->authorizeRequest($request)) !== null) {
            return $deny;
        }

        $pageSize = max(1, min(100, (int) $request->input('limit', 15)));
        $paginator = app(RecordService::class)->paginate(
            $request->input('status'),
            $request->input('keyword'),
            $request->input('date_from'),
            $request->input('date_to'),
            $pageSize
        );

        return $this->ok([
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'data' => collect($paginator->items())->map(
                fn (SmtpmailRecord $r) => $r->toSummaryArray()
            )->values()->toArray(),
        ]);
    }

    /**
     * 应用状态（统计 + 接管开关 + 账号配置情况）
     */
    public function status(Request $request): JsonResponse
    {
        if (($deny = $this->authorizeRequest($request)) !== null) {
            return $deny;
        }

        $account = app(AccountService::class)->defaultAccount();

        return $this->ok([
            'app_id' => 'cmspro.smtpmail',
            'stats' => app(RecordService::class)->stats(1),
            'takeover_enabled' => (string) smtpmail_config('takeover_system_mail', '0') === '1',
            'default_account' => $account?->code ?? '',
        ]);
    }

    /**
     * 令牌校验；通过返回 null，否则返回错误响应
     */
    protected function authorizeRequest(Request $request): ?JsonResponse
    {
        if ((string) smtpmail_config('api_enabled', '0') !== '1') {
            return $this->error('HTTP 接口未开启，请在应用设置中开启', 40301);
        }

        $token = (string) smtpmail_config('api_token', '');
        $provided = (string) $request->header('X-Smtpmail-Token', '');

        if ($token === '' || $provided === '' || ! hash_equals($token, $provided)) {
            return $this->error('接口令牌无效', 40101);
        }

        return null;
    }

    protected function ok(mixed $data = null, string $message = 'ok'): JsonResponse
    {
        return response()->json(['code' => 0, 'message' => $message, 'data' => $data]);
    }

    protected function error(string $message, int $code = 40001): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => null], $code >= 50000 ? 500 : 200);
    }
}
