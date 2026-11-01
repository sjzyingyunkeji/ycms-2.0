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
 * 设置控制器（读写 settings 表）
 */
final class SettingController
{
    /**
     * 公开设置（前台使用）
     */
    public function index(Request $request): void
    {
        $settings = $this->allSettings();
        // 博主资料存放在用户表，输出时派生到 owner_* / social_* 以保持前台兼容
        $owner = $this->ownerProfile();
        if (Auth::user($request) === null) {
            // 公开接口过滤敏感项
            $public = [
                'site_name' => $settings['site_name'] ?? '',
                'site_title' => $settings['site_title'] ?? '',
                'site_description' => $settings['site_description'] ?? '',
                'site_keywords' => $settings['site_keywords'] ?? '',
                'site_icp' => $settings['site_icp'] ?? '',
                'logo_type' => $settings['logo_type'] ?? 'text',
                'logo_image' => $settings['logo_image'] ?? '',
                'hero_suffix' => $settings['hero_suffix'] ?? '',
                'nav_links' => $this->decodeNavLinks($settings['nav_links'] ?? ''),
                'site_closed' => $settings['site_closed'] ?? '0',
                'site_closed_message' => $settings['site_closed_message'] ?? '',
                'comment_audit' => $settings['comment_audit'] ?? '1',
                'posts_per_page' => $settings['posts_per_page'] ?? '10',
                'home_post_sort' => $settings['home_post_sort'] ?? 'hot',
                'track_enabled' => $settings['track_enabled'] ?? '1',
            ];
            Response::success(array_merge($public, $owner));
        }
        $settings['nav_links'] = $this->decodeNavLinks($settings['nav_links'] ?? '');
        Response::success(array_merge($settings, $owner));
    }

    /**
     * 更新设置（管理员）
     */
    public function update(Request $request): void
    {
        Auth::requireAdmin($request);
        $input = $request->input('settings', []);
        if (!is_array($input) || $input === []) {
            Response::error('没有需要更新的设置', 422);
        }

        // 注意：博主资料（昵称/头像/简介/社交账号）不在此处维护，统一走 /api/auth/profile
        $allowedKeys = [
            'site_name', 'site_title', 'site_description', 'site_keywords', 'site_icp',
            'logo_type', 'logo_image', 'hero_suffix',
            'nav_links', 'site_closed', 'site_closed_message', 'comment_audit', 'posts_per_page',
            'home_post_sort', 'track_enabled', 'antibot_enabled', 'antibot_limit', 'antibot_ban_minutes',
        ];

        Database::beginTransaction();
        try {
            foreach ($input as $key => $value) {
                $key = (string) $key;
                if (!in_array($key, $allowedKeys, true)) {
                    continue;
                }
                if (!is_scalar($value)) {
                    continue;
                }

                // 导航友站链接：JSON 数组校验与清洗
                if ($key === 'nav_links') {
                    $value = json_encode($this->decodeNavLinks((string) $value), JSON_UNESCAPED_UNICODE);
                }

                // Logo 类型：仅允许 text / image 二选一
                if ($key === 'logo_type' && !in_array((string) $value, ['text', 'image'], true)) {
                    $value = 'text';
                }

                // 关站开关：仅允许 0 / 1
                if ($key === 'site_closed' && !in_array((string) $value, ['0', '1'], true)) {
                    $value = '0';
                }

                // 首页文章排序：hot 热门优先 / latest 最新优先
                if ($key === 'home_post_sort' && !in_array((string) $value, ['hot', 'latest'], true)) {
                    $value = 'hot';
                }

                // 访问统计开关：仅允许 0 / 1
                if ($key === 'track_enabled' && !in_array((string) $value, ['0', '1'], true)) {
                    $value = '1';
                }

                // 爬虫防护：开关 / 每分钟阈值 / 封禁分钟数
                if ($key === 'antibot_enabled' && !in_array((string) $value, ['0', '1'], true)) {
                    $value = '1';
                }
                if ($key === 'antibot_limit') {
                    $value = (string) max(10, min(600, (int) $value));
                }
                if ($key === 'antibot_ban_minutes') {
                    $value = (string) max(1, min(1440, (int) $value));
                }

                $exists = Database::selectOne('SELECT key FROM settings WHERE key = :key', ['key' => $key]);
                if ($exists !== null) {
                    Database::update('settings', ['value' => mb_substr((string) $value, 0, 2000), 'updated_at' => now()], 'key = :key', ['key' => $key]);
                } else {
                    Database::insert('settings', [
                        'key' => $key,
                        'value' => mb_substr((string) $value, 0, 2000),
                        'group_name' => 'site',
                    ]);
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('设置保存失败: ' . $e->getMessage(), 500);
        }

        $result = $this->allSettings();
        $result['nav_links'] = $this->decodeNavLinks($result['nav_links'] ?? '');
        Response::success($result, '设置已保存');
    }

    /**
     * 爬虫防护状态（当前封禁列表 + 生效阈值）
     */
    public function antibotStatus(Request $request): void
    {
        Auth::requireAdmin($request);
        $settings = $this->allSettings();

        Response::success([
            'enabled' => ($settings['antibot_enabled'] ?? '1') !== '0',
            'limit' => (int) ($settings['antibot_limit'] ?? 60),
            'ban_minutes' => (int) ($settings['antibot_ban_minutes'] ?? 10),
            'banned' => \App\Support\AntiBot::bannedList(),
        ]);
    }

    /**
     * 清空全部封禁记录
     */
    public function clearAntibot(Request $request): void
    {
        Auth::requireAdmin($request);
        $count = \App\Support\AntiBot::clearAll();
        Response::success(['cleared' => $count], '已解除全部封禁');
    }

    /**
     * @return array<string, string>
     */
    private function allSettings(): array
    {
        $rows = Database::select('SELECT key, value FROM settings');
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['key']] = (string) $row['value'];
        }
        return $map;
    }

