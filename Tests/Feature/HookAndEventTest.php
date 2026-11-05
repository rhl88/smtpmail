<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Events\MessageFailed;
use App\Apps\CmsproSmtpmail\Events\MessageSent;
use App\Apps\CmsproSmtpmail\Services\MailerFactory;
use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;
use App\Services\HookManager;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * 钩子扩展点与 Laravel 事件测试
 */
class HookAndEventTest extends SmtpmailTestCase
{
    public function test_should_send钩子返回false时拦截发送(): void
    {
        $this->makeAccount();
        $this->makeTemplate();

        app(HookManager::class)->registerFilter(
            'cmspro.smtpmail.should_send',
            fn ($allowed) => false,
            10,
            'test.consumer'
        );

        $result = app(MailService::class)->send('test_tpl', 'to@example.com');

        $this->assertFalse($result['success']);
        $this->assertSame('canceled', $result['status']);
        $this->assertSame(0, $result['record_id']);
    }

    public function test_before_send钩子可改写载荷(): void
    {
        $this->makeAccount();
        $this->makeTemplate();

        app(HookManager::class)->registerFilter(
            'cmspro.smtpmail.before_send',
            function (array $payload): array {
                $payload['subject'] = '钩子改写后的主题';

                return $payload;
            },
            10,
            'test.consumer'
        );

        $result = app(MailService::class)->send('test_tpl', 'to@example.com');
        $record = \App\Apps\CmsproSmtpmail\Models\SmtpmailRecord::findOrFail($result['record_id']);

        $this->assertSame('钩子改写后的主题', $record->subject);
    }

    public function test_发送成功触发Action钩子与事件(): void
    {
        Event::fake([MessageSent::class]);
        $this->makeAccount();
        $this->makeTemplate();

        $fired = false;
        app(HookManager::class)->registerAction(
            'cmspro.smtpmail.sent',
            function () use (&$fired): void {
                $fired = true;
            },
            10,
            'test.consumer'
        );

        $result = app(MailService::class)->send('test_tpl', 'to@example.com');

        $this->assertTrue($result['success']);
        $this->assertTrue($fired);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_发送失败触发Action钩子与事件(): void
    {
        Event::fake([MessageFailed::class]);
        $this->makeAccount();
        $this->makeTemplate();

        $errors = [];
        app(HookManager::class)->registerAction(
            'cmspro.smtpmail.failed',
            function (int $recordId, string $error) use (&$errors): void {
                $errors[] = $error;
            },
            10,
            'test.consumer'
        );

        app(MailerFactory::class)->usingTransport(new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new \RuntimeException('SMTP 连接超时');
            }

            public function __toString(): string
            {
                return 'failing://test';
            }
        });

        $result = app(MailService::class)->send('test_tpl', 'to@example.com');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($errors);
        Event::assertDispatched(MessageFailed::class);
    }
}
