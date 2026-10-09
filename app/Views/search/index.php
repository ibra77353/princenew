<?php
/**
 * البحث الشامل.
 * @var string $term
 * @var array<string,array<int,array<string,mixed>>> $results
 */
$groups = [
    'packages' => ['الباقات', 'package'],
    'distributors' => ['الموزعون', 'users'],
    'sales' => ['الفواتير', 'cart'],
    'collections' => ['التحصيلات', 'coins'],
    'expenses' => ['المصروفات', 'receipt'],
    'lines' => ['الخطوط', 'wifi'],
    'line_payments' => ['دفعات الخطوط', 'credit-card'],
    'withdrawals' => ['سحوبات المالك', 'wallet'],
    'cash' => ['حركات الصندوق', 'chart'],
];
$totalResults = 0;
foreach ($results as $rows) {
    $totalResults += count($rows);
}
?>
<div class="page-header">
    <div>
        <h1>البحث الشامل</h1>
        <p>ابحث في الباقات والموزعين والفواتير والتحصيلات والمصروفات والخطوط وحركات الصندوق.</p>
    </div>
</div>

<form method="get" action="<?= e(url('/search')) ?>" class="filters">
    <div class="field field--wide">
        <label for="s-term">كلمة البحث</label>
        <input class="input" id="s-term" name="q" type="search" value="<?= e($term) ?>" placeholder="اسم موزع، رقم فاتورة، وصف مصروف..." autofocus>
    </div>
    <div class="field" style="align-self:end">
        <button type="submit" class="btn btn--primary"><?= icon('search') ?> بحث</button>
    </div>
</form>

<?php if ($term === ''): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('search') ?></div>
            <h2>ابدأ بالبحث</h2>
            <p>اكتب كلمة في الحقل أعلاه ثم اضغط «بحث» لعرض النتائج من كل الأقسام.</p>
        </div>
    </div>
<?php elseif ($totalResults === 0): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('search') ?></div>
            <h2>لا توجد نتائج</h2>
            <p>لا يوجد أي سجل مطابق لـ «<?= e($term) ?>».</p>
        </div>
    </div>
<?php else: ?>
    <p class="text-sm text-muted mb-3">عدد النتائج: <?= e((string) $totalResults) ?></p>

    <div class="grid grid--two">
        <?php foreach ($groups as $key => [$label, $iconName]): ?>
            <?php if (($results[$key] ?? []) === []): ?>
                <?php continue; ?>
            <?php endif; ?>
            <section class="result-group card card--pad">
                <h3><?= icon($iconName) ?> <?= e($label) ?> (<?= e((string) count($results[$key])) ?>)</h3>
                <ul class="result-list">
                    <?php foreach ($results[$key] as $row): ?>
                        <li>
                            <?php if ($key === 'packages'): ?>
                                <a href="<?= e(url('/packages')) ?>"><span><?= e($row['name']) ?></span><span class="text-bold"><?= e(money($row['price'])) ?></span></a>
                            <?php elseif ($key === 'distributors'): ?>
                                <a href="<?= e(url('/distributors/' . $row['id'])) ?>"><span><?= e($row['name']) ?></span><span class="text-xs text-muted"><?= e($row['phone'] ?? '') ?></span></a>
                            <?php elseif ($key === 'sales'): ?>
                                <a href="<?= e(url('/sales/' . $row['id'])) ?>">
                                    <span><?= e($row['invoice_number']) ?> — <?= e($row['distributor_name']) ?></span>
                                    <span class="text-bold"><?= e(money($row['total_amount'])) ?></span>
                                </a>
                            <?php elseif ($key === 'collections'): ?>
                                <a href="<?= e(url('/collections')) ?>"><span><?= e($row['distributor_name'] ?? '—') ?></span><span class="text-bold"><?= e(money($row['amount'])) ?></span></a>
                            <?php elseif ($key === 'expenses'): ?>
                                <a href="<?= e(url('/expenses')) ?>">
                                    <span><?= e($row['description']) ?> — <?= e(\App\Models\Expense::CATEGORIES[$row['category']] ?? $row['category']) ?></span>
                                    <span class="text-bold"><?= e(money($row['amount'])) ?></span>
                                </a>
                            <?php elseif ($key === 'lines'): ?>
                                <a href="<?= e(url('/lines/' . $row['id'])) ?>"><span><?= e($row['name']) ?></span><span class="text-xs text-muted"><?= e($row['provider'] ?? '') ?></span></a>
                            <?php elseif ($key === 'line_payments'): ?>
                                <a href="<?= e(url('/line-payments')) ?>"><span><?= e($row['line_name'] ?? '—') ?> — <?= e(fdate($row['payment_date'])) ?></span><span class="text-bold"><?= e(money($row['amount'])) ?></span></a>
                            <?php elseif ($key === 'withdrawals'): ?>
                                <a href="<?= e(url('/owner-withdrawals')) ?>"><span><?= e($row['reason']) ?></span><span class="text-bold"><?= e(money($row['amount'])) ?></span></a>
                            <?php else: ?>
                                <a href="<?= e(url('/cash')) ?>"><span><?= e($row['description']) ?></span><span class="text-bold"><?= e(money($row['amount'])) ?></span></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
