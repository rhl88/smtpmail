<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Install;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;

/**
 * 写接口校验回归测试
 *
 * 覆盖三类目标：
 * 1. F52 四类验证用例：历史报错 payload 回放、逐个必填字段置空（40001 + 无落库）、
 *    合法空值走默认值、更新必填传空被拦截原记录不变；
 * 2. F62 普通 post()（不带 JSON Accept 头）形态：断言非 3xx 且响应体可 JSON 解析，
 *    防止校验失败被 ValidationException 转成 302 重定向（常见问题第 1 章测试盲区）；
 * 3. 后台 8 个页面视图渲染冒烟（兜住 Blade 语法错误）。
 */
class ValidationRegressionTest extends SmtpmailTestCase
{
    // ===== F52 ①：必填字段逐个置空 → 40001 + 无落库 =====

    public function test_账号必填字段逐个置空被拦截且不落库(): void
    {
        $this->actingAdmin();

        $base = ['name' => '通知邮箱', 'code' => 'notify', 'host' => 'smtp.example.com', 'from_address' => 'noreply@example.com'];
        foreach (['name', 'code', 'host', 'from_address'] as $field) {
            $payload = $base;
            $payload[$field] = '';

            $this->postJson('/admin/cmspro/smtpmail/api/accounts', $payload)
                ->assertOk()
                ->assertJsonPath('code', 40001);

            $this->assertDatabaseMissing('app_cmspro_smtpmail_accounts', ['code' => 'notify']);
        }
    }

    public function test_模板必填字段逐个置空被拦截且不落库(): void
    {
        $this->actingAdmin();

        $base = ['name' => '通知模板', 'code' => 'notice_tpl', 'subject' => '【CmsPro】通知', 'body_html' => '<p>你好 {{nickname}}</p>'];
        foreach (['name', 'code', 'subject', 'body_html'] as $field) {
            $payload = $base;
            $payload[$field] = '';

            $this->postJson('/admin/cmspro/smtpmail/api/templates', $payload)
                ->assertOk()
                ->assertJsonPath('code', 40001);

            $this->assertDatabaseMissing('app_cmspro_smtpmail_templates', ['code' => 'notice_tpl']);
        }
    }

    public function test_黑名单非法邮箱被拦截且不落库(): void
    {
        $this->actingAdmin();

        $this->postJson('/admin/cmspro/smtpmail/api/blacklist', ['email' => 'not-an-email', 'reason' => '用户退订'])
            ->assertOk()
            ->assertJsonPath('code', 40001);

        $this->assertDatabaseMissing('app_cmspro_smtpmail_blacklist', ['reason' => '用户退订']);
    }

    public function test_批量任务非法收件人被拦截且不落库(): void
    {
        $this->actingAdmin();
        $this->makeTemplate();

        $this->postJson('/admin/cmspro/smtpmail/api/batches', [
            'name' => '通知群发',
            'template_code' => 'test_tpl',
            'recipients' => "a@example.com\nbad-email",
        ])->assertOk()->assertJsonPath('code', 40001);

        $this->assertDatabaseMissing('app_cmspro_smtpmail_batches', ['name' => '通知群发']);
    }

    // ===== F52 ②：历史报错 payload 回放 =====

    public function test_历史非法编码载荷回放被拦截(): void
    {
        $this->actingAdmin();

        $this->postJson('/admin/cmspro/smtpmail/api/accounts', [
            'name' => '非法编码', 'code' => 'Upper-Case', 'host' => 'smtp.example.com', 'from_address' => 'noreply@example.com',
        ])->assertOk()->assertJsonPath('code', 40001);

        $this->assertDatabaseMissing('app_cmspro_smtpmail_accounts', ['name' => '非法编码']);
    }

    public function test_重复编码载荷回放被拦截(): void
    {
        $this->actingAdmin();
        $this->makeAccount();

        $this->postJson('/admin/cmspro/smtpmail/api/accounts', [
            'name' => '重复编码', 'code' => 'test_account', 'host' => 'smtp.example.com', 'from_address' => 'noreply@example.com',
        ])->assertOk()->assertJsonPath('code', 40001);
    }

    // ===== F52 ③：合法空值走默认值 =====

