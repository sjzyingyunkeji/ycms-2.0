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

namespace App\Pro\Theme;

use App\Core\Router;

/**
 * Pro 主题模块：向内核注册主题安装/切换路由
 *
 * 挂载方式：backend/config/modules.php 返回 [self::class]。
 * 开源发行包中 modules.php 为空数组，且 src/Pro 目录被剔除。
 */
final class ThemeModule
{
    public static function register(Router $router): void
    {
        $router->post('/api/system/themes/install', [ThemeAdminController::class, 'install']);
        $router->post('/api/system/themes/activate', [ThemeAdminController::class, 'activate']);
    }
}
