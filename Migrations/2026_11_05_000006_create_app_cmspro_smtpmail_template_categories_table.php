<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP邮箱 · 模板分类表
 *
 * 独立维护模板分类（名称唯一），供模板列表「分类管理」与新建/编辑模板下拉选择；
 * templates.category 字段仍存分类名称文本，历史自由文本分类在选项接口中合并兼容。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_smtpmail_template_categories')) {
            return;
        }

        Schema::create('app_cmspro_smtpmail_template_categories', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('name', 100)->comment('分类名称，应用内唯一');
            $table->unsignedInteger('sort')->default(0)->comment('排序值，越小越靠前');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique('name', 'uk_cmspro_smtpmail_tpl_categories_name');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_cmspro_smtpmail_template_categories` COMMENT 'SMTP邮箱-模板分类表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_smtpmail_template_categories');
    }
};
