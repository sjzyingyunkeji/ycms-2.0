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
 * 评论控制器
 */
final class CommentController
{
    /**
     * 列表（公开接口仅返回已审核）
     */
    public function index(Request $request): void
    {
        $user = Auth::user($request);
        // 仅管理员可查看待审核/垃圾评论与邮箱，普通访客只看已审核
        $isAdmin = $user !== null && ($user['role'] ?? '') === 'admin';

        if ($isAdmin) {
            $page = max(1, (int) ($request->query['page'] ?? 1));
            $size = min(100, max(1, (int) ($request->query['size'] ?? 20)));
            $status = (string) ($request->query['status'] ?? '');
            $postId = (int) ($request->query['post_id'] ?? 0);

            $where = ['1 = 1'];
            $params = [];
            if ($status !== '') {
                $where[] = 'c.status = :status';
                $params['status'] = $status;
            }
            if ($postId > 0) {
                $where[] = 'c.post_id = :post_id';
                $params['post_id'] = $postId;
            }
            $whereSql = implode(' AND ', $where);

            $total = (int) (Database::selectOne("SELECT COUNT(*) AS c FROM comments c WHERE {$whereSql}", $params)['c'] ?? 0);
            $items = Database::select(
                "SELECT c.*, p.title AS post_title
                 FROM comments c LEFT JOIN posts p ON p.id = c.post_id
                 WHERE {$whereSql}
                 ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset",
                array_merge($params, ['limit' => $size, 'offset' => ($page - 1) * $size])
            );
            Response::paginate($items, $total, $page, $size);
        }

        // 公开：按文章返回已审核评论
        $postId = (int) ($request->query['post_id'] ?? 0);
        if ($postId <= 0) {
            Response::success([]);
        }
        $items = Database::select(
            "SELECT id, post_id, nickname, content, created_at
             FROM comments
             WHERE post_id = :post_id AND status = 'approved'
             ORDER BY created_at ASC",
            ['post_id' => $postId]
        );
        Response::success($items);
    }

    /**
     * 提交评论（公开）
     */
    public function store(Request $request): void
    {
        $postId = (int) $request->input('post_id', 0);
        $nickname = trim((string) $request->input('nickname', ''));
        $email = trim((string) $request->input('email', ''));
        $content = trim((string) $request->input('content', ''));

        if ($postId <= 0 || $content === '') {
            Response::error('评论内容不能为空', 422);
        }
        $post = Database::selectOne("SELECT id, allow_comment FROM posts WHERE id = :id AND status = 'published'", ['id' => $postId]);
        if ($post === null) {
            Response::error('文章不存在', 404);
        }
        if ((int) $post['allow_comment'] !== 1) {
            Response::error('该文章已关闭评论', 422);
        }
        if ($nickname === '') {
            $nickname = '匿名旅人';
        }
        if (mb_strlen($nickname) > 50) {
            Response::error('昵称过长', 422);
        }
        if (mb_strlen($content) > 1000) {
            Response::error('评论内容过长（最多 1000 字符）', 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 422);
        }

        // 频率限制：同一 IP 对同一文章 60 秒 1 条、24 小时最多 20 条
        $ip = $this->clientIp($request);
        if ($ip !== '') {
            $recent = Database::selectOne(
                'SELECT id FROM comments WHERE ip = :ip AND post_id = :pid AND created_at > :since LIMIT 1',
                ['ip' => $ip, 'pid' => $postId, 'since' => date('Y-m-d H:i:s', time() - 60)]
            );
            if ($recent !== null) {
                Response::error('评论提交太频繁，请稍后再试', 429);
            }

            $daily = (int) (Database::selectOne(
                'SELECT COUNT(*) AS c FROM comments WHERE ip = :ip AND created_at > :since',
                ['ip' => $ip, 'since' => date('Y-m-d H:i:s', time() - 86400)]
            )['c'] ?? 0);
            if ($daily >= 20) {
                Response::error('今日评论数量已达上限，请明天再来', 429);
            }
        }

        $audit = (string) (Database::selectOne("SELECT value FROM settings WHERE key = 'comment_audit'")['value'] ?? '1');
        $status = $audit === '0' ? 'approved' : 'pending';

        $id = Database::insert('comments', [
            'post_id' => $postId,
            'nickname' => $nickname,
            'email' => $email,
            'content' => $content,
            'status' => $status,
            'ip' => $ip,
        ]);

        Response::success(['id' => $id, 'status' => $status], $status === 'pending' ? '评论已提交，等待博主审核' : '评论发表成功', 201);
    }

    /**
     * 审核 / 更新状态
     */
    public function update(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $comment = Database::selectOne('SELECT * FROM comments WHERE id = :id', ['id' => $id]);
        if ($comment === null) {
            Response::error('评论不存在', 404);
        }
        $status = (string) $request->input('status', '');
        if (!in_array($status, ['pending', 'approved', 'spam'], true)) {
            Response::error('非法的评论状态', 422);
        }
        Database::update('comments', ['status' => $status], 'id = :id', ['id' => $id]);
        Response::success(Database::selectOne('SELECT * FROM comments WHERE id = :id', ['id' => $id]), '评论状态已更新');
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        Database::delete('comments', 'id = :id', ['id' => $id]);
        Response::success(null, '评论已删除');
    }

    /**
     * 客户端 IP（仅接受合法地址，防止伪造的 X-Forwarded-For 落库）
     */
    private function clientIp(Request $request): string
    {
        $forwarded = (string) ($request->header('X-Forwarded-For') ?? '');
        if ($forwarded !== '') {
            $ip = trim(explode(',', $forwarded)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return substr($ip, 0, 45);
            }
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($remote, FILTER_VALIDATE_IP) ? substr($remote, 0, 45) : '';
    }
}
