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

use PDO;
use PDOException;

/**
 * SQLite 数据库连接（单例）
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = config('database.path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            self::$pdo = $pdo;
        } catch (PDOException $e) {
            json_response(['message' => '数据库连接失败: ' . $e->getMessage()], 500);
        }

        return self::$pdo;
    }

    /**
     * 执行查询并返回所有结果
     * @return array<int, array<string, mixed>>
     */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 查询单行
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * 执行写操作，返回受影响行数
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 插入并返回自增 ID
     */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        self::execute($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * 更新
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = :{$column}";
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::execute($sql, array_merge($data, $whereParams));
    }

    /**
     * 删除
     */
    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return self::execute($sql, $whereParams);
    }

    /**
     * 开启事务
     */
    public static function beginTransaction(): bool
    {
        return self::pdo()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::pdo()->commit();
    }

    public static function rollBack(): bool
    {
        return self::pdo()->rollBack();
    }
}
