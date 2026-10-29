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
 * 文章控制器
 */
final class PostController
{
    /**
     * 列表（公开接口仅返回已发布）
     */
    public function index(Request $request): void
    {
        $user = Auth::user($request);
        $isAdmin = $user !== null;

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $size = min(50, max(1, (int) ($request->query['size'] ?? 10)));
        $status = (string) ($request->query['status'] ?? '');
        $categoryId = (int) ($request->query['category_id'] ?? 0);
        $tagId = (int) ($request->query['tag_id'] ?? 0);
        $keyword = trim((string) ($request->query['keyword'] ?? ''));
        $sort = (string) ($request->query['sort'] ?? 'latest');
        if (!in_array($sort, ['latest', 'hot', 'top'], true)) {
            $sort = 'latest';
        }

        $where = ['1 = 1'];
        $params = [];

        if (!$isAdmin) {
            $where[] = "p.status = 'published'";
        } elseif ($status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }
        if ($categoryId > 0) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($tagId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = :tag_id)';
            $params['tag_id'] = $tagId;
        }
        if ($keyword !== '') {
            $where[] = '(p.title LIKE :keyword OR p.summary LIKE :keyword OR p.content LIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }
        if ($sort === 'top') {
            $where[] = 'p.is_top = 1';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM posts p WHERE {$whereSql}", $params)['c'] ?? 0);

        // 排序：置顶永远优先，组内按所选方式（latest 发布时间 / hot 热度 / top 仅置顶）
        $orderSql = match ($sort) {
            'hot', 'top' => 'p.is_top DESC, p.views DESC, COALESCE(p.published_at, p.created_at) DESC',
            default => 'p.is_top DESC, COALESCE(p.published_at, p.created_at) DESC',
        };

        $items = Database::select(
            "SELECT p.*, c.name AS category_name, u.nickname AS author_name
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $size, 'offset' => ($page - 1) * $size])
        );

        foreach ($items as &$item) {
            $item['tags'] = $this->postTags((int) $item['id']);
            $item['comment_count'] = (int) (Database::selectOne(
                "SELECT COUNT(*) AS c FROM comments WHERE post_id = :pid AND status = 'approved'",
                ['pid' => $item['id']]
            )['c'] ?? 0);
        }
        unset($item);

