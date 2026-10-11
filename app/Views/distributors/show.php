<?php
/**
 * كشف حساب موزع: اختيار الموزع والفترة، ثم بياناته وأرصدته وفواتيره وتحصيلاته.
 * @var array<string,mixed>|null $distributor الموزع المختار (null قبل الاختيار)
 * @var array<int,array<string,mixed>> $distributors قائمة الموزعين للاختيار
 * @var array<int,array<string,mixed>> $sales
 * @var array<int,array<string,mixed>> $collections
 * @var float $activeSalesTotal
 * @var float $paidAtSale
 * @var float $validCollections
 * @var float $balance
 * @var array{from:string,to:string} $filters
 * @var string $rangeError
 */
$selectedId = $distributor !== null ? (int) $distributor['id'] : 0;
$statementUrl = url('/distributors/statement') . ($selectedId > 0 ? '?distributor_id=' . $selectedId : '');
// رابط تصدير PDF: نفس الموزع والفترة المعروضة (يُبنى الملف على الخادم عبر PdfService/mPDF)
$pdfQuery = array_filter([
    'distributor_id' => $selectedId > 0 ? (string) $selectedId : '',
    'from' => (string) $filters['from'],
    'to' => (string) $filters['to'],
], static fn (string $value): bool => $value !== '');
$pdfUrl = url('/distributors/statement/pdf') . ($pdfQuery !== [] ? '?' . http_build_query($pdfQuery) : '');
?>
<div class="page-header">
    <div>
        <?php if ($distributor === null): ?>
            <h1>كشف حساب موزع</h1>
            <p>اختر اسم الموزع والفترة ثم اعرض الكشف أو اطبعه.</p>
        <?php else: ?>
            <h1><?= e($distributor['name']) ?></h1>
            <p>
                <?= e($distributor['phone'] ?? 'بدون هاتف') ?>
                <?= !empty($distributor['address']) ? ' • ' . e($distributor['address']) : '' ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="btn-group">
        <a class="btn btn--outline" href="<?= e(url('/distributors')) ?>">رجوع</a>
        <?php if ($distributor !== null): ?>
            <a class="btn btn--primary" href="<?= e(url('/collections')) ?>"><?= icon('coins') ?> تسجيل تحصيل</a>
            <a class="btn btn--outline" href="<?= e($pdfUrl) ?>"><?= icon('download') ?> تصدير PDF</a>
            <button type="button" class="btn btn--outline" data-print><?= icon('printer') ?> طباعة الكشف</button>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= e(url('/distributors/statement')) ?>" class="filters no-print">
    <div class="field field--wide">
        <label for="d-distributor">اسم الموزع</label>
        <select class="select" id="d-distributor" name="distributor_id">
            <option value="">— اختر الموزع —</option>
            <?php foreach ($distributors as $option): ?>
                <option value="<?= e((string) $option['id']) ?>" <?= (int) $option['id'] === $selectedId ? 'selected' : '' ?>>
                    <?= e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
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
    <?php if ($distributor !== null): ?>
        <div class="field self-end">
            <a class="btn btn--outline" href="<?= e($statementUrl) ?>">كل الفترات</a>
        </div>
    <?php endif; ?>
</form>

<?php \App\Services\Core\View::partial('partials/filter-warning', ['rangeError' => $rangeError ?? '']); ?>

<?php if ($distributor === null): // لا موزع مختار بعد: شاشة الاختيار فقط ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('receipt') ?></div>
            <h2>اختر موزعًا لعرض كشفه</h2>
            <p>يظهر الكشف بعد الاختيار: كل فواتيره، والتحصيلات التي قام بها، والرصيد المستحق عليه حاليًا — ويمكن حصر الفواتير والتحصيلات بفترة.</p>
            <a class="btn btn--outline" href="<?= e(url('/distributors')) ?>"><?= icon('users') ?> قائمة الموزعين</a>
        </div>
    </div>
<?php else: ?>
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
                <a class="btn btn--primary no-print" href="<?= e(url('/sales/create')) ?>">إنشاء فاتورة</a>
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
<?php endif; ?>
