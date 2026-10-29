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
 * 媒体库控制器
 */
final class MediaController
{
    /**
     * 列表
     */
    public function index(Request $request): void
    {
        Auth::requireAdmin($request);
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $size = min(100, max(1, (int) ($request->query['size'] ?? 24)));

        $total = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM media')['c'] ?? 0);
        $items = Database::select(
            'SELECT * FROM media ORDER BY id DESC LIMIT :limit OFFSET :offset',
            ['limit' => $size, 'offset' => ($page - 1) * $size]
        );
        Response::paginate($items, $total, $page, $size);
    }

    /**
     * 上传
     */
    public function store(Request $request): void
    {
        Auth::requireAdmin($request);
        $file = $request->file('file');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('请选择要上传的文件', 422);
        }

        $maxSize = (int) config('upload.max_size', 10485760);
        if ($file['size'] > $maxSize) {
            Response::error('文件大小超过限制（最大 10MB）', 422);
        }

        // 扩展名白名单（与 MIME 双重校验，杜绝可执行文件/伪造扩展名）
        // 注意：SVG 可内嵌脚本，同源直出会形成 XSS，默认不接收
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'md'];
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            Response::error('不支持的文件扩展名，仅允许：' . implode(' / ', $allowedExtensions), 422);
        }

        $allowed = (array) config('upload.allowed_mimes', []);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            Response::error('不支持的文件类型: ' . $mime, 422);
        }

        // 图片内容真实性校验（防止把脚本等非图片伪造成 .png 上传）
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extension, $imageExtensions, true) && @getimagesize($file['tmp_name']) === false) {
            Response::error('文件内容不是有效的图片', 422);
        }

        // 零配置部署：媒体文件放在站点根目录下，由 Web 服务器直接托管（无需路由规则）
        $uploadDir = self::publicStorageDir() . '/uploads/' . date('Ym');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        self::protectUploadDir();

        $filename = date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            Response::error('文件保存失败', 500);
        }

        $id = Database::insert('media', [
            'filename' => $filename,
            'original_name' => mb_substr((string) $file['name'], 0, 200),
            'mime' => $mime,
            'size' => (int) $file['size'],
            'path' => '/storage/uploads/' . date('Ym') . '/' . $filename,
        ]);

        Response::success(Database::selectOne('SELECT * FROM media WHERE id = :id', ['id' => $id]), '上传成功', 201);
    }

    /**
     * 站点根目录下的媒体目录（public/storage）
     */
    private static function publicStorageDir(): string
    {
        return dirname(__DIR__, 3) . '/public/storage';
    }

    /**
     * 上传目录防护：Apache 下禁止执行脚本与目录浏览
     * （Nginx 不读取 .htaccess，需在站点配置中对上传目录禁用 PHP）
     */
    private static function protectUploadDir(): void
    {
        $file = self::publicStorageDir() . '/.htaccess';
        if (is_file($file)) {
            return;
        }
        $rules = "Options -Indexes\n"
            . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
            . "RemoveHandler .php .phtml .phar .php3 .php4 .php5 .php7 .php8 .cgi .pl .py .sh\n"
            . "RemoveType .php .phtml .phar\n";
        @file_put_contents($file, $rules);
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $id): void
    {
        Auth::requireAdmin($request);
        $media = Database::selectOne('SELECT * FROM media WHERE id = :id', ['id' => $id]);
        if ($media === null) {
            Response::error('文件不存在', 404);
        }
        $filePath = self::publicStorageDir() . preg_replace('#^/storage#', '', (string) $media['path']);
        if (is_file($filePath)) {
            @unlink($filePath);
        }
        Database::delete('media', 'id = :id', ['id' => $id]);
        Response::success(null, '文件已删除');
    }
}
