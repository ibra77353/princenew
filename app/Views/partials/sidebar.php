<?php
/**
 * القائمة الجانبية — التنقّل الرئيسي للنظام.
 * @var array<string,mixed> $settings
 * @var array<string,mixed>|null $currentUser
 */
$nav = [
    ['/', 'الرئيسية', 'dashboard'],
    ['/packages', 'الباقات', 'package'],
    ['/inventory', 'المخزون', 'boxes'],
    ['/distributors', 'الموزعون', 'users'],
    ['/sales', 'المبيعات', 'cart'],
    ['/collections', 'التحصيلات', 'coins'],
    ['/lines', 'الخطوط', 'wifi'],
    ['/line-payments', 'دفعات الخطوط', 'credit-card'],
    ['/expenses', 'المصروفات', 'receipt'],
    ['/owner-withdrawals', 'سحوبات المالك', 'wallet'],
    ['/cash', 'الصندوق', 'chart'],
    ['/reports', 'التقارير', 'chart'],
    ['/search', 'البحث', 'search'],
    ['/audit', 'سجل التدقيق', 'history'],
];

// عناصر المدير فقط (الإعدادات والنسخ الاحتياطي وإدارة المستخدمين) — الفرض الفعلي على الخادم لا في الواجهة.
$adminNav = [
    ['/users', 'المستخدمون', 'shield'],
    ['/settings', 'الإعدادات', 'settings'],
    ['/backup', 'النسخ الاحتياطي', 'database'],
];

// شاشات المالية/التقارير وسجل التدقيق للمدير فقط، فتُخفى تمامًا من قائمة الموظف (والمسارات ترد 403 له).
$adminOnlyPaths = ['/expenses', '/owner-withdrawals', '/reports', '/audit'];

if (is_admin()) {
    $nav = array_merge($nav, $adminNav);
} else {
    $nav = array_values(array_filter(
        $nav,
        static fn (array $item): bool => !in_array($item[0], $adminOnlyPaths, true)
    ));
}
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar__brand">
        <div class="sidebar__logo"><?= icon('network') ?></div>
        <div>
            <div class="sidebar__title"><?= e($settings['network_name'] ?? 'شبكة البرنس') ?></div>
            <div class="sidebar__subtitle">نظام إدارة الشبكة</div>
        </div>
    </div>

    <nav class="sidebar__nav">
        <?php foreach ($nav as [$path, $label, $iconName]): ?>
            <a class="nav-item<?= is_active_path($path) ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>">
                <?= icon($iconName) ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
        <div class="sidebar__user">
            <?= e($currentUser['name'] ?? '') ?><br>
            <span class="opacity-70"><?= e($currentUser['email'] ?? '') ?></span>
        </div>
        <button type="button" class="btn btn--ghost btn--on-dark btn--block" data-theme-toggle>
            <span data-theme-icon>☾</span>
            <span data-theme-label>الوضع الداكن</span>
        </button>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--on-dark btn--block">
                <?= icon('logout') ?>
                <span>تسجيل الخروج</span>
            </button>
        </form>
    </div>
</aside>
