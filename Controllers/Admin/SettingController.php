<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use App\Models\ConfigItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 应用设置（以 manifest.json 的 config_groups 为唯一基准）
 */
class SettingController extends BaseAdminController
{
    private const PERMISSION = 'cmspro.smtpmail.setting';

    private const CODE_PREFIX = 'app_cmspro_smtpmail_';

    /**
     * 设置白名单：与 manifest.json config_groups 声明的 12 个配置项一一对应，
     * 仅接受明确列出的键，防止前端传入任意 code 篡改其他应用配置（12A.3）
     */
    private const ALLOWED_KEYS = [
        'takeover_system_mail',
        'use_queue',
        'max_retries',
        'retry_delay',
        'rate_limit',
        'log_retention_days',
        'site_name',
        'enable_blacklist',
        'api_enabled',
        'api_token',
        'template_verify_code',
        'template_password_reset',
    ];

    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.settings.index');
    }

    /**
     * 读取全部配置项（扁平数组，含 group 供前端分组）
     */
    public function getSettings(): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () {
            $items = [];
            foreach ($this->definitions() as $key => $def) {
                $code = self::CODE_PREFIX . $key;
                $row = ConfigItem::where('code', $code)->first();
                $value = $row !== null ? (string) $row->value : (string) ($def['value'] ?? '');

                $items[] = [
                    'key' => $key,
                    'name' => $key,
                    'group' => $def['group'],
                    'title' => $def['title'],
                    'type' => $def['type'],
                    'value' => $value,
                    'tips' => $def['tips'],
                ];
            }

            return $this->success($items);
        });
    }

    /**
     * 保存设置（ALLOWED_KEYS 白名单键；密钥类字段明文回显，清空输入框提交表示不修改）
     */
    public function update(Request $request): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () use ($request) {
            $input = $request->only(self::ALLOWED_KEYS);
            $changed = [];

            foreach ($this->definitions() as $key => $def) {
                if (! array_key_exists($key, $input)) {
                    continue;
                }

                $value = is_scalar($input[$key]) ? (string) $input[$key] : '';
                if ($def['type'] === 'password' && $value === '') {
                    continue; // 明文回显后清空提交视为不修改，避免误清空令牌
                }

                ConfigItem::updateOrCreate(
                    ['code' => self::CODE_PREFIX . $key],
                    [
                        'group_id' => $def['group_id'],
                        'name' => $def['title'],
                        'value' => $value,
                        'type' => $def['type'],
                        'tips' => $def['tips'],
                        'sort' => $def['sort'],
                        'status' => 1,
                    ]
                );

                $changed[] = $key;
            }

            smtpmail_forget_config_cache($changed);
            app(AccountService::class)->forgetCache();

            return $this->success(['changed' => $changed], '设置已保存并立即生效');
        });
    }

    /**
     * 接管开关生效状态（供设置页醒目提示）
     */
    public function takeoverStatus(): JsonResponse
    {
        if (! $this->hasPermission(self::PERMISSION)) {
            return $this->permissionDenied();
        }

        return $this->run(function () {
            $enabled = (string) smtpmail_config('takeover_system_mail', '0') === '1';
            $account = app(AccountService::class)->defaultAccount();
            $hasDefault = $account !== null;

            $message = ! $enabled
                ? '接管未开启，系统注册验证码与找回密码邮件保持原生行为'
                : ($hasDefault
                    ? '接管已生效：系统邮件改用默认账号「' . $account->name . '」与内置模板'
                    : '接管已开启但未配置默认发信账号，系统邮件仍保持原生行为');

            return $this->success([
                'enabled' => $enabled,
                'has_default_account' => $hasDefault,
                'effective' => $enabled && $hasDefault,
                'default_account' => $account?->name ?? '',
                'message' => $message,
            ]);
        });
    }

    /**
     * 从 manifest.json 读取配置项定义（键名 => 定义）
     *
     * @return array<string,array<string,mixed>>
     */
    protected function definitions(): array
    {
        $path = dirname(__DIR__, 2) . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true);
        $definitions = [];

        foreach (($manifest['config_groups'] ?? []) as $group) {
            $groupName = (string) ($group['name'] ?? '');
            $row = \App\Models\ConfigGroup::where('code', self::CODE_PREFIX . $groupName)->first();

            foreach (($group['items'] ?? []) as $index => $item) {
                $definitions[(string) $item['name']] = [
                    'group' => $groupName,
                    'group_id' => $row?->id,
                    'title' => (string) ($item['title'] ?? $item['name']),
                    'type' => (string) ($item['type'] ?? 'text'),
                    'value' => (string) ($item['value'] ?? ''),
                    'tips' => (string) ($item['tips'] ?? ''),
                    'sort' => (int) $index,
                ];
            }
        }

        return $definitions;
    }
}
