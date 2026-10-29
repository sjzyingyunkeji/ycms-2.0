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
use App\Support\Theme\ThemeManager;

/**
 * 主题信息（只读）
 *
 * 基础版仅支持通过 CLI 安装/切换主题；在线切换属于 Pro 能力。
 */
final class ThemeController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin($request);

        $active = ThemeManager::active();
        $items = [];
        foreach (ThemeManager::installed() as $slug => $meta) {
            $items[] = [
                'slug' => $slug,
                'name' => (string) ($meta['name'] ?? ''),
                'version' => (string) ($meta['version'] ?? ''),
                'description' => (string) ($meta['description'] ?? ''),
                'author' => (string) ($meta['author'] ?? ''),
                'preview' => (string) ($meta['preview'] ?? ''),
                'active' => $slug === $active,
            ];
        }

        $hasProModules = ((array) config('modules', [])) !== [];

        Response::success([
            'active' => $active,
            'state' => ThemeManager::state(),
            'installed' => $items,
            'core_version' => ThemeManager::coreVersion(),
            'switch_supported' => $hasProModules,
            'switch_hint' => $hasProModules
                ? '已启用主题管理模块（Pro）：可通过接口安装/切换主题'
                : '开源版为单主题部署：更换主题请替换 release 主题包并重新构建发布产物',
        ]);
    }
}
