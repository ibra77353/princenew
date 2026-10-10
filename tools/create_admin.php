<?php

declare(strict_types=1);

/**
 * أداة إنشاء أو تحديث حساب مدير — تعمل من سطر الأوامر فقط.
 *
 * الاستخدام:
 *   php tools/create_admin.php "الاسم" user@example.com "كلمة المرور" [اسم_المستخدم]
 *   php tools/create_admin.php --password-only user@example.com "كلمة المرور الجديدة"
 *   php tools/create_admin.php (بدون وسائط) → إنشاء مدير بكلمة مرور عشوائية تُطبع مرة واحدة
 *
 * لا تُخزَّن كلمات المرور في المستودع، ولا تُقبل هذه الأداة من الويب.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('هذه الأداة تعمل من سطر الأوامر فقط.');
}

define('BASE_PATH', dirname(__DIR__));

$config = require BASE_PATH . '/config/database.php';
$db = $config['database'];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']),
        (string) $db['user'],
        (string) $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "تعذر الاتصال بقاعدة البيانات: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$args = array_slice($argv, 1);
$now = date('Y-m-d H:i:s');

if (isset($args[0]) && $args[0] === '--password-only') {
    $email = $args[1] ?? '';
    $password = $args[2] ?? '';
    if ($email === '' || $password === '') {
        fwrite(STDERR, "الاستخدام: php tools/create_admin.php --password-only <email> <new-password>" . PHP_EOL);
        exit(1);
    }
    $statement = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE email = ? OR username = ?');
    $statement->execute([password_hash($password, PASSWORD_DEFAULT), $now, $email, $email]);
    echo $statement->rowCount() > 0
        ? "تم تحديث كلمة المرور." . PHP_EOL
        : "لم يتم العثور على مستخدم بهذا البريد أو اسم المستخدم." . PHP_EOL;
    exit($statement->rowCount() > 0 ? 0 : 1);
}

$name = $args[0] ?? 'مدير النظام';
$email = $args[1] ?? 'admin@prince-net.local';
$password = $args[2] ?? bin2hex(random_bytes(6));
$username = $args[3] ?? null;
$generated = !isset($args[2]);

if (mb_strlen($password) < 8) {
    fwrite(STDERR, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.' . PHP_EOL);
    exit(1);
}

$existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$existing->execute([$email]);
$found = $existing->fetch();

if ($found) {
    $update = $pdo->prepare('UPDATE users SET name = ?, username = COALESCE(?, username), password_hash = ?, status = ?, updated_at = ? WHERE id = ?');
    $update->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), 'active', $now, $found['id']]);
    echo "تم تحديث حساب المدير: {$email}" . PHP_EOL;
} else {
    $insert = $pdo->prepare('INSERT INTO users (name, email, username, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$name, $email, $username, password_hash($password, PASSWORD_DEFAULT), 'admin', 'active', $now, $now]);
    echo "تم إنشاء حساب المدير: {$email}" . PHP_EOL;
}

if ($generated) {
    echo "كلمة المرور المؤقتة: {$password}" . PHP_EOL;
    echo "احفظها الآن وغيّرها لاحقًا — لن تُطبع مرة أخرى." . PHP_EOL;
}

$audit = $pdo->prepare('INSERT INTO audit_logs (user_id, user_name, action, entity_type, entity_id, details, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?)');
$audit->execute(['(سطر الأوامر)', 'setup', 'auth', null, 'إنشاء/تحديث حساب مدير: ' . $email, $now]);
