<?php
/**
 * قائمة الموزعين مع الأرصدة المستحقة.
 * @var array<int,array<string,mixed>> $distributors
 * @var string $term
 * @var float $totalDebt
 */
?>
<div class="page-header">
    <div>
        <h1>الموزعون</h1>
        <p>إدارة بيانات الموزعين ومتابعة الأرصدة المستحقة.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="distributor-create"><?= icon('plus') ?> موزع جديد</button>
</div>

<form method="get" action="<?= e(url('/distributors')) ?>" class="filters">
    <div class="field field--wide">
        <label for="d-q">بحث بالاسم أو الهاتف</label>
        <input class="input" id="d-q" name="q" type="search" value="<?= e($term) ?>" placeholder="اكتب للبحث...">
    </div>
    <div class="field" style="align-self:end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> بحث</button>
    </div>
</form>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">عدد الموزعين المعروضين</span>
        <span class="stat__value"><?= e((string) count($distributors)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">إجمالي الديون المستحقة</span>
        <span class="stat__value text-danger"><?= e(money($totalDebt)) ?></span>
    </div>
</div>

<?php if ($distributors === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('users') ?></div>
            <h2><?= $term !== '' ? 'لا توجد نتائج مطابقة' : 'لا يوجد موزعون' ?></h2>
            <p><?= $term !== '' ? 'جرّب كلمة بحث أخرى.' : 'أضف أول موزع لتبدأ بتسجيل المبيعات والتحصيلات.' ?></p>
            <button type="button" class="btn btn--primary" data-modal-open="distributor-create"><?= icon('plus') ?> إضافة موزع</button>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الهاتف</th>
                    <th>المبيعات النشطة</th>
                    <th>المدفوع عند البيع</th>
                    <th>التحصيلات</th>
                    <th>الرصيد المستحق</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($distributors as $distributor): ?>
                    <tr>
                        <td>
                            <a class="text-bold" href="<?= e(url('/distributors/' . $distributor['id'])) ?>">
                                <?= e($distributor['name']) ?>
                            </a>
                        </td>
                        <td><?= e($distributor['phone'] ?? '—') ?></td>
                        <td><?= e(money($distributor['sales_total'])) ?></td>
                        <td><?= e(money($distributor['sales_paid'])) ?></td>
                        <td><?= e(money($distributor['collected'])) ?></td>
                        <td class="<?= (float) $distributor['balance'] > 0 ? 'text-danger text-bold' : 'text-success' ?>">
                            <?= e(money($distributor['balance'])) ?>
                        </td>
                        <td>
                            <span class="badge <?= $distributor['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                                <?= $distributor['status'] === 'active' ? 'نشط' : 'معطّل' ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn--outline btn--sm" data-fill="distributor-edit"
                                        data-values='<?= e(json_encode([
                                            'id' => (int) $distributor['id'],
                                            'name' => $distributor['name'],
                                            'phone' => $distributor['phone'],
                                            'address' => $distributor['address'],
                                            'notes' => $distributor['notes'],
                                            'status' => $distributor['status'],
                                        ], JSON_UNESCAPED_UNICODE)) ?>'>
                                    <?= icon('edit') ?>
                                </button>
                                <form method="post" action="<?= e(url('/distributors/' . $distributor['id'] . '/delete')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--soft-danger btn--sm"
                                            data-confirm="حذف الموزع «<?= e($distributor['name']) ?>»؟">
                                        <?= icon('trash') ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- نافذة إضافة موزع -->
<div class="modal" id="distributor-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('users') ?> موزع جديد</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/distributors')) ?>" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('distributors/form', ['distributor' => null]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل موزع -->
<div class="modal" id="distributor-edit" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('edit') ?> تعديل بيانات الموزع</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/distributors')) ?>" id="distributor-edit-form" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('distributors/form', ['distributor' => null]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('distributor-edit');
        if (!modal) { return; }
        modal.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-fill="distributor-edit"]');
            if (!trigger) { return; }
            var id = JSON.parse(trigger.getAttribute('data-values') || '{}').id;
            var form = document.getElementById('distributor-edit-form');
            if (form && id) {
                form.action = <?= json_encode(url('/distributors')) ?> + '/' + id + '/update';
            }
        }, true);
    })();
</script>
