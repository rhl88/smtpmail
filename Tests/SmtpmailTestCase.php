<?php

namespace App\Apps\CmsproSmtpmail\Tests;

use App\Apps\CmsproSmtpmail\Models\SmtpmailAccount;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use App\Apps\CmsproSmtpmail\ServiceProvider;
use App\Apps\CmsproSmtpmail\Services\MailerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * SMTP邮箱 · 应用测试基类
 *
 * 使用 RefreshDatabase + phpunit.xml 锁定的 sqlite :memory:，绝不连接真实库。
 * 迁移中对 MySQL 专用 ALTER TABLE COMMENT 已做驱动判断，可直接在 SQLite 跑。
 * 通过 ArrayTransport 注入假传输层，测试全程不连接真实 SMTP 服务器。
 */
abstract class SmtpmailTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'app/Apps/CmsproSmtpmail/Migrations',
            '--force' => true,
        ])->run();

        // 关闭重试/队列/限流/接管，保证用例行为确定
        config([
            'apps.cmspro.smtpmail.max_retries' => '0',
            'apps.cmspro.smtpmail.retry_delay' => '1',
            'apps.cmspro.smtpmail.use_queue' => '0',
            'apps.cmspro.smtpmail.rate_limit' => '0',
            'apps.cmspro.smtpmail.enable_blacklist' => '1',
            'apps.cmspro.smtpmail.takeover_system_mail' => '0',
            'apps.cmspro.smtpmail.api_enabled' => '0',
            'apps.cmspro.smtpmail.api_token' => '',
        ]);
        Cache::flush();

        $this->app->register(ServiceProvider::class);
        $this->app->make('router')->getRoutes()->refreshNameLookups();

        app(MailerFactory::class)->usingTransport(new ArrayTransport());
    }

    /**
     * @param array<string,mixed> $attrs
     */
    protected function makeAccount(array $attrs = []): SmtpmailAccount
    {
        return SmtpmailAccount::create(array_merge([
            'name' => '测试发信账号',
            'code' => 'test_account',
            'host' => 'smtp.example.com',
            'port' => 587,
            'scheme' => SmtpmailAccount::SCHEME_TLS,
            'username' => 'user@example.com',
            'password' => Crypt::encryptString('secret'),
            'from_address' => 'noreply@example.com',
            'from_name' => '测试发件人',
            'reply_to' => '',
            'timeout' => 30,
            'rate_limit' => 0,
            'is_default' => 1,
            'status' => 1,
        ], $attrs));
    }

    /**
     * @param array<string,mixed> $attrs
     */
    protected function makeTemplate(array $attrs = []): SmtpmailTemplate
    {
        return SmtpmailTemplate::create(array_merge([
            'code' => 'test_tpl',
            'name' => '测试模板',
            'category' => 'custom',
            'subject' => '【{{site_name}}】{{title}}',
            'body_html' => '<p>您好 {{name}}</p><div>{{{raw}}}</div>',
            'body_text' => '您好 {{name}}',
            'variables' => [['name' => 'name', 'label' => '姓名']],
            'account_id' => null,
            'status' => 1,
            'is_system' => 0,
            'remark' => '',
        ], $attrs));
    }
}
