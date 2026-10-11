<?php

declare(strict_types=1);

/**
 * إعدادات التطبيق وقاعدة البيانات.
 * تُقرأ من متغيرات البيئة، مع قيم افتراضية مناسبة للتشغيل على استضافة مشتركة
 * (حيث تُعدَّل القيم مباشرة في هذا الملف).
 */

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'sql302.infinityfree.com',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'if0_43097781_prin',
        'user' => getenv('DB_USER') ?: 'if0_43097781',
        'pass' => getenv('DB_PASS') ?: 'Ibra773537707',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        // true في الإنتاج عند العمل على HTTPS
        'secure' => (getenv('SESSION_SECURE') ?: '1') === '1',
        'name' => 'prince_net_session',
        'lifetime' => 0,
    ],

    'app' => [
        'name' => 'شبكة البرنس',
        'currency' => 'YER',
        'currency_symbol' => 'ر.ي',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Aden',
        'low_stock_threshold' => 10,
        'opening_balance' => 0,
        'debug' => (getenv('APP_DEBUG') ?: '0') === '1',
        'backup_dir' => BASE_PATH . '/database/backups',
    ],
];
