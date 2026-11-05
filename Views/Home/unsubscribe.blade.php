<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>邮件退订结果</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <style>
        /* 前台退订结果页：简洁居中卡片，样式内联不依赖外部资源 */
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body { font-family: "Microsoft YaHei", Arial, sans-serif; background: #f2f4f7; color: #333; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .box { width: 100%; max-width: 460px; background: #fff; border-radius: 6px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08); padding: 36px 30px; text-align: center; }
        .icon { font-size: 56px; line-height: 1; margin-bottom: 18px; }
        .icon.ok { color: #52a532; }
        .icon.fail { color: #d9534f; }
        h1 { font-size: 20px; font-weight: 600; margin: 0 0 14px; }
        .message { font-size: 14px; line-height: 1.8; color: #555; margin: 0 0 18px; word-break: break-all; }
        .tip { font-size: 12px; color: #999; margin: 0; border-top: 1px solid #eee; padding-top: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="box">
        {{-- 根据处理结果展示对应图标与标题 --}}
        <div class="icon {{ $success ? 'ok' : 'fail' }}">
            <i class="fa {{ $success ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
        </div>
        <h1>{{ $success ? '退订成功' : '退订失败' }}</h1>
        <p class="message">{{ $message }}</p>
        <p class="tip">如非本人操作，可忽略此页面；如有疑问请联系站点管理员。</p>
    </div>
</div>
</body>
</html>
