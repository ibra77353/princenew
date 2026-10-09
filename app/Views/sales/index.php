<?php
/**
 * قائمة المبيعات مع التصفية والإلغاء.
 * @var array<int,array<string,mixed>> $sales
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var int $total
 * @var array<string,float> $totals
 */
$hasFilters = $filters['term'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['status'] !== '';
?>
<div class="page-header">
    <div>
        <h1>المبيعات</h1>
        <p>إنشاء وإدارة فواتير البيع. الأسعار المعتمدة تأتي من قاعدة البيانات.</p>
    </div>
    <a class="btn btn--primary" href="<?= e(url('/sales/create')) ?>"><?= icon('plus') ?> بيع جديد</a>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">عدد الفواتير (المطابقة للتصفية)</span>
        <span class="stat__value"><?= e((string) $total) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي المبيعات النشطة</span>
        <span class="stat__value"><?= e(money($totals['all'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">المدفوع من المبيعات النشطة</span>
        <span class="stat__value text-success"><?= e(money($totals['paid'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">الآجل (المتبقي)</span>
        <span class="stat__value text-warning"><?= e(money($totals['all'] - $totals['paid'])) ?></span>
    </div>
</div>

<form method="get" action="<?= e(url('/sales')) ?>" class="filters">
    <div class="field field--wide">
        <label for="s-q">رقم الفاتورة أو اسم الموزع</label>
        <input class="input" id="s-q" name="q" type="search" value="<?= e($filters['term']) ?>" placeholder="للبحث...">
    </div>
    <div class="field">
        <label for="s-from">من تاريخ</label>
        <input class="input" id="s-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="s-to">إلى تاريخ</label>
        <input class="input" id="s-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
        <label for="s-status">الحالة</label>
        <select class="select" id="s-status" name="status">
            <option value="">الكل</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>نشطة</option>
            <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>ملغاة</option>
        </select>
    </div>
    <div class="field" style="align-self:end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php if ($sales === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('cart') ?></div>
            <h2><?= $hasFilters ? 'لا توجد نتائج مطابقة' : 'لا توجد مبيعات' ?></h2>
            <p><?= $hasFilters ? 'جرّب تعديل معايير التصفية.' : 'ابدأ بإنشاء أول فاتورة بيع.' ?></p>
            <a class="btn btn--primary" href="<?= e(url('/sales/create')) ?>"><?= icon('plus') ?> إنشاء بيع</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>الموزع</th>
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
                    <?php $remaining = (float) $sale['total_amount'] - (float) $sale['paid_amount'] - (float) \App\Services\Core\Database::scalar("SELECT COALESCE(SUM(amount),0) FROM collections WHERE sale_id = ? AND status = 'active'", [$sale['id']]); ?>
                    <tr>
                        <td><a class="text-bold" href="<?= e(url('/sales/' . $sale['id'])) ?>"><?= e($sale['invoice_number']) ?></a></td>
                        <td><?= e($sale['distributor_name']) ?></td>
                        <td><?= e(fdatetime($sale['sale_date'])) ?></td>
                        <td><?= $sale['payment_type'] === 'cash' ? 'نقدي' : 'آجل' ?></td>
                        <td><?= e(money($sale['total_amount'])) ?></td>
                        <td><?= e(money($sale['paid_amount'])) ?></td>
                        <td class="<?= $remaining > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($remaining)) ?></td>
                        <td>
                            <span class="badge <?= $sale['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                                <?= $sale['status'] === 'active' ? 'نشطة' : 'ملغاة' ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn--outline btn--sm" href="<?= e(url('/sales/' . $sale['id'])) ?>"><?= icon('printer') ?> فاتورة</a>
                                <?php if ($sale['status'] === 'active'): ?>
                                    <button type="button" class="btn btn--soft-danger btn--sm" data-fill="sale-cancel"
                                            data-values='<?= e(json_encode([
                                                'sale_id' => (int) $sale['id'],
                                                'invoice_number' => $sale['invoice_number'],
                                                'total_amount' => amount($sale['total_amount']),
                                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                                        <?= icon('x') ?> إلغاء
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
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

<!-- نافذة إلغاء فاتورة -->
<div class="modal" id="sale-cancel" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('alert') ?> إلغاء الفاتورة</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/sales')) ?>" id="sale-cancel-form" class="stack">
                <?= csrf_field() ?>
                <p class="text-sm">
                    سيتم إرجاع كميات الباقات إلى المخزون، وعكس المبالغ المدفوعة والتحصيلات المرتبطة،
                    مع الاحتفاظ بالفاتورة في السجل بحالة «ملغاة».
                </p>
                <div class="field">
                    <label for="cancel-reason">سبب الإلغاء</label>
                    <textarea class="input" id="cancel-reason" name="cancel_reason" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>تراجع</button>
                    <button type="submit" class="btn btn--danger"><?= icon('x') ?> تأكيد الإلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('sale-cancel');
        if (!modal) { return; }
        modal.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-fill="sale-cancel"]');
            if (!trigger) { return; }
            var id = JSON.parse(trigger.getAttribute('data-values') || '{}').sale_id;
            var form = document.getElementById('sale-cancel-form');
            if (form && id) {
                form.action = <?= json_encode(url('/sales')) ?> + '/' + id + '/cancel';
            }
        }, true);
    })();
</script>
