<?php

use App\Apps\CmsproSmtpmail\Controllers\Admin\AccountController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\BatchController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\BlacklistController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\DashboardController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\RecordController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\SendController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\SettingController;
use App\Apps\CmsproSmtpmail\Controllers\Admin\TemplateController;
use App\Apps\CmsproSmtpmail\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMTP邮箱 · 后台管理端路由
|--------------------------------------------------------------------------
| 挂载信息（ServiceProvider::registerRoutes）：
|   前缀 admin/cmspro/smtpmail，中间件 ['web','auth:admin','permission:admin']
| 页面路由直接挂根；数据接口统一放 api/ 子前缀，命中系统 CSRF 白名单。
| 所有 {id} 均加 whereNumber 约束，静态路径一律放在动态路径之前。
*/

Route::get('/', fn () => redirect()->route('cmspro.smtpmail.admin.send.index'))->name('home');

// 一、页面路由（后台菜单 iframe 加载）
Route::get('/send', [SendController::class, 'index'])->name('send.index');
Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
Route::get('/records', [RecordController::class, 'index'])->name('records.index');
Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
Route::get('/blacklist', [BlacklistController::class, 'index'])->name('blacklist.index');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');

// 二、数据接口（强制 JSON 响应：validate 失败返回 422 JSON，禁止 302 重定向）
Route::prefix('api')->middleware(ForceJsonResponse::class)->name('api.')->group(function () {
    // 发信账号
    Route::get('/accounts/options', [AccountController::class, 'options'])->name('accounts.options');
    Route::get('/accounts', [AccountController::class, 'list'])->name('accounts.list');
    Route::get('/accounts/{id}', [AccountController::class, 'show'])->whereNumber('id')->name('accounts.show');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{id}', [AccountController::class, 'update'])->whereNumber('id')->name('accounts.update');
    Route::delete('/accounts/{id}', [AccountController::class, 'destroy'])->whereNumber('id')->name('accounts.destroy');
    Route::post('/accounts/{id}/default', [AccountController::class, 'setDefault'])->whereNumber('id')->name('accounts.default');
    Route::post('/accounts/{id}/test', [AccountController::class, 'test'])->whereNumber('id')->name('accounts.test');

    // 邮件模板
    Route::get('/templates/options', [TemplateController::class, 'options'])->name('templates.options');
    Route::get('/templates', [TemplateController::class, 'list'])->name('templates.list');
    Route::get('/templates/{id}', [TemplateController::class, 'show'])->whereNumber('id')->name('templates.show');
    Route::post('/templates/{id}/duplicate', [TemplateController::class, 'duplicate'])->whereNumber('id')->name('templates.duplicate');
    Route::post('/templates/{id}/toggle', [TemplateController::class, 'toggle'])->whereNumber('id')->name('templates.toggle');
    Route::post('/templates/{id}/preview', [TemplateController::class, 'preview'])->whereNumber('id')->name('templates.preview');
    Route::post('/templates/{id}/test', [TemplateController::class, 'test'])->whereNumber('id')->name('templates.test');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{id}', [TemplateController::class, 'update'])->whereNumber('id')->name('templates.update');
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->whereNumber('id')->name('templates.destroy');

    // 发送邮件
    Route::get('/send/options', [SendController::class, 'options'])->name('send.options');
    Route::post('/send/preview', [SendController::class, 'preview'])->name('send.preview');
    Route::post('/send/upload', [SendController::class, 'upload'])->name('send.upload');
    Route::post('/send/attachments/resolve', [SendController::class, 'resolveAttachments'])->name('send.resolve');
    Route::post('/send', [SendController::class, 'send'])->name('send.send');

    // 发送日志
    Route::get('/records/export', [RecordController::class, 'export'])->name('records.export');
    Route::get('/records', [RecordController::class, 'list'])->name('records.list');
    Route::get('/records/{id}', [RecordController::class, 'show'])->whereNumber('id')->name('records.show');
    Route::post('/records/{id}/retry', [RecordController::class, 'retry'])->whereNumber('id')->name('records.retry');
    Route::post('/records/{id}/cancel', [RecordController::class, 'cancel'])->whereNumber('id')->name('records.cancel');
    Route::delete('/records/{id}', [RecordController::class, 'destroy'])->whereNumber('id')->name('records.destroy');

    // 批量群发
    Route::get('/batches', [BatchController::class, 'list'])->name('batches.list');
    Route::post('/batches', [BatchController::class, 'store'])->name('batches.store');
    Route::post('/batches/{id}/cancel', [BatchController::class, 'cancel'])->whereNumber('id')->name('batches.cancel');

    // 黑名单
    Route::get('/blacklist', [BlacklistController::class, 'list'])->name('blacklist.list');
    Route::post('/blacklist', [BlacklistController::class, 'store'])->name('blacklist.store');
    Route::delete('/blacklist/{id}', [BlacklistController::class, 'destroy'])->whereNumber('id')->name('blacklist.destroy');

    // 统计看板
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // 应用设置
    Route::get('/settings/takeover-status', [SettingController::class, 'takeoverStatus'])->name('settings.takeover');
    Route::get('/settings', [SettingController::class, 'getSettings'])->name('settings.get');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});
