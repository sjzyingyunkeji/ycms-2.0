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
 * API 入口（真实文件）
 *
 * 访问形式：/api.php/posts、/api.php/auth/login …
 * 因为路径中存在真实 PHP 文件，Nginx/Apache 默认配置即可命中，
 * 无需任何 try_files / rewrite 规则。
 */

require dirname(__DIR__) . '/backend/public/index.php';
