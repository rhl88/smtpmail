<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>应用设置 - SMTP邮箱</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        /* 固定底部按钮栏 + 内容区自适应滚动 */
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        *, *::before, *::after { box-sizing: border-box; }
        .settings-form { height: 100%; }
        .settings-scroll { height: calc(100vh - 52px); overflow-y: auto; padding: 15px; margin: 0; }
        .bottom { height: 52px; background: #fff; border-top: 1px solid #e6e6e6; text-align: right; }
        .bottom .button-container { display: inline-block; line-height: 52px; padding-right: 15px; }
        .bottom .button-container button { margin-left: 10px; }
        .helper-tip { color: #999; font-size: 12px; margin-top: 4px; }
        .takeover-doc { background: #f0f7ff; border: 1px solid #cfe3ff; color: #31578a; padding: 10px 14px; border-radius: 3px; font-size: 13px; line-height: 1.9; margin-bottom: 12px; }
        .takeover-status { padding: 10px 14px; border-radius: 3px; font-size: 13px; line-height: 1.9; margin-bottom: 12px; }
        .takeover-status.ok { background: #f0f9eb; border: 1px solid #c2e7b0; color: #3f7a24; }
        .takeover-status.warn { background: #fff7e6; border: 1px solid #ffd591; color: #ad4e00; }
        /* 表单标签单行显示，不覆盖 Layui 默认宽度 */
        .layui-form-label { white-space: nowrap; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<form class="layui-form settings-form" lay-filter="settingsForm" autocomplete="off">
    <div class="layui-row settings-scroll">
        <div class="layui-col-md12">
            {{-- 接管系统邮件开关说明 --}}
            <div class="takeover-doc">
                <i class="fa fa-info-circle"></i> <strong>接管系统邮件：</strong>开启后，系统自带的注册验证码、找回密码邮件改用本应用的默认账号与内置模板（<code>verify_code</code> 验证码、<code>password_reset</code> 找回密码）发送，不修改框架代码；关闭时系统邮件保持原生行为。需同时配置默认发信账号，并保证两套内置模板处于启用状态。
            </div>

            {{-- 接管生效状态提示 --}}
            <div class="takeover-status warn" id="takeoverStatus"><i class="fa fa-spinner"></i> 正在检测接管状态…</div>

            {{-- 按分组渲染的配置项 --}}
            <div id="settingsGroups"></div>
        </div>
    </div>

    {{-- 固定底部按钮栏 --}}
    <div class="bottom">
        <div class="button-container">
            <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="btnSaveSettings" data-permission="cmspro.smtpmail.setting"><i class="fa fa-save"></i> 保存设置</button>
            <button type="reset" class="layui-btn layui-btn-primary layui-btn-sm"><i class="fa fa-refresh"></i> 重新载入</button>
        </div>
    </div>
</form>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'form', 'layer'], function () {
    var $ = layui.jquery,
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
    var PERM = 'cmspro.smtpmail.setting';
    var GROUPS = [
        { key: 'general', title: '发信基础设置' },
        { key: 'api', title: '接口访问设置' },
        { key: 'system_templates', title: '系统邮件模板映射' }
    ];

    // HTML 转义，避免拼接内容造成注入
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 权限判断与按钮隐藏（本页无表格，页面初始化后隐藏一次即可）
    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    // 将接口返回的配置项整理为三个分组
    function normalize(data) {
        var out = { general: [], api: [], system_templates: [] };
        function push(it) {
            if (!it || typeof it !== 'object') { return; }
            var g = it.group || it.group_name || it.config_group;
            if (!g) {
                var n = String(it.name || it.key || '');
                if (n === 'api_enabled' || n === 'api_token' || n.indexOf('api_') === 0) { g = 'api'; }
                else if (n.indexOf('template_') === 0) { g = 'system_templates'; }
                else { g = 'general'; }
            }
            if (!out[g]) { g = 'general'; }
            out[g].push(it);
        }
        if (Array.isArray(data)) {
            data.forEach(push);
        } else if (data && typeof data === 'object') {
            var consumed = false;
            GROUPS.forEach(function (gr) {
                if (Array.isArray(data[gr.key])) {
                    data[gr.key].forEach(function (it) { it.group = gr.key; push(it); });
                    consumed = true;
                }
            });
            if (!consumed) {
                if (Array.isArray(data.items)) { data.items.forEach(push); }
                else if (Array.isArray(data.settings)) { data.settings.forEach(push); }
                else if (Array.isArray(data.groups)) {
                    data.groups.forEach(function (gr) {
                        (gr.items || []).forEach(function (it) { it.group = gr.name || gr.key; push(it); });
                    });
                }
            }
        }
        return out;
    }

    // 渲染分组表单：开关、密码、数字、文本四类字段
    function renderGroups(data) {
        var groups = normalize(data);
        var html = '';
        GROUPS.forEach(function (gr) {
            var items = groups[gr.key] || [];
            html += '<div class="layui-card"><div class="layui-card-header"><i class="fa fa-cog"></i> ' + escapeHtml(gr.title) + '</div><div class="layui-card-body">';
            if (!items.length) { html += '<div class="helper-tip">该分组暂无配置项。</div>'; }
            items.forEach(function (it) {
                var key = it.key || it.name || '';
                var title = it.title || it.label || key;
                var type = it.type || 'text';
                var value = it.value == null ? '' : it.value;
                var tips = it.tips || '';
                html += '<div class="layui-form-item"><label class="layui-form-label">' + escapeHtml(title) + '</label><div class="layui-input-block">';
                if (type === 'switch') {
                    var on = (String(value) === '1' || value === 1 || value === true || value === 'true');
                    html += '<input type="checkbox" lay-skin="switch" lay-text="开启|关闭" data-key="' + escapeHtml(key) + '" data-type="switch"' + (on ? ' checked' : '') + '>';
                } else if (type === 'password') {
                    // 密码字段返回时已脱敏，不回填；留空表示不修改
                    html += '<input type="password" class="layui-input" data-key="' + escapeHtml(key) + '" data-type="password" value="" placeholder="已脱敏保存，留空表示不修改">';
                } else if (type === 'number') {
                    html += '<input type="number" class="layui-input" data-key="' + escapeHtml(key) + '" data-type="number" value="' + escapeHtml(value) + '">';
                } else {
                    html += '<input type="text" class="layui-input" data-key="' + escapeHtml(key) + '" data-type="text" value="' + escapeHtml(value) + '">';
                }
                if (tips) { html += '<div class="helper-tip">' + escapeHtml(tips) + '</div>'; }
                html += '</div></div>';
            });
            html += '</div></div>';
        });
        $('#settingsGroups').html(html);
        form.render();
    }

    // 加载配置项
    function loadSettings() {
        var ld = layer.load(1, { shade: 0.3 });
        $.get(API + '/settings', function (res) {
            layer.close(ld);
            if (res.code !== 0) { layer.msg(res.message || '设置读取失败', { icon: 2 }); return; }
            renderGroups(res.data || {});
        }, 'json').fail(function () { layer.close(ld); layer.msg('设置读取失败', { icon: 2 }); });
    }

    // 按候选键取布尔值，兼容不同状态字段命名
    function pickBool(o, keys) {
        for (var i = 0; i < keys.length; i++) {
            var v = o[keys[i]];
            if (v !== undefined && v !== null) {
                return (v === true || v === 1 || v === '1' || v === 'true' || v === 'yes');
            }
        }
        return false;
    }

    // 加载并展示接管是否生效、默认账号是否配置
    function loadTakeoverStatus() {
        $.get(API + '/settings/takeover-status', function (res) {
            if (res.code !== 0) {
                $('#takeoverStatus').removeClass('ok').addClass('warn').html('<i class="fa fa-warning"></i> 接管状态读取失败：' + escapeHtml(res.message || '未知原因'));
                return;
            }
            var d = res.data || {};
            var enabled = pickBool(d, ['enabled', 'active', 'takeover_enabled', 'takeover', 'is_active']);
            var hasDefault = pickBool(d, ['has_default_account', 'default_account_configured', 'has_default', 'default_account']);
            var html = '<i class="fa ' + (enabled && hasDefault ? 'fa-check-circle' : 'fa-warning') + '"></i> <strong>接管状态：</strong>';
            html += enabled ? '接管系统邮件已生效' : '接管系统邮件未生效（当前系统邮件保持原生行为）';
            html += '；';
            html += hasDefault ? '默认发信账号已配置' : '默认发信账号未配置，请先到「发信账号」设置默认账号';
            $('#takeoverStatus').removeClass('ok warn').addClass(enabled && hasDefault ? 'ok' : 'warn').html(html);
        }, 'json').fail(function () {
            $('#takeoverStatus').removeClass('ok').addClass('warn').html('<i class="fa fa-warning"></i> 接管状态读取失败');
        });
    }

    // 保存：按键值扁平对象提交，密码字段留空则跳过
    $('#btnSaveSettings').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        var payload = {};
        $('[data-key]').each(function () {
            var $el = $(this);
            var key = $el.data('key');
            var type = $el.data('type');
            var val = (type === 'switch') ? ($el.is(':checked') ? '1' : '0') : $el.val();
            if (type === 'password' && $.trim(val) === '') { return; }
            payload[key] = val;
        });

        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/settings',
            type: 'PUT', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld);
                if (res.code === 0) {
                    layer.msg(res.message || '保存成功', { icon: 1, time: 1200 });
                    loadSettings();
                    loadTakeoverStatus();
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2, time: 2000 });
                }
            },
            error: function () { layer.close(ld); layer.msg('保存失败', { icon: 2 }); }
        });
    });

    // 重新载入：恢复为服务端最新配置
    $('button[type="reset"]').on('click', function () { setTimeout(loadSettings, 0); });

    loadSettings();
    loadTakeoverStatus();
});
</script>
</body>
</html>
