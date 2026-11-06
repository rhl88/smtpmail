<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Services\TemplateRenderer;
use App\Apps\CmsproSmtpmail\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 发送邮件（模板发送 + 直发、预览、定时、队列）
 */
class SendController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.send';

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.send.index');
    }

    /**
     * 页面初始化数据：模板、账号、内置变量
     */
    public function options(): JsonResponse
    {
        return $this->run(fn () => $this->success([
            'templates' => app(TemplateService::class)->options(),
            'accounts' => app(AccountService::class)->options(),
            'builtin_variables' => ['site_name', 'year', 'date', 'datetime'],
        ]));
    }

    /**
     * 预览（不发送、不落库）
     */
    public function preview(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $vars = (array) $request->input('vars', []);
            $templateCode = trim((string) $request->input('template_code', ''));

            if ($templateCode !== '') {
                return $this->success(app(TemplateRenderer::class)->renderByCode($templateCode, $vars));
            }

            return $this->success(app(TemplateRenderer::class)->renderRaw(
                (string) $request->input('subject', ''),
                (string) $request->input('html', ''),
                (string) $request->input('text', ''),
                $vars
            ));
        });
    }

    /**
     * 附件上传（发送页「上传附件」Tab）
     *
     * 存储到 storage/app/smtpmail/attachments/<Ymd>/，返回绝对路径供
     * attachFromPath() 读取；文件名随机化避免覆盖，展示名保留原始文件名。
     */
    public function upload(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $request->validate(['file' => ['required', 'file', 'max:10240']], [], ['file' => '附件']);

            $file = $request->file('file');
            $ext = strtolower((string) ($file->getClientOriginalExtension() ?: ''));
            $blocked = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'jspx', 'htaccess', 'htpasswd'];
            if (in_array($ext, $blocked, true)) {
                return $this->fail('禁止上传服务端脚本文件：' . $ext);
            }

            // 直接落盘到应用附件目录，不依赖 filesystems 磁盘 root 约定
            $dir = storage_path('app/smtpmail/attachments/' . date('Ymd'));
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $filename = date('His') . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
            $moved = $file->move($dir, $filename);
            $path = (string) $moved->getRealPath();

            return $this->success([
                'path' => $path,
                'name' => (string) $file->getClientOriginalName(),
                'size' => is_file($path) ? (int) filesize($path) : 0,
            ], '附件上传成功');
        });
    }

    /**
     * 执行发送
     */
    public function send(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $to = $request->input('to');
            if (empty($to)) {
                return $this->fail('收件人不能为空');
            }

            $options = [
                'cc' => $request->input('cc', []),
                'bcc' => $request->input('bcc', []),
                'reply_to' => $request->input('reply_to', []),
                'attachments' => $request->input('attachments', []),
                'headers' => $request->input('headers', []),
                'priority' => $request->input('priority', 3),
                'source' => 'admin',
                'operator' => (string) (auth('admin')->user()?->name ?? ''),
            ];

            // 仅在显式填写时覆盖，避免空字符串把账号默认发件人冲掉
            foreach (['account_code', 'from_address', 'from_name', 'scheduled_at'] as $optional) {
                $value = $request->input($optional);
                if ($value !== null && $value !== '') {
                    $options[$optional] = $value;
                }
            }

            if ($request->has('queue')) {
                $options['queue'] = (bool) $request->input('queue');
            }

            // 直发内容中的 {{变量}} 由 sendRaw 渲染
            $options['vars'] = (array) $request->input('vars', []);
            $options['subject'] = (string) $request->input('subject', '');
            $options['html'] = (string) $request->input('html', '');
            $options['text'] = (string) $request->input('text', '');

            $mail = app(MailService::class);
            $templateCode = trim((string) $request->input('template_code', ''));

            $result = $templateCode !== ''
                ? $mail->send($templateCode, $to, $options['vars'], $options)
                : $mail->sendRaw($to, $options);

            return $this->success($result, $result['message']);
        });
    }
}
