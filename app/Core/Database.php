<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Accès MySQL via PDO (requêtes préparées natives, InnoDB, transactions).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = App::config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => (bool) $c['persistent'],
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . date('P') . "'",
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        // Les requêtes préparées natives n'acceptent pas deux fois le même
        // paramètre nommé : on duplique automatiquement (:p => :p__1, :p__2...).
        if ($params && !array_is_list($params)) {
            $seen = [];
            $sql = (string) preg_replace_callback('/(?<![:\w]):([A-Za-z_]\w*)/', static function ($m) use (&$seen, &$params) {
                $name = $m[1];
                if (!array_key_exists($name, $params) && !array_key_exists(':' . $name, $params)) {
                    return $m[0];
                }
                $seen[$name] = ($seen[$name] ?? 0) + 1;
                if ($seen[$name] === 1) {
                    return $m[0];
                }
                $alias = $name . '__' . $seen[$name];
                $params[$alias] = $params[$name] ?? $params[':' . $name];
                return ':' . $alias;
            }, $sql);
            // retire les paramètres non utilisés (sinon PDO lève une erreur)
            foreach (array_keys($params) as $k) {
                $base = preg_replace('/__\d+$/', '', ltrim((string) $k, ':'));
                if (!isset($seen[$base])) {
                    unset($params[$k]);
                }
            }
        }
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                $v === null => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $stmt->bindValue($key, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Liste clé => valeur (2 colonnes). */
    public static function pairs(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', array_map(static fn($c) => ':' . $c, $cols))
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, int $id, string $pk = 'id'): int
    {
        if (!$data) {
            return 0;
        }
        $set = implode(',', array_map(static fn($c) => "`$c` = :$c", array_keys($data)));
        $data['__pk'] = $id;
        return self::query("UPDATE `$table` SET $set WHERE `$pk` = :__pk", $data)->rowCount();
    }

    public static function delete(string $table, int $id, string $pk = 'id'): int
    {
        return self::query("DELETE FROM `$table` WHERE `$pk` = :id", ['id' => $id])->rowCount();
    }

    public static function find(string $table, int $id): ?array
    {
        return self::one("SELECT * FROM `$table` WHERE id = :id", ['id' => $id]);
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
