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

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

/**
 * 独立页面控制器
 */
final class PageController
{
    /**
     * 列表
     */
    public function index(Request $request): void
    {
        $isAdmin = Auth::user($request) !== null;
        $where = $isAdmin ? '1 = 1' : "status = 'published'";
        $pages = Database::select(
            "SELECT * FROM pages WHERE {$where} ORDER BY sort_order ASC, id ASC"
        );
        Response::success($pages);
    }

    /**
     * 详情（按 ID 或 slug）
     */
    public function show(Request $request, string $id): void
    {
        $isAdmin = Auth::user($request) !== null;
        $column = ctype_digit($id) ? 'id' : 'slug';
        $page = Database::selectOne(
            "SELECT * FROM pages WHERE {$column} = :key",
            ['key' => $id]
        );
        if ($page === null) {
            Response::error('页面不存在', 404);
        }
        if ($page['status'] !== 'published' && !$isAdmin) {
            Response::error('页面不存在', 404);
        }
        Response::success($page);
    }

    /**
     * 创建
     */
    public function store(Request $request): void
    {
        Auth::requireAdmin($request);
        $data = $this->validate($request);
        $id = Database::insert('pages', $data);
        Response::success(Database::selectOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]), '页面创建成功', 201);
    }

    /**
     * 更新
     */
    public function update(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $existing = Database::selectOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]);
        if ($existing === null) {
            Response::error('页面不存在', 404);
        }
        Database::update('pages', $this->validate($request, $existing), 'id = :id', ['id' => $id]);
        Response::success(Database::selectOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]), '页面更新成功');
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        Database::delete('pages', 'id = :id', ['id' => $id]);
        Response::success(null, '页面已删除');
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function validate(Request $request, ?array $existing = null): array
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Response::error('页面标题不能为空', 422);
        }

        // 别名会作为静态页目录名，必须做白名单净化，避免 ../ 等路径穿越
        $slugInput = trim((string) $request->input('slug', ''));
        if ($slugInput !== '') {
            $slug = safe_slug($slugInput, '');
            if ($slug === '') {
                Response::error('页面别名不合法，仅支持字母、数字、中文、下划线与连字符', 422);
            }
        } else {
            $slug = safe_slug($title, 'page');
        }
        $duplicate = Database::selectOne(
            'SELECT id FROM pages WHERE slug = :slug AND id != :id',
            ['slug' => $slug, 'id' => (int) ($existing['id'] ?? 0)]
        );
        if ($duplicate !== null) {
            Response::error('已存在相同别名的页面', 422);
        }

        $status = (string) $request->input('status', $existing['status'] ?? 'published');
        if (!in_array($status, ['draft', 'published'], true)) {
            Response::error('非法的页面状态', 422);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'content' => (string) $request->input('content', ''),
            'status' => $status,
            'sort_order' => (int) $request->input('sort_order', 0),
            'updated_at' => now(),
        ];
    }
}
