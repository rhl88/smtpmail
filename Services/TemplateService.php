<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * SMTP邮箱 · 邮件模板服务
 *
 * 模板编码是跨应用调用的唯一键；系统内置模板（is_system=1）可编辑但禁止删除。
 */
class TemplateService
{
    /**
     * 内置默认变量定义（渲染时由 TemplateRenderer::withDefaults() 自动注入默认值）
     *
     * 中文名统一在此维护并经接口下发，前端变量表单的内置变量 label 以此为准，
     * 不再依赖各模板声明中写死的 label，新增内置变量只需维护本方法。
     *
     * @return array<int,array<string,string>>
     */
    public static function builtinVariables(): array
    {
        return [
            ['name' => 'site_name', 'label' => '站点名称'],
            ['name' => 'year', 'label' => '年'],
            ['name' => 'date', 'label' => '日期'],
            ['name' => 'datetime', 'label' => '日期时间'],
        ];
    }

    public function paginate(?string $keyword, ?string $category, int $pageSize = 15): LengthAwarePaginator
    {
        $query = SmtpmailTemplate::query()->keyword($keyword);

        $category = trim((string) $category);
        if ($category !== '') {
            $query->where('category', $category);
        }

        return $query->orderByDesc('is_system')->orderBy('id')->paginate($pageSize);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function options(): array
    {
        return SmtpmailTemplate::query()->enabled()->orderByDesc('is_system')->orderBy('id')
            ->get()
            ->map(fn (SmtpmailTemplate $t) => [
                'code' => (string) $t->code,
                'name' => (string) $t->name,
                'subject' => (string) $t->subject,
                'variables' => $t->variables ?? [],
                'extracted_variables' => $t->extractVariables(),
                'is_system' => (int) $t->is_system,
            ])->all();
    }

    public function find(int $id): SmtpmailTemplate
    {
        $template = SmtpmailTemplate::find($id);
        if ($template === null) {
            throw new SmtpmailException('邮件模板不存在');
        }

        return $template;
    }

    public function findByCode(string $code): ?SmtpmailTemplate
    {
        return SmtpmailTemplate::query()->where('code', trim($code))->where('status', 1)->first();
    }

    /**
     * 预览渲染结果（允许渲染禁用模板，不影响数据）
     *
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    public function preview(int $id, array $vars = []): array
    {
        return app(TemplateRenderer::class)->renderTemplate($this->find($id), $vars);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): SmtpmailTemplate
    {
        $payload = $this->normalize($data, true);
        $this->assertCodeAvailable($payload['code']);

        return SmtpmailTemplate::create($payload);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): SmtpmailTemplate
    {
        $template = $this->find($id);
        $payload = $this->normalize($data, false);

        if (isset($payload['code'])) {
            $this->assertCodeAvailable($payload['code'], $id);
            // 系统内置模板编码被系统邮件映射引用，禁止修改
            if ((int) $template->is_system === 1 && $payload['code'] !== $template->code) {
                throw new SmtpmailException('系统内置模板编码不可修改');
            }
        }

        // 系统模板的分类固定为 system，is_system 字段不允许通过接口改动
        if ((int) $template->is_system === 1) {
            $payload['category'] = 'system';
        }

        $template->update($payload);

        return $template->fresh();
    }

    public function delete(int $id): void
    {
        $template = $this->find($id);
        if ((int) $template->is_system === 1) {
            throw new SmtpmailException('系统内置模板禁止删除，可将其设为禁用');
        }

        $template->delete();
    }

    /**
     * 复制模板（自动生成新编码）
     */
    public function duplicate(int $id): SmtpmailTemplate
    {
        $source = $this->find($id);
        $base = $source->code . '_copy';
        $code = $base;
        $index = 1;
        while (SmtpmailTemplate::query()->where('code', $code)->exists()) {
            $code = $base . $index++;
        }

        return DB::transaction(function () use ($source, $code): SmtpmailTemplate {
            return SmtpmailTemplate::create([
                'code' => $code,
                'name' => $source->name . '（副本）',
                'category' => 'custom',
                'subject' => $source->subject,
                'body_html' => $source->body_html,
                'body_text' => $source->body_text,
                'variables' => $source->variables,
                'account_id' => $source->account_id,
                'status' => 0,
                'is_system' => 0,
                'remark' => '复制自模板 ' . $source->code,
            ]);
        });
    }

    public function toggle(int $id): SmtpmailTemplate
    {
        $template = $this->find($id);
        $template->update(['status' => (int) $template->status === 1 ? 0 : 1]);

        return $template->fresh();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function normalize(array $data, bool $creating): array
    {
        $fields = [
            'code', 'name', 'category', 'subject', 'body_html', 'body_text',
            'variables', 'account_id', 'status', 'remark',
        ];

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = is_string($data[$field]) ? $data[$field] : $data[$field];
            }
        }

        if (isset($payload['code'])) {
            $payload['code'] = trim((string) $payload['code']);
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $payload['code'])) {
                throw new SmtpmailException('模板编码仅允许小写字母、数字与下划线，且必须以字母开头');
            }
        }

        if (isset($payload['variables']) && is_string($payload['variables'])) {
            $decoded = json_decode($payload['variables'], true);
            $payload['variables'] = is_array($decoded) ? $decoded : [];
        }

        if (isset($payload['account_id'])) {
            $payload['account_id'] = $payload['account_id'] === '' || $payload['account_id'] === null
                ? null
                : (int) $payload['account_id'];
        }

        foreach (['status'] as $intField) {
            if (isset($payload[$intField])) {
                $payload[$intField] = (int) $payload[$intField];
            }
        }

        if (! $creating) {
            // 更新入口兜底校验：关键字段一旦出现，传空即拦截，避免把名称/主题/正文清空
            foreach (['name', 'subject', 'body_html'] as $required) {
                if (array_key_exists($required, $payload) && trim((string) $payload[$required]) === '') {
                    throw new SmtpmailException('请完整填写模板名称、主题与 HTML 正文');
                }
            }
        }

        if ($creating) {
            foreach (['code', 'name', 'subject', 'body_html'] as $required) {
                if (empty($payload[$required])) {
                    throw new SmtpmailException('请完整填写模板编码、名称、主题与 HTML 正文');
                }
            }

            $payload += [
                'category' => 'custom',
                'body_text' => '',
                'variables' => [],
                'account_id' => null,
                'status' => 1,
                'is_system' => 0,
                'remark' => '',
            ];
        }

        unset($payload['is_system']);

        return $payload;
    }

    protected function assertCodeAvailable(string $code, ?int $excludeId = null): void
    {
        $query = SmtpmailTemplate::query()->where('code', $code);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new SmtpmailException('模板编码已存在：' . $code);
        }
    }
}
