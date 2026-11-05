<?php

namespace App\Apps\CmsproSmtpmail\Services;

/**
 * SMTP邮箱 · 系统邮件接管上下文
 *
 * 系统 Mail::send() 先渲染视图（此时把渲染结果暂存到本上下文），
 * 随后 MessageSending 监听器读取上下文改写主题与纯文本正文。
 * 实例按容器单例逐请求隔离，避免常驻进程跨请求污染。
 */
class TakeoverContext
{
    /** @var array<string,mixed>|null */
    private ?array $render = null;

    /**
     * @param array<string,mixed> $render
     */
    public function setRender(array $render): void
    {
        $this->render = $render;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getRender(): ?array
    {
        return $this->render;
    }

    /**
     * 读取并清空（监听器消费一次后复位）
     *
     * @return array<string,mixed>|null
     */
    public function consume(): ?array
    {
        $render = $this->render;
        $this->render = null;

        return $render;
    }
}
