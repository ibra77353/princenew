<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * المصادقة والجلسات: تحقق كلمة المرور، حماية المسارات، وإعداد المدير الأول.
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        $user = Database::one('SELECT * FROM users WHERE id = ? AND status = ? LIMIT 1', [$id, 'active']);
        if ($user === null) {
            unset($_SESSION['user_id']);

            return null;
        }

        self::$user = $user;

        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function name(): string
    {
        return (string) (self::user()['name'] ?? 'مستخدم');
    }

    /** هل النظام ما زال بحاجة إلى إنشاء حساب المدير الأول؟ */
    public static function needsSetup(): bool
    {
        try {
            $count = (int) Database::scalar('SELECT COUNT(*) FROM users');
        } catch (\Throwable $e) {
            // قاعدة البيانات غير متاحة: تظهر شاشة الإعداد الأول، وتُسجَّل التفاصيل داخليًا (M5)
            ErrorHandler::report($e);

            return true;
        }

        return $count === 0;
    }

    public static function attempt(string $identifier, string $password): bool
    {
        $identifier = trim($identifier);
        $user = Database::one(
            'SELECT * FROM users WHERE (email = ? OR username = ?) AND status = ? LIMIT 1',
            [$identifier, $identifier, 'active']
        );

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::affect('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT),
                $user['id'],
            ]);
        }

        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = $user;

        Database::affect('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::destroy();
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        if (self::needsSetup()) {
            Response::redirect('/setup');
        }

        Flash::error('يجب تسجيل الدخول للمتابعة.');
        Response::redirect('/login');
    }

    /** إنشاء حساب المدير الأول. مسموح فقط عندما لا يوجد أي مستخدم. */
    public static function createFirstAdmin(string $name, string $email, string $password, ?string $username = null): int
    {
        if (!self::needsSetup()) {
            throw new \RuntimeException('تم إعداد حساب المدير مسبقًا.');
        }

        return Database::insert(
            'INSERT INTO users (name, email, username, password_hash, role, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $email,
                $username !== null && $username !== '' ? $username : null,
                password_hash($password, PASSWORD_DEFAULT),
                'admin',
                'active',
                now(),
                now(),
            ]
        );
    }
}
