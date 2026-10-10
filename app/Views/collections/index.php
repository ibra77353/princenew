<?php
/**
 * التحصيلات: تسجيل تحصيل عام أو مرتبط بفاتورة.
 * @var array<int,array<string,mixed>> $collections
 * @var array<int,array<string,mixed>> $distributors
 * @var array<int,array<string,mixed>> $outstanding
 * @var array<string,mixed> $filters
 * @var array{page:int,pages:int} $pager
 * @var array<string,float> $totals
 */
?>
<div class="page-header">
    <div>
        <h1>التحصيلات</h1>
        <p>تُتحقق المبالغ من الدين المستحق على الخادم قبل القبول.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="collection-create"><?= icon('plus') ?> تحصيل جديد</button>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">إجمالي التحصيلات الصالحة</span>
        <span class="stat__value text-success"><?= e(money($totals['valid'])) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">تحصيلات معكوسة (فواتير ملغاة)</span>
        <span class="stat__value text-danger"><?= e(money($totals['reversed'])) ?></span>
    </div>
</div>

<form method="get" action="<?= e(url('/collections')) ?>" class="filters">
    <div class="field field--wide">
        <label for="c-dist">الموزع</label>
        <select class="select" id="c-dist" name="distributor_id">
            <option value="">كل الموزعين</option>
            <?php foreach ($distributors as $distributor): ?>
                <option value="<?= e((string) $distributor['id']) ?>" <?= (int) $filters['distributor_id'] === (int) $distributor['id'] ? 'selected' : '' ?>>
                    <?= e($distributor['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="c-from">من تاريخ</label>
        <input class="input" id="c-from" name="from" type="date" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="c-to">إلى تاريخ</label>
        <input class="input" id="c-to" name="to" type="date" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field self-end">
        <button type="submit" class="btn btn--outline"><?= icon('search') ?> تصفية</button>
    </div>
</form>

<?php if ($collections === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('coins') ?></div>
            <h2>لا توجد تحصيلات</h2>
            <p>سجّل أول تحصيل من موزع.</p>
            <button type="button" class="btn btn--primary" data-modal-open="collection-create"><?= icon('plus') ?> تسجيل تحصيل</button>
        </div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>الموزع</th>
                    <th>الفاتورة</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                    <th>ملاحظات</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $collection): ?>
                    <?php $isReversed = $collection['status'] === 'reversed' || ($collection['sale_status'] ?? null) === 'cancelled'; ?>
                    <tr>
                        <td><?= e($collection['distributor_name'] ?? '—') ?></td>
                        <td><?= e($collection['invoice_number'] ?? 'عام') ?></td>
                        <td class="<?= $isReversed ? 'text-muted' : 'text-success' ?> text-bold">
                            <?= $isReversed ? '' : '+' ?><?= e(money($collection['amount'])) ?>
                        </td>
                        <td><?= e(fdatetime($collection['collection_date'])) ?></td>
                        <td class="wrap"><?= e($collection['notes'] ?? '') ?></td>
                        <td>
                            <?php if ($isReversed): ?>
                                <span class="badge badge--danger">فاتورة ملغاة</span>
                            <?php else: ?>
                                <span class="badge badge--success">صالحة</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php \App\Services\Core\View::partial('partials/pagination', [
        'pager' => $pager,
        'query' => array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 0),
    ]); ?>
<?php endif; ?>

<!-- نافذة تسجيل تحصيل -->
<div class="modal" id="collection-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('coins') ?> تسجيل تحصيل</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/collections')) ?>" class="stack"
                  data-collections-form
                  data-outstanding-url="<?= e(url('/collections/outstanding')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="col-distributor">الموزع</label>
                    <select class="select" id="col-distributor" name="distributor_id" required>
                        <option value="">اختر الموزع</option>
                        <?php foreach ($distributors as $distributor): ?>
                            <option value="<?= e((string) $distributor['id']) ?>"><?= e($distributor['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="col-sale">الفاتورة (اختياري)</label>
                    <select class="select" id="col-sale" name="sale_id">
                        <option value="">تحصيل عام (بدون فاتورة)</option>
                        <?php foreach ($outstanding as $sale): ?>
                            <option value="<?= e((string) $sale['id']) ?>">
                                <?= e($sale['invoice_number']) ?> — متبقي <?= e(money($sale['remaining'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint" data-balance-hint></span>
                </div>

                <div class="grid grid--form">
                    <div class="field">
                        <label for="col-amount">المبلغ</label>
                        <input class="input" id="col-amount" name="amount" type="number" min="1" step="1" required>
                    </div>
                    <div class="field">
                        <label for="col-date">تاريخ التحصيل</label>
                        <input class="input" id="col-date" name="collection_date" type="date" value="<?= e(today()) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="col-notes">ملاحظات</label>
                    <textarea class="input" id="col-notes" name="notes" rows="2"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> تأكيد التحصيل</button>
                </div>
            </form>
        </div>
    </div>
</div>
