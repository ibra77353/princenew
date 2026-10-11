<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Services\AuditService;

/**
 * وحدة تحكم أساسية: قراءة المدخلات، التحقق من CSRF، والعرض وإعادة التوجيه.
 */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data['flashes'] = Flash::all();
        $data['currentUser'] = Auth::user();
        $data['settings'] = Setting::all();
        View::render($template, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }

    protected function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($referer !== '' && $host !== '' && str_contains($referer, $host)) {
            $path = parse_url($referer, PHP_URL_PATH) ?: $fallback;
            $query = parse_url($referer, PHP_URL_QUERY);
            Response::redirect($path . ($query ? '?' . $query : ''));

            exit;
        }

        Response::redirect($fallback);
    }

    /** @return array<string,mixed> */
    protected function post(): array
    {
        return $_POST;
    }

    /** @return array<string,mixed> */
    protected function query(): array
    {
        return $_GET;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    protected function str(string $key, string $default = ''): string
    {
        return trim((string) $this->input($key, $default));
    }

    protected function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, null);

        return $value === null ? $default : (int) $value;
    }

    protected function moneyInput(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, null);
        if ($value === null) {
            return $default;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }

    /** @return array<int,mixed> */
    protected function array(string $key): array
    {
        $value = $_POST[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    protected function verifyCsrf(): void
    {
        Csrf::verify($_POST['_token'] ?? null);
    }

    /** رمز منع تكرار الطلب المرافق للنموذج (فارغ للنماذج القديمة). */
    protected function requestId(): string
    {
        return trim((string) ($_POST['_request_id'] ?? ''));
    }

    /**
     * يقرأ حدّي الفترة من الطلب ويتحقق منهما (M7).
     * عند قيمة غير صالحة يوضّح الخطأ ويُهمل الحدّ بدل إنتاج إجماليات خاطئة (فترة فارغة/كلية صامتة).
     * @return array{from:string,to:string,error:string}
     */
    protected function dateRange(): array
    {
        try {
            $range = DateRange::normalize($this->str('from'), $this->str('to'));

            return ['from' => $range['from'] ?? '', 'to' => $range['to'] ?? '', 'error' => ''];
        } catch (\RuntimeException $e) {
            return ['from' => '', 'to' => '', 'error' => $e->getMessage()];
        }
    }

    /** تسجيل عملية إدارية في سجل التدقيق. */
    protected function audit(string $action, string $entityType, ?int $entityId, string $details = ''): void
    {
        AuditService::log($action, $entityType, $entityId, $details);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    /**
     * تنفيذ عملية كتابة مع توحيد معالجة الأخطاء ورسائل التنبيه.
     * يمكن للعملية أن تُعيد مسارًا لإعادة التوجيه بعد النجاح.
     */
    protected function action(callable $callback, string $failRedirect, string $successMessage = ''): void
    {
        $target = $failRedirect;

        try {
            $result = $callback();
            if ($successMessage !== '') {
                Flash::success($successMessage);
            }
            if (is_string($result) && $result !== '') {
                $target = $result;
            }
        } catch (\Throwable $e) {
            // رسائل التحقق المقصودة تُعرض للمستخدم، أما الأخطاء التقنية (SQL، مسارات الملفات) فتُسجَّل فقط (M5)
            $message = $e instanceof \PDOException ? '' : trim($e->getMessage());
            if ($message === '' || !$e instanceof \RuntimeException) {
                ErrorHandler::report($e);
            }
            Flash::error($message !== '' ? $message : 'تعذر تنفيذ العملية. تحقق من البيانات وحاول مرة أخرى.');
            Flash::withOld($this->post());
        }

        $this->redirect($target);
    }

    /** @return array{page:int,perPage:int,offset:int,pages:int} */
    protected function pagination(int $total, int $perPage = 25): array
    {
        $page = max(1, $this->int('page', 1));
        $pages = max(1, (int) ceil($total / max(1, $perPage)));

        if ($page > $pages) {
            $page = $pages;
        }

        return [
            'page' => $page,
            'perPage' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'pages' => $pages,
        ];
    }
}
