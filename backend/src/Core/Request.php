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

namespace App\Core;

/**
 * HTTP 请求封装
 */
final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array $query;
    public readonly array $body;
    public readonly array $headers;
    public readonly array $routeParams;

    /**
     * 由路由器设置路由参数（readonly 仅允许本类作用域初始化）
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // 零配置部署：前端通过 /api.php/xxx 访问（无需 Nginx 重写规则）
        // 这里统一归一化为 /api/xxx，与 /api/xxx 两种形式共用同一套路由
        if ($uri === '/api.php' || str_starts_with($uri, '/api.php/')) {
            $uri = '/api' . substr($uri, strlen('/api.php'));
        }

        $this->path = rtrim($uri, '/') ?: '/';
        $this->query = $_GET;
        $this->headers = $this->collectHeaders();

        $contentType = $this->header('Content-Type') ?? '';
        $raw = file_get_contents('php://input') ?: '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            $this->body = is_array($decoded) ? $decoded : [];
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            $this->body = array_merge($_POST, $_FILES ? ['__files' => $_FILES] : []);
        } else {
            $this->body = array_merge($_POST, ['__raw' => $raw]);
        }
    }

    private function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    public function header(?string $name = null): mixed
    {
        if ($name === null) {
            return $this->headers;
        }
        return $this->headers[$name] ?? null;
    }

    /**
     * 获取 Bearer Token
     */
    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');
        if (is_string($authorization) && preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * 从路由参数或请求体中取值
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * 取文件
     */
    public function file(string $key): ?array
    {
        return $this->body['__files'][$key] ?? null;
    }

    public function all(): array
    {
        return $this->body;
    }
}
