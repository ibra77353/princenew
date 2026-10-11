<?php
/**
 * تنبيه فلتر تاريخ غير صالح (M7).
 * يُعرض داخل الصفحة مباشرة لأن رسائل التنبيه المؤقتة (Flash) لا تظهر في نفس الطلب.
 * @var string $rangeError
 */
if (($rangeError ?? '') !== ''): ?>
    <div class="alert alert--error" role="alert">
        <?= icon('alert') ?>
        <span><?= e($rangeError) ?> تم تجاهل هذا الحدّ الزمني في التصفية.</span>
    </div>
<?php endif; ?>
