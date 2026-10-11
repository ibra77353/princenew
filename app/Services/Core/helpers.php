<?php

declare(strict_types=1);

use App\Services\Core\Setting;

if (!function_exists('e')) {
    /** ترميز المخرجات لمنع XSS. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path_url')) {
    /** مسار التطبيق الأساسي (يدعم التثبيت داخل مجلد فرعي). */
    function base_path_url(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

        return $dir === '/' ? '' : $dir;
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return base_path_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** رابط ملف أصل مع رقم نسخة من وقت تعديل الملف، فلا يعرض المتصفح نسخة قديمة بعد أي تعديل. */
    function asset(string $path): string
    {
        $relative = 'assets/' . ltrim($path, '/');
        $file = BASE_PATH . '/' . $relative;
        $version = is_file($file) ? filemtime($file) : false;

        return url($relative . ($version !== false ? '?v=' . $version : ''));
    }
}

if (!function_exists('money')) {
    /** تنسيق المبالغ كأعداد صحيحة مع رمز العملة. */
    function money(mixed $amount, ?string $symbol = null): string
    {
        $value = (float) ($amount ?? 0);
        $symbol ??= Setting::symbol();

        return number_format($value, 0, '.', ',') . ' ' . $symbol;
    }
}

if (!function_exists('num')) {
    function num(mixed $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', ',');
    }
}

if (!function_exists('amount')) {
    /** إخراج المبلغ كرقم خام للعرض داخل الصفحات (بلا منازل عشرية عندما تكون القيمة عددًا صحيحًا). */
    function amount(mixed $value): string
    {
        $number = (float) ($value ?? 0);
        $decimals = $number == (float) (int) $number ? 0 : 2;

        return number_format($number, $decimals, '.', '');
    }
}

if (!function_exists('ar_months')) {
    /** @return array<int,string> */
    function ar_months(): array
    {
        return [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
            7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
    }
}

if (!function_exists('fdate')) {
    function fdate(?string $date, string $fallback = '-'): string
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000-00-00')) {
            return $fallback;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $fallback;
        }

        return sprintf('%d %s %d', (int) date('j', $ts), ar_months()[(int) date('n', $ts)], (int) date('Y', $ts));
    }
}

