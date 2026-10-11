<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * منع تنفيذ الطلب نفسه مرتين عند إعادة إرساله (M13).
 *
 * كل عرض نموذج مالي/مخزني يحمل رمزًا عشوائيًا فريدًا (`request_field()`)،
 * ويُسجَّل الرمز في جدول `request_tokens` **داخل معاملة العملية نفسها**، فأي
 * إرسال ثانٍ بالرمز نفسه (تحديث الصفحة، زر «رجوع» ثم إرسال، أو نقر مزدوج)
 * يُرفض قبل إنشاء أي فاتورة أو خصم مخزون أو حركة صندوق. وُضوح الرمز في قاعدة
 * البيانات — لا في المتصفح — يجعله فعّالًا عبر كل الأجهزة والجلسات.
 */
final class Idempotency
{
    /**
     * يحجز الرمز مرة واحدة. عند تكراره يرمي RuntimeException برسالة عربية واضحة.
     * يجب استدعاؤه في بداية معاملة العملية حتى يُلغى الحجز تلقائيًا إن فشلت العملية.
     */
    public static function claim(string $token, string $scope): void
    {
        $token = trim($token);
        if ($token === '') {
            // نماذج قديمة (بلا رمز) تبقى صالحة — الحماية هنا إضافية لا تمنع الحفظ.
            return;
        }
        if (strlen($token) > 64) {
            $token = substr($token, 0, 64);
        }

        try {
            Database::run(
                'INSERT INTO request_tokens (token, scope, created_at) VALUES (?, ?, ?)',
                [$token, $scope, now()]
            );
        } catch (\PDOException $e) {
            $sqlState = (string) $e->getCode();
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($sqlState === '23000' || $driverCode === 1062) {
                throw new \RuntimeException('تم استلام هذا الطلب مسبقًا ولم يُنفَّذ مرة أخرى.');
            }
            throw $e;
        }
    }

    /** تنظيف الرموز القديمة (جدول مؤقت لا يدخل النسخ الاحتياطي). */
    public static function prune(int $days = 7): void
    {
        Database::run(
            'DELETE FROM request_tokens WHERE created_at < ?',
            [date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        );
    }
}
