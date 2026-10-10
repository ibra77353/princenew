<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * إدارة الجلسات الآمنة.
 */
final class Session
{
    /** @param array<string,mixed> $config */
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            return;
        }

        session_name((string) ($config['name'] ?? 'prince_net_session'));
        session_set_cookie_params([
            'lifetime' => (int) ($config['lifetime'] ?? 0),
            'path' => '/',
            'httponly' => true,
            'secure' => (bool) ($config['secure'] ?? false),
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
