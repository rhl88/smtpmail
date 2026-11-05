{{-- 系统找回密码邮件视图覆盖：仅在「接管系统邮件」开关开启时被优先命中 --}}
{!! smtpmail_render_system(
    (string) smtpmail_config('template_password_reset', 'password_reset'),
    [
        'code' => $code ?? '',
        'minutes' => $minutes ?? 10,
        'nickname' => $nickname ?? '',
    ]
) !!}
