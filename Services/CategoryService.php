<?php

namespace App\Apps\CmsproSmtpmail\Services;

use App\Apps\CmsproSmtpmail\Exceptions\SmtpmailException;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplate;
use App\Apps\CmsproSmtpmail\Models\SmtpmailTemplateCategory;
use Illuminate\Support\Facades\DB;

/**
 * SMTP邮箱 · 模板分类服务
 *
 * 分类名称应用内唯一；templates.category 仍存名称文本，
 * 历史自由文本分类在选项中合并返回，保证编辑老模板时下拉不丢值。
 */
class CategoryService
{
    /**
     * 全量分类列表（按排序值与 id 升序）
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        return SmtpmailTemplateCategory::query()
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->map(fn (SmtpmailTemplateCategory $c) => $c->toSummaryArray())
            ->all();
    }

    /**
     * 分类选项：分类表全量 + 模板表中已使用但未登记的历史分类合并去重
     *
     * @return array<int,array<string,string>>
     */
    public function options(): array
    {
        $names = SmtpmailTemplateCategory::query()
            ->orderBy('sort')->orderBy('id')
            ->pluck('name')
            ->map(fn ($n) => (string) $n)
            ->all();

        $used = SmtpmailTemplate::query()->where('category', '!=', '')
            ->distinct()->orderBy('category')
            ->pluck('category')
            ->map(fn ($n) => (string) $n)
            ->all();

        $merged = array_values(array_unique(array_merge($names, $used)));

        // value 与 name 同值，便于前端下拉组件直接复用
        return array_map(fn ($name) => ['name' => $name, 'value' => $name], $merged);
    }

    public function add(string $name): SmtpmailTemplateCategory
    {
        $name = $this->normalizeName($name);

        if (SmtpmailTemplateCategory::query()->where('name', $name)->exists()) {
            throw new SmtpmailException('分类名称「' . $name . '」已存在');
        }

        return SmtpmailTemplateCategory::create(['name' => $name]);
    }

    public function rename(int $id, string $name): SmtpmailTemplateCategory
    {
        $category = SmtpmailTemplateCategory::find($id);
        if ($category === null) {
            throw new SmtpmailException('模板分类不存在');
        }

        $name = $this->normalizeName($name);

        $exists = SmtpmailTemplateCategory::query()
            ->where('name', $name)
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            throw new SmtpmailException('分类名称「' . $name . '」已存在');
        }

        $oldName = (string) $category->name;
        $category->name = $name;
        $category->save();

        // 同步改名已使用旧名称的模板，保持分类归属不丢失
        if ($oldName !== $name) {
            SmtpmailTemplate::query()->where('category', $oldName)->update(['category' => $name]);
        }

        return $category;
    }

    public function remove(int $id): void
    {
        $category = SmtpmailTemplateCategory::find($id);
        if ($category === null) {
            throw new SmtpmailException('模板分类不存在');
        }

        $name = (string) $category->name;
        $used = SmtpmailTemplate::query()->where('category', $name)->count();
        if ($used > 0) {
            throw new SmtpmailException('有 ' . $used . ' 个模板正在使用该分类，请先调整模板分类后再删除');
        }

        $category->delete();
    }

    protected function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new SmtpmailException('请填写分类名称');
        }
        if (mb_strlen($name) > 100) {
            throw new SmtpmailException('分类名称不能超过 100 个字符');
        }

        return $name;
    }
}
