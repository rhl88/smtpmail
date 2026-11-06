<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Services\TemplateRenderer;
use App\Apps\CmsproSmtpmail\Services\TemplateService;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * 发送邮件（模板发送 + 直发、预览、定时、队列）
 */
class SendController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.send';

    /** 禁止作为邮件附件发送的服务端脚本后缀 */
    private const BLOCKED_EXTENSIONS = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'jspx', 'htaccess', 'htpasswd'];

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
            'builtin_variables' => TemplateService::builtinVariables(),
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
            if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
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
     * 解析附件管理选择的附件（发送页「从附件管理选择」）
     *
     * 附件管理 picker 返回 /uploads/... 形式的访问地址，本接口将其还原为
     * public_uploads 磁盘上的服务器绝对路径，供发送时 attachFromPath() 使用；
     * 同时复用脚本后缀黑名单校验，优先返回附件管理记录中的原始文件名。
     */
    public function resolveAttachments(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $request->validate(['urls' => ['required', 'array', 'min:1', 'max:20']], [], ['urls' => '附件地址']);

            $disk = Storage::disk('public_uploads');
            $resolved = [];

            foreach ((array) $request->input('urls') as $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }

                // 兼容相对地址与带域名的完整地址：统一取 path 部分
                $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
                $rel = (string) preg_replace('#^/uploads/#i', '', $path);
                if ($rel === '' || $rel === $path) {
                    return $this->fail('不是有效的附件管理地址：' . basename($path));
                }

                if (! $disk->exists($rel) || ! is_file($disk->path($rel))) {
                    return $this->fail('附件不存在或已被删除：' . basename($rel));
                }

                $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
                if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                    return $this->fail('禁止发送服务端脚本文件：' . $ext);
                }

                // 优先取附件管理记录中的原始文件名
                $name = basename($rel);
                $record = Attachment::query()->where('url', $path)->first();
                if ($record !== null && (string) $record->name !== '') {
                    $name = (string) $record->name;
                }

                $abs = $disk->path($rel);
                $resolved[] = [
                    'path' => $abs,
                    'name' => $name,
                    'size' => (int) filesize($abs),
                ];
            }

            if (empty($resolved)) {
                return $this->fail('未选择任何附件');
            }

            return $this->success($resolved, '附件解析成功');
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
