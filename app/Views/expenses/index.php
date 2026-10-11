<?php
/**
 * المصروفات.
 * @var array<int,array<string,mixed>> $expenses
 * @var array<string,string> $categories
 * @var array<int,array<string,mixed>> $byCategory
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var float $total
 * @var float $cashBalance
 */
?>
<div class="page-header">
    <div>
        <h1>المصروفات</h1>
        <p>تُسجَّل المصروفات مع حركة نقدية خارجة من الصندوق، ولا تُقبل إذا تجاوزت الرصيد المتاح.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="expense-create"><?= icon('plus') ?> مصروف جديد</button>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">إجمالي المصروفات (المعروضة)</span>
        <span class="stat__value text-danger"><?= e(money($total)) ?></span>
    </div>
    <?php foreach (array_slice($byCategory, 0, 3) as $row): ?>
        <div class="stat">
            <span class="stat__title"><?= e($categories[$row['category']] ?? $row['category']) ?></span>
            <span class="stat__value"><?= e(money($row['total'])) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('/expenses')) ?>" class="filters">
    <div class="field field--wide">
        <label for="e-category">التصنيف</label>
        <select class="select" id="e-category" name="category">
            <option value="">كل التصنيفات</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="e-from">من تاريخ</label>
        <input class="input" id="e-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="e-to">إلى تاريخ</label>
        <input class="input" id="e-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php if ($expenses === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('receipt') ?></div>
            <h2>لا توجد مصروفات</h2>
            <p>سجّل أول مصروف لتتبعه في التقارير والتدفق النقدي.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>التصنيف</th>
                    <th>الوصف</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><span class="badge badge--warning"><?= e($categories[$expense['category']] ?? $expense['category']) ?></span></td>
                        <td class="wrap"><?= e($expense['description']) ?></td>
                        <td class="text-danger text-bold"><?= e(money($expense['amount'])) ?></td>
                        <td><?= e(fdatetime($expense['expense_date'])) ?></td>
                        <td class="wrap"><?= e($expense['notes'] ?? '') ?></td>
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

<div class="modal" id="expense-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('receipt') ?> مصروف جديد</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/expenses')) ?>" class="stack">
                <?= csrf_field() ?>
                <div class="grid grid--form">
                    <div class="field">
                        <label for="exp-category">التصنيف</label>
                        <select class="select" id="exp-category" name="category" required>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="exp-amount">المبلغ</label>
                        <input class="input" id="exp-amount" name="amount" type="number" min="1" step="1" required>
                    </div>
                </div>
                <div class="field">
                    <label for="exp-description">الوصف</label>
                    <input class="input" id="exp-description" name="description" type="text" required>
                </div>
                <div class="grid grid--form">
                    <div class="field">
                        <label for="exp-date">التاريخ</label>
                        <input class="input" id="exp-date" name="expense_date" type="date" value="<?= e(today()) ?>">
                    </div>
                    <div class="field">
                        <label for="exp-notes">ملاحظات</label>
                        <input class="input" id="exp-notes" name="notes" type="text">
                    </div>
                </div>
                <p class="hint">الرصيد المتاح في الصندوق: <?= e(money($cashBalance)) ?></p>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ المصروف</button>
                </div>
            </form>
        </div>
    </div>
</div>
