<?php
/**
 * شاشة المستخدمين: إدارة الحسابات والأدوار (مدير فقط).
 * @var array<int,array<string,mixed>> $users
 * @var array<string,int> $counts
 * @var array<string,mixed>|null $currentUser
 */
?>
<div class="page-header">
    <div>
        <h1>المستخدمون</h1>
        <p>إضافة حسابات الموظفين وتعديل أدوارها. الفرض الفعلي للصلاحيات على الخادم في كل المسارات.</p>
    </div>
    <button type="button" class="btn btn--primary" data-modal-open="user-create"><?= icon('plus') ?> مستخدم جديد</button>
</div>

<div class="grid grid--stats mb-4">
    <div class="stat">
        <span class="stat__title">إجمالي المستخدمين</span>
        <span class="stat__value"><?= e((string) $counts['total']) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">المديرون</span>
        <span class="stat__value text-primary"><?= e((string) $counts['admins']) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">الموظفون</span>
        <span class="stat__value"><?= e((string) $counts['employees']) ?></span>
    </div>
    <div class="stat">
        <span class="stat__title">حسابات معطّلة</span>
        <span class="stat__value text-danger"><?= e((string) $counts['disabled']) ?></span>
    </div>
</div>

<?php if ($users === []): ?>
    <div class="card card--pad">
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('users') ?></div>
            <h2>لا يوجد مستخدمون</h2>
            <p>أضف مستخدمًا لبدء العمل.</p>
            <button type="button" class="btn btn--primary" data-modal-open="user-create"><?= icon('plus') ?> إضافة مستخدم</button>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid--cards">
        <?php foreach ($users as $user): ?>
            <?php
            $isAdmin = $user['role'] === 'admin';
            $isActive = $user['status'] === 'active';
            $isSelf = (int) $user['id'] === (int) ($currentUser['id'] ?? 0);
            ?>
            <div class="card card--pad">
                <div class="row row--between">
                    <div class="row">
                        <span class="icon-box"><?= icon($isAdmin ? 'shield' : 'users') ?></span>
                        <h3 class="text-lg"><?= e($user['name']) ?><?= $isSelf ? ' <span class="text-xs text-muted">(حسابك)</span>' : '' ?></h3>
                    </div>
                    <span class="badge <?= $isActive ? 'badge--success' : 'badge--danger' ?>">
                        <?= $isActive ? 'نشط' : 'معطّل' ?>
                    </span>
                </div>

                <div class="row row--wrap mt-2">
                    <span class="badge <?= $isAdmin ? 'badge--primary' : '' ?>"><?= e(role_label($user['role'])) ?></span>
                </div>

                <div class="stack stack--sm mt-2 text-sm">
                    <div class="row row--between"><span class="text-muted text-xs">البريد</span><span><?= e($user['email']) ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">اسم المستخدم</span><span><?= e($user['username'] ?? '—') ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">آخر دخول</span><span><?= e(fdatetime($user['last_login_at'])) ?></span></div>
                    <div class="row row--between"><span class="text-muted text-xs">تاريخ الإضافة</span><span><?= e(fdate($user['created_at'] ?? null)) ?></span></div>
                </div>

                <div class="btn-group mt-3">
                    <button type="button" class="btn btn--outline btn--sm" data-fill="user-edit"
                            data-action="<?= e(url('/users')) ?>/{id}/update"
                            data-values='<?= e(json_encode([
                                'id' => (int) $user['id'],
                                'name' => $user['name'],
                                'email' => $user['email'],
                                'username' => $user['username'],
                                'role' => $user['role'],
                                'status' => $user['status'],
                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                        <?= icon('edit') ?> تعديل
                    </button>

                    <?php if (!$isSelf): ?>
                        <form method="post" action="<?= e(url('/users/' . $user['id'] . '/delete')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--soft-danger btn--sm"
                                    data-confirm="حذف المستخدم «<?= e($user['name']) ?>»؟ لا يمكن التراجع.">
                                <?= icon('trash') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid--two mt-4">
    <section class="card card--pad">
        <h3 class="section-title"><?= icon('shield') ?> صلاحيات المدير</h3>
        <ul class="text-sm text-muted bullets">
            <li>كل وظائف النظام: الباقات، المخزون، الموزعون، المبيعات، التحصيلات، الخطوط، الصندوق والتقارير.</li>
            <li>الإعدادات والنسخ الاحتياطي والاستعادة.</li>
            <li>إدارة المستخدمين وتغيير الأدوار.</li>
            <li>حذف السجلات الحساسة وإلغاء الفواتير.</li>
        </ul>
    </section>

    <section class="card card--pad">
        <h3 class="section-title"><?= icon('users') ?> صلاحيات الموظف</h3>
        <ul class="text-sm text-muted bullets">
            <li>المبيعات النقدية والآجلة، والتحصيلات.</li>
            <li>إدارة المخزون والخطوط ودفعاتها حسب الحاجة التشغيلية.</li>
            <li>ممنوع من الإعدادات والنسخ الاحتياطي وإدارة المستخدمين.</li>
            <li>ممنوع من حذف الباقات والخطوط والموزعين وإلغاء الفواتير.</li>
        </ul>
    </section>
</div>

<!-- نافذة إضافة مستخدم -->
<div class="modal" id="user-create" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('users') ?> مستخدم جديد</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/users')) ?>" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('users/form', ['user' => null, 'isEdit' => false]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ المستخدم</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل مستخدم -->
<div class="modal" id="user-edit" hidden>
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__panel">
        <div class="modal__head">
            <h3><?= icon('edit') ?> تعديل المستخدم</h3>
            <button type="button" class="modal__close" data-modal-close aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <form method="post" action="<?= e(url('/users')) ?>" class="stack">
                <?= csrf_field() ?>
                <?php \App\Services\Core\View::partial('users/form', ['user' => null, 'isEdit' => true]); ?>
                <div class="form-actions">
                    <button type="button" class="btn btn--outline" data-modal-close>إلغاء</button>
                    <button type="submit" class="btn btn--primary"><?= icon('save') ?> حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>
