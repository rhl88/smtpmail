<?php

namespace App\Apps\CmsproSmtpmail\Controllers\Admin;

use App\Apps\CmsproSmtpmail\Controllers\BaseAdminController;
use App\Apps\CmsproSmtpmail\Services\RecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * 统计看板
 */
class DashboardController extends BaseAdminController
{
    public function index(): View
    {
        return view('cmspro.smtpmail::Admin.dashboard.index');
    }

    public function stats(): JsonResponse
    {
        return $this->run(fn () => $this->success(app(RecordService::class)->stats(7)));
    }
}
