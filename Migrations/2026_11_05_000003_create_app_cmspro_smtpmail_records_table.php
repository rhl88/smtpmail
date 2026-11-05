<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP邮箱 · 发送记录（日志）表
 *
 * 每条发送落一条记录，保存渲染后的正文快照与完整收件人信息，便于追溯与重试。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_smtpmail_records')) {
            return;
        }

        Schema::create('app_cmspro_smtpmail_records', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('template_id')->nullable()->comment('来源模板ID，直发时为空');
            $table->string('template_code', 100)->default('')->comment('来源模板编码，直发时为空');
            $table->unsignedBigInteger('account_id')->nullable()->comment('实际使用的发信账号ID');
            $table->unsignedBigInteger('batch_id')->nullable()->comment('所属批量任务ID');
            $table->string('subject', 255)->comment('邮件主题');
            $table->longText('body_html')->nullable()->comment('渲染后的 HTML 正文快照');
            $table->text('body_text')->nullable()->comment('渲染后的纯文本正文快照');
            $table->json('to_addresses')->comment('收件人地址数组');
            $table->json('cc')->nullable()->comment('抄送地址数组');
            $table->json('bcc')->nullable()->comment('密送地址数组');
            $table->json('reply_to')->nullable()->comment('回复到地址数组');
            $table->string('from_address', 150)->default('')->comment('发件人地址');
            $table->string('from_name', 100)->default('')->comment('发件人名称');
            $table->json('attachments')->nullable()->comment('附件数组 [{"path":"相对路径","name":"显示名"}]');
            $table->json('headers')->nullable()->comment('自定义邮件头键值对');
            $table->unsignedTinyInteger('priority')->default(3)->comment('优先级：1-最高 3-普通 5-最低');
            $table->string('status', 20)->default('pending')->comment('状态：pending-待发送 queued-队列中 scheduled-定时 sending-发送中 success-成功 failed-失败 canceled-取消');
            $table->timestamp('scheduled_at')->nullable()->comment('计划发送时间');
            $table->timestamp('sent_at')->nullable()->comment('实际发送完成时间');
            $table->unsignedInteger('retry_count')->default(0)->comment('已重试次数');
            $table->text('error')->nullable()->comment('最近一次失败原因');
            $table->string('source', 100)->default('')->comment('调用来源：应用ID或 admin/api');
            $table->string('operator', 100)->default('')->comment('操作人标识');
            $table->string('ip', 45)->default('')->comment('发起 IP');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('status', 'idx_cmspro_smtpmail_records_status');
            $table->index(['status', 'scheduled_at'], 'idx_cmspro_smtpmail_records_due');
            $table->index('create_time', 'idx_cmspro_smtpmail_records_create');
            $table->index('batch_id', 'idx_cmspro_smtpmail_records_batch');
            $table->index('template_code', 'idx_cmspro_smtpmail_records_template');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `app_cmspro_smtpmail_records` COMMENT 'SMTP邮箱-发送记录表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_smtpmail_records');
    }
};
