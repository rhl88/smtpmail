<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;

/**
 * SMTP邮箱 · 模板渲染器
 *
 * 支持两类占位符：
 *   {{code}}   变量值经 HTML 转义（HTML 正文防 XSS）
 *   {{{code}}} 变量值原样输出（用于插入可信的 HTML 片段）
 * 主题与纯文本正文不做 HTML 转义。未提供的变量渲染为空字符串。
 * 内置默认变量：site_name、year、date、datetime。
 */
class TemplateRenderer
{
    /**
     * 按编码渲染模板
     *
     * @return array{subject:string,html:string,text:string,template_id:int,template_code:string,account_id:int|null,variables:array<string,mixed>}
     */
    public function renderByCode(string $code, array $vars = []): array
    {
        $template = SmtpmailTemplate::query()
            ->where('code', trim($code))
            ->where('status', 1)
            ->first();

        if ($template === null) {
            throw new SmtpmailException('邮件模板不存在或已禁用：' . $code);
        }

        return $this->renderTemplate($template, $vars);
    }

    /**
     * 渲染指定模板模型（后台预览用，允许渲染禁用模板）
     *
     * @return array<string,mixed>
     */
    public function renderTemplate(SmtpmailTemplate $template, array $vars = []): array
    {
        $vars = $this->withDefaults($vars);

        return [
            'subject' => $this->replace((string) $template->subject, $vars, false),
            'html' => $this->replace((string) $template->body_html, $vars, true),
            'text' => $this->replace((string) $template->body_text, $vars, false),
            'template_id' => (int) $template->id,
            'template_code' => (string) $template->code,
            'account_id' => $template->account_id !== null ? (int) $template->account_id : null,
            'variables' => $vars,
        ];
    }

    /**
     * 渲染直发正文（不走模板）
     *
     * @return array<string,mixed>
     */
    public function renderRaw(string $subject, string $html, string $text = '', array $vars = []): array
    {
        $vars = $this->withDefaults($vars);

        return [
            'subject' => $this->replace($subject, $vars, false),
            'html' => $this->replace($html, $vars, true),
            'text' => $this->replace($text, $vars, false),
            'template_id' => null,
            'template_code' => '',
            'account_id' => null,
            'variables' => $vars,
        ];
    }

    /**
     * 合并内置默认变量（调用方传入的值优先）
     *
     * @return array<string,mixed>
     */
    protected function withDefaults(array $vars): array
    {
        $defaults = [
            'site_name' => (string) (function_exists('smtpmail_config')
                ? smtpmail_config('site_name', 'CmsPro')
                : 'CmsPro'),
            'year' => date('Y'),
            'date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
        ];

        return array_merge($defaults, $vars);
    }

    /**
     * 替换占位符
     *
     * @param array<string,mixed> $vars
     */
    protected function replace(string $content, array $vars, bool $escapeHtml): string
    {
        if ($content === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}\}|\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/',
            function (array $matches) use ($vars, $escapeHtml): string {
                $raw = isset($matches[1]) && $matches[1] !== '';
                $key = $raw ? $matches[1] : ($matches[2] ?? '');
                $value = (string) ($vars[$key] ?? '');

                return ($escapeHtml && ! $raw) ? e($value) : $value;
            },
            $content
        );
    }
}
