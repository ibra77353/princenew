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
        <h1>إدارة الموزعين</h1>
        <p>إدارة بيانات الموزعين وكشوف الحسابات.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="distributor-create"><?= icon('plus') ?> موزع جديد</button>
</div>

<form method="get" action="<?= e(url('/distributors')) ?>" class="filters">
    <div class="field field--wide">
        <label for="d-q">بحث بالاسم أو الهاتف</label>
        <input class="input" id="d-q" name="q" type="search" value="<?= e($term) ?>" placeholder="اكتب للبحث...">
    </div>
    <div class="field self-end">
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
    <div class="record-list record-list--auto">
        <?php foreach ($distributors as $distributor): ?>
            <article class="card card--pad record">
                <div class="record__head">
                    <div>
                        <h3 class="record__id"><?= e($distributor['name']) ?></h3>
                        <div class="record__meta">
                            <?php if (!empty($distributor['phone'])): ?>
                                <span><?= icon('phone') ?> <?= e($distributor['phone']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($distributor['address'])): ?>
                                <span><?= icon('pin') ?> <?= e($distributor['address']) ?></span>
                            <?php endif; ?>
                            <span><?= icon('history') ?> <?= e(fdate($distributor['created_at'] ?? null)) ?></span>
                        </div>
                    </div>
                    <span class="badge <?= $distributor['status'] === 'active' ? 'badge--success' : 'badge--danger' ?>">
                        <?= $distributor['status'] === 'active' ? 'نشط' : 'معطّل' ?>
                    </span>
                </div>

                <div class="record__foot">
                    <div class="record__amount <?= (float) $distributor['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= e(money($distributor['balance'])) ?>
                        <span class="record__sub">الرصيد المستحق</span>
                    </div>
                    <div class="btn-group">
                        <a class="btn btn--outline btn--sm" href="<?= e(url('/distributors/' . $distributor['id'])) ?>">
                            <?= icon('receipt') ?> كشف حساب
                        </a>
                        <button type="button" class="btn btn--outline btn--sm" data-fill="distributor-edit"
                                data-action="<?= e(url('/distributors')) ?>/{id}/update"
                                data-values='<?= e(json_encode([
                                    'id' => (int) $distributor['id'],
                                    'name' => $distributor['name'],
                                    'phone' => $distributor['phone'],
                                    'address' => $distributor['address'],
                                    'notes' => $distributor['notes'],
                                    'status' => $distributor['status'],
                                ], JSON_UNESCAPED_UNICODE)) ?>'
                                aria-label="تعديل بيانات الموزع">
                            <?= icon('edit') ?>
                        </button>
                        <?php if (is_admin()): // حذف الموزع للمدير فقط (مفروض على الخادم أيضًا) ?>
                            <form method="post" action="<?= e(url('/distributors/' . $distributor['id'] . '/delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--soft-danger btn--sm"
                                        aria-label="حذف الموزع"
                                        data-confirm="حذف الموزع «<?= e($distributor['name']) ?>»؟">
                                    <?= icon('trash') ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
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
