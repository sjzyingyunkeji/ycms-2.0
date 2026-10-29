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

use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Support\Theme\ThemeManager;

/**
 * 主题安装与切换接口（Pro 专属，开源发行包不含本文件）
 *
 * 路由由 App\Pro\Theme\ThemeModule 注册：
 *   POST /api/system/themes/install   multipart: package=<zip>
 *   POST /api/system/themes/activate  json: { "slug": "star" }
 */
final class ThemeAdminController
{
    private const MAX_PACKAGE_BYTES = 30 * 1024 * 1024;

    public function install(Request $request): void
    {
        Auth::requireAdmin($request);
        @set_time_limit(300);

        $file = $request->file('package');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('请选择主题包文件（.zip，字段名 package）', 422);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            Response::error('主题包为空', 422);
        }
        if ($size > self::MAX_PACKAGE_BYTES) {
            Response::error('主题包超出限制（最大 30MB）', 422);
        }

        $tmp = sys_get_temp_dir() . '/ycms-theme-' . bin2hex(random_bytes(6)) . '.zip';
        if (!@move_uploaded_file((string) $file['tmp_name'], $tmp)) {
            Response::error('主题包暂存失败，请检查服务器临时目录权限', 500);
        }

        try {
            $result = ThemeInstaller::install($tmp);
        } finally {
            @unlink($tmp);
        }

        if (($result['ok'] ?? false) !== true) {
            Response::error((string) $result['message'], 422);
        }

        ThemeManager::protectThemesDir();
        Response::success(
            [
                'slug' => $result['slug'] ?? null,
                'active' => ThemeManager::active(),
                'installed' => array_keys(ThemeManager::installed()),
            ],
            (string) $result['message']
        );
    }

    public function activate(Request $request): void
    {
        Auth::requireAdmin($request);
        @set_time_limit(600);
        @ignore_user_abort(true);

        $slug = strtolower(trim((string) $request->input('slug', '')));
        if ($slug === '') {
            Response::error('请指定要切换的主题 slug', 422);
        }

        $result = ThemeSwitcher::activate($slug);
        if (($result['ok'] ?? false) !== true) {
            Response::error((string) $result['message'], 422, $result);
        }

        Response::success([
            'active' => ThemeManager::active(),
            'state' => ThemeManager::state(),
        ], (string) $result['message']);
    }
}
