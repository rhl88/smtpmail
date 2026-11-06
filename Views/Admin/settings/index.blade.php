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
        .takeover-doc { background: rgba(30, 159, 255, 0.08); border: 1px solid rgba(30, 159, 255, 0.35); color: #1e9fff; padding: 10px 14px; border-radius: 3px; font-size: 13px; line-height: 1.9; margin-bottom: 12px; }
        .takeover-status { padding: 10px 14px; border-radius: 3px; font-size: 13px; line-height: 1.9; margin-bottom: 12px; }
        .takeover-status.ok { background: rgba(22, 183, 119, 0.08); border: 1px solid rgba(22, 183, 119, 0.35); color: #16b777; }
        .takeover-status.warn { background: rgba(255, 184, 0, 0.08); border: 1px solid rgba(255, 184, 0, 0.35); color: #ffb800; }
        /* 表单标签单行显示，不覆盖 Layui 默认宽度 */
        .layui-form-label { white-space: nowrap; }
        /* 设置 Tab 与接口文档样式 */
        .settings-tab .layui-tab-title { border-bottom: 1px solid #e6e6e6; }
        .api-doc { font-size: 13px; line-height: 1.9; color: #5f5f5f; }
        .api-doc h3 { font-size: 14px; color: #333; margin: 16px 0 8px; }
        .api-doc table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .api-doc th, .api-doc td { border: 1px solid #e6e6e6; padding: 7px 10px; text-align: left; font-size: 12px; }
        .api-doc th { background: whitesmoke; color: #333; font-weight: normal; }
        .api-doc pre { background: whitesmoke; border: 1px solid #e6e6e6; border-radius: 3px; padding: 12px 14px; font-size: 12px; line-height: 1.7; overflow-x: auto; color: #5f5f5f; margin: 8px 0; }
        .api-doc code { color: #1e9fff; }
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
layui.use(['jquery', 'form', 'layer', 'element'], function () {
    var $ = layui.jquery,
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
    var PERM = 'cmspro.smtpmail.setting';
    var GROUPS = [
        { key: 'general', title: '发信基础设置' },
        { key: 'api', title: '接口访问设置' },
        { key: 'system_templates', title: '系统邮件模板映射' }
    ];
    // 开关字段名单（与 manifest type=switch 的配置项一致；checkbox 不被 jQuery serialize 收集，需单独收集转 '1'/'0'）
    var switchFields = ['takeover_system_mail', 'use_queue', 'enable_blacklist', 'welcome_mail', 'api_enabled'];

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

    // 系统邮件模板映射字段：文本框改为下拉选择模板编码
    var MAP_FIELDS = { template_verify_code: 1, template_password_reset: 1 };
    var templateListCache = [];

    function loadTemplateOptions() {
        $.get(API + '/templates/options', function (res) {
            var d = res.data || {};
            templateListCache = Array.isArray(d) ? d : (d.templates || []);
        }, 'json');
    }

    // 渲染单个配置项控件
    function renderControl(key, title, type, value) {
        var html = '';
        if (type === 'switch') {
            var on = (String(value) === '1' || value === 1 || value === true || value === 'true');
            html = '<input type="checkbox" id="cfg_' + escapeHtml(key) + '" name="' + escapeHtml(key) + '" lay-skin="switch" lay-text="开启|关闭" data-key="' + escapeHtml(key) + '" data-type="switch"' + (on ? ' checked' : '') + '>';
        } else if (type === 'password') {
            // 密钥字段按规范明文回显已保存值，需修改时直接输入新值覆盖保存（12A.5）
            html = '<input type="password" id="cfg_' + escapeHtml(key) + '" name="' + escapeHtml(key) + '" class="layui-input" data-key="' + escapeHtml(key) + '" data-type="password" value="' + escapeHtml(value) + '" placeholder="请输入接口令牌">';
        } else if (type === 'number') {
            html = '<input type="number" id="cfg_' + escapeHtml(key) + '" name="' + escapeHtml(key) + '" class="layui-input" data-key="' + escapeHtml(key) + '" data-type="number" value="' + escapeHtml(value) + '">';
        } else if (MAP_FIELDS[key]) {
            // 系统邮件模板映射：下拉选择模板（当前值不在选项中时追加兜底项）
            var opts = templateListCache.filter(function (t) { return t && String(t.status) !== '0'; });
            html = '<select id="cfg_' + escapeHtml(key) + '" name="' + escapeHtml(key) + '" data-key="' + escapeHtml(key) + '" data-type="text">';
            html += '<option value=""' + (value === '' ? ' selected' : '') + '>不映射（留空）</option>';
            var hit = false;
            opts.forEach(function (t) {
                if (String(t.code) === String(value)) { hit = true; }
            });
            if (value !== '' && !hit) {
                html += '<option value="' + escapeHtml(value) + '" selected>' + escapeHtml(value) + '（当前值）</option>';
            }
            opts.forEach(function (t) {
                html += '<option value="' + escapeHtml(t.code) + '"' + (String(t.code) === String(value) ? ' selected' : '') + '>' + escapeHtml((t.code || '') + (t.name ? ' · ' + t.name : '')) + '</option>';
            });
            html += '</select>';
        } else {
            html = '<input type="text" id="cfg_' + escapeHtml(key) + '" name="' + escapeHtml(key) + '" class="layui-input" data-key="' + escapeHtml(key) + '" data-type="text" value="' + escapeHtml(value) + '">';
        }
        return html;
    }

    // 接口文档 Tab：展示对外 HTTP API 的开关、鉴权、清单与调用示例
    function apiDocHtml() {
        return '<div class="api-doc">' +
            '<h3>一、开关与令牌</h3>' +
            '<table><thead><tr><th style="width:110px;">项目</th><th>说明</th></tr></thead><tbody>' +
            '<tr><td>开关</td><td>在「接口访问设置」开启 <code>HTTP 接口</code>（api_enabled，默认关闭）</td></tr>' +
            '<tr><td>令牌</td><td>在「接口访问设置」填写接口令牌（api_token，留空表示禁止接口调用）</td></tr>' +
            '<tr><td>请求头</td><td>每次请求携带 <code>X-Smtpmail-Token: &lt;接口令牌&gt;</code></td></tr>' +
            '<tr><td>路由前缀</td><td><code>/api/smtpmail</code>，限流每分钟 60 次</td></tr>' +
            '</tbody></table>' +
            '<div class="helper-tip">必须同时开启 HTTP 接口且令牌非空，接口才可用；错误码：40301 接口未开启、40101 令牌无效、40001 参数/业务错误、50001 系统异常。</div>' +
            '<h3>二、接口清单</h3>' +
            '<table><thead><tr><th style="width:70px;">方法</th><th style="width:220px;">路径</th><th>请求参数</th><th>返回 data</th></tr></thead><tbody>' +
            '<tr><td>POST</td><td>/api/smtpmail/send</td><td>模板模式 {template_code, to, vars?}；直发模式 {subject, html, text?, to, options?}；options 可含 cc/bcc/reply_to/attachments/headers/priority/account_code/scheduled_at/queue/source</td><td>{success, record_id, status, message}</td></tr>' +
            '<tr><td>GET</td><td>/api/smtpmail/templates</td><td>无</td><td>模板选项数组 [{code, name, subject, variables, extracted_variables, is_system}]</td></tr>' +
            '<tr><td>GET</td><td>/api/smtpmail/records</td><td>status / keyword / date_from / date_to / per_page</td><td>{total, page, data: 记录摘要数组}</td></tr>' +
            '<tr><td>GET</td><td>/api/smtpmail/status</td><td>无</td><td>{app_id, stats, takeover_enabled, default_account}</td></tr>' +
            '</tbody></table>' +
            '<div class="helper-tip">统一响应结构 {code: 0, message, data, timestamp}，code 非 0 表示失败。</div>' +
            '<h3>三、调用示例</h3>' +
            '<pre># 发送邮件（POST /api/smtpmail/send）\ncurl -X POST \'https://your-domain.com/api/smtpmail/send\' \\\n  -H \'Content-Type: application/json\' \\\n  -H \'X-Smtpmail-Token: YOUR_API_TOKEN\' \\\n  -d \'{"template_code": "verify_code", "to": "user@example.com", "vars": {"code": "482913", "minutes": 10}}\'\n\n# 模板列表\ncurl -X GET \'https://your-domain.com/api/smtpmail/templates\' \\\n  -H \'X-Smtpmail-Token: YOUR_API_TOKEN\'\n\n# 发送记录\ncurl -X GET \'https://your-domain.com/api/smtpmail/records?status=success\' \\\n  -H \'X-Smtpmail-Token: YOUR_API_TOKEN\'\n\n# 状态查询\ncurl -X GET \'https://your-domain.com/api/smtpmail/status\' \\\n  -H \'X-Smtpmail-Token: YOUR_API_TOKEN\'</pre>' +
        '</div>';
    }

    // 渲染分组表单：按配置组分类为 Tab（发信基础 / 接口访问 / 系统邮件模板映射 / 接口文档）
    function renderGroups(data) {
        var groups = normalize(data);
        var tabTitles = '', tabContents = '';
        GROUPS.forEach(function (gr, idx) {
            tabTitles += '<li' + (idx === 0 ? ' class="layui-this"' : '') + '>' + escapeHtml(gr.title) + '</li>';
            var items = groups[gr.key] || [];
            var body = '';
            if (!items.length) { body += '<div class="helper-tip">该分组暂无配置项。</div>'; }
            items.forEach(function (it) {
                var key = it.key || it.name || '';
                var title = it.title || it.label || key;
                var type = it.type || 'text';
                var value = it.value == null ? '' : it.value;
                var tips = it.tips || '';
                body += '<div class="layui-form-item"><label class="layui-form-label" for="cfg_' + escapeHtml(key) + '">' + escapeHtml(title) + '</label><div class="layui-input-block">';
                body += renderControl(key, title, type, value);
                if (tips) { body += '<div class="helper-tip">' + escapeHtml(tips) + '</div>'; }
                body += '</div></div>';
            });
            tabContents += '<div class="layui-tab-item' + (idx === 0 ? ' layui-show' : '') + '">' +
                '<div class="layui-card"><div class="layui-card-body">' + body + '</div></div></div>';
        });
        // 第四个 Tab：接口文档（纯展示，无表单项）
        tabTitles += '<li>接口文档</li>';
        tabContents += '<div class="layui-tab-item"><div class="layui-card"><div class="layui-card-body">' + apiDocHtml() + '</div></div></div>';

        $('#settingsGroups').html(
            '<div class="layui-tab layui-tab-brief settings-tab" lay-filter="settingsTab">' +
                '<ul class="layui-tab-title">' + tabTitles + '</ul>' +
                '<div class="layui-tab-content">' + tabContents + '</div>' +
            '</div>'
        );
        form.render();
    }

    // 加载配置项（先拉取模板选项供映射下拉使用，失败不阻塞设置渲染）
    function loadSettings() {
        var ld = layer.load(1, { shade: 0.3 });
        $.get(API + '/templates/options', function () { loadSettingsInner(ld); }, 'json')
            .fail(function () { loadSettingsInner(ld); });
    }

    function loadSettingsInner(ld) {
        $.get(API + '/settings', function (res) {
            layer.close(ld);
            if (res.code !== 0) { layer.msg(res.message || '设置读取失败', { icon: 2 }); return; }
            renderGroups(res.data || {});
        }, 'json').fail(function (xhr) { layer.close(ld); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '设置读取失败'), { icon: 2 }); });
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
        }, 'json').fail(function (xhr) {
            if (xhr.status === 401) { return; }
            $('#takeoverStatus').removeClass('ok').addClass('warn').html('<i class="fa fa-warning"></i> 接管状态读取失败：' + escapeHtml(parseError(xhr, '未知原因')));
        });
    }

    // 保存：先收集普通输入项，再按 switchFields 单独收集开关（12A.5），密钥清空提交视为不修改
    $('#btnSaveSettings').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        if (busyLock()) { return; }
        var payload = {};
        // 普通输入项 + 模板映射下拉（select 不被 input 收集器覆盖，需单独纳入）
        $('.settings-form input[type="text"], .settings-form input[type="password"], .settings-form input[type="number"], .settings-form select[data-key]').each(function () {
            var key = $(this).data('key');
            if (key) { payload[key] = String($(this).val() == null ? '' : $(this).val()); }
        });
        switchFields.forEach(function (field) {
            payload[field] = $('input[data-key="' + field + '"]').is(':checked') ? '1' : '0';
        });
        if ($.trim(payload.api_token || '') === '') { delete payload.api_token; }

        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/settings',
            type: 'PUT', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld);
                busyUnlock();
                if (res.code === 0) {
                    layer.msg(res.message || '保存成功', { icon: 1, time: 1200 });
                    loadSettings();
                    loadTakeoverStatus();
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2, time: 2000 });
                }
            },
            error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '保存失败'), { icon: 2 }); }
        });
    });

    // 重新载入：恢复为服务端最新配置
    $('button[type="reset"]').on('click', function () { setTimeout(loadSettings, 0); });

    loadTemplateOptions();
    loadSettings();
    loadTakeoverStatus();
});
</script>
</body>
</html>
