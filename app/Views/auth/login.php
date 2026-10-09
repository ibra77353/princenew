<?php /** @var array<string,mixed> $settings */ ?>
<h1>تسجيل الدخول</h1>
<p>أدخل بيانات حساب المدير للمتابعة إلى <?= e($settings['network_name'] ?? 'شبكة البرنس') ?></p>

<form method="post" action="<?= e(url('/login')) ?>" class="stack">
    <?= csrf_field() ?>

    <div class="field">
        <label for="identifier">البريد الإلكتروني أو اسم المستخدم</label>
        <input class="input" id="identifier" name="identifier" type="text" autocomplete="username"
               value="<?= e(old('identifier')) ?>" required autofocus>
    </div>

    <div class="field">
        <label for="password">كلمة المرور</label>
        <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn btn--primary btn--block">دخول</button>
</form>

<p class="text-xs text-muted mt-3 text-center">
    لا تُنشأ حسابات عامة. لإضافة مستخدمين جدد استخدم أداة <code>tools/create_admin.php</code>.
</p>
