<?php
/**
 * لوحة التحكم — إحصائيات حقيقية من قاعدة البيانات.
 * @var array<string,mixed> $stats
 * @var array<string,mixed> $alerts
 * @var array<int,array<string,mixed>> $recent
 * @var array<int,array<string,mixed>> $stockByPackage
 * @var array<string,mixed> $settings
 * @var array<string,int> $counts
 */
$threshold = (int) ($settings['low_stock_threshold'] ?? 10);

$cards = [
    ['مبيعات اليوم', money($stats['todaySalesTotal']), 'dashboard', 'tone-primary'],
    ['مبيعات الشهر', money($stats['monthSalesTotal']), 'chart', 'tone-green'],
    ['تحصيلات اليوم', money($stats['todayCollectionsTotal']), 'coins', 'tone-purple'],
    ['ديون الموزعين', money($stats['totalDistributorDebt']), 'alert', 'tone-red'],
    ['رصيد الصندوق', money($stats['cashBalance']), 'wallet', 'tone-green'],
    ['قيمة المخزون', money($stats['inventoryValue']), 'boxes', 'tone-amber'],
    ['عدد الباقات', $stats['packagesCount'] . ' (' . $stats['activePackagesCount'] . ' نشطة)', 'package', 'tone-primary'],
    ['عدد الموزعين', (string) $stats['distributorsCount'], 'users', 'tone-purple'],
    ['إجمالي المصروفات', money($stats['totalExpenses']), 'receipt', 'tone-red'],
    ['سحوبات المالك', money($stats['totalWithdrawals']), 'arrow-down', 'tone-amber'],
    ['مقبوضات الصندوق', money($stats['cashIn']), 'dashboard', 'tone-green'],
    ['مدفوعات الصندوق', money($stats['cashOut']), 'arrow-down', 'tone-red'],
];
?>
<div class="page-header">
    <div>
        <h1>لوحة التحكم</h1>
        <p>مرحبًا بك في <?= e($settings['network_name'] ?? 'شبكة البرنس') ?> — نظرة شاملة على الشبكة.</p>
    </div>
    <div class="btn-group">
        <a class="btn btn--primary" href="<?= e(url('/sales/create')) ?>"><?= icon('plus') ?> بيع جديد</a>
        <a class="btn btn--outline" href="<?= e(url('/collections')) ?>"><?= icon('coins') ?> تحصيل</a>
    </div>
</div>

<?php if ($alerts['lowStock'] !== [] || $alerts['indebtedDistributors'] !== []): ?>
    <div class="mb-4">
        <?php foreach ($alerts['lowStock'] as $item): ?>
            <div class="alert alert--warning">
                <?= icon('alert') ?>
                <span>المخزون منخفض: <?= e($item['name']) ?> — المتبقي <?= e((string) $item['current']) ?> شدة</span>
            </div>
        <?php endforeach; ?>
        <?php foreach ($alerts['indebtedDistributors'] as $item): ?>
            <div class="alert alert--error">
                <?= icon('alert') ?>
                <span>دين مستحق على <?= e($item['name']) ?> — <?= e(money($item['debt'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid--stats mb-4">
    <?php foreach ($cards as [$cardTitle, $value, $iconName, $tone]): ?>
        <div class="stat">
            <div class="stat__head">
                <span class="stat__title"><?= e($cardTitle) ?></span>
                <span class="stat__icon <?= e($tone) ?>"><?= icon($iconName) ?></span>
            </div>
            <span class="stat__value"><?= e((string) $value) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid--two">
    <section class="card card--pad">
        <h3 class="section-title"><?= icon('activity') ?> العمليات الأخيرة</h3>
        <?php if ($recent === []): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon('activity') ?></div>
                <p>لا توجد عمليات بعد. ابدأ بإضافة باقة ومخزون ثم سجّل عملية بيع.</p>
            </div>
        <?php else: ?>
            <div class="stack stack--sm">
                <?php foreach ($recent as $item): ?>
                    <div class="row row--between" style="border-bottom:1px solid var(--border); padding-bottom:0.5rem">
                        <div style="min-width:0">
                            <p class="text-sm text-bold truncate"><?= e($item['label']) ?></p>
                            <p class="text-xs text-muted">
                                <?= e(fdatetime($item['date'])) ?><?= $item['distributor'] ? ' • ' . e($item['distributor']) : '' ?>
                            </p>
                        </div>
                        <span class="text-sm text-bold nowrap <?= in_array($item['type'], ['expense', 'withdrawal', 'line_payment'], true) ? 'text-danger' : 'text-success' ?>">
                            <?= in_array($item['type'], ['expense', 'withdrawal', 'line_payment'], true) ? '-' : '+' ?><?= e(money($item['amount'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card card--pad">
        <h3 class="section-title"><?= icon('boxes') ?> حالة المخزون</h3>
        <?php if ($stockByPackage === []): ?>
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon('boxes') ?></div>
                <p>لا توجد باقات بعد.</p>
            </div>
        <?php else: ?>
            <div class="stack stack--sm">
                <?php foreach ($stockByPackage as $row): ?>
                    <div class="row row--between" style="border-bottom:1px solid var(--border); padding-bottom:0.5rem">
                        <div class="row">
                            <span style="width:0.6rem;height:0.6rem;border-radius:999px;background:<?= e($row['color'] ?: '#2563eb') ?>"></span>
                            <span class="text-sm"><?= e($row['name']) ?></span>
                        </div>
                        <div class="row">
                            <span class="text-xs text-muted"><?= e(money($row['value'])) ?></span>
                            <span class="badge <?= (int) $row['current'] <= $threshold ? 'badge--danger' : 'badge--success' ?>">
                                <?= e((string) $row['current']) ?> شدة
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid--cards mt-4">
    <a class="card card--pad" href="<?= e(url('/sales')) ?>">
        <p class="text-sm text-bold"><?= icon('cart') ?> المبيعات</p>
        <p class="text-xs text-muted mt-1"><?= e((string) $counts['sales']) ?> فاتورة مسجّلة</p>
    </a>
    <a class="card card--pad" href="<?= e(url('/collections')) ?>">
        <p class="text-sm text-bold"><?= icon('coins') ?> التحصيلات</p>
        <p class="text-xs text-muted mt-1"><?= e((string) $counts['collections']) ?> عملية تحصيل</p>
    </a>
    <a class="card card--pad" href="<?= e(url('/lines')) ?>">
        <p class="text-sm text-bold"><?= icon('wifi') ?> الخطوط</p>
        <p class="text-xs text-muted mt-1"><?= e((string) $counts['lines']) ?> خط مسجّل</p>
    </a>
    <a class="card card--pad" href="<?= e(url('/expenses')) ?>">
        <p class="text-sm text-bold"><?= icon('receipt') ?> المصروفات</p>
        <p class="text-xs text-muted mt-1"><?= e((string) $counts['expenses']) ?> مصروف مسجّل</p>
    </a>
</div>
