<?php
/**
 * إدارة المخزون: المخزون الحالي، سجل الحركات، الإضافة والتسوية.
 * @var array<int,array<string,mixed>> $summary
 * @var array<int,array<string,mixed>> $movements
 * @var float $totalValue
 * @var array<int,array<string,mixed>> $lowStock
 * @var int $threshold
 */
$types = \App\Services\InventoryService::TYPES;
$activeTab = ($_GET['tab'] ?? 'stock') === 'history' ? 'history' : 'stock';
?>
<div class="page-header">
    <div>
        <h1>إدارة المخزون</h1>
        <p>متابعة الكميات وحركة المخزون. تُحفظ تكلفة كل إضافة وقت إنشائها ولا تتغيّر لاحقًا.</p>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn--primary" data-modal-open="inventory-add"><?= icon('plus') ?> إضافة مخزون</button>
        <button type="button" class="btn btn--outline" data-modal-open="inventory-adjust">تسوية المخزون</button>
    </div>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">قيمة المخزون الكلية</span>
        <span class="stat__value"><?= e(money($totalValue)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">عدد الباقات</span>
        <span class="stat__value"><?= e((string) count($summary)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">تنبيهات المخزون المنخفض</span>
        <span class="stat__value"><?= e((string) count($lowStock)) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">حد التنبيه</span>
        <span class="stat__value"><?= e((string) $threshold) ?> شدة</span>
    </div>
</div>

<div class="tabs">
    <a class="tab<?= $activeTab === 'stock' ? ' is-active' : '' ?>" href="<?= e(url('/inventory?tab=stock')) ?>"><?= icon('boxes') ?> المخزون الحالي</a>
    <a class="tab<?= $activeTab === 'history' ? ' is-active' : '' ?>" href="<?= e(url('/inventory?tab=history')) ?>"><?= icon('history') ?> سجل الحركات</a>
</div>

<?php if ($activeTab === 'stock'): ?>
    <?php if ($summary === []): ?>
        <div class="card card--pad">
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon('boxes') ?></div>
                <h2>لا توجد باقات</h2>
                <p>أضف باقات أولًا ثم سجّل كميات الشدات.</p>
                <a class="btn btn--primary" href="<?= e(url('/packages')) ?>">إدارة الباقات</a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid--cards grid--single">
            <?php foreach ($summary as $row): ?>
                <div class="card card--pad">
                    <div class="row">
                        <span style="width:0.75rem;height:0.75rem;border-radius:999px;background:<?= e($row['color'] ?: '#2563eb') ?>"></span>
                        <h3 class="text-lg"><?= e($row['name']) ?></h3>
                    </div>

                    <p class="big-number text-primary mt-2"><?= e((string) $row['current']) ?></p>
                    <p class="text-xs text-muted">الشدات المتبقية</p>

                    <div class="grid grid--auto mt-3 text-center text-xs">
                        <div style="padding:0.4rem;border-radius:var(--radius-sm);background:color-mix(in srgb, var(--tone-green) 10%, transparent)">
                            <p class="text-bold text-success"><?= e((string) $row['added']) ?></p>
                            <p class="text-muted">مضاف</p>
                        </div>
                        <div style="padding:0.4rem;border-radius:var(--radius-sm);background:color-mix(in srgb, var(--tone-red) 10%, transparent)">
                            <p class="text-bold text-danger"><?= e((string) $row['sold']) ?></p>
                            <p class="text-muted">مباع</p>
                        </div>
                        <div style="padding:0.4rem;border-radius:var(--radius-sm);background:var(--primary-soft)">
                            <p class="text-bold"><?= e((string) $row['returned']) ?></p>
                            <p class="text-muted">مرتجع</p>
                        </div>
                    </div>

                    <?php if ((int) $row['adjust_net'] !== 0): ?>
                        <p class="text-xs text-warning mt-2">
                            تسوية: <?= (int) $row['adjust_net'] > 0 ? '+' : '' ?><?= e((string) $row['adjust_net']) ?>
                        </p>
                    <?php endif; ?>

                    <p class="text-xs text-muted mt-1">متوسط التكلفة: <?= e(money($row['average_cost'])) ?></p>
                    <p class="text-xs text-muted">قيمة المخزون: <?= e(money($row['value'])) ?></p>

                    <button type="button" class="btn btn--outline btn--sm btn--block mt-2"
                            data-fill="inventory-adjust"
                            data-values='<?= e(json_encode(['package_id' => (int) $row['package_id'], 'target_quantity' => (int) $row['current']], JSON_UNESCAPED_UNICODE)) ?>'>
                        <?= icon('edit') ?> تعديل
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($movements === []): ?>
        <div class="card card--pad">
            <div class="empty-state">
                <div class="empty-state__icon"><?= icon('history') ?></div>
                <h2>لا توجد حركات</h2>
                <p>ستظهر هنا كل حركات الإضافة والبيع والإرجاع والتسوية.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>الباقة</th>
                        <th>نوع الحركة</th>
                        <th>الكمية</th>
                        <th>تكلفة الشدة</th>
                        <th>السبب</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <?php
                        $isIncrease = in_array($movement['type'], ['add', 'return'], true)
                            || ($movement['type'] === 'adjust' && $movement['direction'] === 'up');
                        ?>
                        <tr>
                            <td><?= e($movement['package_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= $movement['type'] === 'sell' ? 'badge--danger' : ($movement['type'] === 'adjust' ? 'badge--warning' : 'badge--success') ?>">
                                    <?= e($types[$movement['type']] ?? $movement['type']) ?>
                                </span>
                            </td>
                            <td class="<?= $isIncrease ? 'text-success' : 'text-danger' ?> text-bold">
                                <?= $isIncrease ? '+' : '-' ?><?= e((string) $movement['quantity']) ?>
                            </td>
                            <td><?= e(money($movement['unit_cost'])) ?></td>
                            <td class="wrap"><?= e($movement['reason'] ?? '') ?></td>
                            <td><?= e(fdatetime($movement['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- نافذة إضافة مخزون -->
<div class="modal" id="inventory-add" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('plus') ?> إضافة مخزون</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/inventory/add')) ?>" class="stack">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="inv-package">الباقة</label>
                    <select class="select" id="inv-package" name="package_id" required>
                        <option value="">اختر الباقة</option>
                        <?php foreach ($summary as $row): ?>
                            <option value="<?= e((string) $row['package_id']) ?>"><?= e($row['name']) ?> (المخزون: <?= e((string) $row['current']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid--form">
                    <div class="field">
                        <label for="inv-cost">سعر الشدة</label>
                        <input class="input" id="inv-cost" name="unit_price" type="number" min="0" step="1" value="0" required>
                    </div>
                    <div class="field">
                        <label for="inv-quantity">كمية الشدات</label>
                        <input class="input" id="inv-quantity" name="quantity" type="number" min="1" step="1" required>
                    </div>
                </div>
                <div class="field">
                    <label for="inv-notes">ملاحظات</label>
                    <textarea class="input" id="inv-notes" name="notes" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('plus') ?> إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تسوية المخزون -->
<div class="modal" id="inventory-adjust" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('edit') ?> تسوية المخزون</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/inventory/adjust')) ?>" class="stack">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="adj-package">الباقة</label>
                    <select class="select" id="adj-package" name="package_id" required>
                        <option value="">اختر الباقة</option>
                        <?php foreach ($summary as $row): ?>
                            <option value="<?= e((string) $row['package_id']) ?>"><?= e($row['name']) ?> (الحالي: <?= e((string) $row['current']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="adj-target">الكمية الصحيحة بعد الجرد</label>
                    <input class="input" id="adj-target" name="target_quantity" type="number" min="0" step="1" required>
                </div>
                <div class="field">
                    <label for="adj-reason">سبب التسوية</label>
                    <textarea class="input" id="adj-reason" name="reason" rows="2" required></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> تسجيل التسوية</button>
                </div>
            </form>
        </div>
    </div>
</div>
