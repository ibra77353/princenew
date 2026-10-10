<?php

declare(strict_types=1);

/**
 * إعدادات التطبيق وقاعدة البيانات.
 * تُقرأ من متغيرات البيئة، مع قيم افتراضية مناسبة للتشغيل على استضافة مشتركة
 * (حيث تُعدَّل القيم مباشرة في هذا الملف).
 */

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'prince_net',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        // true في الإنتاج عند العمل على HTTPS
        'secure' => (getenv('SESSION_SECURE') ?: '0') === '1',
        'name' => 'prince_net_session',
        'lifetime' => 0,
    ],

    'app' => [
        'name' => 'شبكة البرنس',
        'currency' => 'YER',
        'currency_symbol' => 'ر.ي',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Riyadh',
        'low_stock_threshold' => 10,
        'opening_balance' => 0,
        'debug' => (getenv('APP_DEBUG') ?: '0') === '1',
        'backup_dir' => BASE_PATH . '/database/backups',
    ],
];