        Response::paginate($items, $total, $page, $size);
    }

    /**
     * 详情（支持按 ID 或 slug 查找）
     */
    public function show(Request $request, string $id): void
    {
        $isNumeric = ctype_digit($id);
        $post = Database::selectOne(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.nickname AS author_name
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE ' . ($isNumeric ? 'p.id = :id' : 'p.slug = :id'),
            ['id' => $id]
        );
        if ($post === null) {
            Response::error('文章不存在', 404);
        }
        if ($post['status'] !== 'published' && Auth::user($request) === null) {
            Response::error('文章不存在或未发布', 404);
        }

        $post['tags'] = $this->postTags((int) $post['id']);
        $post['comment_count'] = (int) (Database::selectOne(
            "SELECT COUNT(*) AS c FROM comments WHERE post_id = :pid AND status = 'approved'",
            ['pid' => $post['id']]
        )['c'] ?? 0);

        // 上一篇 / 下一篇
        $prev = Database::selectOne(
            "SELECT id, title, slug FROM posts WHERE status = 'published' AND COALESCE(published_at, created_at) < COALESCE(:pa, :ca) ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1",
            ['pa' => $post['published_at'], 'ca' => $post['created_at']]
        );
        $next = Database::selectOne(
            "SELECT id, title, slug FROM posts WHERE status = 'published' AND COALESCE(published_at, created_at) > COALESCE(:pa, :ca) ORDER BY COALESCE(published_at, created_at) ASC LIMIT 1",
            ['pa' => $post['published_at'], 'ca' => $post['created_at']]
        );
        $post['prev_post'] = $prev;
        $post['next_post'] = $next;

        // 相关文章（同标签优先，其次同分类，再按热度）——用于前台展示与 SEO 内链
        $post['related_posts'] = $this->relatedPosts((int) $post['id'], $post['category_id']);

        Response::success($post);
    }

    /**
     * 相关文章
     *
     * @return array<int, array<string, mixed>>
     */
    private function relatedPosts(int $postId, mixed $categoryId, int $limit = 6): array
    {
        $rows = Database::select(
            "SELECT p.id, p.title, p.slug, p.summary, p.cover_image, p.views, c.name AS category_name,
                    (SELECT COUNT(*) FROM post_tags pt2
                     WHERE pt2.post_id = p.id
                       AND pt2.tag_id IN (SELECT tag_id FROM post_tags WHERE post_id = :pid)
                    ) AS tag_score
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.id <> :pid
             ORDER BY tag_score DESC, (CASE WHEN p.category_id = :cat THEN 1 ELSE 0 END) DESC,
                      p.views DESC, COALESCE(p.published_at, p.created_at) DESC
             LIMIT :limit",
            ['pid' => $postId, 'cat' => $categoryId, 'limit' => $limit]
        );

        return $rows;
    }


    /**
     * 创建文章
     */
    public function store(Request $request): void
    {
        $user = Auth::requireAdmin($request);
        $data = $this->validate($request);
        $data['author_id'] = $user['id'];

        $tagIds = $request->input('tag_ids', []);
        if (!is_array($tagIds)) {
            $tagIds = [];
        }

        $status = (string) $data['status'];
        if ($status === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $postId = Database::insert('posts', $data);
        $this->syncTags($postId, $tagIds);

        $this->rebuildStatic();

        Response::success(Database::selectOne('SELECT * FROM posts WHERE id = :id', ['id' => $postId]), '文章创建成功', 201);
    }

    /**
     * 更新文章
     */
    public function update(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $existing = Database::selectOne('SELECT * FROM posts WHERE id = :id', ['id' => $id]);
        if ($existing === null) {
            Response::error('文章不存在', 404);
        }

        $data = $this->validate($request, $existing);
        $tagIds = $request->input('tag_ids', []);
        if (!is_array($tagIds)) {
            $tagIds = [];
        }

        if (($data['status'] ?? '') === 'published' && empty($existing['published_at'])) {
            $data['published_at'] = now();
        }

        Database::update('posts', $data, 'id = :id', ['id' => $id]);
        $this->syncTags((int) $id, $tagIds);

        $this->rebuildStatic();

        Response::success(Database::selectOne('SELECT * FROM posts WHERE id = :id', ['id' => $id]), '文章更新成功');
    }

    /**
     * 删除文章
     */
    public function destroy(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $deleted = Database::delete('posts', 'id = :id', ['id' => $id]);
        if ($deleted === 0) {
            Response::error('文章不存在', 404);
        }
        $this->rebuildStatic();

        Response::success(null, '文章已删除');
    }

    /**
     * 增加浏览量
     */
    public function addView(Request $request, string $id): void
    {
        Database::execute('UPDATE posts SET views = views + 1 WHERE id = :id', ['id' => $id]);
        Response::success(['id' => (int) $id], '浏览量已更新');
    }

    /**
     * 置顶 / 取消置顶（后台列表快捷操作）
     */
    public function toggleTop(Request $request, string $id): void
    {
        Auth::requireAdmin($request);

        $post = Database::selectOne('SELECT id, is_top FROM posts WHERE id = :id', ['id' => $id]);
        if ($post === null) {
            Response::error('文章不存在', 404);
        }

        $input = $request->input('is_top');
        $isTop = $input === null ? ((int) $post['is_top'] === 1 ? 0 : 1) : ((int) $input === 1 ? 1 : 0);

        Database::update('posts', ['is_top' => $isTop, 'updated_at' => now()], 'id = :id', ['id' => $id]);

        $this->rebuildStatic();

        Response::success(['id' => (int) $id, 'is_top' => $isTop], $isTop === 1 ? '已置顶' : '已取消置顶');
    }

    /**
     * 内容变更后重建静态页（失败不影响主流程）
     */
    private function rebuildStatic(): void
    {
        try {
            \App\Support\StaticBuilder::rebuild();
        } catch (\Throwable $e) {
            // 静态化失败不阻断文章保存
        }
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function validate(Request $request, ?array $existing = null): array
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Response::error('文章标题不能为空', 422);
        }
        if (mb_strlen($title) > 200) {
            Response::error('文章标题过长（最多 200 字符）', 422);
        }

        $status = (string) $request->input('status', $existing['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published'], true)) {
            Response::error('非法的文章状态', 422);
        }

        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            $category = Database::selectOne('SELECT id FROM categories WHERE id = :id', ['id' => $categoryId]);
            if ($category === null) {
                Response::error('所选分类不存在', 422);
            }
            $categoryId = (int) $categoryId;
        } else {
            $categoryId = null;
        }

        // 别名会作为静态页目录名，必须做白名单净化，避免 ../ 等路径穿越
        $slugInput = trim((string) $request->input('slug', ''));
        if ($slugInput !== '') {
            $slug = safe_slug($slugInput, '');
            if ($slug === '') {
                Response::error('文章别名不合法，仅支持字母、数字、中文、下划线与连字符', 422);
            }
        } else {
            $slug = safe_slug($title, 'post') . '-' . substr(md5($title . microtime(true)), 0, 4);
        }
        $duplicate = Database::selectOne(
            'SELECT id FROM posts WHERE slug = :slug AND id != :id',
            ['slug' => $slug, 'id' => (int) ($existing['id'] ?? 0)]
        );
        if ($duplicate !== null) {
            Response::error('已存在相同别名的文章，请更换别名', 422);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'summary' => mb_substr(trim((string) $request->input('summary', '')), 0, 500),
            'content' => (string) $request->input('content', ''),
            'cover_image' => mb_substr(trim((string) $request->input('cover_image', '')), 0, 500),
            'category_id' => $categoryId,
            'status' => $status,
            'is_top' => $request->input('is_top', false) ? 1 : 0,
            'allow_comment' => $request->input('allow_comment', true) ? 1 : 0,
            'updated_at' => now(),
        ];
    }

    /**
     * @param array<int, mixed> $tagIds
     */
    private function syncTags(int $postId, array $tagIds): void
    {
        Database::delete('post_tags', 'post_id = :pid', ['pid' => $postId]);
        $seen = [];
        foreach ($tagIds as $tagId) {
            $tagId = (int) $tagId;
            if ($tagId <= 0 || isset($seen[$tagId])) {
                continue;
            }
            $seen[$tagId] = true;
            $exists = Database::selectOne('SELECT id FROM tags WHERE id = :id', ['id' => $tagId]);
            if ($exists !== null) {
                Database::insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function postTags(int $postId): array
    {
        return Database::select(
            'SELECT t.id, t.name, t.slug FROM tags t
             JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = :pid',
            ['pid' => $postId]
        );
    }
}
