<?php

/*
 * YCMS 2.0 | 基于 PHP + Node.js 的轻量内容管理系统
 * Copyright © 2026 影云网络科技（石家庄）有限公司
 * 开源协议：AGPLv3 License
 * GitHub：https://github.com/sjzyingyunkeji/ycms-2.0
 * 本程序遵循 AGPLv3 协议：公网提供服务、衍生分发时，必须同步开放对应源码；
 * 如有商用授权、定制合作需求，欢迎联系：info@sjzyingyun.cn
 */


/**
 * 通用辅助函数
 */

if (!function_exists('config')) {
    /**
     * 读取配置，支持点号路径
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__) . '/../config/config.php';
        }
        if ($key === null) {
            return $config;
        }
        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('json_response')) {
    /**
     * 输出 JSON 响应并结束
     */
    function json_response(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('slugify')) {
    /**
     * 生成 URL 友好的 slug
     */
    function slugify(string $text): string
    {
        // 尝试拼音化不可简场景保留中文：直接保留中文与字母数字
        $text = trim($text);
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text) ?? $text;
        $text = preg_replace('/-+/', '-', $text) ?? $text;
        return strtolower(trim($text, '-')) ?: 'untitled';
    }
}

if (!function_exists('safe_slug')) {
    /**
     * 生成安全的 URL 别名
     *
     * 静态页会以 slug 作为目录名（public/post/<slug>/index.html），
     * 因此必须剔除 / \ . 等可造成路径穿越的字符。
     */
    function safe_slug(string $value, string $fallback = 'untitled'): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/[\s\x{3000}]+/u', '-', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\-_]/u', '', $value) ?? $value;
        $value = preg_replace('/-{2,}/', '-', $value) ?? $value;
        $value = trim($value, '-_.');
        $value = mb_substr($value, 0, 120);

        return $value === '' ? $fallback : $value;
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('random_token')) {
    function random_token(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
