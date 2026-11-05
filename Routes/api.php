<?php

use App\Apps\CmsproSmtpmail\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMTP邮箱 · 外部 HTTP 接口
|--------------------------------------------------------------------------
| 前缀 api/smtpmail，中间件 throttle:60,1；令牌由 ApiController 校验
| 请求头：X-Smtpmail-Token: <应用设置中的接口令牌>
| 开关：应用设置「开启 HTTP 接口」且令牌非空时才可用
*/

Route::post('/send', [ApiController::class, 'send'])->name('send');
Route::get('/templates', [ApiController::class, 'templates'])->name('templates');
Route::get('/records', [ApiController::class, 'records'])->name('records');
Route::get('/status', [ApiController::class, 'status'])->name('status');
