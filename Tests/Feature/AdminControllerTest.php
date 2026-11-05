<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;
use App\Models\AdminRole;
use App\Models\AdminUser;

/**
 * 后台控制器功能测试（权限、Layui 返回格式、设置保存链路）
 */
class AdminControllerTest extends SmtpmailTestCase
{
    public function test_未登录访问后台接口被拒绝(): void
    {
        // 框架 auth:admin 中间件统一返回 40003（未登录），不会进入应用控制器
        $this->getJson('/admin/cmspro/smtpmail/api/accounts')
            ->assertStatus(401)->assertJsonPath('code', 40003);
    }

    public function test_账号列表返回Layui分页格式(): void
    {
        $this->actingAdmin();
        $this->makeAccount();

        $this->getJson('/admin/cmspro/smtpmail/api/accounts')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.code', 'test_account');
    }

    public function test_设置读取与保存链路可用(): void
    {
        $this->actingAdmin();
        app(\App\Apps\CmsproSmtpmail\Install::class)->install();

        $this->getJson('/admin/cmspro/smtpmail/api/settings')
            ->assertOk()->assertJsonPath('code', 0);

        $this->putJson('/admin/cmspro/smtpmail/api/settings', [
            'takeover_system_mail' => '1',
            'max_retries' => '5',
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('config_items', [
            'code' => 'app_cmspro_smtpmail_max_retries',
            'value' => '5',
        ]);

        $this->getJson('/admin/cmspro/smtpmail/api/settings/takeover-status')
            ->assertOk()->assertJsonPath('data.enabled', true);
    }

    public function test_后台发送邮件接口可落记录(): void
    {
        $this->actingAdmin();
        $this->makeAccount();
        $this->makeTemplate();

        $this->postJson('/admin/cmspro/smtpmail/api/send', [
            'template_code' => 'test_tpl',
            'to' => 'to@example.com',
            'vars' => ['title' => '后台通知', 'name' => '赵六'],
            'queue' => 0,
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('app_cmspro_smtpmail_records', [
            'subject' => '【CmsPro】后台通知',
            'status' => 'success',
        ]);
    }

    public function test_模板内置模板禁止删除(): void
    {
        $this->actingAdmin();
        app(\App\Apps\CmsproSmtpmail\Install::class)->install();
        $template = \App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate::where('code', 'verify_code')->firstOrFail();

        $this->deleteJson('/admin/cmspro/smtpmail/api/templates/' . $template->id)
            ->assertOk()->assertJsonPath('code', 40001);
    }
}
