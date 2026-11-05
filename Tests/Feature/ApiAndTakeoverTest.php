<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Listeners\ApplySystemMailTakeover;
use App\Apps\CmsproSmtpmail\Services\TakeoverContext;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mime\Email;

/**
 * HTTP 接口与系统邮件接管测试
 */
class ApiAndTakeoverTest extends SmtpmailTestCase
{
    public function test_接口未开启时拒绝调用(): void
    {
        $response = $this->postJson('/api/smtpmail/send', [
            'template_code' => 'test_tpl',
            'to' => 'to@example.com',
        ]);

        $response->assertOk()->assertJsonPath('code', 40301);
    }

    public function test_令牌无效时拒绝调用(): void
    {
        config([
            'apps.cmspro.smtpmail.api_enabled' => '1',
            'apps.cmspro.smtpmail.api_token' => 'correct-token',
        ]);
        Cache::flush();

        $this->postJson('/api/smtpmail/send', ['to' => 'to@example.com'])
            ->assertOk()->assertJsonPath('code', 40101);

        $this->withHeader('X-Smtpmail-Token', 'wrong-token')
            ->getJson('/api/smtpmail/status')
            ->assertOk()->assertJsonPath('code', 40101);
    }

    public function test_携带正确令牌可按模板发信(): void
    {
        config([
            'apps.cmspro.smtpmail.api_enabled' => '1',
            'apps.cmspro.smtpmail.api_token' => 'correct-token',
        ]);
        Cache::flush();

        $this->makeAccount();
        $this->makeTemplate();

        $response = $this->withHeader('X-Smtpmail-Token', 'correct-token')
            ->postJson('/api/smtpmail/send', [
                'template_code' => 'test_tpl',
                'to' => 'to@example.com',
                'vars' => ['title' => '接口通知', 'name' => '王五'],
            ]);

        $response->assertOk()->assertJsonPath('code', 0);
        $this->assertDatabaseHas('app_cmspro_smtpmail_records', [
            'subject' => '【CmsPro】接口通知',
            'status' => 'success',
        ]);
    }

    public function test_接口可查询模板与状态(): void
    {
        config([
            'apps.cmspro.smtpmail.api_enabled' => '1',
            'apps.cmspro.smtpmail.api_token' => 'correct-token',
        ]);
        Cache::flush();
        $this->makeTemplate();

        $this->withHeader('X-Smtpmail-Token', 'correct-token')
            ->getJson('/api/smtpmail/templates')
            ->assertOk()->assertJsonPath('code', 0)
            ->assertJsonPath('data.0.code', 'test_tpl');

        $this->withHeader('X-Smtpmail-Token', 'correct-token')
            ->getJson('/api/smtpmail/status')
            ->assertOk()->assertJsonPath('data.app_id', 'cmspro.smtpmail');
    }

    public function test_接管开启时监听器改写系统邮件主题与正文(): void
    {
        config(['apps.cmspro.smtpmail.takeover_system_mail' => '1']);
        Cache::flush();

        app(TakeoverContext::class)->setRender([
            'subject' => '接管后的主题',
            'html' => '<p>接管后的正文</p>',
            'text' => '纯文本正文',
        ]);

        $email = new Email();
        $email->subject('系统原主题')->html('<p>系统原正文</p>');
        app(ApplySystemMailTakeover::class)->handle(new MessageSending($email));

        $this->assertSame('接管后的主题', $email->getSubject());
        $this->assertSame('<p>接管后的正文</p>', $email->getHtmlBody());
        $this->assertSame('纯文本正文', $email->getTextBody());
        $this->assertNull(app(TakeoverContext::class)->getRender());
    }

    public function test_接管关闭时监听器不改写系统邮件(): void
    {
        app(TakeoverContext::class)->setRender(['subject' => '不应生效']);

        $email = new Email();
        $email->subject('系统原主题')->html('<p>系统原正文</p>');
        app(ApplySystemMailTakeover::class)->handle(new MessageSending($email));

        $this->assertSame('系统原主题', $email->getSubject());
        $this->assertSame('<p>系统原正文</p>', $email->getHtmlBody());
    }

    public function test_系统邮件覆盖视图使用内置模板渲染(): void
    {
        $this->makeTemplate([
            'code' => 'verify_code',
            'subject' => '验证码 {{code}}',
            'body_html' => '<b>您的验证码：{{code}}</b>',
            'body_text' => '验证码 {{code}}',
            'is_system' => 1,
        ]);
        Cache::flush();

        app('view')->getFinder()->prependLocation(
            base_path('app/Apps/CmsproSmtpmail/Views/Override')
        );

        $html = view('emails.register-code', ['code' => '123456', 'minutes' => 10])->render();

        $this->assertStringContainsString('123456', $html);
        $render = app(TakeoverContext::class)->getRender();
        $this->assertSame('验证码 123456', $render['subject']);
        $this->assertSame('验证码 123456', trim((string) $render['text']));
    }
}
