<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>批量群发 - SMTP邮箱</title>
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
                <span style="color:#666;font-size:13px;">按模板向一批收件人批量发送邮件，可设置定时执行。</span>
                <div class="layui-inline" style="float:right;">
                    <button type="button" class="layui-btn layui-btn-sm" id="btnCreate" data-permission="cmspro.smtpmail.batch"><i class="fa fa-plus"></i> 新建群发</button>
                </div>
            </div>
        </div>
    </div>

    {{-- 群发任务列表 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="batchesTable" lay-filter="batchesTable"></table>
        </div>
    </div>

    {{-- 以下模板段供 Layui 表格渲染，保持原样输出 --}}
    @verbatim
    <script type="text/html" id="statusTpl">
        {{# var s = d.status; }}
        {{# if(s === 'completed' || s === 'done' || s === 'success' || s === 2){ }}<span class="layui-badge layui-bg-green">已完成</span>
        {{# } else if(s === 'cancelled' || s === 'cancel'){ }}<span class="layui-badge layui-bg-gray">已取消</span>
        {{# } else if(s === 'processing' || s === 'running' || s === 'sending' || s === 1){ }}<span class="layui-badge layui-bg-blue">进行中</span>
        {{# } else if(s === 'failed' || s === 'fail'){ }}<span class="layui-badge layui-bg-red">失败</span>
        {{# } else { }}<span class="layui-badge layui-bg-orange">待处理</span>{{# } }}
    </script>
    <script type="text/html" id="rowBar">
        {{# if(d.status === 'completed' || d.status === 'done' || d.status === 'cancelled' || d.status === 'cancel' || d.status === 2){ }}
        <span style="color:#ccc;">—</span>
        {{# } else { }}
        <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="cancel" data-permission="cmspro.smtpmail.batch"><i class="fa fa-ban"></i> 取消</a>
        {{# } }}
    </script>
    @endverbatim
</div>

{{-- 新建群发弹窗容器，由列表页注入，弹层取本容器节点打开 --}}
@include('cmspro.smtpmail::Admin.batches.form')

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
    var PERM = 'cmspro.smtpmail.batch';

    // HTML 转义，避免拼接内容造成注入
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
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

    // 按模板编码查找模板名称（来自模板选项缓存；未命中如已停用/删除则回退显示编码）
    function findTemplateName(code) {
        var list = templateOptionsCache || [];
        for (var i = 0; i < list.length; i++) {
            if (String(firstVal(list[i], ['code', 'value', 'id'])) === String(code)) {
                return String(firstVal(list[i], ['name', 'label', 'title']) || code);
            }
        }
        return '';
    }

    // ============ 群发任务列表 ============
    var cols = [[
        { type: 'numbers', title: '#', width: 50 },
        { field: 'name', title: '名称', minWidth: 150, templet: function (d) { return escapeHtml(d.name || ''); } },
        { field: 'template_code', title: '模板', width: 160, templet: function (d) {
            var code = String(d.template_code || '');
            var name = findTemplateName(code);
            if (!name) { return escapeHtml(code); }
            return '<span title="' + escapeHtml(code) + '">' + escapeHtml(name) + '</span>';
        } },
        { field: 'total_count', title: '总数', width: 90, align: 'center', templet: function (d) { return d.total_count != null ? d.total_count : (d.total != null ? d.total : 0); } },
        { field: 'success_count', title: '成功', width: 90, align: 'center', templet: function (d) { return d.success_count != null ? d.success_count : (d.success != null ? d.success : 0); } },
        { field: 'failed_count', title: '失败', width: 90, align: 'center', templet: function (d) { return d.failed_count != null ? d.failed_count : (d.failed != null ? d.failed : 0); } },
        { field: 'status', title: '状态', width: 100, align: 'center', templet: '#statusTpl' },
        { field: 'create_time', title: '创建时间', width: 165, templet: function (d) { return escapeHtml(d.create_time || d.created_at || ''); } },
        { title: '操作', width: 110, toolbar: '#rowBar', fixed: 'right' }
    ]];

    table.render({
        elem: '#batchesTable',
        id: 'batchesTable',
        url: API + '/batches',
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

    // ============ 模板下拉（取自模板选项接口） ============
    var templateOptionsCache = null;
    function fillSelect($sel, list, placeholder) {
        $sel.empty();
        if (placeholder !== undefined) { $sel.append('<option value="">' + escapeHtml(placeholder) + '</option>'); }
        (list || []).forEach(function (it) {
            var val, txt;
            if (it && typeof it === 'object') {
                val = firstVal(it, ['code', 'value', 'id']);
                txt = firstVal(it, ['name', 'label', 'title']);
                if (txt === '') { txt = val; }
            } else { val = txt = it; }
            $sel.append('<option value="' + escapeHtml(val) + '">' + escapeHtml(txt) + '</option>');
        });
        form.render('select');
    }
    function loadTemplateOptions(cb) {
        if (templateOptionsCache) {
            fillSelect($('#batTemplateCode'), templateOptionsCache, '请选择模板');
            if (cb) { cb(); }
            return;
        }
        $.get(API + '/templates/options', function (res) {
            if (res.code === 0) { templateOptionsCache = res.data || []; }
            fillSelect($('#batTemplateCode'), templateOptionsCache || [], '请选择模板');
            // 选项到达后重渲染列表，使模板列由编码回退升级为名称显示
            table.reload('batchesTable');
            if (cb) { cb(); }
        }, 'json');
    }
    loadTemplateOptions();

    // 渲染所选模板声明的变量输入框：左侧显示变量中文 label，placeholder 保持按变量 key 提示（与发送页一致）
    function renderBatchVars(code) {
        var $wrap = $('#batVarsWrap');
        $wrap.empty();
        if (!code) { return; }
        var tpl = null;
        var list = templateOptionsCache || [];
        for (var i = 0; i < list.length; i++) {
            if (String(firstVal(list[i], ['code', 'value', 'id'])) === String(code)) { tpl = list[i]; break; }
        }
        var declared = tpl ? (tpl.variables || []) : [];
        var extracted = tpl ? (tpl.extracted_variables || []) : [];
        if (typeof declared === 'string') {
            try { declared = JSON.parse(declared); } catch (e) { declared = []; }
        }
        // 归一化为 {name, label} 列表：优先模板声明（含中文 label），再补正文提取到的 key
        var vars = [], seen = {};
        (Array.isArray(declared) ? declared : []).forEach(function (v) {
            if (v && typeof v === 'object') {
                var n = firstVal(v, ['name', 'key', 'var']);
                if (!n) { return; }
                vars.push({ name: String(n), label: String(v.label || v.title || n) });
                seen[n] = true;
            } else if (v) {
                var k = String(v);
                vars.push({ name: k, label: k });
                seen[k] = true;
            }
        });
        (Array.isArray(extracted) ? extracted : []).forEach(function (v) {
            var n = (v && typeof v === 'object') ? firstVal(v, ['name', 'key', 'var', 'label']) : String(v);
            if (!n || seen[n]) { return; }
            vars.push({ name: String(n), label: String((v && typeof v === 'object') ? firstVal(v, ['label', 'title', 'name']) : n) });
            seen[n] = true;
        });
        if (!vars.length) { $wrap.append('<div class="helper-tip">该模板未声明变量，可留空直接创建。</div>'); return; }
        vars.forEach(function (v) {
            $wrap.append(
                '<div class="layui-form-item">' +
                    '<label class="layui-form-label" for="batvar_' + escapeHtml(v.name) + '">' + escapeHtml(v.label) + '</label>' +
                    '<div class="layui-input-block">' +
                        '<input type="text" id="batvar_' + escapeHtml(v.name) + '" class="layui-input var-input" data-var="' + escapeHtml(v.name) + '" placeholder="填写 ' + escapeHtml(v.name) + ' 的值">' +
                    '</div>' +
                '</div>'
            );
        });
    }

    // 选择模板后按模板声明渲染变量表单
    form.on('select(batTemplateCode)', function (data) {
        renderBatchVars(data.value);
    });

    // ============ 新建群发弹窗 ============
    var batchLayerIndex = 0;

    function fmtDateTime(v) {
        if (!v) { return ''; }
        var s = String(v).replace('T', ' ');
        if (s.length === 16) { s += ':00'; }
        return s;
    }

    function openForm() {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        $('#batName').val('');
        $('#batRecipients').val('');
        renderBatchVars('');
        $('#batScheduledAt').val('');
        loadTemplateOptions(function () {
            $('#batTemplateCode').val('');
            form.render('select');
            renderBatchVars('');
            batchLayerIndex = layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
                type: 1,
                title: '新建批量群发',
                area: ['680px', '540px'],
                content: $('#batchForm'),
                btn: false,
                success: function () { $('#batchForm').show(); form.render(); },
                end: function () { $('#batchForm').hide(); }
            });
        });
    }

    $('#btnCreate').on('click', openForm);
    $('#btnCancelBatch').on('click', function () { if (batchLayerIndex) { layer.close(batchLayerIndex); } });

    // 保存群发任务
    $('#btnSaveBatch').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        // 收集模板变量输入框为对象（按模板声明生成，不再要求手写 JSON）
        var vars = {};
        $('#batVarsWrap .var-input').each(function () { vars[$(this).data('var')] = $(this).val(); });
        var payload = {
            name: $.trim($('#batName').val()),
            template_code: $('#batTemplateCode').val() || '',
            recipients: $('#batRecipients').val(),
            vars: vars,
            scheduled_at: fmtDateTime($('#batScheduledAt').val())
        };
        if (!payload.name || !payload.template_code) { layer.msg('请填写名称并选择模板', { icon: 2 }); return; }
        if (!$.trim(payload.recipients)) { layer.msg('请填写收件人邮箱，每行一个', { icon: 2 }); return; }

        if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/batches',
            type: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld); busyUnlock();
                if (res.code === 0) {
                    layer.close(batchLayerIndex);
                    layer.msg(res.message || '已创建', { icon: 1, time: 1200 });
                    table.reload('batchesTable');
                } else {
                    layer.msg(res.message || '创建失败', { icon: 2, time: 2000 });
                }
            },
            error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '创建失败'), { icon: 2 }); }
        });
    });

    // ============ 行内取消 ============
    table.on('tool(batchesTable)', function (obj) {
        var data = obj.data, layEvent = obj.event;
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        if (layEvent !== 'cancel') { return; }
        layer.confirm('确定取消群发任务「' + escapeHtml(data.name || '') + '」？', { icon: 3, title: '取消确认' }, function (idx) {
            layer.close(idx);
            $.ajax({
                url: API + '/batches/' + data.id + '/cancel',
                type: 'POST', contentType: 'application/json', data: JSON.stringify({}), dataType: 'json',
                success: function (res) {
                    if (res.code === 0) { layer.msg(res.message || '已取消', { icon: 1 }); table.reload('batchesTable'); }
                    else { layer.msg(res.message || '取消失败', { icon: 2 }); }
                },
                error: function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '取消失败'), { icon: 2 }); }
            });
        });
    });
});
</script>
</body>
</html>
