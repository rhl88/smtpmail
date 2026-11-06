<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use App\Models\User;
use App\Services\HookManager;
use Illuminate\Support\Facades\Cache;

/**
 * 欢迎邮件钩子（user.after_register）行为测试
 */
class WelcomeMailHookTest extends \App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 种子内置 welcome 模板（仅模板，不跑完整 install）
        if (! SmtpmailTemplate::where('code', 'welcome')->exists()) {
            SmtpmailTemplate::create([
                'code' => 'welcome',
                'name' => '新用户欢迎邮件',
                'category' => 'system',
                'subject' => '【{{site_name}}】欢迎加入，{{nickname}}',
                'body_html' => '<p>欢迎 {{nickname}}</p>',
                'body_text' => '欢迎 {{nickname}}',
                'variables' => [['name' => 'nickname', 'label' => '用户昵称']],
                'account_id' => null,
                'status' => 1,
                'is_system' => 1,
                'remark' => '',
            ]);
        }
        Cache::flush();
    }

    protected function fireHook(User $user): void
    {
        app(HookManager::class)->doAction('user.after_register', $user);
    }

    protected function makeUser(array $attrs = []): User
    {
        return new User(array_merge([
            'username' => 'user_' . uniqid(),
            'email' => 'newuser@example.com',
            'nickname' => '张三',
            'status' => 1,
        ], $attrs));
    }

    public function test_开关开启且有邮箱时注册钩子发送欢迎邮件(): void
    {
        $this->makeAccount();
        config(['apps.cmspro.smtpmail.welcome_mail' => '1']);
        Cache::flush();

        $this->fireHook($this->makeUser());

        $record = SmtpmailRecord::where('template_code', 'welcome')->first();
        $this->assertNotNull($record, '应产生 welcome 模板发送记录');
        $this->assertSame('success', (string) $record->status);
        $this->assertStringContainsString('newuser@example.com', json_encode($record->to_addresses, JSON_UNESCAPED_UNICODE));
        $this->assertSame('hook', (string) $record->source);
    }

    public function test_开关关闭时注册钩子不发送(): void
    {
        config(['apps.cmspro.smtpmail.welcome_mail' => '0']);
        Cache::flush();

        $this->fireHook($this->makeUser());

        $this->assertSame(0, SmtpmailRecord::where('template_code', 'welcome')->count());
    }

    public function test_注册用户无邮箱时钩子不发送(): void
    {
        config(['apps.cmspro.smtpmail.welcome_mail' => '1']);
        Cache::flush();

        $this->fireHook($this->makeUser(['email' => '']));

        $this->assertSame(0, SmtpmailRecord::where('template_code', 'welcome')->count());
    }

    public function test_注册用户邮箱非法时钩子不发送(): void
    {
        config(['apps.cmspro.smtpmail.welcome_mail' => '1']);
        Cache::flush();

        $this->fireHook($this->makeUser(['email' => 'not-an-email']));

        $this->assertSame(0, SmtpmailRecord::where('template_code', 'welcome')->count());
    }

    public function test_welcome模板缺失时钩子不抛异常且不产生记录(): void
    {
        config(['apps.cmspro.smtpmail.welcome_mail' => '1']);
        Cache::flush();
        SmtpmailTemplate::where('code', 'welcome')->delete();

        $this->fireHook($this->makeUser());

        $this->assertSame(0, SmtpmailRecord::where('template_code', 'welcome')->count());
    }
}
