{{-- 新增黑名单邮箱弹窗容器：仅含隐藏节点，字段由列表页脚本读写，弹层直接取本节点显示 --}}
<div id="blacklistForm" style="display:none">
    <div class="modal-wrap">
        <form class="layui-form modal-scroll" lay-filter="blacklistForm" autocomplete="off">
            <div class="layui-form-item">
                <label class="layui-form-label">邮箱地址</label>
                <div class="layui-input-block">
                    <input type="text" id="blEmail" class="layui-input" placeholder="如：spam@example.com">
                    <div class="helper-tip">加入后该邮箱将在发送前被自动过滤。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">加入原因</label>
                <div class="layui-input-block">
                    <textarea id="blReason" class="layui-textarea" style="min-height:80px;" placeholder="选填，如：用户投诉 / 地址无效"></textarea>
                </div>
            </div>
        </form>
        {{-- 固定底部按钮栏，始终贴底不随内容滚动 --}}
        <div class="modal-bottom">
            <div class="button-container">
                <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="btnSaveBlacklist" data-permission="cmspro.smtpmail.setting"><i class="fa fa-save"></i> 保存</button>
                <button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="btnCancelBlacklist"><i class="fa fa-times"></i> 取消</button>
            </div>
        </div>
    </div>
</div>
