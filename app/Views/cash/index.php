<?php
/**
 * الصندوق: سجل موحّد لكل الحركات النقدية.
 * @var array<int,array<string,mixed>> $transactions
 * @var array<string,string> $sources
 * @var array<int,array<string,mixed>> $bySource
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var float $openingBalance
 * @var array<string,float> $periodTotals
 * @var float $balance
 * @var array<string,float> $allTotals
 */
?>
<div class="page-header">
    <div>
        <h1>الصندوق</h1>
        <p>الرصيد = الرصيد الافتتاحي + إجمالي المقبوضات − إجمالي المدفوعات.</p>
    </div>
    <?php if (is_admin()): // التقارير للمدير فقط، والرابط يرد 403 للموظف ?>
        <div class="btn-group">
            <a class="btn btn--outline" href="<?= e(url('/reports')) ?>"><?= icon('chart') ?> تقارير التدفق النقدي</a>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">الرصيد الحالي</span>
        <span class="stat__value <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($balance)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">الرصيد الافتتاحي</span>
        <span class="stat__value"><?= e(money($openingBalance)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي المقبوضات (الكل)</span>
        <span class="stat__value text-success"><?= e(money($allTotals['in'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي المدفوعات (الكل)</span>
        <span class="stat__value text-danger"><?= e(money($allTotals['out'])) ?></span>
    </div>
</div>

<section class="card card--pad mb-4">
    <h3 class="section-title"><?= icon('chart') ?> الحركات حسب المصدر</h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>المصدر</th>
                    <th>مقبوضات</th>
                    <th>مدفوعات</th>
                    <th>الصافي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bySource as $row): ?>
                    <?php $net = (float) $row['cash_in'] - (float) $row['cash_out']; ?>
                    <tr>
                        <td><?= e($sources[$row['source']] ?? $row['source']) ?></td>
                        <td class="text-success"><?= e(money($row['cash_in'])) ?></td>
                        <td class="text-danger"><?= e(money($row['cash_out'])) ?></td>
                        <td class="text-bold"><?= e(money($net)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<form method="get" action="<?= e(url('/cash')) ?>" class="filters">
    <div class="field field--wide">
        <label for="cash-source">المصدر</label>
        <select class="select" id="cash-source" name="source">
            <option value="">كل المصادر</option>
            <?php foreach ($sources as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $filters['source'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="cash-from">من تاريخ</label>
        <input class="input" id="cash-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="cash-to">إلى تاريخ</label>
        <input class="input" id="cash-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php \App\Services\Core\View::partial('partials/filter-warning', ['rangeError' => $rangeError ?? '']); ?>

<?php if ($transactions === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('chart') ?></div>
            <h2>لا توجد حركات نقدية</h2>
            <p>ستظهر هنا حركات المبيعات والتحصيلات والمصروفات والدفعات والسحوبات.</p>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>النوع</th>
                    <th>المصدر</th>
                    <th>البيان</th>
                    <th>مقبوض</th>
                    <th>مدفوع</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?= e(fdatetime($transaction['transaction_date'])) ?></td>
                        <td>
                            <span class="badge <?= $transaction['type'] === 'in' ? 'badge--success' : 'badge--danger' ?>">
                                <?= $transaction['type'] === 'in' ? 'مقبوض' : 'مدفوع' ?>
                            </span>
                        </td>
                        <td><?= e($sources[$transaction['source']] ?? $transaction['source']) ?></td>
                        <td class="wrap"><?= e($transaction['description']) ?></td>
                        <td class="text-success"><?= $transaction['type'] === 'in' ? e(money($transaction['amount'])) : '—' ?></td>
                        <td class="text-danger"><?= $transaction['type'] === 'out' ? e(money($transaction['amount'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">إجمالي الفترة المعروضة</td>
                    <td class="text-success"><?= e(money($periodTotals['in'])) ?></td>
                    <td class="text-danger"><?= e(money($periodTotals['out'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php \App\Services\Core\View::partial('partials/pagination', [
        'pager' => $pager,
        'query' => array_filter($filters, static fn ($value): bool => $value !== ''),
    ]); ?>
<?php endif; ?>
