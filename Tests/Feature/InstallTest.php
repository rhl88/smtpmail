<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Install;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;
use Illuminate\Support\Facades\Schema;

/**
 * 安装 / 升级脚本测试
 */
class InstallTest extends SmtpmailTestCase
{
    public function test_安装后内置验证码与找回密码模板存在(): void
    {
        app(Install::class)->install();

        $this->assertDatabaseHas('app_cmspro_smtpmail_templates', [
            'code' => 'verify_code', 'is_system' => 1, 'status' => 1,
        ]);
        $this->assertDatabaseHas('app_cmspro_smtpmail_templates', [
            'code' => 'password_reset', 'is_system' => 1, 'status' => 1,
        ]);

        $template = SmtpmailTemplate::where('code', 'verify_code')->firstOrFail();
        $this->assertContains('code', $template->extractVariables());
        $this->assertStringContainsString('{{code}}', (string) $template->body_html);
    }

    public function test_安装后内置欢迎与通知模板存在(): void
    {
        app(Install::class)->install();

        foreach (['welcome', 'notice'] as $code) {
            $this->assertDatabaseHas('app_cmspro_smtpmail_templates', [
                'code' => $code, 'is_system' => 1, 'status' => 1, 'category' => 'system',
            ]);
        }
        $this->assertSame(4, SmtpmailTemplate::count());
    }

    public function test_升级不覆盖用户已编辑的内置模板(): void
    {
        $install = app(Install::class);
        $install->install();
        SmtpmailTemplate::where('code', 'verify_code')->update(['subject' => '用户自定义主题']);

        $install->upgrade('1.0.0', '1.0.1');

        $this->assertSame(
            '用户自定义主题',
            SmtpmailTemplate::where('code', 'verify_code')->value('subject')
        );
        $this->assertSame(4, SmtpmailTemplate::count());
    }

    public function test_卸载通过迁移回滚删除全部应用表(): void
    {
        $install = app(Install::class);
        $install->install();
        $install->uninstall();

        foreach ([
            'app_cmspro_smtpmail_accounts',
            'app_cmspro_smtpmail_templates',
            'app_cmspro_smtpmail_records',
            'app_cmspro_smtpmail_batches',
            'app_cmspro_smtpmail_blacklist',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table . ' 应已被回滚删除');
        }
    }
}
