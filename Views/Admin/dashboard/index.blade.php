<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>统计看板 - SMTP邮箱</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .stat-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
        .stat-card { flex: 1 1 150px; min-width: 150px; background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: 16px 10px; text-align: center; }
        .stat-card .stat-value { font-size: 26px; font-weight: 600; color: #333; line-height: 1.4; }
        .stat-card .stat-label { font-size: 13px; color: #999; margin-top: 4px; }
        .stat-card.hl .stat-value { color: #1e6fff; }
        .stat-card.good .stat-value { color: #52a532; }
        .stat-card.bad .stat-value { color: #d9534f; }
        /* 近 7 日趋势：纯 CSS 柱状条，不依赖任何外部图表库 */
        .trend-chart { display: flex; align-items: flex-end; gap: 10px; height: 230px; padding: 18px 8px 0; border-bottom: 1px solid #e6e6e6; }
        .trend-item { flex: 1 1 0; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; }
        .trend-bars { flex: 1 1 auto; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 4px; }
        .trend-bar { width: 18px; border-radius: 3px 3px 0 0; position: relative; min-height: 2px; }
        .trend-bar.total { background: #5fb0f7; }
        .trend-bar.success { background: #67b93a; }
        .trend-bar .val { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 11px; color: #666; white-space: nowrap; }
        .trend-label { height: 30px; line-height: 30px; font-size: 12px; color: #999; white-space: nowrap; }
        .trend-legend { font-size: 12px; color: #999; padding-top: 8px; }
        .trend-legend .dot { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin: 0 4px 0 12px; vertical-align: middle; }
        .trend-legend .dot.total { background: #5fb0f7; }
        .trend-legend .dot.success { background: #67b93a; }
        .empty-tip { color: #999; font-size: 13px; padding: 40px 0; text-align: center; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    {{-- 工具栏 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <div class="layui-form-item" style="margin-bottom:0;">
                <span style="color:#666;font-size:13px;">邮件发送概览与近 7 日发送趋势。</span>
                <div class="layui-inline" style="float:right;">
                    {{-- 看板为只读数据，刷新按钮沿用应用设置权限码 --}}
                    <button type="button" class="layui-btn layui-btn-sm" id="btnRefresh" data-permission="cmspro.smtpmail.setting"><i class="fa fa-refresh"></i> 刷新</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 统计卡片 --}}
    <div class="stat-grid" id="statGrid"></div>

    {{-- 趋势图 --}}
    <div class="layui-card">
        <div class="layui-card-header"><i class="fa fa-bar-chart"></i> 近 7 日发送趋势</div>
        <div class="layui-card-body">
            <div class="trend-chart" id="trendChart"></div>
            <div class="trend-legend">
                <span class="dot total"></span>发送总数
                <span class="dot success"></span>成功数
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'layer'], function () {
    var $ = layui.jquery,
        layer = layui.layer;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        statusCode: {
            401: function () {
                layer.msg('登录已过期，请重新登录', { icon: 2, time: 1500 }, function () {
                    top.location.href = '/admin/login';
                });
            }
        }
    });

    var API = '/admin/cmspro/smtpmail/api';
    var PERM = 'cmspro.smtpmail.setting';

    // HTML 转义，避免拼接内容造成注入
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    // 按候选键取第一个存在的值，兼容不同统计字段命名
    function pick(o, keys, def) {
        for (var i = 0; i < keys.length; i++) {
            if (o && o[keys[i]] !== undefined && o[keys[i]] !== null) { return o[keys[i]]; }
        }
        return def;
    }

    // 权限判断与按钮隐藏（本页无表格，页面初始化后隐藏一次即可）
    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    // 成功率格式化：小数比例转换为百分数
    function fmtRate(v) {
        if (typeof v === 'string' && v.indexOf('%') > -1) { return v; }
        var n = parseFloat(v) || 0;
        if (n > 0 && n <= 1) { n = n * 100; }
        return (Math.round(n * 10) / 10) + '%';
    }

    // 渲染六张统计卡片
    function renderCards(d) {
        var cards = [
            { label: '今日发送', value: pick(d, ['today', 'today_count', 'today_total'], 0), cls: 'hl' },
            { label: '发送总数', value: pick(d, ['total', 'total_count', 'all'], 0), cls: '' },
            { label: '成功', value: pick(d, ['success', 'success_count', 'succeeded'], 0), cls: 'good' },
            { label: '失败', value: pick(d, ['failed', 'failed_count', 'fail'], 0), cls: 'bad' },
            { label: '待发送', value: pick(d, ['pending', 'pending_count', 'waiting'], 0), cls: '' },
            { label: '成功率', value: fmtRate(pick(d, ['success_rate', 'rate', 'successRate'], 0)), cls: 'hl' }
        ];
        var html = '';
        cards.forEach(function (c) {
            html += '<div class="stat-card ' + c.cls + '">' +
                '<div class="stat-value">' + escapeHtml(c.value) + '</div>' +
                '<div class="stat-label">' + escapeHtml(c.label) + '</div>' +
            '</div>';
        });
        $('#statGrid').html(html);
    }

    // 归一化近 7 日趋势数据
    function normalizeTrend(arr) {
        if (!Array.isArray(arr)) { return []; }
        return arr.slice(-7).map(function (it) {
            return {
                date: pick(it, ['date', 'day', 'label'], ''),
                total: Number(pick(it, ['total', 'count', 'total_count', 'sent'], 0)) || 0,
                success: Number(pick(it, ['success', 'success_count', 'succeeded'], 0)) || 0
            };
        });
    }

    // 渲染纯 CSS 柱状趋势图
    function renderTrend(d) {
        var rows = normalizeTrend(pick(d, ['trend', 'last_7_days', 'days', 'chart', 'series'], []));
        if (!rows.length) { $('#trendChart').html('<div class="empty-tip">暂无趋势数据</div>'); return; }
        var max = 1;
        rows.forEach(function (r) { if (r.total > max) { max = r.total; } });
        var html = '';
        rows.forEach(function (r) {
            var hTotal = Math.max(2, Math.round(r.total / max * 100));
            var hSuccess = Math.max(2, Math.round(r.success / max * 100));
            html += '<div class="trend-item">' +
                '<div class="trend-bars">' +
                    '<div class="trend-bar total" style="height:' + hTotal + '%"><span class="val">' + escapeHtml(r.total) + '</span></div>' +
                    '<div class="trend-bar success" style="height:' + hSuccess + '%"></div>' +
                '</div>' +
                '<div class="trend-label">' + escapeHtml(r.date) + '</div>' +
            '</div>';
        });
        $('#trendChart').html(html);
    }

    // 加载统计数据
    function loadStats() {
        var ld = layer.load(1, { shade: 0.3 });
        $.get(API + '/dashboard/stats', function (res) {
            layer.close(ld);
            if (res.code !== 0) { layer.msg(res.message || '统计加载失败', { icon: 2 }); return; }
            renderCards(res.data || {});
            renderTrend(res.data || {});
        }, 'json').fail(function () { layer.close(ld); layer.msg('统计加载失败', { icon: 2 }); });
    }

    $('#btnRefresh').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        loadStats();
    });

    loadStats();
});
</script>
</body>
</html>
