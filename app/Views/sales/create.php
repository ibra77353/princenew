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
$currency = $settings['currency_symbol'] ?? 'ر.ي';
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
        </div>

        <hr class="divider">

        <div class="row row--between">
            <h3 class="section-title" style="margin:0"><?= icon('package') ?> إضافة عنصر</h3>
            <button type="button" class="btn btn--outline btn--sm" data-add-row><?= icon('plus') ?> إضافة سطر</button>
        </div>

        <div class="sale-list" data-sale-items>
            <?php for ($i = 0; $i < $rows; $i++): ?>
                <div class="sale-row">
                    <div class="field">
                        <span class="sale-row__label">الباقة</span>
                        <select class="select" name="item_package_id[]" aria-label="الباقة">
                            <option value="">— بدون —</option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?= e((string) $package['id']) ?>" data-price="<?= e(amount($package['price'])) ?>">
                                    <?= e($package['name']) ?> — <?= e(money($package['price'])) ?>
                                    (متاح: <?= e((string) ($stockByPackage[(int) $package['id']] ?? 0)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sale-row__meta">
                        <div class="field">
                            <span class="sale-row__label">عدد الشدات</span>
                            <input class="input" name="item_quantity[]" type="number" min="1" step="1" placeholder="0" aria-label="عدد الشدات">
                        </div>

                        <div class="sale-row__total">
                            <span class="sale-row__label">إجمالي السطر</span>
                            <span class="text-bold"><span data-line-total>0</span> <?= e($currency) ?></span>
                        </div>

                        <button type="button" class="btn btn--soft-danger btn--sm" data-remove-row aria-label="حذف السطر">
                            <?= icon('trash') ?>
                        </button>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <template data-sale-row-template>
            <div class="sale-row" hidden>
                <div class="field">
                    <span class="sale-row__label">الباقة</span>
                    <select class="select" name="item_package_id[]" aria-label="الباقة">
                        <option value="">— بدون —</option>
                        <?php foreach ($packages as $package): ?>
                            <option value="<?= e((string) $package['id']) ?>" data-price="<?= e(amount($package['price'])) ?>">
                                <?= e($package['name']) ?> — <?= e(money($package['price'])) ?>
                                (متاح: <?= e((string) ($stockByPackage[(int) $package['id']] ?? 0)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sale-row__meta">
                    <div class="field">
                        <span class="sale-row__label">عدد الشدات</span>
                        <input class="input" name="item_quantity[]" type="number" min="1" step="1" placeholder="0" aria-label="عدد الشدات">
                    </div>

                    <div class="sale-row__total">
                        <span class="sale-row__label">إجمالي السطر</span>
                        <span class="text-bold"><span data-line-total>0</span> <?= e($currency) ?></span>
                    </div>

                    <button type="button" class="btn btn--soft-danger btn--sm" data-remove-row aria-label="حذف السطر">
                        <?= icon('trash') ?>
                    </button>
                </div>
            </div>
        </template>

        <div class="sale-summary">
            <div>
                <span class="sale-row__label">الإجمالي</span>
                <p class="sale-summary__value sale-summary__value--total"><span data-sale-total>0</span> <?= e($currency) ?></p>
            </div>
            <div class="text-end">
                <span class="sale-row__label">المتبقي بعد الدفع</span>
                <p class="sale-summary__value sale-summary__value--remaining"><span data-sale-remaining>0</span> <?= e($currency) ?></p>
            </div>
        </div>

        <div class="field">
            <label for="sale-paid">المبلغ المدفوع</label>
            <input class="input" id="sale-paid" name="paid_amount" type="number" min="0" step="1" value="<?= e(old('paid_amount', '0')) ?>">
            <span class="hint">في البيع النقدي يُسجَّل الإجمالي كاملًا تلقائيًا.</span>
        </div>

        <div class="field">
            <label for="sale-notes">ملاحظات</label>
            <textarea class="input" id="sale-notes" name="notes" rows="2"><?= e(old('notes')) ?></textarea>
        </div>

        <div class="form-actions sale-actions">
            <a class="btn btn--outline" href="<?= e(url('/sales')) ?>">إلغاء</a>
            <button type="submit" class="btn btn--primary"><?= icon('cart') ?> تأكيد البيع</button>
        </div>
    </form>
<?php endif; ?>
