<?php

namespace App\Apps\CmsproSmtpmail\Tests\Unit;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;

/**
 * 发信账号服务单元测试
 */
class AccountServiceTest extends SmtpmailTestCase
{
    public function test_密码加密存储且可解密(): void
    {
        $service = app(AccountService::class);
        $account = $service->create([
            'name' => '主账号',
            'code' => 'primary',
            'host' => 'smtp.example.com',
            'from_address' => 'primary@example.com',
            'username' => 'primary@example.com',
            'password' => 'my-secret',
        ]);

        $this->assertNotSame('my-secret', $account->password);
        $this->assertSame('my-secret', $service->decryptPassword($account));
        $this->assertArrayNotHasKey('password', $account->toSummaryArray());
        $this->assertTrue($account->toSummaryArray()['has_password']);
    }

    public function test_更新时密码留空保持原密文(): void
    {
        $service = app(AccountService::class);
        $account = $service->create([
            'name' => '主账号', 'code' => 'primary', 'host' => 'smtp.example.com',
            'from_address' => 'primary@example.com', 'password' => 'my-secret',
        ]);
        $original = $account->password;

        $updated = $service->update((int) $account->id, ['name' => '改名', 'password' => '']);

        $this->assertSame('改名', $updated->name);
        $this->assertSame($original, $updated->password);
        $this->assertSame('my-secret', $service->decryptPassword($updated));
    }

    public function test_首个账号自动设为默认(): void
    {
        $account = app(AccountService::class)->create([
            'name' => '主账号', 'code' => 'primary', 'host' => 'smtp.example.com',
            'from_address' => 'primary@example.com',
        ]);

        $this->assertSame(1, (int) $account->is_default);
        $this->assertSame('primary', app(AccountService::class)->defaultAccount()?->code);
    }

    public function test_编码格式非法时抛出业务异常(): void
    {
        $this->expectException(SmtpmailException::class);
        $this->expectExceptionMessage('账号编码仅允许小写字母');

        app(AccountService::class)->create([
            'name' => '非法', 'code' => 'Upper-Case', 'host' => 'smtp.example.com',
            'from_address' => 'a@example.com',
        ]);
    }

    public function test_编码重复时抛出业务异常(): void
    {
        $service = app(AccountService::class);
        $service->create([
            'name' => 'A', 'code' => 'same', 'host' => 'smtp.example.com',
            'from_address' => 'a@example.com',
        ]);

        $this->expectException(SmtpmailException::class);
        $service->create([
            'name' => 'B', 'code' => 'same', 'host' => 'smtp.example.com',
            'from_address' => 'b@example.com',
        ]);
    }

    public function test_设为默认后其他账号取消默认(): void
    {
        $service = app(AccountService::class);
        $first = $service->create([
            'name' => 'A', 'code' => 'aaa', 'host' => 'smtp.example.com', 'from_address' => 'a@example.com',
        ]);
        $second = $service->create([
            'name' => 'B', 'code' => 'bbb', 'host' => 'smtp.example.com', 'from_address' => 'b@example.com',
        ]);

        $service->setDefault((int) $second->id);

        $this->assertSame(0, (int) $first->fresh()->is_default);
        $this->assertSame(1, (int) $second->fresh()->is_default);
    }
}
