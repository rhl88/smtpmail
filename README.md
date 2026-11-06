# SMTP邮箱 应用文档

| 项目 | 内容 |
|------|------|
| 应用 ID | `cmspro.smtpmail` |
| 文档版本 | 1.0.2 |
| 应用版本 | 1.0.2 |
| 依赖 | PHP `>=8.3` / CmsPro `>=5.0.0` |
| 更新日期 | 2026-11-05 |

**官方地址**：<https://www.cmspro.cn/apps/cmspro.smtpmail>

---

## 应用概述

「SMTP邮箱」是 CmsPro v5 的独立 SMTP 邮件服务中台，提供多账号管理、可视化邮件模板、队列/定时/批量发送、发送日志与统计看板、黑名单与一键退订；同时通过辅助函数、Service 层、钩子点、Laravel 事件、HTTP 接口五类方式向其他应用输出发信能力，内置「邮箱验证码」与「找回密码验证码」两套模板，并可在不改动任何框架代码的前提下接管系统自带邮件。

- 三终端：后台管理端（8 个页面）、外部 HTTP 接口（`/api/smtpmail/`）、前台退订页（`/smtpmail/unsubscribe`）。
- 数据层：5 张 `app_cmspro_smtpmail_*` 业务表；配置层：3 组 12 项，支持三向同步与缓存。
- 隔离：目录、命名空间、数据库、视图、配置五大隔离；应用自身发信使用独立 Mailer，不读写框架 `mail_*` 配置。

---

## 目录结构

以下为应用根目录 `code/app/Apps/CmsproSmtpmail/` 的真实磁盘结构。

```text
CmsproSmtpmail/
├── Assets/                         # 静态资源（css/ js/）
│   ├── css/
│   └── js/
├── Config/
│   └── smtpmail.php                # 应用默认配置
├── Console/
│   └── Commands/
│       ├── CleanRecords.php
│       └── DispatchDueMails.php
├── Controllers/
│   ├── Admin/                      # 8 个后台模块控制器
│   ├── Api/ApiController.php       # 外部 HTTP 接口（令牌校验）
│   ├── Home/UnsubscribeController.php
│   └── BaseAdminController.php     # 后台控制器基类
├── Events/
│   ├── MessageFailed.php
│   └── MessageSent.php
├── Exceptions/
│   └── SmtpmailException.php
├── Jobs/
│   └── SendMailJob.php
├── Listeners/
│   └── ApplySystemMailTakeover.php
├── Mail/
│   └── SmtpmailMailable.php
├── Migrations/
│   ├── 2026_11_05_000001_create_app_cmspro_smtpmail_accounts_table.php
│   ├── 2026_11_05_000002_create_app_cmspro_smtpmail_templates_table.php
│   ├── 2026_11_05_000003_create_app_cmspro_smtpmail_records_table.php
│   ├── 2026_11_05_000004_create_app_cmspro_smtpmail_batches_table.php
│   └── 2026_11_05_000005_create_app_cmspro_smtpmail_blacklist_table.php
├── Models/
│   ├── SmtpmailAccount.php
│   ├── SmtpmailBatch.php
│   ├── SmtpmailBlacklist.php
│   ├── SmtpmailRecord.php
│   └── SmtpmailTemplate.php
├── Routes/
│   ├── admin.php
│   ├── api.php
│   └── home.php
├── Services/
│   ├── AccountService.php
│   ├── BatchService.php
│   ├── BlacklistService.php
│   ├── MailService.php
│   ├── MailerFactory.php
│   ├── RecordService.php
│   ├── TakeoverContext.php
│   ├── TemplateRenderer.php
│   └── TemplateService.php
├── Tests/                          # 应用测试（Feature/ Unit/）
│   ├── Feature/
│   └── Unit/
├── Views/
│   ├── Admin/
│   │   ├── accounts/{index,form}.blade.php
│   │   ├── batches/{index,form}.blade.php
│   │   ├── blacklist/{index,form}.blade.php
│   │   ├── dashboard/index.blade.php
│   │   ├── records/{index,detail}.blade.php
│   │   ├── send/index.blade.php
│   │   ├── settings/index.blade.php
│   │   └── templates/{index,form}.blade.php
│   ├── Home/
│   │   └── unsubscribe.blade.php
│   └── Override/
│       └── emails/
│           ├── password-reset-code.blade.php
│           └── register-code.blade.php
├── doc/
│   ├── CmsPro-SMTP邮箱-特性清单.md
│   ├── CmsPro-SMTP邮箱-使用指南.md
│   └── CmsPro-SMTP邮箱-扩展指南.md
├── Hooks.php
├── Install.php
├── ServiceProvider.php
├── helpers.php
├── manifest.json
├── README.md
├── icon.svg
├── .exportignore
└── .gitignore
```

---

## 文档索引

| 文档 | 说明 |
|------|------|
| [特性清单](doc/CmsPro-SMTP邮箱-特性清单.md) | 应用覆盖的框架特性、实现文件位置与架构图 |
| [使用指南](doc/CmsPro-SMTP邮箱-使用指南.md) | 安装配置、8 个后台页面、全部配置项、模板变量、队列与定时任务、FAQ |
| [扩展指南](doc/CmsPro-SMTP邮箱-扩展指南.md) | 其他应用如何调用：辅助函数、Service、钩子、事件、HTTP 接口、接管系统邮件 |
