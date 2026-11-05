<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP邮箱 · 批量群发任务表
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_smtpmail_batches')) {
            return;
        }

        Schema::create('app_cmspro_smtpmail_batches', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('name', 100)->comment('任务名称');
            $table->string('template_code', 100)->comment('使用的模板编码');
            $table->unsignedInteger('total_count')->default(0)->comment('收件人总数');
            $table->unsignedInteger('sent_count')->default(0)->comment('已投递数');
            $table->unsignedInteger('success_count')->default(0)->comment('成功数');
            $table->unsignedInteger('failed_count')->default(0)->comment('失败数');
            $table->string('status', 20)->default('pending')->comment('状态：pending-待处理 running-进行中 finished-已完成 canceled-已取消');
            $table->timestamp('scheduled_at')->nullable()->comment('计划开始时间');
            $table->timestamp('finished_at')->nullable()->comment('完成时间');
            $table->string('remark', 255)->default('')->comment('备注说明');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('status', 'idx_cmspro_smtpmail_batches_status');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_cmspro_smtpmail_batches` COMMENT 'SMTP邮箱-批量群发任务表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_smtpmail_batches');
    }
};
