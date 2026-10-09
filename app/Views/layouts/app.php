<?php
/**
 * التخطيط العام للتطبيق: قائمة جانبية، ترويسة، شريط جوال.
 * @var string $content
 * @var string $title
 * @var array<string,mixed> $settings
 * @var array<string,mixed>|null $currentUser
 */
$networkName = (string) ($settings['network_name'] ?? 'شبكة البرنس');
$alerts = \App\Services\DashboardService::alerts();
$alertsCount = count($alerts['lowStock']) + count($alerts['indebted']);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="نظام إدارة شبكة البرنس — باقات، مخزون، مبيعات، تحصيلات وتقارير">
    <title><?= e($title ?? 'لوحة التحكم') ?> — <?= e($networkName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app">
    <?php \App\Services\Core\View::partial('partials/sidebar', ['settings' => $settings, 'currentUser' => $currentUser, 'alerts' => $alerts]); ?>

    <div class="backdrop" hidden data-backdrop></div>

    <div class="main">
        <?php \App\Services\Core\View::partial('partials/topbar', [
            'settings' => $settings,
            'currentUser' => $currentUser,
            'alerts' => $alerts,
            'alertsCount' => $alertsCount,
        ]); ?>

        <main class="content">
            <?php \App\Services\Core\View::partial('partials/flash', ['flashes' => $flashes ?? []]); ?>
            <?= $content ?>
        </main>
    </div>

    <?php \App\Services\Core\View::partial('partials/mobilebar'); ?>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
