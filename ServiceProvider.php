<?php

namespace App\Apps\CmsproSmtpmail;

use App\Apps\CmsproSmtpmail\Console\Commands\CleanRecords;
use App\Apps\CmsproSmtpmail\Console\Commands\DispatchDueMails;
use App\Apps\CmsproSmtpmail\Listeners\ApplySystemMailTakeover;
use App\Apps\CmsproSmtpmail\Services\AccountService;
use App\Apps\CmsproSmtpmail\Services\MailerFactory;
use App\Apps\CmsproSmtpmail\Services\TakeoverContext;
use App\Services\HookManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * SMTP邮箱 · 服务提供者
 *
 * 注册内容：应用级 helpers、默认配置、后台/接口/退订三组路由、视图命名空间、
 * 钩子、Artisan 命令、调度任务、系统邮件接管开关、邮件发送事件监听。
 *
 * 关键约束：本 Provider 不修改任何框架文件。接管开关通过「运行时注入 mail 配置
 * + 视图查找优先路径 + MessageSending 事件改写主题」实现；应用自身发信则通过
 * MailerFactory 构造完全独立的 Mailer，不读写框架 mail_* 配置项。
 *
 * 系统 AppServiceProvider 在 $this->app->booted() 回调中注册本 Provider，
 * 因此 boot() 阶段数据库已就绪，可安全读取配置与账号。
 */
class ServiceProvider extends BaseServiceProvider
{
    /** 应用根目录相对路径 */
    private const APP_PATH = 'app/Apps/CmsproSmtpmail';

    /** 应用 ID */
    private const APP_ID = 'cmspro.smtpmail';

    /** 应用配置命名空间 */
    private const CONFIG_KEY = 'apps.cmspro.smtpmail';

    /** 视图命名空间 */
    private const VIEW_NAMESPACE = 'cmspro.smtpmail';

    /**
     * 注册阶段：只做绑定，不查询数据库
     */
    public function register(): void
    {
        $helpers = base_path(self::APP_PATH . '/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $this->mergeConfigFrom(
            base_path(self::APP_PATH . '/Config/smtpmail.php'),
            self::CONFIG_KEY
        );

        // 接管上下文按请求隔离，避免常驻进程（Octane）跨请求污染
        $this->app->singleton(TakeoverContext::class, fn () => new TakeoverContext());

        // 工厂单例：保证测试注入的假传输层、运行时的自定义配置在同一请求内生效
        $this->app->singleton(MailerFactory::class, fn () => new MailerFactory());
    }

    /**
     * 启动阶段
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->loadViews();
        $this->registerHooks();
        $this->registerEvents();
        $this->registerCommands();
        $this->registerSchedule();
        $this->registerTakeover();
    }

    /**
     * 注册路由：后台管理端、外部 HTTP 接口、前台退订页
     */
    protected function registerRoutes(): void
    {
        // 后台：页面 + api/ 子前缀下的数据接口
        Route::prefix('admin/cmspro/smtpmail')
            ->middleware(['web', 'auth:admin', 'permission:admin'])
            ->name('cmspro.smtpmail.admin.')
            ->group(base_path(self::APP_PATH . '/Routes/admin.php'));

        // 外部接口：不走后台登录态，由控制器校验令牌，throttle 限流
        Route::prefix('api/smtpmail')
            ->middleware(['throttle:60,1'])
            ->name('cmspro.smtpmail.api.')
            ->group(base_path(self::APP_PATH . '/Routes/api.php'));

        // 前台退订页：公开访问
        Route::prefix('smtpmail')
            ->middleware(['web'])
            ->name('cmspro.smtpmail.home.')
            ->group(base_path(self::APP_PATH . '/Routes/home.php'));
    }

    /**
     * 注册视图命名空间（cmspro.smtpmail::Admin.xxx.index）
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(base_path(self::APP_PATH . '/Views'), self::VIEW_NAMESPACE);
    }

    /**
     * 注册钩子（系统通道 + 应用通道双保险，按 app_id 去重）
     */
    protected function registerHooks(): void
    {
        try {
            $hookManager = $this->app->make(HookManager::class);
            $registered = $hookManager->getRegisteredHooks(self::APP_ID);

            if (! empty($registered['actions']) || ! empty($registered['filters'])) {
                return;
            }

            (new Hooks())->register($hookManager);
        } catch (\Throwable $e) {
            // 钩子注册失败不阻断应用启动
        }
    }

    /**
     * 注册系统邮件接管监听器
     */
    protected function registerEvents(): void
    {
        Event::listen(MessageSending::class, ApplySystemMailTakeover::class);
    }

    /**
     * 注册应用级 Artisan 命令
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DispatchDueMails::class,
            CleanRecords::class,
        ]);
    }

    /**
     * 注册调度任务（不侵入框架 routes/console.php）
     *
     * 由运维已有的 artisan schedule:run 驱动：每分钟派发到期/待重试邮件，
     * 每日凌晨清理过期日志。
     */
    protected function registerSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('smtpmail:dispatch-due')->everyMinute()->withoutOverlapping();
            $schedule->command('smtpmail:clean-records')->dailyAt('03:20')->withoutOverlapping();
        });
    }

    /**
     * 系统邮件接管开关
     *
     * 开启且有启用的默认账号时：
     * 1. 运行时覆盖 mail.default / mail.mailers.smtp.* / mail.from.*（仅内存，不改配置文件）
     * 2. 把 Views/Override 加入视图查找最高优先级，替换 emails.register-code 与
     *    emails.password-reset-code 两个系统视图，正文改由本应用模板渲染
     * 3. MessageSending 监听器据接管上下文改写主题与纯文本正文
     *
     * 关闭时不做任何注入，系统邮件保持原生行为。
     */
    protected function registerTakeover(): void
    {
        try {
            if ((string) smtpmail_config('takeover_system_mail', '0') !== '1') {
                return;
            }

            /** @var AccountService $accountService */
            $accountService = $this->app->make(AccountService::class);
            $account = $accountService->defaultAccount();

            if ($account === null) {
                return; // 未配置默认账号：不接管，系统邮件保持原生行为
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => (string) $account->host,
                'mail.mailers.smtp.port' => (int) $account->port,
                'mail.mailers.smtp.username' => (string) $account->username,
                'mail.mailers.smtp.password' => $accountService->decryptPassword($account),
                'mail.mailers.smtp.scheme' => $account->scheme === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.timeout' => (int) $account->timeout,
                'mail.from.address' => (string) $account->from_address,
                'mail.from.name' => (string) $account->from_name,
            ]);

            app('view')->getFinder()->prependLocation(base_path(self::APP_PATH . '/Views/Override'));
        } catch (\Throwable $e) {
            // 接管配置异常时不影响系统启动，系统邮件回退原生行为
        }
    }
}
