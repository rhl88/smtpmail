<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Home;

use App\Apps\CmsproSmtpmail\Services\BlacklistService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SMTP邮箱 · 前台一键退订
 */
class UnsubscribeController extends Controller
{
    public function index(Request $request): View
    {
        $token = trim((string) $request->input('token', ''));

        try {
            app(BlacklistService::class)->unsubscribe($token);
            $success = true;
            $message = '退订成功，后续将不再向该邮箱发送营销类邮件。';
        } catch (\Throwable $e) {
            $success = false;
            $message = $e->getMessage() ?: '退订失败，请检查链接是否完整。';
        }

        return view('cmspro.smtpmail::Home.unsubscribe', [
            'success' => $success,
            'message' => $message,
        ]);
    }
}
