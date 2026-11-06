<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>发送邮件 - SMTP邮箱</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        /* 固定底部按钮栏 + 内容区自适应滚动 */
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        *, *::before, *::after { box-sizing: border-box; }
        .send-form { height: 100%; }
        .send-scroll { height: calc(100vh - 52px); overflow-y: auto; padding: 15px; margin: 0; }
        .bottom { height: 52px; background: #fff; border-top: 1px solid #e6e6e6; text-align: right; }
        .bottom .button-container { display: inline-block; line-height: 52px; padding-right: 15px; }
        .bottom .button-container button { margin-left: 10px; }
        .helper-tip { color: #999; font-size: 12px; margin-top: 4px; }
        .notice-bar { background: rgba(30, 159, 255, 0.08); border: 1px solid rgba(30, 159, 255, 0.35); color: #1e9fff; padding: 8px 12px; border-radius: 3px; font-size: 13px; line-height: 1.8; margin-bottom: 12px; }
        .code-editor { font-size: 12px; line-height: 1.6; background: whitesmoke; color: #5f5f5f; min-height: 160px; }
        .result-box { margin-top: 12px; padding: 12px 14px; border-radius: 3px; background: rgba(22, 183, 119, 0.08); border: 1px solid rgba(22, 183, 119, 0.35); color: #16b777; font-size: 14px; line-height: 1.8; }
        /* 表单标签单行显示，不覆盖 Layui 默认宽度 */
        .layui-form-label { white-space: nowrap; }
        /* 高级选项折叠开关 */
        .advanced-toggle { display: inline-block; margin: 2px 0 14px; padding: 6px 10px; color: #1e9fff; font-size: 13px; cursor: pointer; border-radius: 3px; }
        .advanced-toggle:hover { background: rgba(30, 159, 255, 0.08); }
        .advanced-toggle .fa { margin-right: 4px; }
        /* 已上传附件列表 */
        .upload-list { margin-top: 8px; }
        .upload-list .upload-item { display: flex; align-items: center; margin-bottom: 6px; font-size: 13px; color: #5f5f5f; }
        .upload-list .upload-item .fa-file-o { color: #1e9fff; margin-right: 6px; }
        .upload-list .upload-item .remove-upload { color: #ff5722; margin-left: 10px; cursor: pointer; }
        .upload-list .upload-item .upload-path-tip { color: #999; font-size: 12px; margin-left: 8px; }
    </style>
    @include('admin.partials.permission-script')
</head>
<body>
<form class="layui-form send-form" lay-filter="sendForm" autocomplete="off">
    <div class="layui-row send-scroll">
        <div class="layui-col-md12">
            {{-- 模式说明 --}}
            <div class="notice-bar">
                <i class="fa fa-info-circle"></i> <strong>两种发送模式：</strong>选择邮件模板即为「模板模式」，填写模板变量后按模板渲染发送；选择「不使用模板（直发）」即为「直发模式」，直接填写邮件主题与 HTML 正文发送。收件人、抄送、密送均可用逗号或分号分隔多个地址。
            </div>

            {{-- 基础信息 --}}
            <div class="layui-card">
                <div class="layui-card-header"><i class="fa fa-paper-plane"></i> 发送信息</div>
                <div class="layui-card-body">
                    <div class="layui-form-item">
                        <label class="layui-form-label">邮件模板</label>
                        <div class="layui-input-block">
                            <select id="sendTemplate" lay-filter="sendTemplate"></select>
                            <div class="helper-tip">留空即进入直发模式；模板与账号下拉数据来自选项接口。</div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">发信账号</label>
                        <div class="layui-input-block">
                            <select id="sendAccountCode"></select>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">收件人</label>
                        <div class="layui-input-block">
                            <input type="text" id="sendTo" class="layui-input" placeholder="必填；多个地址用逗号或分号分隔">
                        </div>
                    </div>
                    <a href="javascript:;" id="btnAdvanced" class="advanced-toggle"><i class="fa fa-angle-down" id="advancedArrow"></i> 高级选项（抄送、密送、回信地址、优先级、定时与队列）</a>
                    <div id="advancedWrap" style="display:none;">
                    <div class="layui-form-item">
                        <label class="layui-form-label">抄送</label>
                        <div class="layui-input-block">
                            <input type="text" id="sendCc" class="layui-input" placeholder="选填；多个地址用逗号或分号分隔">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">密送</label>
                        <div class="layui-input-block">
                            <input type="text" id="sendBcc" class="layui-input" placeholder="选填；多个地址用逗号或分号分隔">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">回信地址</label>
                        <div class="layui-input-block">
                            <input type="text" id="sendReplyTo" class="layui-input" placeholder="选填">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">优先级</label>
                        <div class="layui-input-block">
                            <select id="sendPriority">
                                <option value="0">普通（0）</option>
                                <option value="1">优先（1）</option>
                                <option value="5">紧急（5）</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">定时发送</label>
                        <div class="layui-input-block">
                            <input type="datetime-local" id="sendScheduledAt" class="layui-input" style="width:260px;">
                            <div class="helper-tip">留空表示立即发送；填写后转换为年月日时分秒格式提交。</div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">队列异步</label>
                        <div class="layui-input-block">
                            <input type="checkbox" id="sendQueue" title="投递到队列异步发送">
                            <div class="helper-tip">勾选后由队列异步发送；需保证队列服务已运行。</div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>

            {{-- 模板变量区：仅在模板模式下显示 --}}
            <div class="layui-card" id="varsWrapCard">
                <div class="layui-card-header"><i class="fa fa-code"></i> 模板变量</div>
                <div class="layui-card-body">
                    <div id="varsWrap"></div>
                </div>
            </div>

            {{-- 直发内容区：仅在未选模板时显示 --}}
            <div class="layui-card" id="directWrapCard">
                <div class="layui-card-header"><i class="fa fa-file-text-o"></i> 直发内容</div>
                <div class="layui-card-body">
                    <div class="layui-form-item">
                        <label class="layui-form-label">邮件主题</label>
                        <div class="layui-input-block">
                            <input type="text" id="sendSubject" class="layui-input" placeholder="直发模式下必填">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">HTML 正文</label>
                        <div class="layui-input-block">
                            <textarea id="sendHtml" class="layui-textarea code-editor" placeholder="直接填写邮件 HTML 内容"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 附件：上传附件 / 附件路径 二选一（以当前所在 Tab 为准） --}}
            <div class="layui-card">
                <div class="layui-card-header"><i class="fa fa-paperclip"></i> 附件（上传附件与附件路径二选一）</div>
                <div class="layui-card-body">
                    <div class="layui-tab layui-tab-brief" lay-filter="attachTab">
                        <ul class="layui-tab-title">
                            <li class="layui-this">上传附件</li>
                            <li>附件路径</li>
                        </ul>
                        <div class="layui-tab-content">
                            <div class="layui-tab-item layui-show">
                                <button type="button" class="layui-btn layui-btn-sm" id="btnUploadAttach"><i class="fa fa-upload"></i> 选择文件上传</button>
                                <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="btnPickAttach"><i class="fa fa-folder-open"></i> 从附件管理选择</button>
                                <div id="uploadList" class="upload-list"></div>
                                <div class="helper-tip">支持多选；单个文件不超过 10M，禁止上传 php 等服务端脚本文件；也可从系统附件管理中选取已有文件；发送时以当前所在 Tab 为准。</div>
                            </div>
                            <div class="layui-tab-item">
                                <textarea id="sendAttachments" class="layui-textarea" style="min-height:80px;" placeholder="每行一个服务器相对路径或绝对路径"></textarea>
                                <div class="helper-tip">每行一个文件路径，提交时转换为含路径与文件名的附件对象数组。</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 发送结果 --}}
            <div id="sendResult" class="result-box" style="display:none;"></div>
        </div>
    </div>

    {{-- 固定底部按钮栏 --}}
    <div class="bottom">
        <div class="button-container">
            <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="btnPreview" data-permission="cmspro.smtpmail.send"><i class="fa fa-eye"></i> 预览</button>
            <button type="button" class="layui-btn layui-btn-sm" id="btnSend" data-permission="cmspro.smtpmail.send"><i class="fa fa-send"></i> 发送</button>
            <button type="reset" class="layui-btn layui-btn-primary layui-btn-sm"><i class="fa fa-refresh"></i> 重置</button>
        </div>
    </div>
</form>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['jquery', 'form', 'layer', 'upload'], function () {
    var $ = layui.jquery,
        form = layui.form,
        layer = layui.layer,
        upload = layui.upload;

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
    var PERM = 'cmspro.smtpmail.send';
    var templateList = [];

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

    // 权限判断与按钮隐藏（本页无表格，页面初始化后隐藏一次即可）
    function hasPerm(code) { return !window.hasPermission || window.hasPermission(code); }
    function hidePermButtons() {
        $('button[data-permission], a[data-permission]').each(function () {
            if (!hasPerm($(this).data('permission'))) { $(this).hide(); }
        });
    }
    hidePermButtons();

    // 通用下拉填充，兼容不同接口的字段命名
    function fillSelect($sel, list, placeholder, valKeys, txtKeys) {
        $sel.empty();
        if (placeholder !== undefined) { $sel.append('<option value="">' + escapeHtml(placeholder) + '</option>'); }
        (list || []).forEach(function (it) {
            var val, txt;
            if (it && typeof it === 'object') {
                val = firstVal(it, valKeys);
                txt = firstVal(it, txtKeys);
                if (txt === '') { txt = val; }
            } else { val = txt = it; }
            $sel.append('<option value="' + escapeHtml(val) + '">' + escapeHtml(txt) + '</option>');
        });
        form.render('select');
    }

    // 加载模板与账号下拉
    var builtinLabelMap = {}; // 后端统一下发的内置变量中文表单名（site_name=站点名称 等，不再写死在前端）
    function loadOptions() {
        $.get(API + '/send/options', function (res) {
            if (res.code !== 0) { layer.msg(res.message || '选项加载失败', { icon: 2 }); return; }
            var d = res.data || {};
            var templates = d.templates || d.template_options || d.template_list || [];
            var accounts = d.accounts || d.account_options || d.account_list || [];
            templateList = templates;
            builtinLabelMap = {};
            (d.builtin_variables || []).forEach(function (bv) {
                if (bv && typeof bv === 'object' && bv.name) {
                    builtinLabelMap[String(bv.name)] = String(bv.label || bv.name);
                } else if (bv) {
                    builtinLabelMap[String(bv)] = String(bv);
                }
            });
            fillSelect($('#sendTemplate'), templates, '不使用模板（直发）', ['code', 'value', 'id'], ['name', 'label', 'title']);
            fillSelect($('#sendAccountCode'), accounts, '跟随默认账号', ['code', 'value', 'id'], ['name', 'label', 'title']);
            toggleMode();
        }, 'json').fail(function (xhr) { if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '选项加载失败'), { icon: 2 }); });
    }

    // 根据是否选择模板切换变量区与直发内容区
    function toggleMode() {
        var code = $('#sendTemplate').val();
        if (code) { $('#varsWrapCard').show(); $('#directWrapCard').hide(); }
        else { $('#varsWrapCard').hide(); $('#directWrapCard').show(); }
    }

    // 渲染所选模板声明的变量输入框：左侧显示变量中文 label，placeholder 保持按变量 key 提示
    function renderVars(code) {
        var $wrap = $('#varsWrap');
        $wrap.empty();
        if (!code) { return; }
        var tpl = null;
        for (var i = 0; i < templateList.length; i++) {
            if (String(firstVal(templateList[i], ['code', 'value', 'id'])) === String(code)) { tpl = templateList[i]; break; }
        }
        var declared = tpl ? (tpl.variables || []) : [];
        var extracted = tpl ? (tpl.extracted_variables || []) : [];
        if (typeof declared === 'string') {
            try { declared = JSON.parse(declared); } catch (e) { declared = []; }
        }
        // 归一化为 {name, label} 列表：内置变量表单名以后端下发为准，其余用模板声明（含中文 label），再补正文提取到的 key
        function resolveVarLabel(n, declaredLabel) {
            if (builtinLabelMap[n]) { return builtinLabelMap[n]; }
            return declaredLabel || n;
        }
        var list = [], seen = {};
        (Array.isArray(declared) ? declared : []).forEach(function (v) {
            if (v && typeof v === 'object') {
                var n = firstVal(v, ['name', 'key', 'var']);
                if (!n) { return; }
                list.push({ name: String(n), label: resolveVarLabel(String(n), String(v.label || v.title || '')) });
                seen[n] = true;
            } else if (v) {
                var k = String(v);
                list.push({ name: k, label: resolveVarLabel(k, '') });
                seen[k] = true;
            }
        });
        (Array.isArray(extracted) ? extracted : []).forEach(function (v) {
            var n = (v && typeof v === 'object') ? firstVal(v, ['name', 'key', 'var', 'label']) : String(v);
            if (!n || seen[n]) { return; }
            list.push({ name: String(n), label: resolveVarLabel(String(n), String((v && typeof v === 'object') ? (firstVal(v, ['label', 'title', 'name']) || '') : '')) });
            seen[n] = true;
        });
        if (!list.length) { $wrap.append('<div class="helper-tip">该模板未声明变量，可留空直接发送。</div>'); return; }
        list.forEach(function (v) {
            $wrap.append(
                '<div class="layui-form-item">' +
                    '<label class="layui-form-label" for="var_' + escapeHtml(v.name) + '">' + escapeHtml(v.label) + '</label>' +
                    '<div class="layui-input-block">' +
                        '<input type="text" id="var_' + escapeHtml(v.name) + '" class="layui-input var-input" data-var="' + escapeHtml(v.name) + '" placeholder="填写 ' + escapeHtml(v.name) + ' 的值">' +
                    '</div>' +
                '</div>'
            );
        });
    }

    // 收集模板变量为对象
    function collectVars() {
        var vars = {};
        $('#varsWrap .var-input').each(function () { vars[$(this).data('var')] = $(this).val(); });
        return vars;
    }

    // 高级选项折叠切换：默认收起，点击展开/收起
    $('#btnAdvanced').on('click', function () {
        var $wrap = $('#advancedWrap');
        var opening = !$wrap.is(':visible');
        $wrap.toggle();
        $('#advancedArrow').toggleClass('fa-angle-down', !opening).toggleClass('fa-angle-up', opening);
    });

    // 已上传附件列表（与附件路径 Tab 二选一）
    var uploadedFiles = [];
    var activeAttachTab = 'upload'; // layui tab 初始即为「上传附件」
    function renderUploadList() {
        var $list = $('#uploadList');
        $list.empty();
        if (!uploadedFiles.length) { return; }
        uploadedFiles.forEach(function (f, idx) {
            $list.append(
                '<div class="upload-item">' +
                    '<i class="fa fa-file-o"></i>' + escapeHtml(f.name || '') +
                    '<span class="upload-path-tip">' + escapeHtml(f.path || '') + '</span>' +
                    '<span class="remove-upload" data-idx="' + idx + '" title="移除"><i class="fa fa-trash"></i></span>' +
                '</div>'
            );
        });
    }
    $('#uploadList').on('click', '.remove-upload', function () {
        uploadedFiles.splice(parseInt($(this).data('idx'), 10), 1);
        renderUploadList();
    });

    upload.render({
        elem: '#btnUploadAttach',
        url: API + '/send/upload',
        field: 'file',
        accept: 'file',
        multiple: true,
        size: 10240,
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        before: function () { busyLock(); },
        done: function (res) {
            busyUnlock();
            if (res && res.code === 0 && res.data && res.data.path) {
                uploadedFiles.push({ path: String(res.data.path), name: String(res.data.name || '') });
                renderUploadList();
            } else {
                layer.msg((res && res.message) || '附件上传失败', { icon: 2 });
            }
        },
        error: function () {
            busyUnlock();
            layer.msg('附件上传失败', { icon: 2 });
        }
    });

    // 从系统附件管理选择：picker 弹窗选择后经后端解析为服务器绝对路径，加入附件列表
    $('#btnPickAttach').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        layer.open({
            type: 2,
            title: '从附件管理选择',
            area: ['90%', '80%'],
            content: '/admin/attachment?picker=1',
            end: function () {
                // 附件管理 picker：单选写入 selectedAttachmentUrl，批量选择写入 selectedAttachmentUrls（JSON 数组）
                var urls = [];
                var batchRaw = sessionStorage.getItem('selectedAttachmentUrls');
                var singleUrl = sessionStorage.getItem('selectedAttachmentUrl');
                sessionStorage.removeItem('selectedAttachmentUrls');
                sessionStorage.removeItem('selectedAttachmentUrl');
                if (batchRaw) {
                    try { urls = JSON.parse(batchRaw) || []; } catch (e) { urls = []; }
                }
                if (singleUrl) { urls.push(singleUrl); }
                if (!urls.length) { return; }

                if (busyLock()) { return; }
                var ld = layer.load(1, { shade: 0.3 });
                $.ajax({
                    url: API + '/send/attachments/resolve',
                    type: 'POST', contentType: 'application/json', dataType: 'json',
                    data: JSON.stringify({ urls: urls }),
                    success: function (res) {
                        layer.close(ld); busyUnlock();
                        if (res.code === 0 && res.data && res.data.length) {
                            res.data.forEach(function (f) {
                                if (f && f.path) { uploadedFiles.push({ path: String(f.path), name: String(f.name || '') }); }
                            });
                            renderUploadList();
                            layer.msg(res.message || '已加入附件列表', { icon: 1, time: 1200 });
                        } else {
                            layer.msg(res.message || '附件解析失败', { icon: 2 });
                        }
                    },
                    error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '附件解析失败'), { icon: 2 }); }
                });
            }
        });
    });

    // 附件文本转换为路径与文件名对象数组（按当前所在 Tab 二选一）
    function collectAttachments() {
        if (activeAttachTab === 'path') {
            var arr = [];
            String($('#sendAttachments').val() || '').split(/\r?\n/).forEach(function (line) {
                line = $.trim(line);
                if (!line) { return; }
                arr.push({ path: line, name: line.split(/[\\/]/).pop() });
            });
            return arr;
        }
        return uploadedFiles.map(function (f) { return { path: f.path, name: f.name }; });
    }

    // 日期控件值转换为年月日时分秒
    function fmtDateTime(v) {
        if (!v) { return ''; }
        var s = String(v).replace('T', ' ');
        if (s.length === 16) { s += ':00'; }
        return s;
    }

    form.on('select(sendTemplate)', function (data) {
        renderVars(data.value);
        toggleMode();
    });

    // 附件 Tab 切换：跟踪当前 Tab，收集附件时二选一
    var $attachTabItems = $('[lay-filter="attachTab"] .layui-tab-title li');
    $attachTabItems.on('click', function () {
        activeAttachTab = $(this).index() === 0 ? 'upload' : 'path';
    });

    // 邮件 HTML 预览：隔离到 iframe 展示
    function showHtml(title, html) {
        layer.open({ shade: [0.5, '#000'], shadeClose: 0, closeBtn: 1,
            type: 1, title: title, area: ['860px', '640px'],
            content: '<iframe id="sendPreviewFrame" style="display:block;width:100%;height:100%;border:0;"></iframe>',
            success: function () {
                var frame = document.getElementById('sendPreviewFrame');
                if (frame && frame.contentWindow) {
                    var doc = frame.contentWindow.document;
                    doc.open();
                    doc.write(html || '<p style="padding:24px;color:#999;">（无 HTML 内容）</p>');
                    doc.close();
                }
            }
        });
    }
    function pickHtml(res) {
        if (!res || !res.data) { return ''; }
        return (typeof res.data === 'object') ? (res.data.html || '') : String(res.data);
    }

    // 组装发送请求体
    function buildPayload() {
        var payload = {
            template_code: $('#sendTemplate').val() || '',
            to: $.trim($('#sendTo').val()),
            vars: collectVars(),
            cc: $.trim($('#sendCc').val()),
            bcc: $.trim($('#sendBcc').val()),
            reply_to: $.trim($('#sendReplyTo').val()),
            attachments: collectAttachments(),
            priority: parseInt($('#sendPriority').val(), 10) || 0,
            account_code: $('#sendAccountCode').val() || '',
            scheduled_at: fmtDateTime($('#sendScheduledAt').val()),
            queue: $('#sendQueue').is(':checked') ? 1 : 0
        };
        // 直发模式：不填模板编码时补充主题与正文
        if (!payload.template_code) {
            payload.subject = $('#sendSubject').val();
            payload.html = $('#sendHtml').val();
        }
        return payload;
    }

    // 预览
    $('#btnPreview').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        var code = $('#sendTemplate').val();
        if (!code) { layer.msg('直发模式直接填写主题与正文即可，无需模板预览', { icon: 0 }); return; }
        if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/send/preview',
            type: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify({ template_code: code, vars: collectVars() }),
            success: function (res) {
                layer.close(ld); busyUnlock();
                if (res.code === 0) { showHtml('邮件预览', pickHtml(res)); }
                else { layer.msg(res.message || '预览失败', { icon: 2 }); }
            },
            error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '预览失败'), { icon: 2 }); }
        });
    });

    // 发送
    $('#btnSend').on('click', function () {
        if (!hasPerm(PERM)) { layer.msg('无操作权限', { icon: 2 }); return; }
        var payload = buildPayload();
        if (!payload.to) { layer.msg('请填写收件人', { icon: 2 }); return; }
        if (!payload.template_code && !$.trim(payload.subject)) { layer.msg('直发模式请填写邮件主题', { icon: 2 }); return; }

        if (busyLock()) { return; }
        var ld = layer.load(1, { shade: 0.3 });
        $.ajax({
            url: API + '/send',
            type: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify(payload),
            success: function (res) {
                layer.close(ld); busyUnlock();
                if (res.code === 0) {
                    var rid = '';
                    if (res.data && typeof res.data === 'object') { rid = res.data.record_id || res.data.id || ''; }
                    else if (res.data != null && res.data !== '') { rid = res.data; }
                    var html = '<i class="fa fa-check-circle"></i> ' + escapeHtml(res.message || '发送成功');
                    if (rid !== '') { html += '　记录 ID：' + escapeHtml(rid); }
                    $('#sendResult').html(html).show();
                    layer.msg(res.message || '发送成功', { icon: 1 });
                } else {
                    layer.msg(res.message || '发送失败', { icon: 2, time: 2500 });
                }
            },
            error: function (xhr) { layer.close(ld); busyUnlock(); if (xhr.status === 401) { return; } layer.msg(parseError(xhr, '发送失败'), { icon: 2 }); }
        });
    });

    // 重置后回到初始状态
    $('button[type="reset"]').on('click', function () {
        setTimeout(function () {
            $('#sendResult').hide().empty();
            $('#varsWrap').empty();
            uploadedFiles = [];
            renderUploadList();
            $('#advancedWrap').hide();
            $('#advancedArrow').removeClass('fa-angle-up').addClass('fa-angle-down');
            toggleMode();
            form.render();
        }, 0);
    });

    loadOptions();
});
</script>
</body>
</html>
