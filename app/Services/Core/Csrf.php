<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * حماية CSRF بالرموز المخزّنة في الجلسة.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }

    public static function check(?string $token): bool
    {
        $stored = $_SESSION['_csrf'] ?? null;
        if (!is_string($stored) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * التحقق من الرمز وإحباط الطلب عند الفشل.
     * يُستخدم الرمز القياسي 403 لأن بعض الوسائط تُعيد كتابة الرموز غير القياسية.
     */
    public static function verify(?string $token): void
    {
        if (!self::check($token)) {
            Response::abort(403, 'انتهت صلاحية الجلسة أو أن الطلب غير موثوق. أعد المحاولة.', 'طلب غير موثوق');
        }
    }
}
