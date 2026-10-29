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
 * 种子数据：管理员账号 + 示例内容
 * 默认账号: admin / admin123
 */

require_once dirname(__DIR__) . '/src/Support/helpers.php';
require_once dirname(__DIR__) . '/src/Support/env.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';

use App\Core\Database;

date_default_timezone_set('Asia/Shanghai');

function seedDatabase(): void
{
    Database::beginTransaction();
    try {
        // 清理旧数据
        foreach (['comments', 'post_tags', 'posts', 'tags', 'pages', 'media', 'links', 'categories', 'api_tokens', 'users', 'settings'] as $table) {
            Database::execute("DELETE FROM {$table}");
        }
        Database::execute("DELETE FROM sqlite_sequence WHERE name IN ('comments','posts','tags','pages','media','links','categories','users')");

        // 管理员
        Database::insert('users', [
            'username' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            'email' => 'admin@stardust.dev',
            'nickname' => '星尘管理员',
            'bio' => '这个人很懒，什么都没有留下。',
            'role' => 'admin',
        ]);
        $userId = (int) Database::pdo()->lastInsertId();

        // 分类
        $categories = [
            ['name' => '技术随笔', 'slug' => 'tech', 'description' => '记录技术探索与思考', 'sort_order' => 1],
            ['name' => '生活感悟', 'slug' => 'life', 'description' => '生活中的点滴星光', 'sort_order' => 2],
            ['name' => '星空摄影', 'slug' => 'photography', 'description' => '仰望星空的影像记录', 'sort_order' => 3],
        ];
        foreach ($categories as $cat) {
            Database::insert('categories', $cat);
        }

        // 标签
        $tags = ['PHP', 'Vue', 'SQLite', '星空', '随笔', '前端', '后端'];
        foreach ($tags as $tag) {
            Database::insert('tags', ['name' => $tag, 'slug' => slugify($tag)]);
        }

        // 文章
        $posts = [
            [
                'title' => '欢迎来到 ycms2.0',
                'slug' => 'welcome-to-ycms2',
                'summary' => '一个用 PHP 8.2+ 与 Vue 3 打造的星空风格内容管理系统，本文带你快速了解它的全部功能。',
                'content' => "## 关于 ycms2.0\n\n**ycms2.0** 是一个轻量而完整的个人内容管理系统，采用以下技术栈构建：\n\n- 后端：PHP 8.2+（严格类型、枚举、只读属性等现代特性）\n- 前端：Vue 3 + Vite + Tailwind CSS\n- 数据库：SQLite（零配置，WAL 模式）\n- 风格：星空星光主题 + 玻璃拟态设计\n\n### 主要功能\n\n1. 文章管理（Markdown 编辑器、草稿/发布、置顶）\n2. 分类与标签管理\n3. 独立页面管理\n4. 媒体库（图片上传）\n5. 评论审核\n6. 友链管理\n7. 站点设置\n8. 仪表盘统计\n\n### 快速上手\n\n登录后台后，你可以在「文章」中创建你的第一篇内容。所有内容都支持 Markdown 语法。\n\n> 星空之所以美丽，是因为每一颗星星都在自己的位置上闪耀。\n\n```php\n// PHP 8.2+ 示例\nenum PostStatus: string {\n    case Draft = 'draft';\n    case Published = 'published';\n}\n```\n\n祝你使用愉快！",
                'cover_image' => '',
                'category_id' => 1,
                'status' => 'published',
                'is_top' => 1,
                'published_at' => now(),
            ],
            [
                'title' => 'PHP 8.2 的新特性速览',
                'slug' => 'php-82-features',
                'summary' => '只读类、独立类型、 trait 常量、敏感参数隐藏…… PHP 8.2 带来了哪些值得关注的改进？',
                'content' => <<<'EOT'
## PHP 8.2 值得关注的新特性

### 1. 只读类（Readonly Classes）

PHP 8.1 引入了只读属性，8.2 更进一步，可以将整个类标记为只读：

```php
readonly class Point {
    public function __construct(
        public float $x,
        public float $y,
    ) {}
}
```

### 2. 独立类型（Standalone Types）

新增 `null`、`true`、`false` 作为独立类型声明：

```php
function process(null|string $value): true {
    return true;
}
```

### 3. 敏感参数隐藏

使用 `#[\SensitiveParameter]` 属性，在堆栈追踪中隐藏敏感参数值。

### 4. 随机数扩展

新增 `Randomizer` 类，提供更现代的随机数 API：

```php
$randomizer = new Random\Randomizer();
$randomizer->getInt(1, 100);
```

> 这些特性让 PHP 在类型安全与现代工程化上又前进了一大步。
EOT,
                'cover_image' => '',
                'category_id' => 1,
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => '用 Vue 3 组合式 API 组织前端代码',
                'slug' => 'vue3-composition-api',
                'summary' => 'setup、ref、computed、watch……组合式 API 如何让大型前端项目的逻辑复用变得优雅？',
                'content' => "## 组合式 API 的魅力\n\nVue 3 的组合式 API 让我们可以按功能逻辑组织代码，而不是按选项类型拆分。\n\n```vue\n<script setup>\nimport { ref, computed } from 'vue'\n\nconst count = ref(0)\nconst double = computed(() => count.value * 2)\n</script>\n```\n\n### 与 React Hooks 的区别\n\n- 组合式函数的 setup 只执行一次，不存在闭包陷阱\n- 没有 Hook 的顺序调用规则\n- TypeScript 类型推断更友好\n\n### 实战建议\n\n1. 将可复用逻辑抽取为 `useXxx` 组合式函数\n2. 使用 Pinia 管理跨组件状态\n3. 合理拆分组件，保持单一职责\n\n星空浩瀚，代码亦当如此：结构清晰，各安其位。",
                'cover_image' => '',
                'category_id' => 1,
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => '为什么我选择 SQLite',
                'slug' => 'why-i-choose-sqlite',
                'summary' => '零配置、单文件、WAL 模式——对于中小型站点，SQLite 是被低估的王者。',
                'content' => <<<'EOT'
## SQLite 的哲学

SQLite 是世界上部署最广泛的数据库引擎。它的设计哲学是**简单与可靠**。

### 优势

- **零配置**：无需安装数据库服务器
- **单文件**：整个数据库就是一个文件，备份就是复制文件
- **WAL 模式**：读写不互斥，并发性能优秀
- **事务支持**：完整的 ACID 保证

### PHP 中的使用

```php
$pdo = new PDO('sqlite:' . $path, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

### 适合场景

个人博客、CMS、小型站点、嵌入式应用——当你的并发写入量不超过每秒数百次时，SQLite 都是绝佳选择。

> 简单是可靠的灵魂。
EOT,
                'cover_image' => '',
                'category_id' => 1,
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => '星空摄影入门：如何拍好银河',
                'slug' => '',
                'summary' => '（草稿）从器材选择到参数设置，记录我的第一次银河拍摄之旅。',
                'content' => "（草稿）整理中……",
                'cover_image' => '',
                'category_id' => 3,
                'status' => 'draft',
                'is_top' => 0,
                'published_at' => null,
            ],
        ];
        foreach ($posts as $post) {
            $post['author_id'] = $userId;
            Database::insert('posts', $post);
        }

        // 页面
        Database::insert('pages', [
            'title' => '关于我',
            'slug' => 'about',
            'content' => "## 你好，我是星尘\n\n一名热爱技术与星空的开发者。白天写代码，夜晚拍星星。\n\n### 技能栈\n\n- PHP / Vue / TypeScript\n- SQLite / MySQL / Redis\n- Linux / Nginx / Docker\n\n### 联系方式\n\n- Email: hello@stardust.dev\n- GitHub: github.com/stardust\n\n> 愿你的代码如星空般璀璨，愿你的 bug 如流星般稀少。",
            'status' => 'published',
        ]);
        Database::insert('pages', [
            'title' => '留言板',
            'slug' => 'guestbook',
            'content' => "## 留言板\n\n有什么想对我说的？在文章下方评论区，或者通过右上角联系方式找到我。\n\n每一个留言，都是夜空中一闪而过的流星，值得被珍藏。",
            'status' => 'published',
        ]);

        // 友链
        $links = [
            ['name' => 'Vue 官网', 'url' => 'https://vuejs.org', 'description' => '渐进式 JavaScript 框架', 'sort_order' => 1],
            ['name' => 'PHP 官网', 'url' => 'https://www.php.net', 'description' => 'Web 开发的常青树', 'sort_order' => 2],
            ['name' => 'SQLite 官网', 'url' => 'https://www.sqlite.org', 'description' => '小而强大的数据库', 'sort_order' => 3],
        ];
        foreach ($links as $link) {
            Database::insert('links', $link);
        }

        // 站点设置
        $settings = [
            ['key' => 'site_name', 'value' => 'ycms2.0', 'group_name' => 'site'],
            ['key' => 'site_title', 'value' => 'ycms2.0 · 在代码与星空之间', 'group_name' => 'site'],
            ['key' => 'logo_type', 'value' => 'image', 'group_name' => 'site'],
            ['key' => 'logo_image', 'value' => '/logo.svg', 'group_name' => 'site'],
            ['key' => 'hero_suffix', 'value' => '的数字星空', 'group_name' => 'site'],
            ['key' => 'site_description', 'value' => '一个记录技术、生活与星空的个人站点，基于 ycms2.0 构建。', 'group_name' => 'site'],
            ['key' => 'site_keywords', 'value' => 'ycms,技术博客,星空,PHP,Vue', 'group_name' => 'site'],
            ['key' => 'site_icp', 'value' => '', 'group_name' => 'site'],
            ['key' => 'owner_name', 'value' => '星尘', 'group_name' => 'site'],
            ['key' => 'owner_bio', 'value' => '热爱技术与星空的开发者，白天写代码，夜晚拍星星。', 'group_name' => 'site'],
            ['key' => 'owner_avatar', 'value' => '', 'group_name' => 'site'],
            ['key' => 'social_github', 'value' => 'https://github.com', 'group_name' => 'social'],
            ['key' => 'social_email', 'value' => 'hello@stardust.dev', 'group_name' => 'social'],
            ['key' => 'social_weibo', 'value' => '', 'group_name' => 'social'],
            ['key' => 'social_zhihu', 'value' => '', 'group_name' => 'social'],
            ['key' => 'social_csdn', 'value' => '', 'group_name' => 'social'],
            ['key' => 'social_wechat', 'value' => '', 'group_name' => 'social'],
            ['key' => 'social_wechat_qr', 'value' => '', 'group_name' => 'social'],
            ['key' => 'nav_links', 'value' => '[]', 'group_name' => 'site'],
            ['key' => 'site_closed', 'value' => '0', 'group_name' => 'site'],
            ['key' => 'site_closed_message', 'value' => '站点正在维护升级中，暂时关闭访问，请稍后再来。', 'group_name' => 'site'],
            ['key' => 'comment_audit', 'value' => '1', 'group_name' => 'comment'],
            ['key' => 'posts_per_page', 'value' => '10', 'group_name' => 'site'],
        ];
        foreach ($settings as $setting) {
            Database::insert('settings', $setting);
        }

        Database::commit();
        echo "种子数据导入完成\n";
    } catch (\Throwable $e) {
        Database::rollBack();
        fwrite(STDERR, "种子数据导入失败: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === realpath(__FILE__)) {
    seedDatabase();
}
