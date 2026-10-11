<?php
/**
 * التقارير: مبيعات، تحصيلات، ديون، مصروفات، دفعات خطوط، سحوبات، صندوق، ومخزون.
 * @var array<string,mixed> $report
 * @var array<string,mixed> $filters
 * @var string $period
 * @var array<int,array<string,mixed>> $distributors
 * @var array<int,array<string,mixed>> $packages
 */
$categories = \App\Models\Expense::CATEGORIES;
$sources = \App\Models\CashTransaction::SOURCES;
$periods = [
    'today' => 'اليوم',
    'week' => 'آخر 7 أيام',
    'month' => 'هذا الشهر',
    'year' => 'هذه السنة',
    'custom' => 'فترة مخصصة',
];
$sales = $report['sales'];
$cash = $report['cash'];
?>
<div class="page-header">
    <div>
        <h1>التقارير</h1>
        <p>الإجماليات محسوبة من كل السجلات المطابقة للفترة، والمبيعات مفصولة عن التدفق النقدي.</p>
    </div>
    <button type="button" class="btn btn--outline no-print" data-print><?= icon('printer') ?> طباعة التقرير</button>
</div>

<form method="get" action="<?= e(url('/reports')) ?>" class="filters no-print">
    <div class="field">
        <label for="r-period">الفترة</label>
        <select class="select" id="r-period" name="period">
            <?php foreach ($periods as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="r-from">من تاريخ</label>
        <input class="input" id="r-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="r-to">إلى تاريخ</label>
        <input class="input" id="r-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
        <label for="r-dist">الموزع</label>
        <select class="select" id="r-dist" name="distributor_id">
            <option value="">كل الموزعين</option>
            <?php foreach ($distributors as $distributor): ?>
                <option value="<?= e((string) $distributor['id']) ?>" <?= (int) $filters['distributor_id'] === (int) $distributor['id'] ? 'selected' : '' ?>>
                    <?= e($distributor['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="r-package">الباقة</label>
        <select class="select" id="r-package" name="package_id">
            <option value="">كل الباقات</option>
            <?php foreach ($packages as $package): ?>
                <option value="<?= e((string) $package['id']) ?>" <?= (int) $filters['package_id'] === (int) $package['id'] ? 'selected' : '' ?>>
                    <?= e($package['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="r-status">حالة المبيعات</label>
        <select class="select" id="r-status" name="status">
            <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>النشطة فقط</option>
            <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>الملغاة فقط</option>
            <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>النشطة والملغاة</option>
        </select>
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--primary"><?= icon('chart') ?> عرض التقرير</button>
    </div>
</form>

<?php \App\Services\Core\View::partial('partials/filter-warning', ['rangeError' => $rangeError ?? '']); ?>

<p class="text-sm text-muted mb-4">
    الفترة: <?= $filters['from'] !== '' ? e(fdate($filters['from'])) : 'من البداية' ?>
    — <?= $filters['to'] !== '' ? e(fdate($filters['to'])) : 'حتى اليوم' ?>
</p>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">إجمالي المبيعات</span>
        <span class="stat__value"><?= e(money($sales['totals']['total'])) ?></span>
        <span class="text-xs text-muted"><?= e((string) $sales['totals']['invoices']) ?> فاتورة</span>
    </div>
    <div class="stat">
        <span class="stat__title">مبيعات آجلة (غير محصّلة)</span>
        <span class="stat__value text-warning"><?= e(money($sales['totals']['credit'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">التحصيلات الصالحة</span>
        <span class="stat__value text-success"><?= e(money($report['collections']['total'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">ديون الموزعين الحالية</span>
        <span class="stat__value text-danger"><?= e(money($report['debts']['total'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">المصروفات</span>
        <span class="stat__value text-danger"><?= e(money($report['expenses']['total'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">دفعات الخطوط</span>
        <span class="stat__value text-danger"><?= e(money($report['linePayments']['total'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">سحوبات المالك</span>
        <span class="stat__value text-danger"><?= e(money($report['withdrawals']['total'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">صافي التدفق النقدي (الفترة)</span>
        <span class="stat__value <?= $cash['net'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($cash['net'])) ?></span>
    </div>
</div>

<section class="mb-4">
    <h3 class="section-title"><?= icon('cart') ?> المبيعات</h3>
    <?php if ($sales['rows'] === []): ?>
        <div class="card card--pad"><div class="empty-state"><p>لا توجد مبيعات في هذه الفترة.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>الموزع</th><th>النوع</th><th>الإجمالي</th><th>المدفوع</th></tr></thead>
                <tbody>
                    <?php foreach ($sales['rows'] as $row): ?>
                        <tr>
                            <td><?= e($row['invoice_number']) ?></td>
                            <td><?= e(fdatetime($row['sale_date'])) ?></td>
                            <td><?= e($row['distributor_name']) ?></td>
                            <td><?= $row['payment_type'] === 'cash' ? 'نقدي' : 'آجل' ?></td>
                            <td><?= e(money($row['total_amount'])) ?></td>
                            <td><?= e(money($row['paid_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">الإجمالي</td>
                        <td><?= e(money($sales['totals']['total'])) ?></td>
                        <td><?= e(money($sales['totals']['paid'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="mb-4">
    <h3 class="section-title"><?= icon('package') ?> المبيعات حسب الباقة</h3>
    <?php if ($report['salesByPackage'] === []): ?>
        <div class="card card--pad"><div class="empty-state"><p>لا توجد بيانات.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>الباقة</th><th>عدد الشدات المباعة</th><th>الإجمالي</th></tr></thead>
                <tbody>
                    <?php foreach ($report['salesByPackage'] as $row): ?>
                        <tr>
                            <td><?= e($row['package_name']) ?></td>
                            <td><?= e((string) $row['quantity']) ?></td>
                            <td><?= e(money($row['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="mb-4">
    <h3 class="section-title"><?= icon('coins') ?> التحصيلات</h3>
    <?php if ($report['collections']['rows'] === []): ?>
        <div class="card card--pad"><div class="empty-state"><p>لا توجد تحصيلات في هذه الفترة.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>التاريخ</th><th>الموزع</th><th>الفاتورة</th><th>المبلغ</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php foreach ($report['collections']['rows'] as $row): ?>
                        <tr>
                            <td><?= e(fdatetime($row['collection_date'])) ?></td>
                            <td><?= e($row['distributor_name'] ?? '—') ?></td>
                            <td><?= e($row['invoice_number'] ?? 'عام') ?></td>
                            <td><?= e(money($row['amount'])) ?></td>
                            <td><?= $row['status'] === 'active' ? 'صالحة' : 'معكوسة' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="grid grid--two mb-4">
    <section class="card card--pad">
        <h3 class="section-title"><?= icon('users') ?> الديون المستحقة</h3>
        <?php if ($report['debts']['rows'] === []): ?>
            <div class="empty-state"><p>لا توجد ديون مستحقة.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>الموزع</th><th>الهاتف</th><th>الرصيد</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['debts']['rows'] as $row): ?>
                            <tr>
                                <td><?= e($row['name']) ?></td>
                                <td><?= e($row['phone'] ?? '—') ?></td>
                                <td class="text-danger text-bold"><?= e(money($row['balance'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="2">الإجمالي</td><td><?= e(money($report['debts']['total'])) ?></td></tr></tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card card--pad">
        <h3 class="section-title"><?= icon('receipt') ?> المصروفات حسب التصنيف</h3>
        <?php if ($report['expenses']['byCategory'] === []): ?>
            <div class="empty-state"><p>لا توجد مصروفات في هذه الفترة.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>التصنيف</th><th>العدد</th><th>الإجمالي</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['expenses']['byCategory'] as $row): ?>
                            <tr>
                                <td><?= e($categories[$row['category']] ?? $row['category']) ?></td>
                                <td><?= e((string) $row['count']) ?></td>
                                <td><?= e(money($row['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="2">الإجمالي</td><td><?= e(money($report['expenses']['total'])) ?></td></tr></tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="mb-4">
    <h3 class="section-title"><?= icon('chart') ?> التدفق النقدي حسب المصدر (بدون الملغاة)</h3>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>المصدر</th><th>مقبوضات</th><th>مدفوعات</th><th>الصافي</th></tr></thead>
            <tbody>
                <?php foreach ($cash['bySource'] as $row): ?>
                    <?php $net = (float) $row['cash_in'] - (float) $row['cash_out']; ?>
                    <tr>
                        <td><?= e($sources[$row['source']] ?? $row['source']) ?></td>
                        <td class="text-success"><?= e(money($row['cash_in'])) ?></td>
                        <td class="text-danger"><?= e(money($row['cash_out'])) ?></td>
                        <td class="text-bold"><?= e(money($net)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>الإجمالي</td>
                    <td><?= e(money($cash['in'])) ?></td>
                    <td><?= e(money($cash['out'])) ?></td>
                    <td><?= e(money($cash['net'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="hint mt-2">
        رصيد الصندوق الحالي: <?= e(money($cash['balance'])) ?> — أرقام التدفق تستبعد أثر الفواتير
        والتحصيلات الملغاة والمعكوسة (كشاشة الصندوق ولوحة التحكم)، وكل الحركات الأصلية والعكسية
        تبقى في سجل الصندوق وسجل التدقيق بلا حذف.
    </p>
</section>

<div class="grid grid--two">
    <section class="card card--pad">
        <h3 class="section-title"><?= icon('credit-card') ?> دفعات الخطوط</h3>
        <?php if ($report['linePayments']['rows'] === []): ?>
            <div class="empty-state"><p>لا توجد دفعات في هذه الفترة.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>الخط</th><th>المبلغ</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['linePayments']['rows'] as $row): ?>
                            <tr>
                                <td><?= e($row['line_name'] ?? '—') ?></td>
                                <td><?= e(money($row['amount'])) ?></td>
                                <td><?= e(fdatetime($row['payment_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="2">الإجمالي</td><td><?= e(money($report['linePayments']['total'])) ?></td></tr></tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card card--pad">
        <h3 class="section-title"><?= icon('boxes') ?> المخزون وقيمته</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>الباقة</th><th>المخزون الحالي</th><th>متوسط التكلفة</th><th>القيمة</th></tr></thead>
                <tbody>
                    <?php foreach ($report['inventory']['rows'] as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e((string) $row['current']) ?></td>
                            <td><?= e(money($row['average_cost'])) ?></td>
                            <td><?= e(money($row['value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">إجمالي قيمة المخزون (<?= e((string) $report['inventory']['total_units']) ?> شدة)</td>
                        <td><?= e(money($report['inventory']['total_value'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>
