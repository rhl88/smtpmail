<?php

use App\Apps\CmsproSmtpmail\Controllers\Home\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMTP邮箱 · 前台退订页
|--------------------------------------------------------------------------
| 前缀 smtpmail，中间件 web，公开访问；路径已在 manifest.json home_routes 声明
*/

Route::get('/unsubscribe', [UnsubscribeController::class, 'index'])->name('unsubscribe');
