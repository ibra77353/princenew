<?php

declare(strict_types=1);

namespace App\Services\Core;

use Throwable;

/**
 * معالجة مركزية للأخطاء (M5): التفاصيل الكاملة تُسجَّل في سجل الخادم فقط،
 * ولا تُعرض للمستخدم إلا صفحة خطأ عامة بلا مسارات ملفات أو استعلامات.
 * عند تشغيل `APP_DEBUG=1` تُعرض التفاصيل في الصفحة للمطوّر.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    private static bool $rendered = false;

    private static string $appName = 'شبكة البرنس';

    /** @param array<string,mixed> $appConfig */
    public static function register(array $appConfig): void
    {
        self::$debug = (bool) ($appConfig['debug'] ?? false);
        self::$appName = (string) ($appConfig['name'] ?? self::$appName);

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('html_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /** تسجيل خطأ داخلي دون عرض أي تفاصيل للمستخدم. */
    public static function report(Throwable $e): void
    {
        self::log(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false; // خطأ مكتوم بعلامة @
        }

        self::log(self::label($severity), $message, $file, $line);

        // الأخطاء القاتلة تُترك لـ PHP لتُعرض من معالج الإنهاء بصفحة عامة واحدة
        return !in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true);
    }

    public static function handleException(Throwable $e): void
    {
        self::report($e);
        self::renderFatal([
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    public static function handleShutdown(): void
    {
        if (self::$rendered) {
            return;
        }

        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatal, true)) {
            return;
        }

        self::log(self::label((int) $error['type']), (string) $error['message'], (string) $error['file'], (int) $error['line']);
        self::renderFatal([
            'type' => self::label((int) $error['type']),
            'message' => (string) $error['message'],
            'file' => (string) $error['file'],
            'line' => (int) $error['line'],
        ]);
    }

    /** @param array{type:string,message:string,file:string,line:int} $details */
    private static function renderFatal(array $details): void
    {
        if (self::$rendered) {
            return;
        }
        self::$rendered = true;

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo self::page($details);
    }

    /** @param array{type:string,message:string,file:string,line:int} $details */
    private static function page(array $details): string
    {
        $title = 'حدث خطأ غير متوقع';
        $message = 'تعذر إكمال العملية. سُجّلت تفاصيل الخطأ في سجل الخادم. أعد المحاولة، وإن تكرر الخطأ فأبلغ مدير النظام.';
        $css = function_exists('asset') ? asset('css/app.css') : '/assets/css/app.css';
        $home = function_exists('url') ? url('/') : '/';

        $html = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            . '<meta name="robots" content="noindex">'
            . '<title>' . self::escape($title) . ' — ' . self::escape(self::$appName) . '</title>'
            . '<link rel="stylesheet" href="' . self::escape($css) . '"></head>'
            . '<body class="auth-body"><div class="auth-card">'
            . '<h2>' . self::escape($title) . '</h2><p>' . self::escape($message) . '</p>'
            . '<a class="btn btn--primary" href="' . self::escape($home) . '">العودة إلى لوحة التحكم</a>';

        if (self::$debug) {
            $html .= '<pre class="error-detail">' . self::escape(
                $details['type'] . ': ' . $details['message'] . ' — ' . $details['file'] . ':' . $details['line']
            ) . '</pre>';
        }

        return $html . '</div></body></html>';
    }

    private static function log(string $type, string $message, string $file = '', int $line = 0, string $trace = ''): void
    {
        $entry = sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), $type, $message);
        if ($file !== '') {
            $entry .= sprintf(' in %s:%d', $file, $line);
        }
        if ($trace !== '') {
            $entry .= PHP_EOL . $trace;
        }

        error_log($entry);
    }

    private static function label(int $severity): string
    {
        return match ($severity) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'Fatal error',
            E_PARSE => 'Parse error',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE => 'Notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
            default => 'Error',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
