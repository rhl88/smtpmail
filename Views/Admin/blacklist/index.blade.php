<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>黑名单 - SMTP邮箱</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        /* 弹窗内部自建滚动容器，规避框架重置样式对弹层内容的溢出裁剪 */
        .modal-wrap { height: 100%; box-sizing: border-box; }
        .modal-scroll { height: calc(100% - 52px); overflow-y: auto; padding: 15px 20px 0; box-sizing: border-box; }
        .modal-bottom { height: 52px; box-sizing: border-box; background: #fff; border-top: 1px solid #e6e6e6; text-align: right; }
        .modal-bottom .button-container { display: inline-block; line-height: 52px; padding-right: 15px; }
        .modal-bottom .button-container button { margin-left: 10px; }
        .helper-tip { color: #999; font-size: 12px; margin-top: 4px; }
        /* 表单标签单行显示，不覆盖 Layui 默认宽度 */
        .layui-form-label { white-space: nowrap; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    {{-- 工具栏 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <div class="layui-form-item" style="margin-bottom:0;">
                <span style="color:#666;font-size:13px;">加入黑名单的邮箱将在发送前被自动过滤；本模块复用应用设置权限码。</span>
                <div class="layui-inline" style="float:right;">
                    <button type="button" class="layui-btn layui-btn-sm" id="btnCreate" data-permission="cmspro.smtpmail.setting"><i class="fa fa-plus"></i> 新增邮箱</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 黑名单列表 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="blacklistTable" lay-filter="blacklistTable"></table>
        </div>
    </div>

    {{-- 以下模板段供 Layui 表格渲染，保持原样输出 --}}
    @verbatim
    <script type="text/html" id="rowBar">
        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete" data-permission="cmspro.smtpmail.setting"><i class="fa fa-trash"></i> 删除</a>
    </script>
    @endverbatim
</div>

{{-- 新增邮箱弹窗容器，由列表页注入，弹层取本容器节点打开 --}}
@include('cmspro.smtpmail::Admin.blacklist.form')

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'table', 'form', 'layer'], function () {
    var $ = layui.jquery,
        table = layui.table,
        form = layui.form,
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
    // 应用未提供黑名单独立权限码，沿用应用设置权限码
    var PERM = 'cmspro.smtpmail.setting';

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

    // ============ 黑名单列表 ============
    var cols = [[
        { type: 'numbers', title: '#', width: 50 },
        { field: 'email', title: '邮箱地址', minWidth: 220, templet: function (d) { return escapeHtml(d.email || ''); } },
        { field: 'reason', title: '加入原因', minWidth: 220, templet: function (d) { return escapeHtml(d.reason || ''); } },
        { field: 'created_at', title: '加入时间', width: 180 },
        { title: '操作', width: 110, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#blacklistTable',
        id: 'blacklistTable',
        url: API + '/blacklist',
        method: 'get',
        page: { layout: ['count', 'prev', 'page', 'next', 'limit'], groups: 5, limit: 15, limits: [15, 30, 50, 100] },
        skin: false,
        cols: cols,
        parseData: function (res) {
            return { code: res.code === 0 ? 0 : 1, msg: res.msg || '', count: res.count || 0, data: res.data || [] };
        },
        done: function () { hidePermButtons(); },
        error: function () { layer.msg('列表加载失败', { icon: 2 }); }
    });

    // ============ 新增邮箱弹窗 ============
    var blLayerIndex = 0;

    function openForm() {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        $('#blEmail').val('');
        $('#blReason').val('');
        blLayerIndex = layer.open({
            type: 1,
            title: '新增黑名单邮箱',
            area: ['520px', '300px'],
            content: $('#blacklistForm'),
            btn: false,
            success: function () { $('#blacklistForm').show(); form.render(); },
            end: function () { $('#blacklistForm').hide(); }
        });
    }

    $('#btnCreate').on('click', openForm);
    $('#btnCancelBlacklist').on('click', function () { if (blLayerIndex) { layer.close(blLayerIndex); } });

    // 保存黑名单：提交邮箱与原因
    $('#btnSaveBlacklist').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        var payload = {
            email: $.trim($('#blEmail').val()),
            reason: $.trim($('#blReason').val())
        };
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) { layer.msg('邮箱格式不正确', { icon: 2 }); return; }

        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/blacklist',
            type: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld);
                if (res.code === 0) {
                    layer.close(blLayerIndex);
                    layer.msg(res.message || '已加入黑名单', { icon: 1, time: 1200 });
                    table.reload('blacklistTable');
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2, time: 2000 });
                }
            },
            error: function () { layer.close(ld); layer.msg('保存失败', { icon: 2 }); }
        });
    });

    // ============ 行内删除 ============
    table.on('tool(blacklistTable)', function (obj) {
        var data = obj.data, layEvent = obj.event;
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        if (layEvent !== 'delete') { return; }
        layer.confirm('确定将「' + escapeHtml(data.email || '') + '」移出黑名单？', { icon: 3, title: '删除确认' }, function (idx) {
            layer.close(idx);
            $.ajax({
                url: API + '/blacklist/' + data.id,
                type: 'DELETE', dataType: 'json',
                success: function (res) {
                    if (res.code === 0) { layer.msg(res.message || '删除成功', { icon: 1 }, function () { table.reload('blacklistTable'); }); }
                    else { layer.msg(res.message || '删除失败', { icon: 2 }); }
                },
                error: function () { layer.msg('删除失败', { icon: 2 }); }
            });
        });
    });
});
</script>
</body>
</html>
