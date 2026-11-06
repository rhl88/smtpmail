<?php

/**
 * SMTP邮箱 · 应用默认配置
 *
 * 与 manifest.json 的 config_groups.items 一一对应，作为数据库 config_items
 * 无记录时的兜底值。运行时统一通过 smtpmail_config() 读取，禁止直接 config()。
 */
return [
    'takeover_system_mail' => '0',
    'use_queue' => '0',
    'max_retries' => '3',
    'retry_delay' => '60',
    'rate_limit' => '60',
    'log_retention_days' => '90',
    'site_name' => 'CmsPro',
    'enable_blacklist' => '1',
    'welcome_mail' => '0',
    'api_enabled' => '0',
    'api_token' => '',
    'template_verify_code' => 'verify_code',
    'template_password_reset' => 'password_reset',
];
