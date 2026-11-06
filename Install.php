<?php

namespace App\Apps\CmsproSmtpmail;

use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SMTP邮箱 · 安装 / 卸载 / 升级脚本
 *
 * 遵循《CmsPro-v5-应用开发文档》第十二章与第二十章：
 * - install():   迁移兜底 + 配置三向同步 + 内置模板初始化
 * - uninstall(): 迁移回滚兜底（通过 down() 删表）+ 配置清理 + 缓存清理
 * - upgrade():   迁移兜底 + 配置三向同步（保留用户配置 value）+ 内置模板补齐
 *
 * 配置 code 规则：app_ + str_replace('.', '_', app_id) + '_' + name
 * 即 app_cmspro_smtpmail_<key>，与 helpers.php 的 smtpmail_config() 对齐。
 */
class Install
{
    private const APP_ID = 'cmspro.smtpmail';

    private const CODE_PREFIX = 'app_cmspro_smtpmail_';

    /**
     * 安装
     */
    public function install(): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->seedBuiltinTemplates();
    }

    /**
     * 卸载（完全可逆，表由迁移 down() 删除）
     */
    public function uninstall(): void
    {
        $this->rollbackMigrations();
        $this->removeConfigs();
        $this->forgetConfigCache();
    }

    /**
     * 升级
     */
    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->seedBuiltinTemplates();
        $this->forgetConfigCache();
    }

    /**
     * 迁移兜底：直接 require 迁移文件调用 up()，并写入 migrations 表去重
     */
    protected function runMigrations(): void
    {
        $files = glob(__DIR__ . '/Migrations/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            try {
                if (DB::table('migrations')->where('migration', $name)->exists()) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $migration = require $file;
            if (! is_object($migration) || ! method_exists($migration, 'up')) {
                continue;
            }

            try {
                $migration->up();
                DB::table('migrations')->insert([
                    'migration' => $name,
                    'batch' => (int) DB::table('migrations')->max('batch') + 1,
                ]);
            } catch (\Throwable $e) {
                Log::error('SMTP邮箱迁移执行失败', [
                    'app' => self::APP_ID,
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 迁移回滚兜底：逆序执行 down()，并删除 migrations 表记录
     */
    protected function rollbackMigrations(): void
    {
        $files = glob(__DIR__ . '/Migrations/*.php') ?: [];
        sort($files);

        foreach (array_reverse($files) as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            try {
                if (! DB::table('migrations')->where('migration', $name)->exists()) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $migration = require $file;
            if (! is_object($migration) || ! method_exists($migration, 'down')) {
                continue;
            }

            try {
                $migration->down();
                DB::table('migrations')->where('migration', $name)->delete();
            } catch (\Throwable $e) {
                Log::error('SMTP邮箱迁移回滚失败', [
                    'app' => self::APP_ID,
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 以 manifest.json 为唯一基准同步配置组/配置项（三向同步）
     *
     * 新增写默认值；已有仅更新结构、保留用户 value；已移除的清理残留。
     */
    protected function seedDefaultConfigs(): void
    {
        if (! class_exists(ConfigGroup::class) || ! class_exists(ConfigItem::class)) {
            return;
        }

        $path = __DIR__ . '/manifest.json';
        if (! file_exists($path)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $groups = $manifest['config_groups'] ?? [];
        if (! is_array($groups)) {
            return;
        }

        $manifestCodes = [];

        foreach ($groups as $groupIndex => $groupDef) {
            $groupName = (string) ($groupDef['name'] ?? '');
            $group = ConfigGroup::updateOrCreate(
                ['code' => self::CODE_PREFIX . $groupName],
                [
                    'name' => (string) ($groupDef['title'] ?? $groupName),
                    'app_id' => self::APP_ID,
                    'sort' => (int) $groupIndex,
                    'status' => 1,
                ]
            );

            foreach (($groupDef['items'] ?? []) as $index => $itemDef) {
                $itemName = (string) ($itemDef['name'] ?? '');
                $code = self::CODE_PREFIX . $itemName;
                $manifestCodes[] = $code;

                $structure = [
                    'group_id' => $group->id,
                    'name' => (string) ($itemDef['title'] ?? $itemName),
                    'type' => (string) ($itemDef['type'] ?? 'text'),
                    'tips' => (string) ($itemDef['tips'] ?? ''),
                    'sort' => (int) $index,
                    'status' => 1,
                ];

                $existing = ConfigItem::where('code', $code)->first();
                if ($existing !== null) {
                    $existing->update($structure);
                    continue;
                }

                ConfigItem::create(array_merge($structure, [
                    'code' => $code,
                    'value' => (string) ($itemDef['value'] ?? ''),
                ]));
            }
        }

        ConfigItem::where('code', 'like', self::CODE_PREFIX . '%')
            ->whereNotIn('code', $manifestCodes)
            ->delete();

        ConfigGroup::where('app_id', self::APP_ID)->whereDoesntHave('items')->delete();

        $this->forgetConfigCache();
    }

    /**
     * 初始化两套内置模板（仅创建，不覆盖用户已编辑的内容）
     */
    protected function seedBuiltinTemplates(): void
    {
        $templates = [
            [
                'code' => 'verify_code',
                'name' => '邮箱验证码',
                'category' => 'system',
                'subject' => '【{{site_name}}】邮箱验证码',
                'body_html' => '<div style="max-width:560px;margin:0 auto;padding:32px;font-family:Arial,\'Microsoft YaHei\',sans-serif;color:#333">'
                    . '<h2 style="margin:0 0 16px;font-size:20px">{{site_name}}</h2>'
                    . '<p>您好，您正在进行邮箱验证，验证码为：</p>'
                    . '<div style="margin:24px 0;padding:16px;background:#f5f7fa;border-radius:6px;text-align:center;font-size:28px;letter-spacing:6px;font-weight:bold;color:#1e6fff">{{code}}</div>'
                    . '<p style="color:#666">验证码 {{minutes}} 分钟内有效，请勿转发给他人。</p>'
                    . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0">'
                    . '<p style="color:#999;font-size:12px">本邮件由系统自动发送，请勿直接回复。&copy; {{year}} {{site_name}}</p>'
                    . '</div>',
                'body_text' => "您好，您正在进行邮箱验证。\n验证码：{{code}}\n验证码 {{minutes}} 分钟内有效，请勿转发给他人。\n\n{{site_name}}",
                'variables' => [
                    ['name' => 'code', 'label' => '验证码'],
                    ['name' => 'minutes', 'label' => '有效分钟数'],
                    ['name' => 'nickname', 'label' => '用户昵称'],
                ],
            ],
            [
                'code' => 'password_reset',
                'name' => '找回密码验证码',
                'category' => 'system',
                'subject' => '【{{site_name}}】找回密码',
                'body_html' => '<div style="max-width:560px;margin:0 auto;padding:32px;font-family:Arial,\'Microsoft YaHei\',sans-serif;color:#333">'
                    . '<h2 style="margin:0 0 16px;font-size:20px">{{site_name}} · 找回密码</h2>'
                    . '<p>{{nickname}} 您好，我们收到重置密码的申请，验证码为：</p>'
                    . '<div style="margin:24px 0;padding:16px;background:#f5f7fa;border-radius:6px;text-align:center;font-size:28px;letter-spacing:6px;font-weight:bold;color:#1e6fff">{{code}}</div>'
                    . '<p style="color:#666">验证码 {{minutes}} 分钟内有效。若非本人操作，请忽略本邮件。</p>'
                    . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0">'
                    . '<p style="color:#999;font-size:12px">本邮件由系统自动发送，请勿直接回复。&copy; {{year}} {{site_name}}</p>'
                    . '</div>',
                'body_text' => "{{nickname}} 您好，我们收到重置密码的申请。\n验证码：{{code}}\n验证码 {{minutes}} 分钟内有效。若非本人操作，请忽略本邮件。\n\n{{site_name}}",
                'variables' => [
                    ['name' => 'code', 'label' => '验证码'],
                    ['name' => 'minutes', 'label' => '有效分钟数'],
                    ['name' => 'nickname', 'label' => '用户昵称'],
                ],
            ],
            [
                'code' => 'welcome',
                'name' => '新用户欢迎邮件',
                'category' => 'system',
                'subject' => '【{{site_name}}】欢迎加入，{{nickname}}',
                'body_html' => '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#1f2329">'
                    . '<h2>欢迎加入 {{site_name}}</h2>'
                    . '<p>您好，{{nickname}}：</p>'
                    . '<p>您的账号已创建成功，现在可以登录并开始使用全部功能。</p>'
                    . '<p style="color:#86909c">如有疑问，直接回复本邮件即可联系我们。</p>'
                    . '<hr>'
                    . '<p style="color:#86909c">&copy; {{year}} {{site_name}}</p></div>',
                'body_text' => "欢迎加入 {{site_name}}\n\n您好，{{nickname}}：\n您的账号已创建成功，现在可以登录并开始使用全部功能。\n\n© {{year}} {{site_name}}",
                'variables' => [
                    ['name' => 'nickname', 'label' => '用户昵称'],
                    ['name' => 'site_name', 'label' => '站点名称'],
                ],
            ],
            [
                'code' => 'notice',
                'name' => '系统通知公告',
                'category' => 'system',
                'subject' => '【{{site_name}}】{{title}}',
                'body_html' => '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#1f2329">'
                    . '<h2>{{title}}</h2>'
                    . '<p>{{content}}</p>'
                    . '<p style="color:#86909c">本邮件由 {{site_name}} 自动发送，请勿直接回复。</p></div>',
                'body_text' => "{{title}}\n\n{{content}}\n\n本邮件由 {{site_name}} 自动发送，请勿直接回复。",
                'variables' => [
                    ['name' => 'title', 'label' => '通知标题'],
                    ['name' => 'content', 'label' => '通知正文'],
                ],
            ],
        ];

        foreach ($templates as $item) {
            if (SmtpmailTemplate::where('code', $item['code'])->exists()) {
                continue;
            }

            SmtpmailTemplate::create(array_merge($item, [
                'variables' => $item['variables'],
                'account_id' => null,
                'status' => 1,
                'is_system' => 1,
                'remark' => '系统内置模板，可在后台可视化编辑；is_system 模板禁止删除',
            ]));
        }
    }

    /**
     * 卸载时清理本应用配置组与配置项
     */
    protected function removeConfigs(): void
    {
        if (! class_exists(ConfigGroup::class) || ! class_exists(ConfigItem::class)) {
            return;
        }

        $groupIds = ConfigGroup::where('app_id', self::APP_ID)->pluck('id');
        if ($groupIds->isNotEmpty()) {
            ConfigItem::whereIn('group_id', $groupIds->toArray())->delete();
            ConfigGroup::whereIn('id', $groupIds->toArray())->delete();
        }
    }

    /**
     * 清理本应用配置缓存
     */
    protected function forgetConfigCache(): void
    {
        foreach (array_keys((array) config('apps.cmspro.smtpmail', [])) as $key) {
            Cache::forget('smtpmail:config:' . $key);
        }

        Cache::forget('smtpmail:takeover:account');
    }
}
