# SMTP邮箱 应用文档

| 项目 | 内容 |
|------|------|
| 应用 ID | `cmspro.smtpmail` |
| 文档版本 | 1.0.3 |
| 应用版本 | 1.0.3 |
| 依赖 | PHP `>=8.3` / CmsPro `>=5.0.0` |
| 更新日期 | 2026-11-06 |

**官方地址**：<https://www.cmspro.cn/apps/cmspro.smtpmail>

---

## 应用概述

「SMTP邮箱」是 CmsPro v5 的独立 SMTP 邮件服务中台，提供多账号管理、可视化邮件模板、队列/定时/批量发送、发送日志与统计看板、黑名单与一键退订；同时通过辅助函数、Service 层、钩子点、Laravel 事件、HTTP 接口五类方式向其他应用输出发信能力，内置「邮箱验证码」「找回密码验证码」「新用户欢迎邮件」「系统通知公告」四套模板，并可在不改动任何框架代码的前提下接管系统自带邮件。

- 三终端：后台管理端（8 个页面）、外部 HTTP 接口（`/api/smtpmail/`）、前台退订页（`/smtpmail/unsubscribe`）。
- 数据层：5 张 `app_cmspro_smtpmail_*` 业务表；配置层：3 组 12 项，支持三向同步与缓存。
- 隔离：目录、命名空间、数据库、视图、配置五大隔离；应用自身发信使用独立 Mailer，不读写框架 `mail_*` 配置。

---

## 应用截图

> 截图来源：[官方应用页 · cmspro.smtpmail](https://www.cmspro.cn/apps/cmspro.smtpmail)（官网标注 v1.0.3）。每次发布与更新必须核对本节截图与官网「应用截图」是否一致，不一致时以官网为准同步更新。

![应用截图 1](https://www.cmspro.cn/uploads/appstore/2026/11/06/vIDsR0WAUUblOsqc1YVRQnWDQI0W9MbZoSPrkW60.png)

![应用截图 2](https://www.cmspro.cn/uploads/appstore/2026/11/06/YbPAC5jS3NPkQOKVk1WVDaZ15JktveKCyXiob6fc.png)

![应用截图 3](https://www.cmspro.cn/uploads/appstore/2026/11/06/dAuWEnnBYQqgjP8Y3RBjRUkYfFYcORKIFJ6CeqSs.png)

![应用截图 4](https://www.cmspro.cn/uploads/appstore/2026/11/06/F3Udkj4Fyb1vr22QpBQGpsLy5ZkPm6MMh8RY11VQ.png)

![应用截图 5](https://www.cmspro.cn/uploads/appstore/2026/11/06/0J95j3SFGEJ9MbWrJZz6jcHpag6erUNk9ocQFeOV.png)

![应用截图 6](https://www.cmspro.cn/uploads/appstore/2026/11/06/k1h2oEGSXnvmQrwDiECAUpM902QQGSSTYLNw7GI6.png)

![应用截图 7](https://www.cmspro.cn/uploads/appstore/2026/11/06/01xC65CxAw3KMWn8jpfgutQ9kGzIB4iTWxmf1yDr.png)

![应用截图 8](https://www.cmspro.cn/uploads/appstore/2026/11/06/rPV6OSS3NLy82nG6bfqMTQGexJhFsrzk5v85aD56.png)

![应用截图 9](https://www.cmspro.cn/uploads/appstore/2026/11/06/b2reBkEw3AnIWx4D67RNPRa9DNlLZlx5005lwvX9.png)

![应用截图 10](https://www.cmspro.cn/uploads/appstore/2026/11/06/dNeKfwrAOOZkeUJqcy3Ih6zqZqDikG2ekDTnjITS.png)

![应用截图 11](https://www.cmspro.cn/uploads/appstore/2026/11/06/GhTxijr5gH9HCt1D3MYFETSLrGbiGb3Tga9WHa0V.png)

![应用截图 12](https://www.cmspro.cn/uploads/appstore/2026/11/06/Kq2dqrORYGaKznWbyvzh2OefkeNSIfLbuiV5ck7u.png)

![应用截图 13](https://www.cmspro.cn/uploads/appstore/2026/11/06/XqUEfZACtyup38c5cr34u5osBpYwCGHUJ72jb1mx.png)

![应用截图 14](https://www.cmspro.cn/uploads/appstore/2026/11/06/TKu7rlbop5GKFiqFC8vMCuOcKTxP8WFzgb6UMZsN.png)

![应用截图 15](https://www.cmspro.cn/uploads/appstore/2026/11/06/I1dKhzuYbGDwxKpJ4LnKBAW8qrDA42fpLdbQGmcz.png)

![应用截图 16](https://www.cmspro.cn/uploads/appstore/2026/11/06/IflNQKOAIaAH8Qb8BGMoCjgCSa8oVg62mMZghDGv.png)

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
│   ├── Admin/                      # 9 个后台模块控制器（发送/模板/分类/账号/日志/批量/黑名单/看板/设置）
│   ├── Api/ApiController.php       # 外部 HTTP 接口（令牌校验）
│   ├── Home/UnsubscribeController.php
│   └── BaseAdminController.php     # 后台控制器基类
├── Events/
│   ├── MessageFailed.php
│   └── MessageSent.php
├── Exceptions/
│   └── SmtpmailException.php
├── Http/
│   └── Middleware/ForceJsonResponse.php  # 后台接口强制 JSON 响应
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
│   ├── CategoryService.php
│   ├── MailService.php
│   ├── MailerFactory.php
│   ├── RecordService.php
│   ├── TakeoverContext.php
│   ├── TemplateRenderer.php
│   └── TemplateService.php
├── Tests/                          # 应用测试（Feature/ Unit/）
│   ├── Feature/                    # 后台控制器、接口与接管、钩子事件、安装、发信服务、校验回归、欢迎邮件
│   └── Unit/                       # 账号服务、模板渲染
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
