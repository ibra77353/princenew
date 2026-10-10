<?php
/**
 * دفعات الخطوط.
 * @var array<int,array<string,mixed>> $payments
 * @var array<int,array<string,mixed>> $lines
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var float $sum
 */
?>
<div class="page-header">
    <div>
        <h1>دفعات الخطوط</h1>
        <p>كل دفعة تُخصم من الصندوق وتُسجَّل بتاريخ التسديد الفعلي الذي تختاره.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="payment-create"><?= icon('plus') ?> دفعة جديدة</button>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">إجمالي الدفعات</span>
        <span class="stat__value"><?= e(money($sum)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">عدد الدفعات</span>
        <span class="stat__value"><?= e((string) count($payments)) ?></span>
    </div>
</div>

<form method="get" action="<?= e(url('/line-payments')) ?>" class="filters">
    <div class="field field--wide">
        <label for="lp-line">الخط</label>
        <select class="select" id="lp-line" name="line_id">
            <option value="">كل الخطوط</option>
            <?php foreach ($lines as $line): ?>
                <option value="<?= e((string) $line['id']) ?>" <?= (int) $filters['line_id'] === (int) $line['id'] ? 'selected' : '' ?>>
                    <?= e($line['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="lp-from">من تاريخ</label>
        <input class="input" id="lp-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="lp-to">إلى تاريخ</label>
        <input class="input" id="lp-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php if ($payments === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('credit-card') ?></div>
            <h2>لا توجد دفعات</h2>
            <p>سجّل أول دفعة لأحد الخطوط.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>الخط</th>
                    <th>المبلغ</th>
                    <th>تاريخ التسديد</th>
                    <th>ملاحظات</th>
                    <th>تاريخ التسجيل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= e($payment['line_name'] ?? '—') ?></td>
                        <td class="text-danger text-bold"><?= e(money($payment['amount'])) ?></td>
                        <td><?= e(fdatetime($payment['payment_date'])) ?></td>
                        <td class="wrap"><?= e($payment['notes'] ?? '') ?></td>
                        <td><?= e(fdatetime($payment['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php \App\Services\Core\View::partial('partials/pagination', [
        'pager' => $pager,
        'query' => array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 0),
    ]); ?>
<?php endif; ?>

<div class="modal" id="payment-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('credit-card') ?> دفعة خط جديدة</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/line-payments')) ?>" class="stack">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="pay-line">الخط</label>
                    <select class="select" id="pay-line" name="line_id" required>
                        <option value="">اختر الخط</option>
                        <?php foreach ($lines as $line): ?>
                            <option value="<?= e((string) $line['id']) ?>">
                                <?= e($line['name']) ?> — التكلفة <?= e(money($line['subscription_cost'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid--form">
                    <div class="field">
                        <label for="pay-amount">المبلغ</label>
                        <input class="input" id="pay-amount" name="amount" type="number" min="1" step="1" required>
                    </div>
                    <div class="field">
                        <label for="pay-date">تاريخ التسديد</label>
                        <input class="input" id="pay-date" name="payment_date" type="date" value="<?= e(today()) ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="pay-notes">ملاحظات</label>
                    <textarea class="input" id="pay-notes" name="notes" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ الدفعة</button>
                </div>
            </form>
        </div>
    </div>
</div>
