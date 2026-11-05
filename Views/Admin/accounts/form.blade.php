{{-- 发信账号新增编辑弹窗容器：仅含隐藏节点，字段由列表页脚本读写，弹层直接取本节点显示 --}}
<div id="accountForm" style="display:none">
    <div class="modal-wrap">
        <form class="layui-form modal-scroll" lay-filter="accountForm" autocomplete="off">
            <div class="layui-form-item">
                <label class="layui-form-label">名称</label>
                <div class="layui-input-block">
                    <input type="text" id="accName" class="layui-input" placeholder="便于识别的名称，如：主站通知邮箱">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">编码</label>
                <div class="layui-input-block">
                    <input type="text" id="accCode" class="layui-input" placeholder="唯一编码，发送时可据此指定账号">
                    <div class="helper-tip">编码全局唯一，建议使用字母、数字与下划线。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">SMTP 主机</label>
                <div class="layui-input-block">
                    <input type="text" id="accHost" class="layui-input" placeholder="如：smtp.example.com">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">端口</label>
                <div class="layui-input-block">
                    <input type="number" id="accPort" class="layui-input" value="465" min="1" max="65535">
                    <div class="helper-tip">常见端口：25 无加密、465 SSL、587 TLS。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">加密方式</label>
                <div class="layui-input-block">
                    <select id="accScheme">
                        <option value="none">无加密（none）</option>
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">登录用户名</label>
                <div class="layui-input-block">
                    <input type="text" id="accUsername" class="layui-input" placeholder="通常为完整邮箱地址">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">登录密码</label>
                <div class="layui-input-block">
                    <input type="password" id="accPassword" class="layui-input" placeholder="编辑时留空表示保持原值">
                    <div class="helper-tip">出于安全考虑编辑时不回填密码；留空即不修改。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">发件人地址</label>
                <div class="layui-input-block">
                    <input type="text" id="accFromAddress" class="layui-input" placeholder="如：noreply@example.com">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">发件人名称</label>
                <div class="layui-input-block">
                    <input type="text" id="accFromName" class="layui-input" placeholder="如：系统通知">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">回信地址</label>
                <div class="layui-input-block">
                    <input type="text" id="accReplyTo" class="layui-input" placeholder="选填">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">超时秒数</label>
                <div class="layui-input-block">
                    <input type="number" id="accTimeout" class="layui-input" value="10" min="1">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">每分钟限流</label>
                <div class="layui-input-block">
                    <input type="number" id="accRateLimit" class="layui-input" value="0" min="0">
                    <div class="helper-tip">0 表示不限流，且优先于全局限流设置。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">设为默认</label>
                <div class="layui-input-block">
                    <select id="accIsDefault">
                        <option value="0">否</option>
                        <option value="1">是</option>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">状态</label>
                <div class="layui-input-block">
                    <select id="accStatus">
                        <option value="1">启用</option>
                        <option value="0">停用</option>
                    </select>
                </div>
            </div>
        </form>
        {{-- 固定底部按钮栏，始终贴底不随内容滚动 --}}
        <div class="modal-bottom">
            <div class="button-container">
                <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="btnSaveAccount" data-permission="cmspro.smtpmail.account"><i class="fa fa-save"></i> 保存</button>
                <button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="btnCancelAccount"><i class="fa fa-times"></i> 取消</button>
            </div>
        </div>
    </div>
</div>