if (!function_exists('fdatetime')) {
    function fdatetime(?string $date, string $fallback = '-'): string
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000-00-00')) {
            return $fallback;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $fallback;
        }

        return fdate($date) . ' ' . date('H:i', $ts);
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('now')){
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('settings')) {
    /** @return array<string,mixed> */
    function settings(): array
    {
        return Setting::all();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App\Services\Core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('request_field')) {
    /**
     * رمز فريد لكل عرض نموذج، يمنع تنفيذ الطلب المكرر عند إعادة الإرسال (M13).
     * يُتحقَّق منه على الخادم داخل معاملة العملية (انظر Idempotency::claim).
     */
    function request_field(): string
    {
        return '<input type="hidden" name="_request_id" value="' . e(bin2hex(random_bytes(16))) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return App\Services\Core\Flash::old($key, $default);
    }
}

if (!function_exists('is_active_path')) {
    function is_active_path(string $path): bool
    {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $current = '/' . trim(substr($current, strlen(base_path_url())), '/');
        $path = '/' . trim($path, '/');

        return $path === '/' ? $current === '/' : str_starts_with($current, $path);
    }
}

if (!function_exists('is_admin')) {
    /** هل المستخدم الحالي مدير؟ تُستخدم لإخفاء عناصر الواجهة فقط — الفرض الفعلي على الخادم (Auth::requireAdmin). */
    function is_admin(): bool
    {
        return App\Services\Core\Auth::isAdmin();
    }
}

if (!function_exists('role_label')) {
    /** اسم الدور بالعربية (دورَان فقط: مدير / موظف). */
    function role_label(?string $role): string
    {
        return $role === 'admin' ? 'مدير' : 'موظف';
    }
}

if (!function_exists('input_int')) {
    function input_int(mixed $value): int
    {
        return (int) filter_var((string) $value, FILTER_SANITIZE_NUMBER_INT);
    }
}

if (!function_exists('icon')) {
    /** أيقونات SVG محلية (بأسلوب الخطوط المفردة) — دون أي مكتبة خارجية. */
    function icon(string $name, string $class = ''): string
    {
        static $paths = [
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'package' => '<path d="M21 8.5 12 3 3 8.5v7L12 21l9-5.5z"/><path d="M3 8.5 12 14l9-5.5"/><path d="M12 14v7"/>',
            'boxes' => '<path d="M3 7.5 7.5 4 12 7.5 7.5 11z"/><path d="M12 7.5 16.5 4 21 7.5 16.5 11z"/><path d="M3 16.5 7.5 13 12 16.5 7.5 20z"/><path d="M12 16.5 16.5 13 21 16.5 16.5 20z"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13A4 4 0 0 1 16 11"/>',
            'shield' => '<path d="M12 3l7 3v5.6c0 4.5-2.9 7.5-7 8.9-4.1-1.4-7-4.4-7-8.9V6z"/><path d="M9 12.1l2.1 2.1 4.1-4.2"/>',
            'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2.5l2.6 11.4a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 2-1.6L21 7H6"/>',
            'coins' => '<circle cx="12" cy="8" r="5"/><path d="M6 15.5A6.5 6.5 0 0 0 12 21a6.5 6.5 0 0 0 6-2.6"/><path d="M9 12v1.5"/><path d="M15 12v1.5"/>',
            'wifi' => '<path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><path d="M2 9a15 15 0 0 1 20 0"/><circle cx="12" cy="19.5" r="1"/>',
            'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/>',
            'receipt' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6"/><path d="M9 11h6"/><path d="M9 15h4"/>',
            'wallet' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H5.5A2.5 2.5 0 0 1 3 17.5z"/><path d="M16 12.5h3"/>',
            'chart' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 8v4.5l3 1.8"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.4-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4a2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.9 1.4l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 11a2 2 0 1 1 0 4z"/>',
            'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
            'menu' => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
            'logout' => '<path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
            'network' => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M12 7.5v4"/><path d="M12 11.5 6.5 16.5"/><path d="M12 11.5 17.5 16.5"/>',
            'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'printer' => '<path d="M7 8V3h10v5"/><rect x="4" y="8" width="16" height="8" rx="2"/><path d="M7 16h10v5H7z"/>',
            'x' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
            'edit' => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M14 6l4 4"/>',
            'trash' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
            'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 9v5"/><path d="M12 17h.01"/>',
            'check' => '<path d="M4 12.5 9.5 18 20 6"/>',
            'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10.5 20a2 2 0 0 0 3 0"/>',
            'download' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
            'save' => '<path d="M5 3h11l3 3v15H5z"/><path d="M9 3v6h6V3"/><path d="M9 14h6v7H9z"/>',
            'activity' => '<path d="M3 12h4l2.5-7 4 14 2.5-7H21"/>',
            'moon' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.9 4.9l1.4 1.4"/><path d="M17.7 17.7l1.4 1.4"/><path d="M4.9 19.1l1.4-1.4"/><path d="M17.7 6.3l1.4-1.4"/>',
            'refresh' => '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
            'arrow-down' => '<path d="M12 4v14"/><path d="M6 13l6 6 6-6"/>',
            'phone' => '<path d="M5 3.5h3l1.5 4-1.8 1.4a11 11 0 0 0 5.4 5.4L14.5 12.5l4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 3 5.7 2 2 0 0 1 5 3.5z"/>',
            'pin' => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'share' => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.9 15.8 6.8"/><path d="M8.2 13.1l7.6 4.1"/>',
            'message' => '<path d="M21 12a8 8 0 0 1-8 8H8l-5 3 1.5-4.7A8 8 0 1 1 21 12z"/>',
        ];

        $path = $paths[$name] ?? $paths['activity'];

        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $path . '</svg>';
    }
}
