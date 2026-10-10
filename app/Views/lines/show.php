<?php
/**
 * تفاصيل الخط وسجل دفعاته.
 * @var array<string,mixed> $line
 */
?>
<div class="page-header">
    <div>
        <h1><?= e($line['name']) ?></h1>
        <p><?= e($line['provider'] ?? 'بدون مزود') ?><?= !empty($line['identifier']) ? ' • ' . e($line['identifier']) : '' ?></p>
    </div>
    <div class="btn-group">
        <a class="btn btn--outline" href="<?= e(url('/lines')) ?>">رجوع للخطوط</a>
        <a class="btn btn--primary" href="<?= e(url('/line-payments?line_id=' . $line['id'])) ?>"><?= icon('credit-card') ?> تسجيل دفعة</a>
    </div>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">تكلفة الاشتراك</span>
        <span class="stat__value"><?= e(money($line['subscription_cost'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي الدفعات</span>
        <span class="stat__value text-success"><?= e(money($line['paid_total'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">عدد الدفعات</span>
        <span class="stat__value"><?= e((string) count($line['payments'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">تاريخ الاشتراك</span>
        <span class="stat__value text-sm"><?= e(fdate($line['subscription_date'])) ?></span>
    </div>
</div>

<section class="card card--pad">
    <h3 class="section-title"><?= icon('history') ?> سجل الدفعات</h3>
    <?php if ($line['payments'] === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('credit-card') ?></div>
            <p>لا توجد دفعات مسجّلة لهذا الخط.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>تاريخ التسديد</th>
                        <th>المبلغ</th>
                        <th>ملاحظات</th>
                        <th>تاريخ التسجيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line['payments'] as $payment): ?>
                        <tr>
                            <td><?= e(fdatetime($payment['payment_date'])) ?></td>
                            <td class="text-danger text-bold"><?= e(money($payment['amount'])) ?></td>
                            <td class="wrap"><?= e($payment['notes'] ?? '') ?></td>
                            <td><?= e(fdatetime($payment['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>الإجمالي</td>
                        <td colspan="3"><?= e(money($line['paid_total'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
