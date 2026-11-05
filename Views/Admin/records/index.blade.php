<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>发送日志 - SMTP邮箱</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        /* 详情弹窗：上部元信息 + 下部隔离正文 iframe */
        .detail-wrap { height: 100%; box-sizing: border-box; display: flex; flex-direction: column; }
        .detail-meta { flex: 0 0 auto; padding: 10px 15px; border-bottom: 1px solid #e6e6e6; background: whitesmoke; font-size: 13px; line-height: 1.9; color: #5f5f5f; }
        .detail-meta .meta-label { color: #999; margin-right: 4px; }
        .detail-frame { flex: 1 1 auto; width: 100%; border: 0; }
        .helper-tip { color: #999; font-size: 12px; margin-top: 4px; }
        /* 表单标签单行显示，不覆盖 Layui 默认宽度 */
        .layui-form-label { white-space: nowrap; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    {{-- 筛选与工具栏 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="searchForm">
                <div class="layui-form-item" style="margin-bottom:0;">
                    <div class="layui-inline">
                        <label class="layui-form-label">状态</label>
                        <div class="layui-input-inline" style="width:120px;">
                            <select id="filterStatus">
                                <option value="">全部</option>
                                <option value="pending">待发送</option>
                                <option value="sending">发送中</option>
                                <option value="success">成功</option>
                                <option value="failed">失败</option>
                                <option value="cancelled">已取消</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">关键字</label>
                        <div class="layui-input-inline">
                            <input type="text" id="filterKeyword" placeholder="主题 / 收件人" class="layui-input" autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">起始日期</label>
                        <div class="layui-input-inline" style="width:150px;">
                            <input type="date" id="filterDateFrom" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">截止日期</label>
                        <div class="layui-input-inline" style="width:150px;">
                            <input type="date" id="filterDateTo" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn layui-btn-normal" lay-submit lay-filter="doSearch"><i class="fa fa-search"></i> 搜索</button>
                    </div>
                    <div class="layui-inline" style="float:right;">
                        <button type="button" class="layui-btn layui-btn-sm" id="btnExport" data-permission="cmspro.smtpmail.record"><i class="fa fa-download"></i> 导出</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 日志列表 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="recordsTable" lay-filter="recordsTable"></table>
        </div>
    </div>

    {{-- 以下模板段供 Layui 表格渲染，保持原样输出 --}}
    @verbatim
    <script type="text/html" id="statusTpl">
        {{# var s = d.status; }}
        {{# if(s === 'success' || s === 'sent' || s === 1){ }}<span class="layui-badge layui-bg-green">成功</span>
        {{# } else if(s === 'failed' || s === 'fail' || s === 2){ }}<span class="layui-badge layui-bg-red">失败</span>
        {{# } else if(s === 'sending' || s === 'processing'){ }}<span class="layui-badge layui-bg-blue">发送中</span>
        {{# } else if(s === 'cancelled' || s === 'cancel'){ }}<span class="layui-badge layui-bg-gray">已取消</span>
        {{# } else { }}<span class="layui-badge layui-bg-orange">待发送</span>{{# } }}
    </script>
    <script type="text/html" id="rowBar">
        <a class="layui-btn layui-btn-xs" lay-event="detail" data-permission="cmspro.smtpmail.record"><i class="fa fa-eye"></i> 详情</a>
        <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="retry" data-permission="cmspro.smtpmail.record"><i class="fa fa-refresh"></i> 重试</a>
        <a class="layui-btn layui-btn-xs" lay-event="cancel" data-permission="cmspro.smtpmail.record"><i class="fa fa-ban"></i> 取消</a>
        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete" data-permission="cmspro.smtpmail.record"><i class="fa fa-trash"></i> 删除</a>
    </script>
    @endverbatim
</div>

{{-- 日志详情弹窗容器，由列表页注入，弹层取本容器节点打开 --}}
@include('cmspro.smtpmail::Admin.records.detail')

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'table', 'form', 'layer'], function () {
    var $ = layui.jquery,
        table = layui.table,
        form = layui.form,
        layer = layui.layer;

        // 统一错误解析（I4）：优先展示后端 message，401 由 ajaxSetup 统一处理
    function parseError(xhr, fallback) {
        if (xhr && xhr.status === 401) { return ''; }
        try {
            var res = JSON.parse(xhr.responseText || '{}');
            if (res && res.message) { return String(res.message); }
        } catch (e) { /* 非 JSON 响应，走兜底文案 */ }
        return (xhr && xhr.responseText) ? String(xhr.responseText).slice(0, 120) : fallback;
    }
    // 防重复提交锁（I9）：异步提交期间拦截后续触发
    var __busy = false;
    function busyLock() { if (__busy) { return true; } __busy = true; return false; }
    function busyUnlock() { __busy = false; }
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
    var PERM = 'cmspro.smtpmail.record';

    // HTML 转义，避免拼接内容造成注入
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 权限判断与按钮隐藏
    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    // 汇总筛选条件，供列表查询与导出复用
    function collectFilters() {
        return {
            status: $('#filterStatus').val() || '',
            keyword: $.trim($('#filterKeyword').val()),
            dateFrom: $('#filterDateFrom').val() || '',
            dateTo: $('#filterDateTo').val() || ''
        };
    }

    // ============ 日志列表 ============
    var cols = [[
        { field: 'id', title: 'ID', width: 90, align: 'center' },
        { field: 'subject', title: '主题', minWidth: 180, templet: function (d) { return escapeHtml(d.subject || ''); } },
        { field: 'to', title: '收件人', minWidth: 180, templet: function (d) { return escapeHtml(d.to || d.to_address || d.recipient || d.email || ''); } },
        { field: 'status', title: '状态', width: 95, align: 'center', templet: '#statusTpl' },
        { field: 'retry_count', title: '重试次数', width: 95, align: 'center', templet: function (d) {
            return d.retry_count != null ? d.retry_count : (d.retries != null ? d.retries : 0);
        } },
        { field: 'created_at', title: '时间', width: 165, templet: function (d) { return escapeHtml(d.created_at || d.sent_at || d.send_time || ''); } },
        { field: 'source', title: '来源', width: 110, templet: function (d) { return escapeHtml(d.source || d.source_text || ''); } },
        { title: '操作', width: 280, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#recordsTable',
        id: 'recordsTable',
        url: API + '/records',
        method: 'get',
        page: { layout: ['count', 'prev', 'page', 'next', 'limit'], groups: 5, limit: 15, limits: [15, 30, 50, 100] },
        skin: false, request: { pageName: 'page', limitName: 'per_page' },
        cols: cols,
        parseData: function (res) {
            return { code: res.code === 0 ? 0 : 1, msg: res.msg || '', count: res.count || 0, data: res.data || [] };
        },
        done: function () { hidePermButtons(); },
        error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '列表加载失败'), { icon: 2 }); }
    });

    form.on('submit(doSearch)', function () {
        table.reload('recordsTable', { where: collectFilters(), page: { curr: 1 } });
        return false;
    });

    // 导出：按当前筛选条件直接下载
    $('#btnExport').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        location.href = API + '/records/export?' + $.param(collectFilters());
    });

    // ============ 详情弹窗 ============
    function writeFrame(html) {
        var frame = document.getElementById('detailFrame');
        if (frame && frame.contentWindow) {
            var doc = frame.contentWindow.document;
            doc.open();
            doc.write(html || '<p style="padding:24px;color:#999;">（该记录未保存 HTML 正文）</p>');
            doc.close();
        }
    }

    function openDetail(id) {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
        $.get(API + '/records/' + id, function (res) {
            layer.close(ld); busyUnlock();
            if (res.code !== 0) { layer.msg(res.message || '详情读取失败', { icon: 2 }); return; }
            var d = res.data || {};
            var meta = '' +
                '<div><span class="meta-label">主题：</span>' + escapeHtml(d.subject || '') + '</div>' +
                '<div><span class="meta-label">收件人：</span>' + escapeHtml(d.to || d.to_address || d.recipient || '') + '</div>' +
                '<div><span class="meta-label">状态：</span>' + escapeHtml(d.status_text || d.status || '') +
                '　<span class="meta-label">重试次数：</span>' + escapeHtml(d.retry_count != null ? d.retry_count : 0) + '</div>' +
                '<div><span class="meta-label">时间：</span>' + escapeHtml(d.created_at || d.sent_at || '') +
                '　<span class="meta-label">来源：</span>' + escapeHtml(d.source || d.source_text || '') + '</div>';
            $('#detailMeta').html(meta);
            layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
                type: 1,
                title: '邮件详情 · 记录 #' + id,
                area: ['900px', '640px'],
                content: $('#recordDetail'),
                btn: false,
                success: function () { $('#recordDetail').show(); writeFrame(d.body_html || ''); },
                end: function () { $('#recordDetail').hide(); }
            });
        }, 'json').fail(function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '详情读取失败'), { icon: 2 }); });
    }

    // ============ 行内操作 ============
    table.on('tool(recordsTable)', function (obj) {
        var data = obj.data, layEvent = obj.event;
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }

        if (layEvent === 'detail') {
            openDetail(data.id);
        } else if (layEvent === 'retry') {
            $.ajax({
                url: API + '/records/' + data.id + '/retry',
                type: 'POST', contentType: 'application/json', data: JSON.stringify({}), dataType: 'json',
                success: function (res) {
                    if (res.code === 0) { layer.msg(res.message || '已重新投递', { icon: 1 }); table.reload('recordsTable'); }
                    else { layer.msg(res.message || '重试失败', { icon: 2 }); }
                },
                error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '重试失败'), { icon: 2 }); }
            });
        } else if (layEvent === 'cancel') {
            layer.confirm('确定取消该条待发送记录？', { icon: 3, title: '取消确认' }, function (idx) {
                layer.close(idx);
                $.ajax({
                    url: API + '/records/' + data.id + '/cancel',
                    type: 'POST', contentType: 'application/json', data: JSON.stringify({}), dataType: 'json',
                    success: function (res) {
                        if (res.code === 0) { layer.msg(res.message || '已取消', { icon: 1 }); table.reload('recordsTable'); }
                        else { layer.msg(res.message || '取消失败', { icon: 2 }); }
                    },
                    error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '取消失败'), { icon: 2 }); }
                });
            });
        } else if (layEvent === 'delete') {
            layer.confirm('确定删除记录 #' + data.id + '？', { icon: 3, title: '删除确认' }, function (idx) {
                layer.close(idx);
                $.ajax({
                    url: API + '/records/' + data.id,
                    type: 'DELETE', dataType: 'json',
                    success: function (res) {
                        if (res.code === 0) { layer.msg(res.message || '删除成功', { icon: 1 }, function () { table.reload('recordsTable'); }); }
                        else { layer.msg(res.message || '删除失败', { icon: 2 }); }
                    },
                    error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '删除失败'), { icon: 2 }); }
                });
            });
        }
    });
});
</script>
</body>
</html>
