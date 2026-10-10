<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Auth;
use App\Services\Core\Database;
use App\Services\Core\Setting;

/**
 * النسخ الاحتياطي والاستعادة عبر ملفات JSON محمية خارج الوصول العام.
 * تُنشأ نسخة أمان تلقائية قبل أي استعادة.
 */
final class BackupService
{
    public const TABLES = [
        'users', 'packages', 'inventory_movements', 'distributors', 'sales', 'sale_items',
        'collections', 'lines', 'line_payments', 'expenses', 'owner_withdrawals',
        'cash_transactions', 'settings', 'audit_logs',
    ];

    /** اقتباس أسماء الجداول المحجوزة في MySQL. */
    private static function quote(string $table): string
    {
        return $table === 'lines' ? '`lines`' : $table;
    }

    public static function directory(): string
    {
        $dir = (string) Setting::get('backup_dir', BASE_PATH . '/database/backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function export(string $label = ''): array
    {
        $payload = [
            'meta' => [
                'app' => 'prince-net',
                'version' => 1,
                'created_at' => now(),
                'created_by' => Auth::name(),
                'label' => $label,
                'tables' => self::TABLES,
            ],
            'data' => [],
        ];

        foreach (self::TABLES as $table) {
            $payload['data'][$table] = Database::all('SELECT * FROM ' . self::quote($table));
        }

        $filename = 'backup-' . date('Ymd-His') . ($label !== '' ? '-' . preg_replace('/[^a-zA-Z0-9\-_]/', '', $label) : '') . '.json';
        $path = self::directory() . '/' . $filename;

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false || file_put_contents($path, $encoded) === false) {
            throw new \RuntimeException('تعذر إنشاء ملف النسخة الاحتياطية. تحقق من صلاحيات المجلد database/backups.');
        }

        $id = Database::insert(
            'INSERT INTO backups (filename, size, tables_count, label, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$filename, filesize($path) ?: 0, count(self::TABLES), $label !== '' ? $label : null, now(), Auth::id()]
        );

        AuditService::log('export', 'backup', $id, 'إنشاء نسخة احتياطية: ' . $filename);

        return ['id' => $id, 'filename' => $filename, 'size' => filesize($path) ?: 0];
    }

    /** @return array<string,mixed> */
    public static function read(int $id): array
    {
        $backup = Database::one('SELECT * FROM backups WHERE id = ? LIMIT 1', [$id]);
        if ($backup === null) {
            throw new \RuntimeException('النسخة الاحتياطية غير موجودة.');
        }

        $path = self::directory() . '/' . basename((string) $backup['filename']);
        if (!is_file($path)) {
            throw new \RuntimeException('ملف النسخة الاحتياطية غير موجود على الخادم.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new \RuntimeException('ملف النسخة الاحتياطية تالف أو غير صالح.');
        }

        return ['record' => $backup, 'path' => $path, 'payload' => $decoded];
    }

    public static function restore(int $id): void
    {
        $bundle = self::read($id);
        $payload = $bundle['payload'];

        // التحقق الكامل من صحة البنية قبل لمس البيانات الحالية
        foreach (self::TABLES as $table) {
            if (!array_key_exists($table, $payload['data']) || !is_array($payload['data'][$table])) {
                throw new \RuntimeException('النسخة الاحتياطية لا تحتوي على جدول «' . $table . '» بشكل صالح.');
            }
        }

        // نسخة أمان تلقائية قبل الاستعادة
        self::export('pre-restore');

        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TABLES as $table) {
                $pdo->exec('TRUNCATE TABLE ' . self::quote($table));
                $rows = $payload['data'][$table];
                foreach ($rows as $row) {
                    if (!is_array($row) || $row === []) {
                        continue;
                    }
                    $columns = array_keys($row);
                    $sql = 'INSERT INTO ' . self::quote($table) . ' (' . implode(', ', $columns) . ') VALUES ('
                        . implode(', ', array_fill(0, count($columns), '?')) . ')';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($row));
                }
            }
        } catch (\Throwable $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            throw new \RuntimeException('فشلت الاستعادة: ' . $e->getMessage(), 0, $e);
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        \App\Services\Core\Setting::forget();

        AuditService::log('restore', 'backup', $id, 'استعادة النسخة: ' . $bundle['record']['filename']);
    }

    public static function delete(int $id): void
    {
        $backup = Database::one('SELECT * FROM backups WHERE id = ? LIMIT 1', [$id]);
        if ($backup === null) {
            throw new \RuntimeException('النسخة الاحتياطية غير موجودة.');
        }

        $path = self::directory() . '/' . basename((string) $backup['filename']);
        if (is_file($path)) {
            @unlink($path);
        }

        Database::affect('DELETE FROM backups WHERE id = ?', [$id]);
        AuditService::log('delete', 'backup', $id, 'حذف النسخة: ' . $backup['filename']);
    }

    public static function filePath(int $id): array
    {
        $bundle = self::read($id);

        return [(string) $bundle['path'], (string) $bundle['record']['filename']];
    }
}
