<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * معالجة فترات التواريخ في التقارير والفلاتر (M7–M9).
 *
 * - تتحقق من صيغة التاريخ ومن وجوده الفعلي في التقويم (لا 2026-02-30 ولا نص حر).
 * - تحدّد الحدود بشكل **شامل** بلا فقد ولا تكرار: من بداية يوم «من» إلى ما قبل
 *   بداية اليوم التالي لـ«إلى»، بدل الاعتماد على 23:59:59 الذي يُسقط أي وقت في
 *   اللحظة الأخيرة من اليوم.
 * - تقلب الحدود المعكوسة (إلى < من) بدل إنتاج فترة فارغة تُظهر إجماليات صفرية.
 */
final class DateRange
{
    /**
     * يحلّل تاريخًا بصيغة YYYY-MM-DD.
     * يُعيد null للقيمة الفارغة (بلا حد)، ويرمي RuntimeException لغير الصالح.
     */
    public static function parse(string $value, string $label = 'التاريخ'): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            throw new \RuntimeException($label . ' غير صالح: استخدم الصيغة YYYY-MM-DD.');
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \RuntimeException($label . ' غير صالح: هذا التاريخ غير موجود في التقويم.');
        }

        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * يحلّل حدّي الفترة ويعيدهما معياريين (أو null)، مع قلب الحدود المعكوسة.
     * @return array{from:?string,to:?string}
     */
    public static function normalize(string $from, string $to): array
    {
        $f = self::parse($from, 'تاريخ البداية');
        $t = self::parse($to, 'تاريخ النهاية');

        if ($f !== null && $t !== null && $f > $t) {
            [$f, $t] = [$t, $f];
        }

        return ['from' => $f, 'to' => $t];
    }

    /**
     * يضيف شرط الفترة إلى استعلام محضّر: [from 00:00:00 , to+يوم).
     * @param array<int,string> $where
     * @param array<int,mixed>  $params
     */
    public static function apply(array &$where, array &$params, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $where[] = $column . ' >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where[] = $column . ' < ?';
            $params[] = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
        }
    }
}
