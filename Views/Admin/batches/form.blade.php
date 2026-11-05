{{-- 新建批量群发弹窗容器：仅含隐藏节点，字段由列表页脚本读写，弹层直接取本节点显示 --}}
<div id="batchForm" style="display:none">
    <div class="modal-wrap">
        <form class="layui-form modal-scroll" lay-filter="batchForm" autocomplete="off">
            <div class="layui-form-item">
                <label class="layui-form-label">任务名称</label>
                <div class="layui-input-block">
                    <input type="text" id="batName" class="layui-input" placeholder="便于识别的名称，如：本月会员通知">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">邮件模板</label>
                <div class="layui-input-block">
                    <select id="batTemplateCode"></select>
                    <div class="helper-tip">下拉数据来自邮件模板选项接口。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">收件人</label>
                <div class="layui-input-block">
                    <textarea id="batRecipients" class="layui-textarea" style="min-height:130px;" placeholder="每行一个邮箱地址"></textarea>
                    <div class="helper-tip">每行填写一个收件邮箱，按行交由服务端解析。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">模板变量</label>
                <div class="layui-input-block">
                    <textarea id="batVars" class="layui-textarea" style="min-height:80px;" placeholder='JSON 对象，可留空，如：{"site_name":"CmsPro"}'></textarea>
                    <div class="helper-tip">整批共用的变量取值，须为合法 JSON 对象；留空表示不带变量。</div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">定时执行</label>
                <div class="layui-input-block">
                    <input type="datetime-local" id="batScheduledAt" class="layui-input" style="width:260px;">
                    <div class="helper-tip">留空表示立即执行；填写后转换为年月日时分秒格式提交。</div>
                </div>
            </div>
        </form>
        {{-- 固定底部按钮栏，始终贴底不随内容滚动 --}}
        <div class="modal-bottom">
            <div class="button-container">
                <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="btnSaveBatch" data-permission="cmspro.smtpmail.batch"><i class="fa fa-save"></i> 创建</button>
                <button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="btnCancelBatch"><i class="fa fa-times"></i> 取消</button>
            </div>
        </div>
    </div>
</div>
