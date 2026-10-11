<?php
/**
 * تفاصيل الموزع: البيانات، الأرصدة، الفواتير، والتحصيلات.
 * @var array<string,mixed> $distributor
 * @var array<int,array<string,mixed>> $sales
 * @var array<int,array<string,mixed>> $collections
 * @var float $activeSalesTotal
 * @var float $paidAtSale
 * @var float $validCollections
 * @var float $balance
 * @var array{from:string,to:string} $filters
 * @var string $rangeError
 */
?>
<div class="page-header">
    <div>
        <h1><?= e($distributor['name']) ?></h1>
        <p>
            <?= e($distributor['phone'] ?? 'بدون هاتف') ?>
            <?= !empty($distributor['address']) ? ' • ' . e($distributor['address']) : '' ?>
        </p>
    </div>
    <div class="btn-group">
        <a class="btn btn--outline" href="<?= e(url('/distributors')) ?>">رجوع</a>
        <a class="btn btn--primary" href="<?= e(url('/collections')) ?>"><?= icon('coins') ?> تسجيل تحصيل</a>
        <button type="button" class="btn btn--outline" data-print><?= icon('printer') ?> طباعة الكشف</button>
    </div>
</div>

<form method="get" action="<?= e(url('/distributors/' . $distributor['id'])) ?>" class="filters no-print">
    <div class="field">
        <label for="d-from">من تاريخ</label>
        <input class="input" id="d-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="d-to">إلى تاريخ</label>
        <input class="input" id="d-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--primary"><?= icon('chart') ?> عرض الكشف</button>
    </div>
    <div class="field self-end">
        <a class="btn btn--outline" href="<?= e(url('/distributors/' . $distributor['id'])) ?>">كل الفترات</a>
    </div>
</form>

<?php \App\Services\Core\View::partial('partials/filter-warning', ['rangeError' => $rangeError ?? '']); ?>

<div class="print-area">
    <?php // ترويسة ورقية: تُعرض في الطباعة فقط، فتبيّن الورقة صاحب الكشف ونطاقه وحالة الدين ?>
    <div class="print-only mb-4">
        <h2>كشف حساب <?= e($settings['network_name'] ?? 'شبكة البرنس') ?></h2>
        <p class="hint">
            الموزع: <?= e($distributor['name']) ?><?= !empty($distributor['phone']) ? ' — ' . e($distributor['phone']) : '' ?>
            • الفترة: <?= $filters['from'] !== '' ? e(fdate($filters['from'])) : 'من البداية' ?>
            — <?= $filters['to'] !== '' ? e(fdate($filters['to'])) : 'حتى اليوم' ?>
        </p>
        <p class="hint">
            الرصيد المستحق الحالي (كل الفترات): <?= e(money($balance)) ?>
            — إجماليات الكشف للفواتير النشطة فقط (الملغاة تظهر بحالتها ومتبقيها صفر)،
            والتحصيلات المعكوسة تبقى معروضة بحالتها ولا تدخل في إجمالي التحصيلات.
        </p>
    </div>

    <p class="text-sm text-muted mb-4">
        الفترة: <?= $filters['from'] !== '' ? e(fdate($filters['from'])) : 'من البداية' ?>
        — <?= $filters['to'] !== '' ? e(fdate($filters['to'])) : 'حتى اليوم' ?>
    </p>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">إجمالي المبيعات</span>
        <span class="stat__value text-primary"><?= e(money($activeSalesTotal)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي التحصيلات</span>
        <span class="stat__value text-success"><?= e(money($validCollections)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">المدفوع عند البيع</span>
        <span class="stat__value"><?= e(money($paidAtSale)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">الرصيد المستحق</span>
        <span class="stat__value <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($balance)) ?></span>
    </div>
</div>

<?php if (!empty($distributor['notes'])): ?>
    <div class="alert alert--info mb-4"><?= icon('alert') ?><span><?= e($distributor['notes']) ?></span></div>
<?php endif; ?>

<section class="mb-4">
    <h3 class="section-title"><?= icon('cart') ?> سجل المبيعات</h3>
    <?php if ($sales === []): ?>
        <div class="card card--pad">
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon('cart') ?></div>
                <p>لا توجد مبيعات.</p>
                <a class="btn btn--primary" href="<?= e(url('/sales/create')) ?>">إنشاء فاتورة</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <?php // المتبقي الموحّد: الملغاة صفر والتحصيلات مخصومة (كما في قائمة المبيعات وصفحة الفاتورة) ?>
                        <?php $remaining = (float) $sale['remaining']; ?>
                        <tr>
                            <td><?= e($sale['invoice_number']) ?></td>
                            <td><?= e(fdatetime($sale['sale_date'])) ?></td>
                            <td><?= $sale['payment_type'] === 'cash' ? 'نقدي' : 'آجل' ?></td>
                            <td><?= e(money($sale['total_amount'])) ?></td>
                            <td><?= e(money($sale['paid_amount'])) ?></td>
                            <td><?= e(money($remaining)) ?></td>
                            <td>
                                <span class="badge <?= $sale['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                                    <?= $sale['status'] === 'active' ? 'نشطة' : 'ملغاة' ?>
                                </span>
                            </td>
                            <td><a class="btn btn--outline btn--sm" href="<?= e(url('/sales/' . $sale['id'])) ?>">عرض</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section>
    <h3 class="section-title"><?= icon('coins') ?> سجل التحصيلات</h3>
    <?php if ($collections === []): ?>
        <div class="card card--pad">
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon('coins') ?></div>
                <p>لا توجد تحصيلات.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الفاتورة</th>
                        <th>المبلغ</th>
                        <th>ملاحظات</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $collection): ?>
                        <tr>
                            <td><?= e(fdatetime($collection['collection_date'])) ?></td>
                            <td><?= e($collection['invoice_number'] ?? 'عام') ?></td>
                            <td class="<?= $collection['status'] === 'active' ? 'text-success' : 'text-muted' ?> text-bold">
                                <?= e(money($collection['amount'])) ?>
                            </td>
                            <td class="wrap"><?= e($collection['notes'] ?? '') ?></td>
                            <td>
                                <span class="badge <?= $collection['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                                    <?= $collection['status'] === 'active' ? 'صالحة' : 'معكوسة' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>
