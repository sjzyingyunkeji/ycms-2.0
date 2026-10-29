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
 * 标签控制器
 */
final class TagController
{
    /**
     * 列表（含文章数统计）
     */
    public function index(Request $request): void
    {
        $tags = Database::select(
            'SELECT t.*, (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) AS post_count
             FROM tags t ORDER BY t.id ASC'
        );
        Response::success($tags);
    }

    /**
     * 创建
     */
    public function store(Request $request): void
    {
        Auth::requireAdmin($request);
        $data = $this->validate($request);
        $id = Database::insert('tags', $data);
        Response::success(Database::selectOne('SELECT * FROM tags WHERE id = :id', ['id' => $id]), '标签创建成功', 201);
    }

    /**
     * 更新
     */
    public function update(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $existing = Database::selectOne('SELECT * FROM tags WHERE id = :id', ['id' => $id]);
        if ($existing === null) {
            Response::error('标签不存在', 404);
        }
        Database::update('tags', $this->validate($request), 'id = :id', ['id' => $id]);
        Response::success(Database::selectOne('SELECT * FROM tags WHERE id = :id', ['id' => $id]), '标签更新成功');
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        Database::delete('tags', 'id = :id', ['id' => $id]);
        Response::success(null, '标签已删除');
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Request $request): array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::error('标签名称不能为空', 422);
        }
        if (mb_strlen($name) > 30) {
            Response::error('标签名称过长（最多 30 字符）', 422);
        }
        return [
            'name' => $name,
            'slug' => trim((string) $request->input('slug', '')) ?: slugify($name),
        ];
    }
}
