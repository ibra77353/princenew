<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * رسائل التنبيه المؤقتة ومدخلات النموذج السابقة (نمط PRG).
 */
final class Flash
{
    /** @var array<int,array{type:string,message:string}> */
    private static array $current = [];

    /** @var array<string,mixed> */
    private static array $oldInput = [];

    public static function boot(): void
    {
        $pending = $_SESSION['_flash'] ?? [];
        self::$current = is_array($pending) ? $pending : [];
        unset($_SESSION['_flash']);

        $old = $_SESSION['_old'] ?? [];
        self::$oldInput = is_array($old) ? $old : [];
        unset($_SESSION['_old']);
    }

    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'] = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function all(): array
    {
        return self::$current;
    }

    /** @param array<string,mixed> $input */
    public static function withOld(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirmation']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        $value = self::$oldInput[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }
}
