<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP邮箱 · 退订黑名单表
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_smtpmail_blacklist')) {
            return;
        }

        Schema::create('app_cmspro_smtpmail_blacklist', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('email', 150)->comment('被屏蔽的邮箱地址');
            $table->string('reason', 255)->default('')->comment('加入原因：manual-后台添加 unsubscribe-用户退订 bounce-退信');
            $table->string('token', 64)->comment('退订令牌，用于生成一键退订链接');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-已移除 1-生效中');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique('email', 'uk_cmspro_smtpmail_blacklist_email');
            $table->unique('token', 'uk_cmspro_smtpmail_blacklist_token');
            $table->index('status', 'idx_cmspro_smtpmail_blacklist_status');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_cmspro_smtpmail_blacklist` COMMENT 'SMTP邮箱-退订黑名单表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_smtpmail_blacklist');
    }
};
