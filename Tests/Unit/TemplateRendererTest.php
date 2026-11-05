<?php

namespace App\Apps\CmsproSmtpmail\Tests\Unit;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Services\TemplateRenderer;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;

/**
 * 模板渲染单元测试
 */
class TemplateRendererTest extends SmtpmailTestCase
{
    public function test_变量正确渲染且HTML正文转义(): void
    {
        $this->makeTemplate();

        $rendered = app(TemplateRenderer::class)->renderByCode('test_tpl', [
            'title' => '验证码',
            'name' => '<script>alert(1)</script>',
            'raw' => '<b>可信片段</b>',
        ]);

        $this->assertSame('【CmsPro】验证码', $rendered['subject']);
        $this->assertStringContainsString('&lt;script&gt;', $rendered['html']);
        $this->assertStringContainsString('<b>可信片段</b>', $rendered['html']);
        $this->assertSame('您好 <script>alert(1)</script>', $rendered['text']);
        $this->assertSame('test_tpl', $rendered['template_code']);
    }

    public function test_内置默认变量生效(): void
    {
        $this->makeTemplate([
            'subject' => '{{site_name}}',
            'body_html' => '{{year}}-{{date}}',
            'body_text' => '',
        ]);

        $rendered = app(TemplateRenderer::class)->renderByCode('test_tpl');

        $this->assertSame('CmsPro', $rendered['subject']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{2}-\d{2}$/', $rendered['html']);
        $this->assertArrayHasKey('datetime', $rendered['variables']);
    }

    public function test_未提供的变量渲染为空(): void
    {
        $this->makeTemplate();
        $rendered = app(TemplateRenderer::class)->renderByCode('test_tpl');

        $this->assertSame('【CmsPro】', $rendered['subject']);
        $this->assertStringNotContainsString('{{', $rendered['html']);
    }

    public function test_模板不存在时抛出业务异常(): void
    {
        $this->expectException(SmtpmailException::class);
        $this->expectExceptionMessage('邮件模板不存在或已禁用');

        app(TemplateRenderer::class)->renderByCode('not_exists');
    }

    public function test_禁用模板不可用于发送(): void
    {
        $this->makeTemplate(['status' => 0]);

        $this->expectException(SmtpmailException::class);
        app(TemplateRenderer::class)->renderByCode('test_tpl');
    }

    public function test_直发正文同样支持变量渲染(): void
    {
        $rendered = app(TemplateRenderer::class)->renderRaw('您好 {{name}}', '<p>{{name}}</p>', '', ['name' => '张三']);

        $this->assertSame('您好 张三', $rendered['subject']);
        $this->assertSame('<p>张三</p>', $rendered['html']);
    }
}
