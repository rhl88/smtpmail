{{-- 系统注册验证码邮件视图覆盖：仅在「接管系统邮件」开关开启时被优先命中 --}}
{!! smtpmail_render_system(
    (string) smtpmail_config('template_verify_code', 'verify_code'),
    [
        'code' => $code ?? '',
        'minutes' => $minutes ?? 10,
        'nickname' => $nickname ?? '',
    ]
) !!}
