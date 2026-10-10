<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Auth;
use App\Services\Core\Database;

/**
 * تسجيل العمليات الإدارية في سجل التدقيق (لا يُعدّل من الواجهة).
 */
final class AuditService
{
    public const ENTITIES = [
        'auth' => 'الدخول',
        'package' => 'الباقة',
        'inventory' => 'المخزون',
        'distributor' => 'الموزع',
        'sale' => 'الفاتورة',
        'collection' => 'التحصيل',
        'line' => 'الخط',
        'line_payment' => 'دفعة خط',
        'expense' => 'المصروف',
        'withdrawal' => 'سحب مالك',
        'cash' => 'الصندوق',
        'settings' => 'الإعدادات',
        'backup' => 'النسخ الاحتياطي',
    ];

    public static function log(string $action, string $entityType, ?int $entityId, string $details = ''): void
    {
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, user_name, action, entity_type, entity_id, details, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    Auth::id(),
                    Auth::name(),
                    $action,
                    $entityType,
                    $entityId,
                    $details !== '' ? $details : null,
                    now(),
                ]
            );
        } catch (\Throwable) {
            // فشل تسجيل التدقيق لا يجب أن يوقف العملية الأساسية
        }
    }
}
