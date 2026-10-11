<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Auth;
use App\Services\Core\Database;
use App\Services\Core\Setting;

/**
 * النسخ الاحتياطي والاستعادة عبر ملفات JSON محمية خارج الوصول العام.
 * تُنشأ نسخة أمان تلقائية قبل أي استعادة.
 *
 * الاستعادة **ذرّية**: تُنفَّذ داخل معاملة واحدة، وأي خلل في النسخة (تكرار مفتاح فريد،
 * مرجع خارجي معلّق، حقل إلزامي مفقود، عمود غير معروف...) يُرفض ويُرجِع قاعدة البيانات
 * إلى حالتها دون أي تغيير. كما يُعاد بناء رصيد المخزون المشتق من الحركات بعد الاستعادة.
 */
final class BackupService
{
    /** الجداول الأساسية التي تُستعاد صفًا بصف. مرتّبة أبًا قبل ابن لتحقيق سلامة المفاتيح الخارجية. */
    public const TABLES = [
        'users', 'packages', 'inventory_movements', 'distributors', 'sales', 'sale_items',
        'collections', 'lines', 'line_payments', 'expenses', 'owner_withdrawals',
        'cash_transactions', 'settings', 'audit_logs',
    ];

    /** جداول مشتقة: تُصدَّر للعلم، ويُعاد بناؤها من الحركات عند الاستعادة (لا تُقرأ من الملف). */
    public const DERIVED_TABLES = ['inventory_stock'];

    /** اقتباس أسماء الجداول المحجوزة في MySQL. */
    private static function quote(string $table): string
    {
        return $table === 'lines' ? '`lines`' : $table;
    }

