<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * إنشاء الاستجابات: إعادة التوجيه، الأخطاء، وJSON.
 */
final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        if (headers_sent()) {
            echo '<script>location.replace(' . json_encode(url($path)) . ');</script>';

            exit;
        }

        header('Location: ' . url($path), true, $status);

        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function abort(int $status, string $message, ?string $title = null): never
    {
        http_response_code($status);
        $title ??= match ($status) {
            403 => 'غير مصرح',
            404 => 'غير موجود',
            405 => 'طريقة غير مسموحة',
            default => 'خطأ',
        };

        $content = '<div class="empty-state"><div class="empty-state__icon">!</div>'
            . '<h2>' . e($title) . '</h2><p>' . e($message) . '</p>'
            . '<a class="btn btn--primary" href="' . e(url('/')) . '">العودة إلى لوحة التحكم</a></div>';

        if (Auth::check()) {
            // صفحة الخطأ (403/404/405) تُعرض داخل التخطيط العام، فنُمرِّر بيانات القائمة الجانبية والترويسة
            // وإلا ظهرتا بلا اسم الشبكة ولا اسم المستخدم (تحذيرات PHP 8). الموظف يرى هذه الصفحة عند رفض عمليته (M6).
            View::render('errors/index', [
                'title' => $title,
                'message' => $message,
                'settings' => Setting::all(),
                'currentUser' => Auth::user(),
            ], 'layouts/app');

            exit;
        }

        echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title><link rel="stylesheet" href="' . e(asset('css/app.css')) . '">'
            . '</head><body class="auth-body">' . $content . '</body></html>';

        exit;
    }
}
