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

use Closure;

/**
 * 轻量级路由器，支持 GET/POST/PUT/DELETE/PATCH 与 {param} 占位符
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:Closure, params:array<int,string>}> */
    private array $routes = [];

    /** @var array<int, Closure> */
    private array $globalMiddleware = [];

    public function add(string $method, string $path, Closure|array $handler): void
    {
        $params = [];
        $pattern = preg_replace_callback('/\{(\w+)\}/', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path) ?? $path;

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => '#^' . $pattern . '$#',
            'handler' => is_array($handler)
                ? static fn (Request $request, ...$args) => self::invokeHandler($handler, $request, $args)
                : $handler,
            'params' => $params,
        ];
    }

    public function get(string $path, Closure|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, Closure|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, Closure|array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, Closure|array $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, Closure|array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    /**
     * 数组处理器: [ControllerClass, 'method']
     * @param array{0: class-string, 1: string} $handler
     */
    private static function invokeHandler(array $handler, Request $request, array $args): mixed
    {
        [$class, $method] = $handler;
        $instance = new $class();
        return $instance->{$method}($request, ...$args);
    }

    public function middleware(Closure $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * 分发请求
     */
    public function dispatch(Request $request): void
    {
        $path = $request->path;
        $method = $request->method;

        // OPTIONS 预检
        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            exit;
        }

        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $pathMatched = true;
                if ($route['method'] !== $method) {
                    continue;
                }
                array_shift($matches);
                $request->setRouteParams(array_combine($route['params'], $matches) ?: []);

                foreach ($this->globalMiddleware as $middleware) {
                    $middleware($request);
                }

                ($route['handler'])($request, ...array_values($request->routeParams));
                return;
            }
        }

        if ($pathMatched) {
            Response::error('请求方法不被允许', 405);
        }
        Response::error('接口不存在: ' . $method . ' ' . $path, 404);
    }
}
