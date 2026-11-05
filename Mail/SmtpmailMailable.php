<?php

namespace App\Apps\CmsproSmtpmail\Mail;

use App\Apps\CmsproSmtpmail\Models\SmtpmailRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/**
 * SMTP邮箱 · 可邮寄对象
 *
 * 内容来自发送记录中的「渲染后快照」，不依赖 Blade 视图文件，
 * 因此模板存放在数据库、后台可自由编辑。
 */
class SmtpmailMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SmtpmailRecord $record)
    {
        // 纯文本正文、附件、自定义头、优先级在 Symfony 消息层面补充
        $this->withSymfonyMessage(function (Email $message): void {
            $text = (string) $this->record->body_text;
            if ($text !== '') {
                $message->text($text);
            }

            foreach (($this->record->attachments ?? []) as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $path = (string) ($attachment['path'] ?? '');
                if ($path === '' || ! is_file($path)) {
                    continue;
                }

                $message->attachFromPath(
                    $path,
                    (string) ($attachment['name'] ?? basename($path))
                );
            }

            foreach (($this->record->headers ?? []) as $name => $value) {
                if (is_string($name) && $name !== '' && is_scalar($value)) {
                    $message->getHeaders()->addTextHeader($name, (string) $value);
                }
            }

            if ((int) $this->record->priority > 0) {
                $message->priority((int) $this->record->priority);
            }
        });
    }

    /**
     * 信封：发件人、收件人、抄送、密送、回复到、主题
     */
    public function envelope(): Envelope
    {
        $fromAddress = (string) $this->record->from_address;
        if ($fromAddress === '') {
            $fromAddress = (string) config('mail.from.address', 'noreply@example.com');
        }

        return new Envelope(
            from: new Address($fromAddress, (string) $this->record->from_name),
            to: $this->record->to_addresses ?? [],
            cc: $this->record->cc ?? [],
            bcc: $this->record->bcc ?? [],
            replyTo: $this->record->reply_to ?? [],
            subject: (string) $this->record->subject,
        );
    }

    /**
     * 正文：使用预渲染的 HTML 字符串
     */
    public function content(): Content
    {
        return new Content(htmlString: (string) $this->record->body_html);
    }
}
