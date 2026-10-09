<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * إعدادات التطبيق العامة مع قيم افتراضية موحّدة وإمكانية تجاوزها من جدول settings.
 */
final class Setting
{
    /** @var array<string,mixed> */
    private static array $defaults = [];

    /** @var array<string,mixed>|null */
    private static ?array $cached = null;

    /** @param array<string,mixed> $config */
    public static function configure(array $config): void
    {
        date_default_timezone_set((string) ($config['app']['timezone'] ?? 'UTC'));
        self::$defaults = $config['app'] ?? [];
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $row = [];
        try {
            $loaded = Database::one('SELECT * FROM settings ORDER BY id ASC LIMIT 1');
            if (is_array($loaded)) {
                $row = $loaded;
            }
        } catch (\Throwable) {
            $row = [];
        }

        self::$cached = array_merge(self::$defaults, $row);

        return self::$cached;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        return $default ?? (self::$defaults[$key] ?? null);
    }

    public static function forget(): void
    {
        self::$cached = null;
    }

    public static function symbol(): string
    {
        return (string) self::get('currency_symbol', 'ر.ي');
    }
}