    /**
     * 解析导航友站链接 JSON，清洗为 [{name, url}] 结构
     * @return array<int, array{name: string, url: string}>
     */
    private function decodeNavLinks(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $links = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim(mb_substr((string) ($item['name'] ?? ''), 0, 50));
            $url = trim(mb_substr((string) ($item['url'] ?? ''), 0, 300));
            if ($name === '' || $url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $links[] = ['name' => $name, 'url' => $url];
            if (count($links) >= 10) {
                break;
            }
        }
        return $links;
    }

    /**
     * 博主资料（存放在用户表）：取最早创建的账号作为站点博主
     *
     * 输出仍使用 owner_* / social_* 键名，前台无需感知数据迁移。
     * @return array<string, string>
     */
    private function ownerProfile(): array
    {
        $user = Database::selectOne('SELECT * FROM users ORDER BY id ASC LIMIT 1') ?? [];

        $nickname = trim((string) ($user['nickname'] ?? ''));
        if ($nickname === '') {
            $nickname = (string) ($user['username'] ?? '');
        }
        $socialEmail = trim((string) ($user['social_email'] ?? ''));
        if ($socialEmail === '') {
            $socialEmail = trim((string) ($user['email'] ?? ''));
        }

        return [
            'owner_name' => $nickname,
            'owner_bio' => (string) ($user['bio'] ?? ''),
            'owner_avatar' => (string) ($user['avatar'] ?? ''),
            'social_github' => (string) ($user['social_github'] ?? ''),
            'social_email' => $socialEmail,
            'social_weibo' => (string) ($user['social_weibo'] ?? ''),
            'social_zhihu' => (string) ($user['social_zhihu'] ?? ''),
            'social_csdn' => (string) ($user['social_csdn'] ?? ''),
            'social_wechat' => (string) ($user['social_wechat'] ?? ''),
            'social_wechat_qr' => (string) ($user['social_wechat_qr'] ?? ''),
        ];
    }
}
