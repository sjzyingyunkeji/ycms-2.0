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

namespace App\Support;

/**
 * 轻量 Markdown 渲染器（服务端 SEO 输出使用）
 *
 * 支持：标题、段落、有序/无序列表、引用、代码块、行内代码、表格、
 *      图片、链接、粗体、斜体、删除线、分割线。
 * 安全：所有原始 HTML 均被转义，避免 XSS。
 */
final class Markdown
{
    /**
     * Markdown 转 HTML
     */
    public static function toHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $count = count($lines);

        $i = 0;
        while ($i < $count) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // 代码块
            if (str_starts_with($trimmed, '```')) {
                $lang = trim(substr($trimmed, 3));
                $code = [];
                $i++;
                while ($i < $count && !str_starts_with(trim($lines[$i]), '```')) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++; // 跳过结束标记
                $class = $lang !== '' ? ' class="language-' . self::escape($lang) . '"' : '';
                $html[] = '<pre><code' . $class . '>' . self::escape(implode("\n", $code)) . '</code></pre>';
                continue;
            }

            // 空行
            if ($trimmed === '') {
                $i++;
                continue;
            }

            // 分割线
            if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $trimmed)) {
                $html[] = '<hr />';
                $i++;
                continue;
            }

            // 标题
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
                $level = strlen($m[1]);
                $html[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                $i++;
                continue;
            }

            // 表格（GFM）
            if (str_contains($line, '|') && $i + 1 < $count && preg_match('/^\s*\|?[\s:\-\|]+\|[\s:\-\|]*$/', $lines[$i + 1])) {
                [$tableHtml, $i] = self::parseTable($lines, $i);
                $html[] = $tableHtml;
                continue;
            }

            // 引用
            if (str_starts_with($trimmed, '>')) {
                $quote = [];
                while ($i < $count && str_starts_with(trim($lines[$i]), '>')) {
                    $quote[] = preg_replace('/^\s*>\s?/', '', $lines[$i]) ?? '';
                    $i++;
                }
                $inner = self::toHtml(implode("\n", $quote));
                $html[] = '<blockquote>' . $inner . '</blockquote>';
                continue;
            }

            // 列表
            if (preg_match('/^\s*([-*+]|\d+\.)\s+/', $line)) {
                [$listHtml, $i] = self::parseList($lines, $i);
                $html[] = $listHtml;
                continue;
            }

            // 段落
            $paragraph = [];
            while ($i < $count) {
                $t = trim($lines[$i]);
                if ($t === '' || str_starts_with($t, '```') || str_starts_with($t, '#') || str_starts_with($t, '>')
                    || preg_match('/^\s*([-*+]|\d+\.)\s+/', $lines[$i])
                    || preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $t)
                ) {
                    break;
                }
                $paragraph[] = $lines[$i];
                $i++;
            }
            if ($paragraph !== []) {
                $html[] = '<p>' . self::inline(implode("\n", $paragraph)) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    /**
     * 行内格式处理
     */
    private static function inline(string $text): string
    {
        $text = self::escape($text);

        // 行内代码（优先处理，避免内部内容被其他规则改写）
        $codes = [];
        $text = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codes): string {
            $codes[] = $m[1];
            return "\x00CODE" . (count($codes) - 1) . "\x00";
        }, $text) ?? $text;

        // 图片（URL 协议白名单，避免 javascript:/data: 注入）
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', static function (array $m): string {
            return '<img src="' . self::url($m[2]) . '" alt="' . $m[1] . '" loading="lazy" />';
        }, $text) ?? $text;
        // 链接
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $m): string {
            return '<a href="' . self::url($m[2]) . '">' . $m[1] . '</a>';
        }, $text) ?? $text;
        // 粗体 / 斜体 / 删除线
        $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text) ?? $text;
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text) ?? $text;

        // 还原行内代码
        $text = preg_replace_callback('/\x00CODE(\d+)\x00/', static function (array $m) use ($codes): string {
            return '<code>' . $codes[(int) $m[1]] . '</code>';
        }, $text) ?? $text;

        // 软换行转 <br>
        return nl2br($text);
    }

    /**
     * 链接/图片地址协议白名单：仅放行 http(s)、mailto、站内相对路径
     */
    private static function url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url) === 1 || preg_match('/^mailto:/i', $url) === 1) {
            return $url;
        }
        // 其余带协议的写法（javascript:、data:、vbscript: 等）一律失效
        if (str_contains($url, ':')) {
            return '#';
        }
        return $url;
    }

    /**
     * HTML 转义
     */
    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 解析列表
     *
     * @param array<int, string> $lines
     * @return array{0:string, 1:int}
     */
    private static function parseList(array $lines, int $i): array
    {
        $count = count($lines);
        $ordered = (bool) preg_match('/^\s*\d+\.\s+/', $lines[$i]);
        $items = [];

        while ($i < $count) {
            $line = $lines[$i];
            if (trim($line) === '') {
                // 空行后若仍是同类列表则继续，否则结束
                $j = $i + 1;
                if ($j < $count && preg_match('/^\s*([-*+]|\d+\.)\s+/', $lines[$j])) {
                    $i = $j;
                    continue;
                }
                break;
            }
            if (!preg_match('/^\s*([-*+]|\d+\.)\s+(.*)$/', $line, $m)) {
                break;
            }
            $item = $m[2];
            $i++;
            // 续行（缩进内容并入当前项）
            while ($i < $count) {
                $next = $lines[$i];
                if (trim($next) === '') {
                    break;
                }
                if (preg_match('/^\s*([-*+]|\d+\.)\s+/', $next)) {
                    break;
                }
                if (preg_match('/^\s{2,}\S/', $next)) {
                    $item .= ' ' . trim($next);
                    $i++;
                    continue;
                }
                break;
            }
            $items[] = '<li>' . self::inline($item) . '</li>';
        }

        $tag = $ordered ? 'ol' : 'ul';
        return ['<' . $tag . '>' . implode('', $items) . '</' . $tag . '>', $i];
    }

    /**
     * 解析表格
     *
     * @param array<int, string> $lines
     * @return array{0:string, 1:int}
     */
    private static function parseTable(array $lines, int $i): array
    {
        $count = count($lines);
        $header = self::splitRow($lines[$i]);
        $i += 2; // 跳过标题行与分隔行

        $rows = [];
        while ($i < $count && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
            $rows[] = self::splitRow($lines[$i]);
            $i++;
        }

        $html = '<table><thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th>' . self::inline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . self::inline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return [$html, $i];
    }

    /**
     * 拆分表格行
     *
     * @return array<int, string>
     */
    private static function splitRow(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\|/', '', $line) ?? $line;
        $line = preg_replace('/\|$/', '', $line) ?? $line;
        $cells = explode('|', $line);
        return array_map('trim', $cells);
    }
}
