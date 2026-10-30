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

use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;
use App\Support\UpdateService;
use ZipArchive;

/**
 * 系统更新控制器（仅管理员可用）
 *
 * 路由：
 *   GET  /api/system/update            状态 + 缓存清单 + 备份列表
 *   POST /api/system/update/check      检查更新（force=1 忽略 24h 缓存）
 *   POST /api/system/update/run        执行单个升级步骤
 *   GET  /api/system/update/backups    备份列表
 *   POST /api/system/update/rollback   回滚到指定备份
 *   POST /api/system/update/upload     上传离线升级包
 */
final class UpdateController
{
    /** 升级步骤顺序（前端据此渲染进度） */
    private const STEPS = ['download', 'extract', 'backup', 'apply', 'migrate', 'finalize'];

    /**
     * 更新状态（不发起网络请求，避免后台首屏卡顿）
     */
    public function status(Request $request): void
    {
        Auth::requireAdmin($request);

        $root = UpdateService::rootDir();
        $state = UpdateService::state();

        Response::success(array_merge(UpdateService::cached(), [
            'php' => PHP_VERSION,
            'zip' => class_exists(ZipArchive::class),
            'sodium' => function_exists('sodium_crypto_sign_verify_detached'),
            'storage_writable' => is_writable($root . '/backend/storage'),
            'root_writable' => is_writable($root),
            'manifest_url' => UpdateService::manifestUrl(),
            'installed_at' => (string) ($state['installed_at'] ?? ''),
            'installed_version' => (string) ($state['installed_version'] ?? ''),
            'backups' => UpdateService::backups(),
            'max_upload' => $this->maxUploadSize(),
            'steps' => self::STEPS,
        ]));
    }

    /**
     * 检查更新
     */
    public function check(Request $request): void
    {
        Auth::requireAdmin($request);
        @set_time_limit(180);

        $force = (bool) $request->input('force', $request->query['force'] ?? false);
        $summary = UpdateService::check($force);

        $message = $summary['has_update']
            ? '发现新版本 ' . $summary['latest']
            : ($summary['last_result'] === 'fail' ? (string) $summary['last_error'] : '当前已是最新版本');

        Response::success($summary, $message);
    }

    /**
     * 执行升级步骤
     */
    public function run(Request $request): void
    {
        Auth::requireAdmin($request);
        @set_time_limit(600);
        @ignore_user_abort(true);

        $step = (string) $request->input('step', '');
        if (!in_array($step, self::STEPS, true)) {
            Response::error('未知的升级步骤：' . $step, 422);
        }

        $result = UpdateService::runStep($step);
        if (($result['ok'] ?? false) !== true) {
            Response::error((string) ($result['message'] ?? '升级失败'), 500, $result);
        }

        Response::success($result, (string) $result['message']);
    }

    /**
     * 备份列表
     */
    public function backups(Request $request): void
    {
        Auth::requireAdmin($request);
        Response::success(UpdateService::backups());
    }

    /**
     * 回滚
     */
    public function rollback(Request $request): void
    {
        Auth::requireAdmin($request);
        @set_time_limit(600);
        @ignore_user_abort(true);

        $name = (string) $request->input('name', '');
        if ($name === '') {
            Response::error('请指定要回滚的备份文件', 422);
        }

        $result = UpdateService::rollback($name, (bool) $request->input('with_db', false));
        if (($result['ok'] ?? false) !== true) {
            Response::error((string) ($result['message'] ?? '回滚失败'), 500, $result);
        }

        Response::success($result, (string) $result['message']);
    }

    /**
     * 上传离线升级包（服务器无法访问 GitHub 时使用）
     */
    public function upload(Request $request): void
    {
        Auth::requireAdmin($request);
        @set_time_limit(600);
        @ignore_user_abort(true);

        $file = $request->file('package');
        if (!is_array($file)) {
            Response::error('请选择升级包文件（.zip）', 422);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            Response::error('上传失败：' . $this->uploadError($error), 422);
        }

        $size = (int) ($file['size'] ?? 0);
        $max = $this->maxUploadSize();
        if ($size <= 0) {
            Response::error('上传文件为空', 422);
        }
        if ($size > $max) {
            Response::error('升级包超出服务器限制（最大 ' . $this->humanSize($max) . '）', 422);
        }

        $name = basename((string) ($file['name'] ?? 'package.zip'));
        if (!str_ends_with(strtolower($name), '.zip')) {
            Response::error('仅支持 zip 格式的离线包', 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            Response::error('无效的上传文件', 422);
        }

        $result = UpdateService::importPackage($tmp, $name);
        if (($result['ok'] ?? false) !== true) {
            Response::error((string) ($result['message'] ?? '离线包导入失败'), 500, $result);
        }

        Response::success($result, (string) $result['message']);
    }

    /* ==================== 工具 ==================== */

    /**
     * 服务器允许的最大上传体积（取 upload_max_filesize 与 post_max_size 较小值）
     */
    private function maxUploadSize(): int
    {
        $upload = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $post = $this->iniBytes((string) ini_get('post_max_size'));
        $candidates = array_values(array_filter([$upload, $post], static fn (int $v): bool => $v > 0));
        if ($candidates === []) {
            return 8 * 1024 * 1024;
        }
        return min($candidates);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件超过服务器上传限制，请调大 upload_max_filesize / post_max_size',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分，请重试',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '服务器无法写入临时文件',
            UPLOAD_ERR_EXTENSION => '上传被 PHP 扩展中断',
            default => '未知错误（' . $code . '）',
        };
    }
}
