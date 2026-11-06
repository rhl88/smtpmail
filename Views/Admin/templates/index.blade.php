<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>邮件模板 - SMTP邮箱</title>
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
        .tpl-notice { background: rgba(255, 184, 0, 0.08); border: 1px solid rgba(255, 184, 0, 0.35); color: #ffb800; padding: 10px 14px; border-radius: 3px; font-size: 13px; line-height: 1.8; margin-bottom: 10px; }
        /* HTML 正文采用代码风格编辑区 */
        .code-editor { font-size: 12px; line-height: 1.6; background: whitesmoke; color: #5f5f5f; min-height: 190px; }
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
                            <input type="text" name="keyword" placeholder="名称 / 编码 / 主题" class="layui-input" autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">分类</label>
                        <div class="layui-input-inline" style="width:140px;">
                            <input type="text" name="category" placeholder="模板分类" class="layui-input" autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn layui-btn-normal" lay-submit lay-filter="doSearch"><i class="fa fa-search"></i> 搜索</button>
                    </div>
                    <div class="layui-inline" style="float:right;">
                        <button type="button" class="layui-btn layui-btn-sm" id="btnCreate" data-permission="cmspro.smtpmail.template"><i class="fa fa-plus"></i> 新建模板</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 内置模板接管提示 --}}
    <div class="tpl-notice">
        <i class="fa fa-info-circle"></i> <strong>内置模板说明：</strong>系统内置 <code>verify_code</code>（注册验证码）与 <code>password_reset</code>（找回密码）两套模板，可在「应用设置」中开启「接管系统邮件」，改由本应用的默认账号与这两套模板发送系统邮件；内置模板禁止删除、编码不可修改。
    </div>

    {{-- 模板列表 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="templatesTable" lay-filter="templatesTable"></table>
        </div>
    </div>

    {{-- 以下模板段供 Layui 表格渲染，保持原样输出 --}}
    @verbatim
    <script type="text/html" id="systemTpl">
        {{# if(d.is_system == 1){ }}<span class="layui-badge layui-bg-orange">内置</span>{{# } else { }}<span class="layui-badge layui-bg-blue">自定义</span>{{# } }}
    </script>
    <script type="text/html" id="statusTpl">
        {{# if(d.status == 1){ }}<span class="layui-badge layui-bg-green">启用</span>{{# } else { }}<span class="layui-badge layui-bg-gray">停用</span>{{# } }}
    </script>
    <script type="text/html" id="rowBar">
        <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="cmspro.smtpmail.template">编辑</a>
        <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="duplicate" data-permission="cmspro.smtpmail.template">复制</a>
        <a class="layui-btn layui-btn-xs" lay-event="toggle" data-permission="cmspro.smtpmail.template">启停</a>
        <a class="layui-btn layui-btn-xs" lay-event="preview" data-permission="cmspro.smtpmail.template">预览</a>
        <a class="layui-btn layui-btn-xs" lay-event="test" data-permission="cmspro.smtpmail.template">测试</a>
        {{# if(d.is_system != 1){ }}
        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete" data-permission="cmspro.smtpmail.template">删除</a>
        {{# } }}
    </script>
    @endverbatim
</div>

{{-- 新增编辑弹窗容器，由列表页注入，弹层取本容器节点打开 --}}
@include('cmspro.smtpmail::Admin.templates.form')

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
    var PERM = 'cmspro.smtpmail.template';

    // HTML 转义，避免拼接内容造成注入
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 取对象中第一个存在的键值，兼容不同接口的下拉字段命名
    function firstVal(o, keys) {
        for (var i = 0; i < keys.length; i++) {
            if (o[keys[i]] !== undefined && o[keys[i]] !== null) { return o[keys[i]]; }
        }
        return '';
    }

    // 权限判断与按钮隐藏
    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    // ============ 账号下拉（表单中的 account_id） ============
    var accountOptionsCache = null;
    function fillSelect($sel, list, placeholder) {
        $sel.empty();
        if (placeholder !== undefined) { $sel.append('<option value="">' + escapeHtml(placeholder) + '</option>'); }
        (list || []).forEach(function (it) {
            var val, txt;
            if (it && typeof it === 'object') {
                val = firstVal(it, ['id', 'value', 'code', 'key']);
                txt = firstVal(it, ['name', 'label', 'title', 'text']);
                if (txt === '') { txt = val; }
            } else { val = txt = it; }
            $sel.append('<option value="' + escapeHtml(val) + '">' + escapeHtml(txt) + '</option>');
        });
        form.render('select');
    }
    function loadAccountOptions(cb) {
        if (accountOptionsCache) {
            fillSelect($('#tplAccountId'), accountOptionsCache, '跟随默认账号');
            if (cb) { cb(); }
            return;
        }
        $.get(API + '/accounts/options', function (res) {
            if (res.code === 0) { accountOptionsCache = res.data || []; }
            fillSelect($('#tplAccountId'), accountOptionsCache || [], '跟随默认账号');
            if (cb) { cb(); }
        }, 'json');
    }
    loadAccountOptions();

    // ============ 邮件正文预览（隔离到 iframe，避免影响后台页面样式） ============
    function showHtml(title, html) {
        layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
            type: 1,
            title: title,
            area: ['860px', '640px'],
            content: '<iframe id="mailPreviewFrame" style="display:block;width:100%;height:100%;border:0;"></iframe>',
            success: function () {
                var frame = document.getElementById('mailPreviewFrame');
                if (frame && frame.contentWindow) {
                    var doc = frame.contentWindow.document;
                    doc.open();
                    doc.write(html || '<p style="padding:24px;color:#999;">（模板无 HTML 内容）</p>');
                    doc.close();
                }
            }
        });
    }
    function pickHtml(res) {
        if (!res || !res.data) { return ''; }
        return (typeof res.data === 'object') ? (res.data.html || '') : String(res.data);
    }

    // 弹出变量输入小窗，确认后回调变量对象（留空表示不带变量）
    function askVars(cb) {
        layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
            type: 1,
            title: '预览变量（JSON，可留空）',
            area: ['480px', '260px'],
            content: '<div style="padding:15px 18px;">' +
                '<textarea id="tmpVarsText" class="layui-textarea" style="min-height:96px;" placeholder=\'如：{"code":"123456","name":"张三"}\'></textarea>' +
                '<div class="helper-tip">留空表示使用空变量渲染；须为合法 JSON 对象。</div></div>',
            btn: ['确定', '取消'],
            yes: function (idx) {
                var text = $.trim($('#tmpVarsText').val());
                var vars = {};
                if (text) {
                    try {
                        vars = JSON.parse(text);
                        if (typeof vars !== 'object' || vars === null || Array.isArray(vars)) { throw new Error('格式错误'); }
                    } catch (e) { layer.msg('变量 JSON 格式不正确', { icon: 2 }); return; }
                }
                layer.close(idx);
                cb(vars);
            }
        });
    }

    // ============ 模板列表 ============
    var cols = [[
        { type: 'numbers', title: '#', width: 50 },
        { field: 'name', title: '模板名称', minWidth: 150 },
        { field: 'code', title: '编码', width: 150 },
        { field: 'category', title: '分类', width: 110 },
        { field: 'subject', title: '主题', minWidth: 180, templet: function (d) { return escapeHtml(d.subject || ''); } },
        { field: 'is_system', title: '类型', width: 90, align: 'center', templet: '#systemTpl' },
        { field: 'status', title: '状态', width: 80, align: 'center', templet: '#statusTpl' },
        { field: 'updated_at', title: '更新时间', width: 165 },
        { title: '操作', width: 350, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#templatesTable',
        id: 'templatesTable',
        url: API + '/templates',
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

    form.on('submit(doSearch)', function (data) {
        table.reload('templatesTable', {
            where: { keyword: data.field.keyword, category: data.field.category },
            page: { curr: 1 }
        });
        return false;
    });

    // ============ 新增编辑弹窗 ============
    var editingId = 0;
    var tplLayerIndex = 0;

    function parseVars(text) {
        text = $.trim(text || '');
        if (text === '') { return ''; }
        if (/^[\[{]/.test(text)) {
            try { return JSON.parse(text); } catch (e) { return text; }
        }
        return text;
    }
    function varsToText(v) {
        if (v == null || v === '') { return ''; }
        if (typeof v === 'object') {
            try { return JSON.stringify(v, null, 2); } catch (e) { return ''; }
        }
        return String(v);
    }

    function resetForm() {
        $('#tplCode').val('').prop('disabled', false).css('background', '');
        $('#tplName').val('');
        $('#tplCategory').val('');
        $('#tplSubject').val('');
        $('#tplBodyHtml').val('');
        $('#tplBodyText').val('');
        $('#tplVars').val('');
        $('#tplStatus').val('1');
        $('#tplRemark').val('');
        $('#tplAccountId').val('');
    }

    function openForm(id) {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        editingId = id || 0;
        resetForm();

        var doOpen = function () {
            tplLayerIndex = layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
                type: 1,
                title: editingId ? '编辑邮件模板' : '新建邮件模板',
                area: ['900px', '640px'],
                content: $('#templateForm'),
                btn: false,
                success: function () { $('#templateForm').show(); form.render(); },
                end: function () { $('#templateForm').hide(); }
            });
        };

        // 先确保账号下拉已就绪，再回填或打开
        loadAccountOptions(function () {
            if (!editingId) { doOpen(); return; }
            if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
            $.get(API + '/templates/' + editingId, function (res) {
                layer.close(ld); busyUnlock();
                if (res.code !== 0) { layer.msg(res.message || '模板读取失败', { icon: 2 }); return; }
                var d = res.data || {};
                $('#tplCode').val(d.code || '');
                $('#tplName').val(d.name || '');
                $('#tplCategory').val(d.category || '');
                $('#tplSubject').val(d.subject || '');
                $('#tplBodyHtml').val(d.body_html || '');
                $('#tplBodyText').val(d.body_text || '');
                $('#tplVars').val(varsToText(d.variables));
                $('#tplStatus').val(String(d.status != null ? d.status : 1));
                $('#tplRemark').val(d.remark || '');
                $('#tplAccountId').val(d.account_id != null ? String(d.account_id) : '');
                // 内置模板禁止修改编码
                if (d.is_system == 1) {
                    $('#tplCode').prop('disabled', true).css('background', 'whitesmoke');
                }
                form.render('select');
                doOpen();
            }, 'json').fail(function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '模板读取失败'), { icon: 2 }); });
        });
    }

    $('#btnCreate').on('click', function () { openForm(0); });
    $('#btnCancelTemplate').on('click', function () { if (tplLayerIndex) { layer.close(tplLayerIndex); } });

    // 保存模板：新增走 POST，编辑走 PUT
    $('#btnSaveTemplate').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        var accountVal = $('#tplAccountId').val();
        var payload = {
            code: $.trim($('#tplCode').val()),
            name: $.trim($('#tplName').val()),
            category: $.trim($('#tplCategory').val()),
            subject: $('#tplSubject').val(),
            body_html: $('#tplBodyHtml').val(),
            body_text: $('#tplBodyText').val(),
            account_id: accountVal === '' ? null : parseInt(accountVal, 10),
            variables: parseVars($('#tplVars').val()),
            status: parseInt($('#tplStatus').val(), 10) || 0,
            remark: $.trim($('#tplRemark').val())
        };
        if (!payload.code || !payload.name) { layer.msg('请填写模板编码与名称', { icon: 2 }); return; }

        if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: editingId ? (API + '/templates/' + editingId) : (API + '/templates'),
            type: editingId ? 'PUT' : 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld); busyUnlock();
                if (res.code === 0) {
                    layer.close(tplLayerIndex);
                    layer.msg(res.message || '保存成功', { icon: 1, time: 1200 });
                    table.reload('templatesTable');
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2, time: 2000 });
                }
            },
            error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '保存失败'), { icon: 2 }); }
        });
    });

    // 模板测试信弹窗：收件邮箱 + 变量 JSON
    function openTestDialog(id) {
        layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
            type: 1,
            title: '发送模板测试信',
            area: ['520px', '340px'],
            content: '<div style="padding:15px 18px;">' +
                '<div class="layui-form-item"><label class="layui-form-label">收件邮箱</label>' +
                '<div class="layui-input-block"><input type="text" id="tmpTestTo" class="layui-input" placeholder="接收测试信的邮箱地址"></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">变量 JSON</label>' +
                '<div class="layui-input-block"><textarea id="tmpTestVars" class="layui-textarea" style="min-height:80px;" placeholder=\'可留空，如：{"code":"123456"}\'></textarea></div></div>' +
                '<div class="helper-tip">变量用于渲染模板占位符，留空表示不带变量发送。</div></div>',
            btn: ['发送', '取消'],
            yes: function (idx) {
                var to = $.trim($('#tmpTestTo').val());
                var text = $.trim($('#tmpTestVars').val());
                var vars = {};
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) { layer.msg('邮箱格式不正确', { icon: 2 }); return; }
                if (text) {
                    try {
                        vars = JSON.parse(text);
                        if (typeof vars !== 'object' || vars === null || Array.isArray(vars)) { throw new Error('格式错误'); }
                    } catch (e) { layer.msg('变量 JSON 格式不正确', { icon: 2 }); return; }
                }
                layer.close(idx);
                if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
                $.ajax({
                    url: API + '/templates/' + id + '/test',
                    type: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({ to: to, vars: vars }),
                    success: function (res) {
                        layer.close(ld); busyUnlock();
                        layer.msg(res.message || (res.code === 0 ? '测试信已发送' : '发送失败'), { icon: res.code === 0 ? 1 : 2, time: 2500 });
                    },
                    error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '请求失败'), { icon: 2 }); }
                });
            }
        });
    }

    // ============ 行内操作 ============
    table.on('tool(templatesTable)', function (obj) {
        var data = obj.data, layEvent = obj.event;
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }

        if (layEvent === 'edit') {
            openForm(data.id);
        } else if (layEvent === 'duplicate') {
            $.ajax({
                url: API + '/templates/' + data.id + '/duplicate',
                type: 'POST', contentType: 'application/json', data: JSON.stringify({}), dataType: 'json',
                success: function (res) {
                    if (res.code === 0) { layer.msg(res.message || '已复制', { icon: 1 }); table.reload('templatesTable'); }
                    else { layer.msg(res.message || '复制失败', { icon: 2 }); }
                },
                error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '复制失败'), { icon: 2 }); }
            });
        } else if (layEvent === 'toggle') {
            $.ajax({
                url: API + '/templates/' + data.id + '/toggle',
                type: 'POST', contentType: 'application/json', data: JSON.stringify({}), dataType: 'json',
                success: function (res) {
                    if (res.code !== 0) { layer.msg(res.message || '切换失败', { icon: 2 }); return; }
                    layer.msg(res.message || '状态已切换', { icon: 1 });
                    // 仅影响当前行，用本地缓存单行更新，保持滚动位置
                    var cache = table.cache['templatesTable'] || [];
                    var row = null;
                    for (var i = 0; i < cache.length; i++) { if (cache[i].id === data.id) { row = cache[i]; break; } }
                    if (row && obj.tr) {
                        row.status = (row.status == 1) ? 0 : 1;
                        try { table.updateRow('templatesTable', { index: obj.tr.data('index'), data: row }); }
                        catch (e) { table.reload('templatesTable'); }
                    } else { table.reload('templatesTable'); }
                },
                error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '切换失败'), { icon: 2 }); }
            });
        } else if (layEvent === 'preview') {
            askVars(function (vars) {
                if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
                $.ajax({
                    url: API + '/templates/' + data.id + '/preview',
                    type: 'POST', contentType: 'application/json', dataType: 'json',
                    data: JSON.stringify({ vars: vars }),
                    success: function (res) {
                        layer.close(ld); busyUnlock();
                        if (res.code === 0) { showHtml('模板预览', pickHtml(res)); }
                        else { layer.msg(res.message || '预览失败', { icon: 2 }); }
                    },
                    error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '预览失败'), { icon: 2 }); }
                });
            });
        } else if (layEvent === 'test') {
            openTestDialog(data.id);
        } else if (layEvent === 'delete') {
            layer.confirm('确定删除模板「' + escapeHtml(data.name) + '」？', { icon: 3, title: '删除确认' }, function (idx) {
                layer.close(idx);
                $.ajax({
                    url: API + '/templates/' + data.id,
                    type: 'DELETE', dataType: 'json',
                    success: function (res) {
                        if (res.code === 0) { layer.msg(res.message || '删除成功', { icon: 1 }, function () { table.reload('templatesTable'); }); }
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
