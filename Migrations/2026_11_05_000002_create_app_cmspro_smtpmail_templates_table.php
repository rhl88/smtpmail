<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP邮箱 · 邮件模板表
 *
 * 支持 {{变量}} 占位符、HTML/纯文本双正文、绑定指定发信账号。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_smtpmail_templates')) {
            return;
        }

        Schema::create('app_cmspro_smtpmail_templates', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('code', 100)->comment('模板编码，供调用方引用，应用内唯一');
            $table->string('name', 100)->comment('模板名称');
            $table->string('category', 50)->default('custom')->comment('分类：system-系统内置 custom-自定义');
            $table->string('subject', 255)->comment('邮件主题，支持 {{变量}} 占位符');
            $table->longText('body_html')->comment('HTML 正文，支持 {{变量}} 占位符');
            $table->text('body_text')->nullable()->comment('纯文本正文，支持 {{变量}} 占位符');
            $table->json('variables')->nullable()->comment('变量定义 [{"name":"code","label":"验证码"}]');
            $table->unsignedBigInteger('account_id')->nullable()->comment('绑定的发信账号ID，空表示使用默认账号');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用');
            $table->unsignedTinyInteger('is_system')->default(0)->comment('是否系统内置：0-否 1-是（禁止删除）');
            $table->string('remark', 255)->default('')->comment('备注说明');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique('code', 'uk_cmspro_smtpmail_templates_code');
            $table->index(['status', 'category'], 'idx_cmspro_smtpmail_templates_status');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_cmspro_smtpmail_templates` COMMENT 'SMTP邮箱-邮件模板表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_smtpmail_templates');
    }
};
