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
        if (Auth::user($request) === null) {
            // 公开接口过滤敏感项
            $public = [
                'site_name' => $settings['site_name'] ?? '',
                'site_title' => $settings['site_title'] ?? '',
                'site_description' => $settings['site_description'] ?? '',
                'site_keywords' => $settings['site_keywords'] ?? '',
                'site_icp' => $settings['site_icp'] ?? '',
                'owner_name' => $settings['owner_name'] ?? '',
                'owner_bio' => $settings['owner_bio'] ?? '',
                'owner_avatar' => $settings['owner_avatar'] ?? '',
                'logo_type' => $settings['logo_type'] ?? 'text',
                'logo_image' => $settings['logo_image'] ?? '',
                'hero_suffix' => $settings['hero_suffix'] ?? '',
                'social_github' => $settings['social_github'] ?? '',
                'social_email' => $settings['social_email'] ?? '',
                'social_weibo' => $settings['social_weibo'] ?? '',
                'social_zhihu' => $settings['social_zhihu'] ?? '',
                'social_csdn' => $settings['social_csdn'] ?? '',
                'social_wechat' => $settings['social_wechat'] ?? '',
                'social_wechat_qr' => $settings['social_wechat_qr'] ?? '',
                'nav_links' => $this->decodeNavLinks($settings['nav_links'] ?? ''),
                'site_closed' => $settings['site_closed'] ?? '0',
                'site_closed_message' => $settings['site_closed_message'] ?? '',
                'comment_audit' => $settings['comment_audit'] ?? '1',
                'posts_per_page' => $settings['posts_per_page'] ?? '10',
                'home_post_sort' => $settings['home_post_sort'] ?? 'hot',
                'track_enabled' => $settings['track_enabled'] ?? '1',
            ];
            Response::success($public);
        }
        $settings['nav_links'] = $this->decodeNavLinks($settings['nav_links'] ?? '');
        Response::success($settings);
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

        $allowedKeys = [
            'site_name', 'site_title', 'site_description', 'site_keywords', 'site_icp',
            'owner_name', 'owner_bio', 'owner_avatar',
            'logo_type', 'logo_image', 'hero_suffix',
            'social_github', 'social_email', 'social_weibo', 'social_zhihu', 'social_csdn',
            'social_wechat', 'social_wechat_qr',
            'nav_links', 'site_closed', 'site_closed_message', 'comment_audit', 'posts_per_page',
            'home_post_sort', 'track_enabled',
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
}
