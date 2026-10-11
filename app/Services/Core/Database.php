<?php

declare(strict_types=1);

namespace App\Services\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * اتصال PDO واحد للتطبيق مع دوال مساعدة للاستعلامات المحضّرة والمعاملات.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** @var array<string,mixed> */
    private static array $config = [];

    private static int $transactionDepth = 0;

    /** @param array<string,mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $c = self::$config;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'],
            (int) $c['port'],
            $c['name'],
            $c['charset']
        );

        try {
            self::$pdo = new PDO($dsn, (string) $c['user'], (string) $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                // انقطاع قاعدة البيانات يجب أن يفشل سريعًا ويُعرض كخطأ معالج، لا أن يعلّق الصفحة (M5)
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'تعذر الاتصال بقاعدة البيانات. تحقق من إعدادات الاتصال في config/database.php',
                0,
                $e
            );
        }

        return self::$pdo;
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $params */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $params */
    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);

        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string|int,mixed> $params */
    public static function affect(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        if (self::$transactionDepth === 0) {
            self::pdo()->beginTransaction();
        } else {
            self::pdo()->exec('SAVEPOINT sp' . self::$transactionDepth);
        }

        self::$transactionDepth++;

        try {
            $result = $callback();
            self::$transactionDepth--;

            if (self::$transactionDepth === 0) {
                self::pdo()->commit();
            } else {
                self::pdo()->exec('RELEASE SAVEPOINT sp' . self::$transactionDepth);
            }

            return $result;
        } catch (Throwable $e) {
            self::$transactionDepth--;

            if (self::$transactionDepth === 0) {
                if (self::pdo()->inTransaction()) {
                    self::pdo()->rollBack();
                }
            } else {
                self::pdo()->exec('ROLLBACK TO SAVEPOINT sp' . self::$transactionDepth);
            }

            throw $e;
        }
    }
}
