<?php

/*
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Support\VisitLogger;

/**
 * 访问统计控制器
 */
final class StatsController
{
    /**
     * 前台访问上报（公开接口，无需登录）
     */
    public function track(Request $request): void
    {
        $path = (string) $request->input('path', '/');
        $postId = (int) $request->input('post_id', 0);

        VisitLogger::record([
            'path' => $path,
            'post_id' => $postId > 0 ? $postId : null,
            'referer' => (string) $request->input('referer', ''),
            'ua' => (string) $request->header('User-Agent'),
        ]);

        Response::success(null, 'ok');
    }

    /**
     * 总览统计（管理员）
     */
    public function overview(Request $request): void
    {
        Auth::requireAdmin($request);
        $days = (int) ($request->query['days'] ?? 30);
        Response::success(VisitLogger::overview($days));
    }

    /**
     * 单篇文章统计（管理员）
     */
    public function post(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $days = (int) ($request->query['days'] ?? 30);
        Response::success(VisitLogger::postStats((int) $id, $days));
    }
}
