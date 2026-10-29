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
 * 补丁：为既有数据库补充「访问统计 / 首页排序」设置项（幂等，不影响其他数据）
 */

require_once dirname(__DIR__) . '/src/Support/helpers.php';
require_once dirname(__DIR__) . '/src/Support/env.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';

use App\Core\Database;

Database::execute("INSERT OR IGNORE INTO settings (key, value, group_name) VALUES ('home_post_sort', 'hot', 'site')");
Database::execute("INSERT OR IGNORE INTO settings (key, value, group_name) VALUES ('track_enabled', '1', 'site')");

echo "统计与排序设置项已就绪\n";
