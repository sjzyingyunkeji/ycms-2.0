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
 * 仪表盘统计
 */
final class DashboardController
{
    public function stats(Request $request): void
    {
        Auth::requireUser($request);

        $count = static fn (string $table, string $where = '1=1', array $params = []): int => (int) (Database::selectOne("SELECT COUNT(*) AS c FROM {$table} WHERE {$where}", $params)['c'] ?? 0);

        $posts = $count('posts');
        $published = $count('posts', "status = 'published'");
        $drafts = $count('posts', "status = 'draft'");
        $views = (int) (Database::selectOne('SELECT COALESCE(SUM(views), 0) AS v FROM posts')['v'] ?? 0);
        $comments = $count('comments');
        $pendingComments = $count('comments', "status = 'pending'");
        $categories = $count('categories');
        $tags = $count('tags');
        $pages = $count('pages');
        $media = $count('media');

        $recentPosts = Database::select(
            'SELECT p.id, p.title, p.status, p.views, p.created_at, c.name AS category_name
             FROM posts p LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.created_at DESC LIMIT 5'
        );

        $recentComments = Database::select(
            'SELECT id, post_id, nickname, content, status, created_at FROM comments ORDER BY created_at DESC LIMIT 5'
        );

        // 近 30 天发布趋势
        $trend = Database::select(
            "SELECT date(created_at) AS date, COUNT(*) AS count
             FROM posts
             WHERE created_at >= date('now', '-30 days', 'localtime')
             GROUP BY date(created_at)
             ORDER BY date ASC"
        );

        Response::success([
            'cards' => [
                ['label' => '文章总数', 'value' => $posts, 'icon' => 'file-text', 'hint' => "已发布 {$published} · 草稿 {$drafts}"],
                ['label' => '总浏览量', 'value' => $views, 'icon' => 'eye', 'hint' => '所有文章阅读量'],
                ['label' => '评论数', 'value' => $comments, 'icon' => 'message-square', 'hint' => "待审核 {$pendingComments}"],
                ['label' => '媒体文件', 'value' => $media, 'icon' => 'image', 'hint' => '媒体库资源'],
            ],
            'extras' => [
                ['label' => '分类', 'value' => $categories],
                ['label' => '标签', 'value' => $tags],
                ['label' => '页面', 'value' => $pages],
            ],
            'recent_posts' => $recentPosts,
            'recent_comments' => $recentComments,
            'publish_trend' => $trend,
        ]);
    }
}
