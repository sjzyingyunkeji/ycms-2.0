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
 * 可选模块清单（能力分层的唯一挂载点）
 *
 * 开源发行默认空数组，且 src/Pro 目录不会被打包；
 * Pro 发行由 scripts/build-release.mjs --edition=pro 写入商业模块类名。
 *
 * 约束：内核代码不得直接引用列表中的类，只能通过本配置 + register() 契约挂载。
 */
return [];
