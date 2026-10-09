<?php
/**
 * عرض الفاتورة وتفاصيلها مع الطباعة والمشاركة.
 * @var array<string,mixed> $sale
 * @var float $remaining
 * @var array<string,mixed> $settings
 */
$symbol = (string) ($settings['currency_symbol'] ?? 'ر.ي');
$isActive = $sale['status'] === 'active';
?>
<div class="page-header no-print">
    <div>
        <h1>الفاتورة <?= e($sale['invoice_number']) ?></h1>
        <p><?= e($sale['distributor_name']) ?> • <?= e(fdatetime($sale['sale_date'])) ?></p>
    </div>
    <div class="btn-group">
        <a class="btn btn--outline" href="<?= e(url('/sales')) ?>">رجوع</a>
        <button type="button" class="btn btn--outline" data-print><?= icon('printer') ?> طباعة</button>
        <button type="button" class="btn btn--primary"
                data-whatsapp
                data-phone="<?= e($sale['distributor_phone'] ?? '') ?>">
            مشاركة واتساب
        </button>
    </div>
</div>

<?php if (!$isActive): ?>
    <div class="alert alert--error no-print">
        <?= icon('alert') ?>
        <span>هذه الفاتورة ملغاة<?= !empty($sale['cancel_reason']) ? ' — ' . e($sale['cancel_reason']) : '' ?> (<?= e(fdatetime($sale['cancelled_at'])) ?>).</span>
    </div>
<?php endif; ?>

<div class="invoice print-area" data-invoice>
    <div class="invoice__head">
        <div class="row">
            <div class="sidebar__logo" style="background:var(--primary)"><?= icon('network') ?></div>
            <div>
                <h2><?= e($settings['network_name'] ?? 'شبكة البرنس') ?></h2>
                <p class="text-xs text-muted">نظام إدارة شبكة الإنترنت</p>
            </div>
        </div>
        <div class="text-end">
            <p class="text-bold">فاتورة <?= e($sale['invoice_number']) ?></p>
            <p class="text-xs text-muted"><?= e(fdatetime($sale['sale_date'])) ?></p>
            <span class="badge <?= $isActive ? 'badge--success' : 'badge--danger' ?>">
                <?= $isActive ? 'نشطة' : 'ملغاة' ?>
            </span>
        </div>
    </div>

    <dl class="invoice__meta">
        <div>
            <dt>الموزع</dt>
            <dd><?= e($sale['distributor_name']) ?></dd>
        </div>
        <div>
            <dt>الهاتف</dt>
            <dd><?= e($sale['distributor_phone'] ?? '—') ?></dd>
        </div>
        <div>
            <dt>نوع البيع</dt>
            <dd><?= $sale['payment_type'] === 'cash' ? 'نقدي' : 'آجل' ?></dd>
        </div>
        <div>
            <dt>عدد العناصر</dt>
            <dd><?= e((string) count($sale['items'])) ?></dd>
        </div>
    </dl>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>الباقة</th>
                    <th>عدد الشدات</th>
                    <th>سعر الشدة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sale['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['package_name'] ?? '—') ?></td>
                        <td><?= e((string) $item['quantity']) ?></td>
                        <td><?= e(money($item['unit_price'])) ?></td>
                        <td><?= e(money($item['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="invoice__totals">
        <div><span>الإجمالي</span><span><?= e(money($sale['total_amount'])) ?></span></div>
        <div><span>المدفوع عند البيع</span><span><?= e(money($sale['paid_amount'])) ?></span></div>
        <div class="grand"><span>المتبقي</span><span><?= e(money($remaining)) ?></span></div>
    </div>

    <?php if (!empty($sale['notes'])): ?>
        <p class="text-xs text-muted mt-3">ملاحظات: <?= e($sale['notes']) ?></p>
    <?php endif; ?>

    <?php if ($sale['collections'] !== []): ?>
        <hr class="divider">
        <p class="text-xs text-bold mb-2">التحصيلات المرتبطة بهذه الفاتورة</p>
        <ul class="result-list">
            <?php foreach ($sale['collections'] as $collection): ?>
                <li>
                    <span><?= e(fdatetime($collection['collection_date'])) ?></span>
                    <span class="row">
                        <span class="<?= $collection['status'] === 'active' ? 'text-success' : 'text-muted' ?>">
                            <?= e(money($collection['amount'])) ?>
                        </span>
                        <span class="badge <?= $collection['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                            <?= $collection['status'] === 'active' ? 'صالحة' : 'معكوسة' ?>
                        </span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if ($isActive): ?>
    <div class="card card--pad mt-4 no-print" style="max-width:44rem;margin-inline:auto">
        <h3 class="section-title"><?= icon('alert') ?> إلغاء الفاتورة</h3>
        <p class="text-sm text-muted mb-2">
            الإلغاء يُرجع الكميات إلى المخزون ويعكس المدفوعات والتحصيلات المرتبطة،
            دون حذف أي سجل تاريخي.
        </p>
        <form method="post" action="<?= e(url('/sales/' . $sale['id'] . '/cancel')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="field">
                <label for="cancel-reason">سبب الإلغاء</label>
                <input class="input" id="cancel-reason" name="cancel_reason" type="text">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--danger"
                        data-confirm="تأكيد إلغاء الفاتورة <?= e($sale['invoice_number']) ?>؟">
                    <?= icon('x') ?> إلغاء الفاتورة
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>
