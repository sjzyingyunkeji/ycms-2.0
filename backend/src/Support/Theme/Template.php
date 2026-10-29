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

namespace App\Support\Theme;

/**
 * 极简占位符模板引擎（主题包不执行任何 PHP 代码）
 *
 * 语法：
 *   {{ var }}          变量，HTML 转义
 *   {{{ var }}}        原始输出，仅用于后端已净化的正文
 *   {{# list }} ... {{/ list }}   区块循环 / 对象上下文 / 布尔真值
 *   {{^ list }} ... {{/ list }}   反向区块（空值时渲染）
 *   {{ obj.key }}      点号取值
 */
final class Template
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data): string
    {
        $tokens = preg_split(
            '/(\{\{\{.*?\}\}\}|\{\{.*?\}\})/s',
            $template,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        if (!is_array($tokens)) {
            return '';
        }

        $index = 0;
        $nodes = self::parse($tokens, $index, null);

        return self::renderNodes($nodes, [$data]);
    }

    /**
     * @param array<int, string> $tokens
     * @return array<int, array<string, mixed>>
     */
    private static function parse(array $tokens, int &$i, ?string $expectEnd): array
    {
        $nodes = [];
        $total = count($tokens);

        while ($i < $total) {
            $token = $tokens[$i];

            if (preg_match('/^\{\{\s*\/\s*([A-Za-z0-9_.]+)\s*\}\}$/', $token, $m)) {
                if ($expectEnd !== null && $m[1] === $expectEnd) {
                    $i++;
                    return $nodes;
                }
                // 未匹配的结束标签按文本处理，避免模板错误导致整页空白
                $nodes[] = ['type' => 'text', 'value' => $token];
                $i++;
                continue;
            }

            if (preg_match('/^\{\{\s*([#^])\s*([A-Za-z0-9_.]+)\s*\}\}$/', $token, $m)) {
                $i++;
                $children = self::parse($tokens, $i, $m[2]);
                $nodes[] = [
                    'type' => 'block',
                    'name' => $m[2],
                    'inverse' => $m[1] === '^',
                    'children' => $children,
                ];
                continue;
            }

            if (preg_match('/^\{\{\{\s*([A-Za-z0-9_.]+)\s*\}\}\}$/', $token, $m)) {
                $nodes[] = ['type' => 'var', 'name' => $m[1], 'raw' => true];
                $i++;
                continue;
            }

            if (preg_match('/^\{\{\s*([A-Za-z0-9_.]+)\s*\}\}$/', $token, $m)) {
                $nodes[] = ['type' => 'var', 'name' => $m[1], 'raw' => false];
                $i++;
                continue;
            }

            $nodes[] = ['type' => 'text', 'value' => $token];
            $i++;
        }

        return $nodes;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, mixed> $stack
     */
    private static function renderNodes(array $nodes, array $stack): string
    {
        $out = '';

        foreach ($nodes as $node) {
            if ($node['type'] === 'text') {
                $out .= (string) $node['value'];
                continue;
            }

            if ($node['type'] === 'var') {
                $value = self::lookup((string) $node['name'], $stack);
                if ($value === null || is_array($value)) {
                    continue;
                }
                $text = (string) $value;
                $out .= ($node['raw'] ?? false)
                    ? $text
                    : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                continue;
            }

            // block
            $value = self::lookup((string) $node['name'], $stack);
            $children = (array) $node['children'];

            if (($node['inverse'] ?? false) === true) {
                if (self::isEmpty($value)) {
                    $out .= self::renderNodes($children, $stack);
                }
                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        $next = $stack;
                        $next[] = is_array($item) ? $item : ['value' => $item];
                        $out .= self::renderNodes($children, $next);
                    }
                } else {
                    $next = $stack;
                    $next[] = $value;
                    $out .= self::renderNodes($children, $next);
                }
                continue;
            }

            if (!self::isEmpty($value)) {
                $out .= self::renderNodes($children, $stack);
            }
        }

        return $out;
    }

    /**
     * @param array<int, mixed> $stack
     */
    private static function lookup(string $name, array $stack): mixed
    {
        $segments = explode('.', $name);
        $first = $segments[0];

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $context = $stack[$i];
            if (!is_array($context) || !array_key_exists($first, $context)) {
                continue;
            }
            $value = $context[$first];
            foreach (array_slice($segments, 1) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }
            return $value;
        }

        return null;
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null
            || $value === false
            || $value === 0
            || $value === ''
            || $value === []
            || (is_string($value) && trim($value) === '');
    }
}
