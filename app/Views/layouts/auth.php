<?php
/**
 * تخطيط صفحات الدخول والإعداد الأول.
 * @var string $content
 * @var string $title
 * @var array<string,mixed> $settings
 */
$networkName = (string) ($settings['network_name'] ?? 'شبكة البرنس');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <title><?= e($title ?? 'تسجيل الدخول') ?> — <?= e($networkName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-logo"><?= icon('network') ?></div>
        <?php \App\Services\Core\View::partial('partials/flash', ['flashes' => $flashes ?? []]); ?>
        <?= $content ?>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
