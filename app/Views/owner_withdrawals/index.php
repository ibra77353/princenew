<?php
/**
 * سحوبات المالك.
 * @var array<int,array<string,mixed>> $withdrawals
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var float $total
 * @var float $cashBalance
 */
?>
<div class="page-header">
    <div>
        <h1>سحوبات المالك</h1>
        <p>تُخصم السحوبات من الصندوق بعد التحقق من كفاية الرصيد.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="withdrawal-create"><?= icon('plus') ?> سحب جديد</button>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">رصيد الصندوق الحالي</span>
        <span class="stat__value <?= $cashBalance > 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($cashBalance)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي السحوبات (المعروضة)</span>
        <span class="stat__value text-danger"><?= e(money($total)) ?></span>
    </div>
</div>

<form method="get" action="<?= e(url('/owner-withdrawals')) ?>" class="filters">
    <div class="field">
        <label for="w-from">من تاريخ</label>
        <input class="input" id="w-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="w-to">إلى تاريخ</label>
        <input class="input" id="w-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php \App\Services\Core\View::partial('partials/filter-warning', ['rangeError' => $rangeError ?? '']); ?>

<?php if ($withdrawals === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('wallet') ?></div>
            <h2>لا توجد سحوبات</h2>
            <p>لا توجد عمليات سحب مسجّلة في هذه الفترة.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>السبب</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <tr>
                        <td class="wrap"><?= e($withdrawal['reason']) ?></td>
                        <td class="text-danger text-bold"><?= e(money($withdrawal['amount'])) ?></td>
                        <td><?= e(fdatetime($withdrawal['withdrawal_date'])) ?></td>
                        <td class="wrap"><?= e($withdrawal['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php \App\Services\Core\View::partial('partials/pagination', [
        'pager' => $pager,
        'query' => array_filter($filters, static fn ($value): bool => $value !== ''),
    ]); ?>
<?php endif; ?>

<div class="modal" id="withdrawal-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('wallet') ?> سحب جديد</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/owner-withdrawals')) ?>" class="stack">
                <?= csrf_field() ?>
                <?= request_field() ?>
                <div class="grid grid--form">
                    <div class="field">
                        <label for="w-amount">المبلغ</label>
                        <input class="input" id="w-amount" name="amount" type="number" min="1" step="1" required>
                    </div>
                    <div class="field">
                        <label for="w-date">التاريخ</label>
                        <input class="input" id="w-date" name="withdrawal_date" type="date" value="<?= e(today()) ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="w-reason">السبب</label>
                    <input class="input" id="w-reason" name="reason" type="text" required>
                </div>
                <div class="field">
                    <label for="w-notes">ملاحظات</label>
                    <textarea class="input" id="w-notes" name="notes" rows="2"></textarea>
                </div>
                <p class="hint">الرصيد المتاح في الصندوق: <?= e(money($cashBalance)) ?></p>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ السحب</button>
                </div>
            </form>
        </div>
    </div>
</div>
