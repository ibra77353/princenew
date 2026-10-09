<?php
/**
 * إدارة الباقات.
 * @var array<int,array<string,mixed>> $packages
 * @var array<int,array<string,mixed>> $stock
 * @var int $threshold
 */
?>
<div class="page-header">
    <div>
        <h1>الباقات</h1>
        <p>سعر الباقة هو سعر بيع الشدة. تعطيل الباقة لا يحذف تاريخ مبيعاتها.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="package-create"><?= icon('plus') ?> باقة جديدة</button>
</div>

<?php if ($packages === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('package') ?></div>
            <h2>لا توجد باقات</h2>
            <p>أضف أول باقة لتبدأ بتسجيل المخزون والمبيعات.</p>
            <button type="button" class="btn btn--primary" data-modal-open="package-create"><?= icon('plus') ?> إضافة باقة</button>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid--cards">
        <?php foreach ($packages as $package): ?>
            <?php $row = $stock[(int) $package['id']] ?? ['current' => 0, 'value' => 0, 'added' => 0, 'sold' => 0, 'returned' => 0, 'adjust_net' => 0]; ?>
            <div class="card card--pad">
                <div class="row row--between">
                    <div class="row">
                        <span style="width:0.75rem;height:0.75rem;border-radius:999px;background:<?= e($package['color']) ?>"></span>
                        <h3 class="text-lg"><?= e($package['name']) ?></h3>
                    </div>
                    <span class="badge <?= $package['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                        <?= $package['status'] === 'active' ? 'مفعّلة' : 'معطّلة' ?>
                    </span>
                </div>

                <p class="big-number text-primary mt-2"><?= e(money($package['price'])) ?></p>
                <p class="text-xs text-muted">سعر الشدة</p>

                <div class="grid grid--cards mt-3 text-center text-xs">
                    <div class="card card--pad" style="padding:0.5rem">
                        <p class="text-bold text-success"><?= e((string) $row['added']) ?></p>
                        <p class="text-muted">مضاف</p>
                    </div>
                    <div class="card card--pad" style="padding:0.5rem">
                        <p class="text-bold text-danger"><?= e((string) $row['sold']) ?></p>
                        <p class="text-muted">مباع</p>
                    </div>
                    <div class="card card--pad" style="padding:0.5rem">
                        <p class="text-bold"><?= e((string) $row['returned']) ?></p>
                        <p class="text-muted">مرتجع</p>
                    </div>
                </div>

                <div class="row row--between mt-3">
                    <span class="text-xs text-muted">المخزون الحالي</span>
                    <span class="badge <?= (int) $row['current'] <= $threshold ? 'badge--danger' : 'badge--success' ?>">
                        <?= e((string) $row['current']) ?> شدة
                    </span>
                </div>
                <div class="row row--between mt-1">
                    <span class="text-xs text-muted">قيمة المخزون</span>
                    <span class="text-xs text-bold"><?= e(money($row['value'])) ?></span>
                </div>

                <div class="text-xs text-muted mt-2">
                    حجم البيانات: <?= e((string) $package['data_mb']) ?> ميغابايت •
                    الساعات: <?= e((string) $package['hours']) ?>
                </div>
                <?php if (!empty($package['description'])): ?>
                    <p class="text-xs text-muted mt-1"><?= e($package['description']) ?></p>
                <?php endif; ?>

                <div class="btn-group mt-3">
                    <button type="button" class="btn btn--outline btn--sm" data-fill="package-edit"
                            data-values='<?= e(json_encode([
                                'id' => (int) $package['id'],
                                'name' => $package['name'],
                                'price' => amount($package['price']),
                                'data_mb' => (int) $package['data_mb'],
                                'hours' => (int) $package['hours'],
                                'color' => $package['color'],
                                'status' => $package['status'],
                                'description' => $package['description'],
                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                        <?= icon('edit') ?> تعديل
                    </button>

                    <form method="post" action="<?= e(url('/packages/' . $package['id'] . '/toggle')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--outline btn--sm">
                            <?= $package['status'] === 'active' ? 'تعطيل' : 'تفعيل' ?>
                        </button>
                    </form>

                    <form method="post" action="<?= e(url('/packages/' . $package['id'] . '/delete')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--soft-danger btn--sm"
                                data-confirm="حذف الباقة «<?= e($package['name']) ?>»؟ لا يمكن التراجع.">
                            <?= icon('trash') ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- نافذة إضافة باقة -->
<div class="modal" id="package-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('package') ?> باقة جديدة</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/packages')) ?>" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('packages/form', ['package' => null]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ الباقة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل باقة -->
<div class="modal" id="package-edit" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('edit') ?> تعديل الباقة</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/packages')) ?>" id="package-edit-form" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="">
                <?php \App\Services\Core\View::partial('packages/form', ['package' => null]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // توجيه نموذج التعديل إلى مسار الباقة المحددة
    (function () {
        var modal = document.getElementById('package-edit');
        if (!modal) { return; }
        modal.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-fill="package-edit"]');
            if (!trigger) { return; }
            var id = JSON.parse(trigger.getAttribute('data-values') || '{}').id;
            var form = document.getElementById('package-edit-form');
            if (form && id) {
                form.action = <?= json_encode(url('/packages')) ?> + '/' + id + '/update';
            }
        }, true);
    })();
</script>
