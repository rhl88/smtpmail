<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP邮箱 · 发信账号表
 *
 * 密码经 Laravel Crypt（APP_KEY 绑定）加密后存储，禁止明文落库。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_smtpmail_accounts')) {
            return;
        }

        Schema::create('app_cmspro_smtpmail_accounts', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('name', 100)->comment('账号名称，如：系统通知邮箱');
            $table->string('code', 100)->comment('账号编码，供其他应用指定发信账号，应用内唯一');
            $table->string('host', 150)->comment('SMTP 服务器地址');
            $table->unsignedInteger('port')->default(587)->comment('SMTP 端口，常见 25/465/587');
            $table->string('scheme', 10)->default('tls')->comment('加密方式：none-不加密 tls-STARTTLS ssl-隐式SSL');
            $table->string('username', 150)->default('')->comment('SMTP 登录账号，留空表示无需认证');
            $table->text('password')->nullable()->comment('SMTP 密码（Crypt 加密存储）');
            $table->string('from_address', 150)->comment('发件人邮箱地址');
            $table->string('from_name', 100)->default('')->comment('发件人显示名称');
            $table->string('reply_to', 150)->default('')->comment('默认回复到地址，留空则不设置');
            $table->unsignedInteger('timeout')->default(30)->comment('连接与发送超时秒数');
            $table->unsignedInteger('rate_limit')->default(0)->comment('该账号每分钟发信上限，0-使用全局限流');
            $table->unsignedTinyInteger('is_default')->default(0)->comment('是否默认账号：0-否 1-是');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用');
            $table->timestamp('last_test_at')->nullable()->comment('最近一次测试时间');
            $table->string('last_test_result', 500)->default('')->comment('最近一次测试结果说明');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique('code', 'uk_cmspro_smtpmail_accounts_code');
            $table->index(['status', 'is_default'], 'idx_cmspro_smtpmail_accounts_status');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_cmspro_smtpmail_accounts` COMMENT 'SMTP邮箱-发信账号表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_smtpmail_accounts');
    }
};
