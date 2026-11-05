{{-- 邮件模板新增编辑弹窗容器：仅含隐藏节点，字段由列表页脚本读写，弹层直接取本节点显示 --}}
<div id="templateForm" style="display:none">
    <div class="modal-wrap">
        <form class="layui-form modal-scroll" lay-filter="templateForm" autocomplete="off">
            <div class="layui-form-item">
                <label class="layui-form-label">模板编码</label>
                <div class="layui-input-block">
                    <input type="text" id="tplCode" class="layui-input" placeholder="唯一编码，如：welcome_mail">
                    <div class="helper-tip">内置模板的编码不可修改；发送邮件时通过此编码引用模板。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">模板名称</label>
                <div class="layui-input-block">
                    <input type="text" id="tplName" class="layui-input" placeholder="便于识别的名称，如：欢迎邮件">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">分类</label>
                <div class="layui-input-block">
                    <input type="text" id="tplCategory" class="layui-input" placeholder="选填，如：通知 / 营销">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">邮件主题</label>
                <div class="layui-input-block">
                    <input type="text" id="tplSubject" class="layui-input" placeholder="邮件标题，支持变量占位符">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">HTML 正文</label>
                <div class="layui-input-block">
                    <textarea id="tplBodyHtml" class="layui-textarea code-editor" placeholder="填写完整的 HTML 邮件内容"></textarea>
                    <div class="helper-tip">使用 @{{ 变量名 }} 作为占位符；保存后可在列表中点击「预览」查看渲染效果。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">纯文本正文</label>
                <div class="layui-input-block">
                    <textarea id="tplBodyText" class="layui-textarea" style="min-height:80px;" placeholder="不支持 HTML 的邮件客户端显示的纯文本内容，选填"></textarea>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">发信账号</label>
                <div class="layui-input-block">
                    <select id="tplAccountId"></select>
                    <div class="helper-tip">下拉数据来自发信账号列表；留空表示跟随默认账号。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">模板变量</label>
                <div class="layui-input-block">
                    <textarea id="tplVars" class="layui-textarea" style="min-height:70px;" placeholder='可填 JSON 对象或简单文本，允许留空'></textarea>
                    <div class="helper-tip">可填写变量声明或默认取值，允许留空；具体格式以后端解析规则为准。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">状态</label>
                <div class="layui-input-block">
                    <select id="tplStatus">
                        <option value="1">启用</option>
                        <option value="0">停用</option>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">备注</label>
                <div class="layui-input-block">
                    <input type="text" id="tplRemark" class="layui-input" placeholder="选填，便于记录模板用途">
                </div>
            </div>
        </form>
        {{-- 固定底部按钮栏，始终贴底不随内容滚动 --}}
        <div class="modal-bottom">
            <div class="button-container">
                <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="btnSaveTemplate" data-permission="cmspro.smtpmail.template"><i class="fa fa-save"></i> 保存</button>
                <button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="btnCancelTemplate"><i class="fa fa-times"></i> 取消</button>
            </div>
        </div>
    </div>
</div>
