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
 * 分类控制器
 */
final class CategoryController
{
    /**
     * 列表（含文章数统计）
     */
    public function index(Request $request): void
    {
        $categories = Database::select(
            'SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS post_count
             FROM categories c ORDER BY c.sort_order ASC, c.id ASC'
        );
        Response::success($categories);
    }

    /**
     * 创建
     */
    public function store(Request $request): void
    {
        Auth::requireAdmin($request);
        $data = $this->validate($request);
        $id = Database::insert('categories', $data);
        Response::success(Database::selectOne('SELECT * FROM categories WHERE id = :id', ['id' => $id]), '分类创建成功', 201);
    }

    /**
     * 更新
     */
    public function update(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $existing = Database::selectOne('SELECT * FROM categories WHERE id = :id', ['id' => $id]);
        if ($existing === null) {
            Response::error('分类不存在', 404);
        }
        Database::update('categories', $this->validate($request), 'id = :id', ['id' => $id]);
        Response::success(Database::selectOne('SELECT * FROM categories WHERE id = :id', ['id' => $id]), '分类更新成功');
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $count = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM posts WHERE category_id = :id', ['id' => $id])['c'] ?? 0);
        if ($count > 0) {
            Response::error("该分类下还有 {$count} 篇文章，请先移除或移动这些文章", 422);
        }
        Database::delete('categories', 'id = :id', ['id' => $id]);
        Response::success(null, '分类已删除');
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Request $request): array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::error('分类名称不能为空', 422);
        }
        if (mb_strlen($name) > 50) {
            Response::error('分类名称过长（最多 50 字符）', 422);
        }
        $duplicate = Database::selectOne(
            'SELECT id FROM categories WHERE name = :name AND id != :id',
            ['name' => $name, 'id' => (int) $request->param('id', 0)]
        );
        if ($duplicate !== null) {
            Response::error('已存在同名分类', 422);
        }

        return [
            'name' => $name,
            'slug' => trim((string) $request->input('slug', '')) ?: slugify($name),
            'description' => mb_substr((string) $request->input('description', ''), 0, 200),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
