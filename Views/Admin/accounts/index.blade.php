<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>发信账号 - SMTP邮箱</title>
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
        .notice-bar { background: #f0f7ff; border: 1px solid #cfe3ff; color: #31578a; padding: 8px 12px; border-radius: 3px; font-size: 13px; margin-bottom: 10px; }
        /* 表单标签单行显示，不覆盖 Layui 默认宽度 */
        .layui-form-label { white-space: nowrap; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<div class="pear-container">
    {{-- 搜索与工具栏 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="searchForm">
                <div class="layui-form-item" style="margin-bottom:0;">
                    <div class="layui-inline">
                        <label class="layui-form-label">关键字</label>
                        <div class="layui-input-inline">
                            <input type="text" name="keyword" placeholder="名称 / 编码 / 主机" class="layui-input" autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn layui-btn-normal" lay-submit lay-filter="doSearch"><i class="fa fa-search"></i> 搜索</button>
                    </div>
                    <div class="layui-inline" style="float:right;">
                        <button type="button" class="layui-btn layui-btn-sm" id="btnCreate" data-permission="cmspro.smtpmail.account"><i class="fa fa-plus"></i> 新增账号</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 使用说明 --}}
    <div class="notice-bar"><i class="fa fa-info-circle"></i> 可配置多个发信账号；「设为默认」的账号用于接管系统邮件及未指定账号的发送任务。密码编辑时留空表示保持原值。</div>

    {{-- 账号列表 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="accountsTable" lay-filter="accountsTable"></table>
        </div>
    </div>

    {{-- 以下模板段供 Layui 表格渲染，保持原样输出 --}}
    @verbatim
    <script type="text/html" id="schemeTpl">
        {{# if(d.scheme === 'ssl'){ }}<span class="layui-badge layui-bg-green">SSL</span>
        {{# } else if(d.scheme === 'tls'){ }}<span class="layui-badge layui-bg-blue">TLS</span>
        {{# } else { }}<span class="layui-badge layui-bg-gray">无</span>{{# } }}
    </script>
    <script type="text/html" id="defaultTpl">
        {{# if(d.is_default == 1){ }}<span class="layui-badge layui-bg-green">默认</span>{{# } else { }}<span style="color:#ccc;">—</span>{{# } }}
    </script>
    <script type="text/html" id="statusTpl">
        {{# if(d.status == 1){ }}<span class="layui-badge layui-bg-green">启用</span>{{# } else { }}<span class="layui-badge layui-bg-gray">停用</span>{{# } }}
    </script>
    <script type="text/html" id="rowBar">
        <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="cmspro.smtpmail.account"><i class="fa fa-pencil"></i> 编辑</a>
        {{# if(d.is_default != 1){ }}
        <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="default" data-permission="cmspro.smtpmail.account"><i class="fa fa-check"></i> 设为默认</a>
        {{# } }}
        <a class="layui-btn layui-btn-xs" lay-event="test" data-permission="cmspro.smtpmail.account"><i class="fa fa-paper-plane"></i> 测试</a>
        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete" data-permission="cmspro.smtpmail.account"><i class="fa fa-trash"></i> 删除</a>
    </script>
    @endverbatim
</div>

{{-- 新增编辑弹窗容器，由列表页注入，弹层取本容器节点打开 --}}
@include('cmspro.smtpmail::Admin.accounts.form')

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
    var PERM = 'cmspro.smtpmail.account';

    // HTML 转义，避免拼接内容造成注入
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 权限判断与按钮隐藏（未注入权限脚本时放行，避免误隐藏）
    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    // ============ 账号列表 ============
    var cols = [[
        { type: 'numbers', title: '#', width: 50 },
        { field: 'name', title: '名称', minWidth: 130 },
        { field: 'code', title: '编码', width: 130 },
        { field: 'host', title: '主机', width: 150 },
        { field: 'port', title: '端口', width: 80, align: 'center' },
        { field: 'scheme', title: '加密', width: 85, align: 'center', templet: '#schemeTpl' },
        { field: 'from_address', title: '发件人', minWidth: 180, templet: function (d) {
            return escapeHtml(d.from_name ? (d.from_name + ' <' + (d.from_address || '') + '>') : (d.from_address || ''));
        } },
        { field: 'is_default', title: '默认', width: 80, align: 'center', templet: '#defaultTpl' },
        { field: 'status', title: '状态', width: 80, align: 'center', templet: '#statusTpl' },
        { field: 'last_test_at', title: '最近测试', width: 165 },
        { field: 'created_at', title: '创建时间', width: 165 },
        { title: '操作', width: 250, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#accountsTable',
        id: 'accountsTable',
        url: API + '/accounts',
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

    form.on('submit(doSearch)', function (data) {
        table.reload('accountsTable', { where: { keyword: data.field.keyword }, page: { curr: 1 } });
        return false;
    });

    // ============ 新增编辑弹窗 ============
    var editingId = 0;
    var accountLayerIndex = 0;

    function resetForm() {
        $('#accName').val('');
        $('#accCode').val('');
        $('#accHost').val('');
        $('#accPort').val('465');
        $('#accScheme').val('ssl');
        $('#accUsername').val('');
        $('#accPassword').val('');
        $('#accFromAddress').val('');
        $('#accFromName').val('');
        $('#accReplyTo').val('');
        $('#accTimeout').val('10');
        $('#accRateLimit').val('0');
        $('#accIsDefault').val('0');
        $('#accStatus').val('1');
    }

    function openForm(id) {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        editingId = id || 0;
        resetForm();

        var doOpen = function () {
            accountLayerIndex = layer.open({
                type: 1,
                title: editingId ? '编辑发信账号' : '新增发信账号',
                area: ['760px', '600px'],
                content: $('#accountForm'),
                btn: false,
                success: function () { $('#accountForm').show(); form.render(); },
                end: function () { $('#accountForm').hide(); }
            });
        };

        if (editingId) {
            var ld = layer.load(1, { shade: 0.3 });
            $.get(API + '/accounts/' + editingId, function (res) {
                layer.close(ld);
                if (res.code !== 0) { layer.msg(res.message || '账号读取失败', { icon: 2 }); return; }
                var d = res.data || {};
                $('#accName').val(d.name || '');
                $('#accCode').val(d.code || '');
                $('#accHost').val(d.host || '');
                $('#accPort').val(d.port != null ? d.port : 465);
                $('#accScheme').val(d.scheme || 'none');
                $('#accUsername').val(d.username || '');
                $('#accPassword').val('');   // 密码不回填，留空即保持原值
                $('#accFromAddress').val(d.from_address || '');
                $('#accFromName').val(d.from_name || '');
                $('#accReplyTo').val(d.reply_to || '');
                $('#accTimeout').val(d.timeout != null ? d.timeout : 10);
                $('#accRateLimit').val(d.rate_limit != null ? d.rate_limit : 0);
                $('#accIsDefault').val(String(d.is_default != null ? d.is_default : 0));
                $('#accStatus').val(String(d.status != null ? d.status : 1));
                doOpen();
            }, 'json').fail(function () { layer.close(ld); layer.msg('账号读取失败', { icon: 2 }); });
        } else {
            doOpen();
        }
    }

    $('#btnCreate').on('click', function () { openForm(0); });

    $('#btnCancelAccount').on('click', function () {
        if (accountLayerIndex) { layer.close(accountLayerIndex); }
    });

    // 保存账号：新增走 POST，编辑走 PUT
    $('#btnSaveAccount').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        var payload = {
            name: $.trim($('#accName').val()),
            code: $.trim($('#accCode').val()),
            host: $.trim($('#accHost').val()),
            port: parseInt($('#accPort').val(), 10) || 25,
            scheme: $('#accScheme').val(),
            username: $.trim($('#accUsername').val()),
            from_address: $.trim($('#accFromAddress').val()),
            from_name: $.trim($('#accFromName').val()),
            reply_to: $.trim($('#accReplyTo').val()),
            timeout: parseInt($('#accTimeout').val(), 10) || 10,
            rate_limit: parseInt($('#accRateLimit').val(), 10) || 0,
            is_default: parseInt($('#accIsDefault').val(), 10) || 0,
            status: parseInt($('#accStatus').val(), 10) || 0
        };
        var pwd = $('#accPassword').val();
        // 编辑时密码留空不提交，由后端保持原值
        if (!editingId || pwd !== '') { payload.password = pwd; }

        if (!payload.name || !payload.code || !payload.host) {
            layer.msg('请填写名称、编码与主机', { icon: 2 }); return;
        }

        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: editingId ? (API + '/accounts/' + editingId) : (API + '/accounts'),
            type: editingId ? 'PUT' : 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld);
                if (res.code === 0) {
                    layer.close(accountLayerIndex);
                    layer.msg(res.message || '保存成功', { icon: 1, time: 1200 });
                    table.reload('accountsTable');
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2, time: 2000 });
                }
            },
            error: function () { layer.close(ld); layer.msg('保存失败', { icon: 2 }); }
        });
    });

    // ============ 行内操作 ============
    table.on('tool(accountsTable)', function (obj) {
        var data = obj.data, layEvent = obj.event;
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }

        if (layEvent === 'edit') {
            openForm(data.id);
        } else if (layEvent === 'default') {
            // 设为默认会改变多行标记，需整表刷新
            $.ajax({
                url: API + '/accounts/' + data.id + '/default',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({}),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 0) { layer.msg(res.message || '已设为默认', { icon: 1 }); table.reload('accountsTable'); }
                    else { layer.msg(res.message || '设置失败', { icon: 2 }); }
                },
                error: function () { layer.msg('设置失败', { icon: 2 }); }
            });
        } else if (layEvent === 'test') {
            layer.prompt({ title: '输入收件邮箱，发送一封测试信', formType: 0 }, function (value, index) {
                var to = $.trim(value);
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) { layer.msg('邮箱格式不正确', { icon: 2 }); return; }
                layer.close(index);
                var ld = layer.load(1, { shade: 0.3 });
                $.ajax({
                    url: API + '/accounts/' + data.id + '/test',
                    type: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({ to: to }),
                    success: function (res) {
                        layer.close(ld);
                        layer.msg(res.message || (res.code === 0 ? '测试信已发送' : '发送失败'), { icon: res.code === 0 ? 1 : 2, time: 2500 });
                    },
                    error: function () { layer.close(ld); layer.msg('请求失败', { icon: 2 }); }
                });
            });
        } else if (layEvent === 'delete') {
            layer.confirm('确定删除账号「' + escapeHtml(data.name) + '」？', { icon: 3, title: '删除确认' }, function (idx) {
                layer.close(idx);
                $.ajax({
                    url: API + '/accounts/' + data.id,
                    type: 'DELETE',
                    dataType: 'json',
                    success: function (res) {
                        if (res.code === 0) { layer.msg(res.message || '删除成功', { icon: 1 }, function () { table.reload('accountsTable'); }); }
                        else { layer.msg(res.message || '删除失败', { icon: 2 }); }
                    },
                    error: function () { layer.msg('删除失败', { icon: 2 }); }
                });
            });
        }
    });
});
</script>
</body>
</html>
