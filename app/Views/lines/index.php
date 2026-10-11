<?php
/**
 * خطوط الإنترنت.
 * @var array<int,array<string,mixed>> $lines
 * @var array<int,array<string,mixed>> $paidByLine
 */
?>
<div class="page-header">
    <div>
        <h1>خطوط الإنترنت</h1>
        <p>إدارة الخطوط ومزودي الخدمة وتكاليف الاشتراك.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="line-create"><?= icon('plus') ?> خط جديد</button>
</div>

<?php if ($lines === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('wifi') ?></div>
            <h2>لا توجد خطوط</h2>
            <p>أضف أول خط لتسجيل دفعاته.</p>
            <button type="button" class="btn btn--primary" data-modal-open="line-create"><?= icon('plus') ?> إضافة خط</button>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid--cards">
        <?php foreach ($lines as $line): ?>
            <?php $paid = $paidByLine[(int) $line['id']] ?? ['paid' => 0, 'payments' => 0, 'last_payment' => null]; ?>
            <div class="card card--pad">
                <div class="row row--between">
                    <div class="row">
                        <span class="text-primary"><?= icon('wifi') ?></span>
                        <h3 class="text-lg"><?= e($line['name']) ?></h3>
                    </div>
                    <span class="badge <?= $line['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                        <?= $line['status'] === 'active' ? 'نشط' : 'معطّل' ?>
                    </span>
                </div>

                <div class="stack stack--sm mt-2 text-sm">
                    <div class="row row--between"><span class="text-muted text-xs">المزود</span><span><?= e($line['provider'] ?? '—') ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">المعرّف</span><span><?= e($line['identifier'] ?? '—') ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">السرعة</span><span><?= e($line['speed'] ?? '—') ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">تكلفة الاشتراك</span><span class="text-bold"><?= e(money($line['subscription_cost'])) ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">تاريخ الاشتراك</span><span><?= e(fdate($line['subscription_date'])) ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">إجمالي الدفعات</span><span class="text-bold"><?= e(money($paid['paid'])) ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">آخر دفعة</span><span><?= e(fdate($paid['last_payment'])) ?></span></div>
                </div>

                <div class="btn-group mt-3">
                    <a class="btn btn--outline btn--sm" href="<?= e(url('/lines/' . $line['id'])) ?>"><?= icon('history') ?> التفاصيل</a>
                    <button type="button" class="btn btn--outline btn--sm" data-fill="line-edit"
                            data-action="<?= e(url('/lines')) ?>/{id}/update"
                            data-values='<?= e(json_encode([
                                'id' => (int) $line['id'],
                                'name' => $line['name'],
                                'provider' => $line['provider'],
                                'identifier' => $line['identifier'],
                                'speed' => $line['speed'],
                                'subscription_cost' => amount($line['subscription_cost']),
                                'subscription_date' => $line['subscription_date'],
                                'status' => $line['status'],
                                'notes' => $line['notes'],
                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                        <?= icon('edit') ?> تعديل
                    </button>
                    <?php if (is_admin()): // حذف الخط للمدير فقط (مفروض على الخادم أيضًا) ?>
                        <form method="post" action="<?= e(url('/lines/' . $line['id'] . '/delete')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--soft-danger btn--sm"
                                    data-confirm="حذف الخط «<?= e($line['name']) ?>»؟">
                                <?= icon('trash') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $emptyLine = null; ?>
<div class="modal" id="line-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('wifi') ?> خط جديد</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/lines')) ?>" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('lines/form', ['line' => null]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="line-edit" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('edit') ?> تعديل الخط</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/lines')) ?>" id="line-edit-form" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('lines/form', ['line' => null]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>
