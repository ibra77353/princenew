<?php
/**
 * إعدادات النظام والمستخدمون.
 * @var array<string,mixed> $row
 * @var float $cashBalance
 * @var array<int,array<string,mixed>> $users
 * @var array<string,int> $counts
 * @var array<string,mixed> $settings
 */
$value = static fn (string $key, mixed $default = ''): mixed => $row[$key] ?? $settings[$key] ?? $default;
?>
<div class="page-header">
    <div>
        <h1>الإعدادات</h1>
        <p>تُطبَّق التغييرات على الواجهات والحسابات فورًا.</p>
    </div>
</div>

<div class="grid grid--two">
    <section class="card card--pad">
        <h3 class="section-title"><?= icon('settings') ?> إعدادات الشبكة</h3>

        <form method="post" action="<?= e(url('/settings')) ?>" class="stack">
            <?= csrf_field() ?>

            <div class="field">
                <label for="set-network">اسم الشبكة</label>
                <input class="input" id="set-network" name="network_name" type="text" required
                       value="<?= e($value('network_name', 'شبكة البرنس')) ?>">
            </div>

            <div class="grid grid--form">
                <div class="field">
                    <label for="set-currency">العملة</label>
                    <input class="input" id="set-currency" name="currency" type="text" value="<?= e($value('currency', 'YER')) ?>">
                </div>
                <div class="field">
                    <label for="set-symbol">رمز العملة</label>
                    <input class="input" id="set-symbol" name="currency_symbol" type="text" value="<?= e($value('currency_symbol', 'ر.ي')) ?>">
                </div>
            </div>

            <div class="field">
                <label for="set-email">بريد المدير</label>
                <input class="input" id="set-email" name="admin_email" type="email" value="<?= e($value('admin_email')) ?>">
            </div>

            <div class="grid grid--form">
                <div class="field">
                    <label for="set-opening">الرصيد الافتتاحي للصندوق</label>
                    <input class="input" id="set-opening" name="opening_balance" type="number" min="0" step="1"
                           value="<?= e(amount($value('opening_balance', 0))) ?>">
                    <span class="hint">يُحسب الرصيد الحالي من: الرصيد الافتتاحي + المقبوضات − المدفوعات.</span>
                </div>
                <div class="field">
                    <label for="set-threshold">حد تنبيه المخزون</label>
                    <input class="input" id="set-threshold" name="low_stock_threshold" type="number" min="0" step="1"
                           value="<?= e((string) $value('low_stock_threshold', 10)) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ الإعدادات</button>
            </div>
        </form>
    </section>

    <div class="stack">
        <section class="card card--pad">
            <h3 class="section-title"><?= icon('chart') ?> ملخّص النظام</h3>
            <div class="stack stack--sm text-sm">
                <div class="row row--between"><span class="text-muted text-xs">رصيد الصندوق الحالي</span><span class="text-bold"><?= e(money($cashBalance)) ?></span></div>
                <div class="row row--between"><span class="text-muted text-xs">عدد الفواتير</span><span class="text-bold"><?= e((string) $counts['sales']) ?></span></div>
                <div class="row row--between"><span class="text-muted text-xs">عدد الموزعين</span><span class="text-bold"><?= e((string) $counts['distributors']) ?></span></div>
                <div class="row row--between"><span class="text-muted text-xs">عدد الباقات</span><span class="text-bold"><?= e((string) $counts['packages']) ?></span></div>
                <div class="row row--between"><span class="text-muted text-xs">حركات المخزون</span><span class="text-bold"><?= e((string) $counts['movements']) ?></span></div>
            </div>
        </section>

        <section class="card card--pad">
            <h3 class="section-title"><?= icon('users') ?> مستخدمو النظام</h3>
            <?php if ($users === []): ?>
                <div class="empty-state"><p>لا يوجد مستخدمون بعد.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>آخر دخول</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= e($user['name']) ?></td>
                                    <td><?= e($user['email']) ?></td>
                                    <td><span class="badge badge--primary"><?= $user['role'] === 'admin' ? 'مدير' : 'مستخدم' ?></span></td>
                                    <td><?= e(fdatetime($user['last_login_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="hint mt-2">
                    لإضافة مستخدم أو تغيير كلمة مرور المدير، شغّل الأداة:
                    <code>php tools/create_admin.php</code>
                </p>
            <?php endif; ?>
        </section>

        <section class="card card--pad">
            <h3 class="section-title"><?= icon('alert') ?> ملاحظات أمنية</h3>
            <ul class="text-sm text-muted bullets">
                <li>كلمات المرور محفوظة بتشفير <code>password_hash()</code> ولا تُخزَّن كنص صريح.</li>
                <li>لا توجد بيانات دخول افتراضية في المستودع؛ يُنشئ المدير الأول عبر شاشة الإعداد.</li>
                <li>جميع الطلبات التي تغيّر البيانات محمية برمز CSRF.</li>
                <li>لا يوجد قسم للموردين في النظام، وبيع الشدات هو وحدة المخزون الأساسية.</li>
            </ul>
        </section>
    </div>
</div>

<section class="card card--pad mt-4">
    <h3 class="section-title"><?= icon('alert') ?> معلومات التطبيق</h3>
    <div class="text-sm text-muted stack stack--sm">
        <p>التطبيق: شبكة البرنس — نظام إدارة شبكة الإنترنت (Native PHP + MySQL).</p>
        <p>الإصدار: 1.0.0</p>
        <p>العملة الافتراضية: الريال اليمني (ر.ي).</p>
        <p>النسخ الاحتياطي متاح من صفحة «النسخ الاحتياطي».</p>
    </div>
</section>
