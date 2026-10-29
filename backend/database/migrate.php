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

/**
 * 数据库迁移：执行 schema.sql
 */

require_once dirname(__DIR__) . '/src/Support/helpers.php';
require_once dirname(__DIR__) . '/src/Support/env.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';

use App\Core\Database;

function migrateDatabase(): void
{
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($schema === false) {
        fwrite(STDERR, "无法读取 schema.sql\n");
        exit(1);
    }
    Database::pdo()->exec($schema);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === realpath(__FILE__)) {
    migrateDatabase();
    echo "数据库迁移完成\n";
}
