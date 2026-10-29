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
 * JSON 响应辅助
 */
final class Response
{
    /**
     * 成功响应
     */
    public static function success(mixed $data = null, string $message = '操作成功', int $status = 200): never
    {
        json_response([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * 失败响应
     */
    public static function error(string $message = '操作失败', int $status = 400, mixed $errors = null): never
    {
        json_response([
            'code' => $status,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * 分页响应
     * @param array<int, mixed> $items
     */
    public static function paginate(array $items, int $total, int $page, int $size): never
    {
        json_response([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'size' => $size,
                'pages' => (int) ceil($total / max($size, 1)),
            ],
        ]);
    }
}
