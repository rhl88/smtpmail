<?php

namespace App\Apps\CmsproSmtpmail\Tests\Feature;

use App\Apps\CmsproSmtpmail\Jobs\SendMailJob;
use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use App\Apps\CmsproSmtpmail\Services\BlacklistService;
use App\Apps\CmsproSmtpmail\Services\MailerFactory;
use App\Apps\CmsproSmtpmail\Services\MailService;
use App\Apps\CmsproSmtpmail\Tests\SmtpmailTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * 发送链路功能测试（全程假传输层，不连接真实 SMTP）
 */
class MailServiceTest extends SmtpmailTestCase
{
    public function test_按模板发送成功并写入日志(): void
    {
        $this->makeAccount();
        $this->makeTemplate();

        $result = app(MailService::class)->send('test_tpl', 'to@example.com', [
            'title' => '通知', 'name' => '张三',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);

        $record = SmtpmailRecord::findOrFail($result['record_id']);
        $this->assertSame('【CmsPro】通知', $record->subject);
        $this->assertSame(['to@example.com'], $record->to_addresses);
        $this->assertSame('noreply@example.com', $record->from_address);
        $this->assertSame(SmtpmailRecord::STATUS_SUCCESS, $record->status);
        $this->assertNotNull($record->sent_at);
        $this->assertStringContainsString('张三', (string) $record->body_html);
    }

    public function test_发送失败记录错误且按配置不重试(): void
    {
        $this->makeAccount();
        $this->makeTemplate();

        app(MailerFactory::class)->usingTransport($this->failingTransport());

        $result = app(MailService::class)->send('test_tpl', 'to@example.com', ['title' => '通知']);

        $this->assertFalse($result['success']);
        $record = SmtpmailRecord::findOrFail($result['record_id']);
        $this->assertSame(SmtpmailRecord::STATUS_FAILED, $record->status);
        $this->assertStringContainsString('SMTP 连接超时', (string) $record->error);
        $this->assertSame(0, (int) $record->retry_count);
    }

    public function test_失败后按配置安排重试(): void
    {
        config(['apps.cmspro.smtpmail.max_retries' => '2']);
        Cache::flush();

        $this->makeAccount();
        $this->makeTemplate();
        app(MailerFactory::class)->usingTransport($this->failingTransport());

        $result = app(MailService::class)->send('test_tpl', 'to@example.com');
        $record = SmtpmailRecord::findOrFail($result['record_id']);

        $this->assertSame(SmtpmailRecord::STATUS_PENDING, $record->status);
        $this->assertSame(1, (int) $record->retry_count);
        $this->assertNotNull($record->scheduled_at);
    }

    public function test_黑名单自动过滤收件人(): void
    {
        $this->makeAccount();
        $this->makeTemplate();
        app(BlacklistService::class)->add('blocked@example.com');

        $result = app(MailService::class)->send(
            'test_tpl',
            ['blocked@example.com', 'ok@example.com'],
            ['title' => '通知']
        );

        $record = SmtpmailRecord::findOrFail($result['record_id']);
        $this->assertSame(['ok@example.com'], $record->to_addresses);
    }

    public function test_收件人全部在黑名单时取消发送(): void
    {
        $this->makeAccount();
        $this->makeTemplate();
        app(BlacklistService::class)->add('blocked@example.com');

        $result = app(MailService::class)->send('test_tpl', 'blocked@example.com');

        $this->assertFalse($result['success']);
        $this->assertSame(SmtpmailRecord::STATUS_CANCELED, $result['status']);
    }

    public function test_定时发送进入待发状态(): void
    {
        $this->makeAccount();
        $this->makeTemplate();

        $result = app(MailService::class)->send('test_tpl', 'to@example.com', [], [
            'scheduled_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $this->assertSame(SmtpmailRecord::STATUS_SCHEDULED, $result['status']);
        $record = SmtpmailRecord::findOrFail($result['record_id']);
        $this->assertNotNull($record->scheduled_at);
        $this->assertNull($record->sent_at);
    }

    public function test_开启队列时投递任务而不立即发送(): void
    {
        Queue::fake();
        config(['apps.cmspro.smtpmail.use_queue' => '1']);
        Cache::flush();

        $this->makeAccount();
        $this->makeTemplate();

        $result = app(MailService::class)->send('test_tpl', 'to@example.com');

        $this->assertSame(SmtpmailRecord::STATUS_QUEUED, $result['status']);
        Queue::assertPushed(SendMailJob::class);
        $this->assertSame(SmtpmailRecord::STATUS_QUEUED, SmtpmailRecord::findOrFail($result['record_id'])->status);
    }

    public function test_直发自定义正文不依赖模板(): void
    {
        $this->makeAccount();

        $result = app(MailService::class)->sendRaw('to@example.com', [
            'subject' => '直发主题',
            'html' => '<p>直发正文 {{name}}</p>',
            'vars' => ['name' => '李四'],
        ]);

        $record = SmtpmailRecord::findOrFail($result['record_id']);
        $this->assertSame('直发主题', $record->subject);
        $this->assertSame('<p>直发正文 李四</p>', $record->body_html);
        $this->assertSame('', $record->template_code);
    }

    public function test_收件人格式非法时不落记录(): void
    {
        $this->makeAccount();
        $before = SmtpmailRecord::count();

        $this->expectException(\App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException::class);
        try {
            app(MailService::class)->sendRaw('not-an-email', ['subject' => 'x', 'html' => 'y']);
        } finally {
            $this->assertSame($before, SmtpmailRecord::count());
        }
    }

    public function test_手动重试失败记录(): void
    {
        $this->makeAccount();
        $this->makeTemplate();
        app(MailerFactory::class)->usingTransport($this->failingTransport());

        $first = app(MailService::class)->send('test_tpl', 'to@example.com');
        $this->assertSame(SmtpmailRecord::STATUS_FAILED, SmtpmailRecord::findOrFail($first['record_id'])->status);

        // 恢复为正常传输层后重试
        app(MailerFactory::class)->usingTransport(new \Illuminate\Mail\Transport\ArrayTransport());
        $retry = app(MailService::class)->retry($first['record_id']);

        $this->assertTrue($retry['success']);
        $this->assertSame(SmtpmailRecord::STATUS_SUCCESS, SmtpmailRecord::findOrFail($first['record_id'])->status);
    }

    /**
     * 总是抛异常的假传输层
     */
    protected function failingTransport(): TransportInterface
    {
        return new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new \RuntimeException('SMTP 连接超时');
            }

            public function __toString(): string
            {
                return 'failing://test';
            }
        };
    }
}
