<?php

namespace App\Apps\CmsproSmtpmail\Exceptions;

/**
 * SMTP邮箱 · 业务异常
 *
 * 参数错误、模板缺失、账号缺失、发送失败等场景统一抛出，由控制器/辅助函数
 * 转换为友好提示，避免底层异常信息直接暴露给调用方。
 */
class SmtpmailException extends \RuntimeException
{
}