    /** كل الجداول المشمولة في ملف النسخة (الأساسية + المشتقة). @return array<int,string> */
    private static function exportTables(): array
    {
        return array_merge(self::TABLES, self::DERIVED_TABLES);
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
        $tables = self::exportTables();

        $payload = [
            'meta' => [
                'app' => 'prince-net',
                'version' => 2,
                'created_at' => now(),
                'created_by' => Auth::name(),
                'label' => $label,
                'tables' => $tables,
            ],
            'data' => [],
        ];

        foreach ($tables as $table) {
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
            [$filename, filesize($path) ?: 0, count($tables), $label !== '' ? $label : null, now(), Auth::id()]
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

        // 1) تحقق مسبق كامل قبل لمس أي بيانات: البنية، الأعمدة، القيم الإلزامية،
        //    تفرّد المفاتيح، وسلامة المفاتيح الخارجية. أي خلل = رفض دون أي تغيير
        //    (لا تُنشأ حتى نسخة الأمان ولا يُكتب سجل تدقيق).
        self::assertPayloadIsRestorable($payload['data']);

        // نسخة أمان تلقائية للبيانات الحالية قبل الاستعادة (قراءة فقط).
        self::export('pre-restore');

        // 2) استعادة ذرّية داخل معاملة واحدة: أي فشل (تكرار فريد أو مرجع معلّق أو صف تالف)
        //    يُرجِع قاعدة البيانات إلى حالتها بالكامل، فلا فقدان صامت لأي جدول.
        Database::transaction(function () use ($payload): void {
            $pdo = Database::pdo();

            try {
                // الحذف مع تعطيل المفاتيح الخارجية حتى لا تُفعَّل إجراءات التتالي على جداول
                // غير مُستعادة (مثل `backups`) عند حذف `users`.
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                foreach (self::TABLES as $table) {
                    $pdo->exec('DELETE FROM ' . self::quote($table));
                }

                // الإدراج مع تفعيل المفاتيح الخارجية وبالترتيب الأبوي: قاعدة البيانات نفسها
                // ترفض أي صف مكرر أو مرجع معلّق أو عمود غير معروف.
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                foreach (self::TABLES as $table) {
                    self::insertRows($table, $payload['data'][$table]);
                }

                self::rebuildStock();
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        Setting::forget();

        AuditService::log('restore', 'backup', $id, 'استعادة النسخة: ' . $bundle['record']['filename']);
    }

    /**
     * تحقق مسبق كامل من قابلية النسخة للاستعادة، دون أي كتابة في قاعدة البيانات.
     * يُرفض الملف عند: جدول مفقود، عمود غير معروف، حقل إلزامي ناقص، تكرار مفتاح فريد،
     * أو مرجع خارجي معلّق.
     *
     * @param array<string,mixed> $data
     */
    private static function assertPayloadIsRestorable(array $data): void
    {
        // أ) البنية: كل جدول أساسي موجود وله مصفوفة صفوف
        foreach (self::TABLES as $table) {
            if (!array_key_exists($table, $data) || !is_array($data[$table])) {
                throw new \RuntimeException('النسخة الاحتياطية لا تحتوي على جدول «' . $table . '» بشكل صالح.');
            }
        }

        // ب) الأعمدة والقيم الإلزامية
        /** @var array<string,array<string,bool>> $requiredColumns */
        $requiredColumns = [];
        /** @var array<string,array<int,array<int,string>>> $validColumns */
        $validColumns = [];

        foreach (self::TABLES as $table) {
            $required = [];
            $valid = [];
            foreach (Database::all('SHOW COLUMNS FROM ' . self::quote($table)) as $column) {
                $name = (string) $column['Field'];
                $valid[$name] = true;
                $required[$name] = strtoupper((string) $column['Null']) === 'NO'
                    && $column['Default'] === null
                    && !str_contains((string) $column['Extra'], 'auto_increment');
            }
            $requiredColumns[$table] = $required;
            $validColumns[$table] = $valid;
        }

        foreach (self::TABLES as $table) {
            foreach ($data[$table] as $index => $row) {
                if (!is_array($row) || $row === []) {
                    throw new \RuntimeException(
                        sprintf('النسخة الاحتياطية تالفة: صف غير صالح في جدول «%s» (رقم %d).', $table, $index + 1)
                    );
                }

                foreach (array_keys($row) as $key) {
                    if (!isset($validColumns[$table][(string) $key])) {
                        throw new \RuntimeException(
                            sprintf('النسخة الاحتياطية تالفة: عمود غير معروف «%s» في جدول «%s».', (string) $key, $table)
                        );
                    }
                }

                foreach ($requiredColumns[$table] as $name => $isRequired) {
                    if ($isRequired && !array_key_exists($name, $row)) {
                        throw new \RuntimeException(
                            sprintf('النسخة الاحتياطية تالفة: الحقل الإلزامي «%s» مفقود في جدول «%s».', $name, $table)
                        );
                    }
                }
            }
        }

        // ج) تفرّد المفاتيح (PRIMARY + UNIQUE) داخل النسخة
        foreach (self::TABLES as $table) {
            $uniqueKeys = self::uniqueKeys($table);
            if ($uniqueKeys === []) {
                continue;
            }

            $seen = [];
            foreach ($data[$table] as $row) {
                foreach ($uniqueKeys as $index => $columns) {
                    if (!self::hasColumns($row, $columns)) {
                        continue;
                    }

                    $values = [];
                    $hasNull = false;
                    foreach ($columns as $column) {
                        $value = $row[$column] ?? null;
                        if ($value === null) {
                            $hasNull = true;
                            break;
                        }
                        $values[] = (string) $value;
                    }

                    // القيم الفارغة لا تتعارض في المفاتيح الفريدة (سلوك MySQL).
                    if ($hasNull) {
                        continue;
                    }

                    $tuple = implode("\x1F", $values);
                    if (isset($seen[$index][$tuple])) {
                        throw new \RuntimeException(
                            sprintf('النسخة الاحتياطية تالفة: تكرار في مفتاح فريد بجدول «%s» (القيمة «%s»).', $table, $tuple)
                        );
                    }
                    $seen[$index][$tuple] = true;
                }
            }
        }

        // د) سلامة المفاتيح الخارجية: كل قيمة غير فارغة يجب أن تجد صفًّا أبًا داخل النسخة
        foreach (self::TABLES as $table) {
            foreach (self::foreignKeys($table) as $fk) {
                if (!in_array($fk['parent'], self::TABLES, true)) {
                    continue;
                }

                $parentValues = [];
                foreach ($data[$fk['parent']] as $parentRow) {
                    if (is_array($parentRow) && array_key_exists($fk['parent_column'], $parentRow)) {
                        $parentValues[(string) $parentRow[$fk['parent_column']]] = true;
                    }
                }

                foreach ($data[$table] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $value = $row[$fk['column']] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    if (!isset($parentValues[(string) $value])) {
                        throw new \RuntimeException(
                            sprintf(
                                'النسخة الاحتياطية غير متسقة: القيمة «%s» في «%s.%s» لا تشير إلى أي صف في «%s».',
                                (string) $value,
                                $table,
                                $fk['column'],
                                $fk['parent']
                            )
                        );
                    }
                }
            }
        }
    }

    /** أعمدة المفاتيح الفريدة (بما فيها المفتاح الأساسي) لجدول. @return array<int,array<int,string>> */
    private static function uniqueKeys(string $table): array
    {
        $keys = [];
        $rows = Database::all(
            'SELECT INDEX_NAME, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );

        foreach ($rows as $row) {
            $keys[(string) $row['INDEX_NAME']][] = (string) $row['COLUMN_NAME'];
        }

        return array_values($keys);
    }

    /**
     * علاقات المفاتيح الخارجية لجدول.
     * @return array<int,array{column:string,parent:string,parent_column:string}>
     */
    private static function foreignKeys(string $table): array
    {
        $rows = Database::all(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table]
        );

        $keys = [];
        foreach ($rows as $row) {
            $keys[] = [
                'column' => (string) $row['COLUMN_NAME'],
                'parent' => (string) $row['REFERENCED_TABLE_NAME'],
                'parent_column' => (string) $row['REFERENCED_COLUMN_NAME'],
            ];
        }

        return $keys;
    }

    /** @param array<string,mixed> $row @param array<int,string> $columns */
    private static function hasColumns(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int,mixed> $rows */
    private static function insertRows(string $table, array $rows): void
    {
        $pdo = Database::pdo();
        $name = self::quote($table);
        /** @var array<string,\PDOStatement> $statements */
        $statements = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row) || $row === []) {
                throw new \RuntimeException(
                    sprintf('النسخة الاحتياطية تالفة: صف غير صالح في جدول «%s» (رقم %d).', $table, $index + 1)
                );
            }

            $columns = array_keys($row);
            $signature = implode(',', $columns);

            if (!isset($statements[$signature])) {
                $statements[$signature] = $pdo->prepare(
                    'INSERT INTO ' . $name . ' (' . implode(', ', $columns) . ') VALUES ('
                    . implode(', ', array_fill(0, count($columns), '?')) . ')'
                );
            }

            $statements[$signature]->execute(array_values($row));
        }
    }

    /**
     * إعادة بناء رصيد المخزون المشتق من الحركات المستعادة.
     * `inventory_stock` جدول مشتق (صف واحد لكل باقة)، ومصدر الحقيقة هو `inventory_movements`،
     * لذلك يُعاد بناؤه بعد أي استعادة ليبقى مطابقًا تمامًا للحركات ولا ينفصل عنها.
     */
    private static function rebuildStock(): void
    {
        $pdo = Database::pdo();

        $pdo->exec('DELETE FROM inventory_stock');
        $pdo->exec(
            "INSERT INTO inventory_stock (package_id, quantity, updated_at)
             SELECT package_id,
                    SUM(CASE WHEN type IN ('add','return') THEN quantity
                             WHEN type = 'sell' THEN -quantity
                             WHEN type = 'adjust' AND direction = 'up' THEN quantity
                             WHEN type = 'adjust' AND direction = 'down' THEN -quantity
                             ELSE 0 END),
                    NOW()
             FROM inventory_movements
             GROUP BY package_id"
        );
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
