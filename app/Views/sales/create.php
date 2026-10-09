<?php
/**
 * إنشاء فاتورة بيع جديدة — يعمل بدون JavaScript بأسطر ثابتة، مع إمكانية الإضافة.
 * @var array<int,array<string,mixed>> $distributors
 * @var array<int,array<string,mixed>> $packages
 * @var array<int,array<string,mixed>> $stock
 */
$stockByPackage = [];
foreach ($stock as $row) {
    $stockByPackage[(int) $row['package_id']] = (int) $row['current'];
}
$rows = 4;
?>
<div class="page-header">
    <div>
        <h1>عملية بيع جديدة</h1>
        <p>تُحسب الأسعار والإجماليات على الخادم، ويُتحقق من المخزون قبل الحفظ.</p>
    </div>
    <a class="btn btn--outline" href="<?= e(url('/sales')) ?>">رجوع للمبيعات</a>
</div>

<?php if ($distributors === [] || $packages === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('alert') ?></div>
            <h2>لا يمكن إنشاء فاتورة الآن</h2>
            <p>تحتاج إلى موزع نشط وباقة مفعّلة على الأقل.</p>
            <div class="btn-group">
                <a class="btn btn--outline" href="<?= e(url('/distributors')) ?>">إدارة الموزعين</a>
                <a class="btn btn--primary" href="<?= e(url('/packages')) ?>">إدارة الباقات</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url('/sales')) ?>" class="card card--pad stack" data-sale-form data-allow-repeat>
        <?= csrf_field() ?>

        <div class="grid grid--form">
            <div class="field">
                <label for="sale-distributor">الموزع</label>
                <select class="select" id="sale-distributor" name="distributor_id" required>
                    <option value="">اختر الموزع</option>
                    <?php foreach ($distributors as $distributor): ?>
                        <option value="<?= e((string) $distributor['id']) ?>" <?= (string) old('distributor_id') === (string) $distributor['id'] ? 'selected' : '' ?>>
                            <?= e($distributor['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="sale-date">تاريخ البيع</label>
                <input class="input" id="sale-date" name="sale_date" type="date" value="<?= e(old('sale_date', today())) ?>" required>
            </div>

            <div class="field">
                <label for="sale-type">نوع البيع</label>
                <select class="select" id="sale-type" name="payment_type">
                    <option value="cash" <?= old('payment_type') === 'cash' ? 'selected' : '' ?>>نقدي</option>
                    <option value="credit" <?= old('payment_type') === 'credit' ? 'selected' : '' ?>>آجل</option>
                </select>
            </div>

            <div class="field">
                <label for="sale-paid">المبلغ المدفوع (للبيع الآجل)</label>
                <input class="input" id="sale-paid" name="paid_amount" type="number" min="0" step="1" value="<?= e(old('paid_amount', '0')) ?>">
                <span class="hint">في البيع النقدي يُسجَّل الإجمالي كاملًا تلقائيًا.</span>
            </div>
        </div>

        <hr class="divider">

        <div class="row row--between">
            <h3 class="section-title" style="margin:0"><?= icon('package') ?> عناصر الفاتورة</h3>
            <button type="button" class="btn btn--outline btn--sm" data-add-row><?= icon('plus') ?> إضافة سطر</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:14rem">الباقة</th>
                        <th style="min-width:7rem">عدد الشدات</th>
                        <th style="min-width:8rem">إجمالي السطر</th>
                        <th style="min-width:5rem"></th>
                    </tr>
                </thead>
                <tbody data-sale-items>
                    <?php for ($i = 0; $i < $rows; $i++): ?>
                        <tr class="sale-row">
                            <td>
                                <select class="select" name="item_package_id[]">
                                    <option value="">— بدون —</option>
                                    <?php foreach ($packages as $package): ?>
                                        <option value="<?= e((string) $package['id']) ?>" data-price="<?= e(amount($package['price'])) ?>">
                                            <?= e($package['name']) ?> — <?= e(money($package['price'])) ?>
                                            (متاح: <?= e((string) ($stockByPackage[(int) $package['id']] ?? 0)) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="input" name="item_quantity[]" type="number" min="1" step="1" placeholder="0"></td>
                            <td><span data-line-total class="text-bold">0</span> <span class="text-xs text-muted"><?= e($settings['currency_symbol'] ?? 'ر.ي') ?></span></td>
                            <td>
                                <button type="button" class="btn btn--soft-danger btn--sm" data-remove-row aria-label="حذف السطر">
                                    <?= icon('trash') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>الإجمالي</td>
                        <td></td>
                        <td><span data-sale-total class="text-bold">0</span> <?= e($settings['currency_symbol'] ?? 'ر.ي') ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>المتبقي بعد الدفع</td>
                        <td></td>
                        <td><span data-sale-remaining class="text-bold">0</span> <?= e($settings['currency_symbol'] ?? 'ر.ي') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <template data-sale-row-template>
            <tr class="sale-row" hidden>
                <td>
                    <select class="select" name="item_package_id[]">
                        <option value="">— بدون —</option>
                        <?php foreach ($packages as $package): ?>
                            <option value="<?= e((string) $package['id']) ?>" data-price="<?= e(amount($package['price'])) ?>">
                                <?= e($package['name']) ?> — <?= e(money($package['price'])) ?>
                                (متاح: <?= e((string) ($stockByPackage[(int) $package['id']] ?? 0)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input class="input" name="item_quantity[]" type="number" min="1" step="1" placeholder="0"></td>
                <td><span data-line-total class="text-bold">0</span></td>
                <td>
                    <button type="button" class="btn btn--soft-danger btn--sm" data-remove-row aria-label="حذف السطر">
                        <?= icon('trash') ?>
                    </button>
                </td>
            </tr>
        </template>

        <div class="field">
            <label for="sale-notes">ملاحظات</label>
            <textarea class="input" id="sale-notes" name="notes" rows="2"><?= e(old('notes')) ?></textarea>
        </div>

        <div class="form-actions">
            <a class="btn btn--outline" href="<?= e(url('/sales')) ?>">إلغاء</a>
            <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ الفاتورة</button>
        </div>
    </form>
<?php endif; ?>
