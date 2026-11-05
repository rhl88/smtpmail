<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Models\SmtpmailAccount;
use Illuminate\Mail\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * SMTP邮箱 · 独立 Mailer 工厂
 *
 * 应用自身发信完全不读写框架 mail_* 配置：按账号参数直接构造 Symfony
 * EsmtpTransport，再包成 Illuminate\Mail\Mailer。这样多账号并存、且与框架
 * 自带的全局邮箱配置互不影响。
 *
 * 测试可通过 usingTransport() 注入 ArrayTransport，避免连接真实 SMTP。
 */
class MailerFactory
{
    private ?TransportInterface $transportOverride = null;

    /**
     * 注入自定义传输层（测试用；传 null 恢复默认）
     */
    public function usingTransport(?TransportInterface $transport): void
    {
        $this->transportOverride = $transport;
    }

    /**
     * 按账号构造独立 Mailer
     */
    public function make(SmtpmailAccount $account): Mailer
    {
        $transport = $this->transportOverride ?? $this->buildTransport($account);

        return new Mailer(
            'cmspro.smtpmail',
            app('view'),
            $transport,
            app('events')
        );
    }

    /**
     * 构造 SMTP 传输层
     *
     * EsmtpTransport 在 465 端口自动启用隐式 TLS；其他端口按 scheme 决定
     * 是否 STARTTLS。SSL（smtps）对应 $tls=true，none 对应 false。
     */
    protected function buildTransport(SmtpmailAccount $account): TransportInterface
    {
        $tls = $account->scheme !== SmtpmailAccount::SCHEME_NONE;
        $transport = new EsmtpTransport((string) $account->host, (int) $account->port, $tls);

        if ((string) $account->username !== '') {
            $transport->setUsername((string) $account->username);
            $transport->setPassword(app(AccountService::class)->decryptPassword($account));
        }

        $timeout = (int) $account->timeout;
        $stream = $transport->getStream();
        if ($timeout > 0 && method_exists($stream, 'setTimeout')) {
            $stream->setTimeout($timeout);
        }

        return $transport;
    }
}