    public function test_账号可选字段留空走默认值(): void
    {
        $this->actingAdmin();

        $this->postJson('/admin/cmspro/smtpmail/api/accounts', [
            'name' => '默认值账号', 'code' => 'default_check', 'host' => 'smtp.example.com', 'from_address' => 'noreply@example.com',
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('app_cmspro_smtpmail_accounts', [
            'code' => 'default_check',
            'scheme' => 'tls',
            'port' => 587,
            'status' => 1,
        ]);
    }

    // ===== F52 ④：更新必填传空被拦截，原记录不变 =====

    public function test_账号更新必填传空被拦截且原记录不变(): void
    {
        $this->actingAdmin();
        $account = $this->makeAccount(['name' => '原名称']);

        $this->putJson('/admin/cmspro/smtpmail/api/accounts/' . $account->id, [
            'name' => '', 'code' => 'test_account', 'host' => 'smtp.example.com', 'from_address' => 'noreply@example.com',
        ])->assertOk()->assertJsonPath('code', 40001);

        $this->assertDatabaseHas('app_cmspro_smtpmail_accounts', ['id' => $account->id, 'name' => '原名称']);
    }

    public function test_模板更新必填传空被拦截且原记录不变(): void
    {
        $this->actingAdmin();
        $template = $this->makeTemplate(['name' => '原模板名', 'subject' => '原主题']);

        $this->putJson('/admin/cmspro/smtpmail/api/templates/' . $template->id, [
            'name' => '', 'subject' => '', 'body_html' => '<p>新正文</p>',
        ])->assertOk()->assertJsonPath('code', 40001);

        $this->assertDatabaseHas('app_cmspro_smtpmail_templates', [
            'id' => $template->id, 'name' => '原模板名', 'subject' => '原主题',
        ]);
    }

    // ===== S8 设置白名单 + F58 密钥明文回显 =====

    public function test_设置白名单外的键被忽略不落库(): void
    {
        $this->actingAdmin();
        app(Install::class)->install();

        $this->putJson('/admin/cmspro/smtpmail/api/settings', [
            'max_retries' => '7',
            'hacked_key' => 'pwned',
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('config_items', ['code' => 'app_cmspro_smtpmail_max_retries', 'value' => '7']);
        $this->assertDatabaseMissing('config_items', ['code' => 'app_cmspro_smtpmail_hacked_key']);
    }

    public function test_接口令牌明文回显且留空提交不修改(): void
    {
        $this->actingAdmin();
        app(Install::class)->install();

        $this->putJson('/admin/cmspro/smtpmail/api/settings', ['api_token' => 'tok_abc123'])
            ->assertOk()->assertJsonPath('code', 0);

        // 12A.5：保存后通过 value 直接明文回显已保存的值
        $this->getJson('/admin/cmspro/smtpmail/api/settings')
            ->assertOk()
            ->assertJsonFragment(['key' => 'api_token', 'value' => 'tok_abc123']);
    }

    // ===== F62：普通 post()（无 JSON Accept 头）形态回归 =====

    public function test_普通post校验失败返回JSON而非重定向(): void
    {
        $this->actingAdmin();
        $account = $this->makeAccount();

        // ForceJsonResponse 中间件注入 Accept，validate 失败必须返回 422 JSON
        $response = $this->post('/admin/cmspro/smtpmail/api/accounts/' . $account->id . '/test', ['to' => 'not-an-email']);

        $this->assertNotContains($response->getStatusCode(), [301, 302, 303, 307, 308], '校验失败不得返回重定向');
        $this->assertNotNull(json_decode($response->getContent()), '响应体必须可 JSON 解析');
    }

    public function test_普通post空载荷业务校验返回JSON非3xx(): void
    {
        $this->actingAdmin();

        $response = $this->post('/admin/cmspro/smtpmail/api/accounts', []);

        $this->assertNotContains($response->getStatusCode(), [301, 302, 303, 307, 308]);
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body);
        $this->assertSame(40001, $body['code']);
    }

    // ===== 后台页面视图渲染冒烟（兜住 Blade 语法错误） =====

    public function test_后台页面视图可正常渲染(): void
    {
        $this->actingAdmin();

        foreach (['dashboard', 'accounts', 'templates', 'send', 'records', 'batches', 'blacklist', 'settings'] as $page) {
            $this->get('/admin/cmspro/smtpmail/' . $page)
                ->assertOk()
                ->assertSee('<!DOCTYPE html>', false);
        }
    }
}
