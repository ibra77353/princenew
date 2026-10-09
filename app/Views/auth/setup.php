<?php /** @var array<string,mixed> $settings */ ?>
<h1>إعداد حساب المدير</h1>
<p>هذه أول مرة يعمل فيها النظام. أنشئ حساب المدير الآن — لن تُطلب هذه البيانات من المستودع أبدًا.</p>

<form method="post" action="<?= e(url('/setup')) ?>" class="stack">
    <?= csrf_field() ?>

    <div class="field">
        <label for="name">الاسم الكامل</label>
        <input class="input" id="name" name="name" type="text" value="<?= e(old('name')) ?>" required autofocus>
    </div>

    <div class="field">
        <label for="email">البريد الإلكتروني</label>
        <input class="input" id="email" name="email" type="email" value="<?= e(old('email')) ?>" required>
    </div>

    <div class="field">
        <label for="username">اسم المستخدم (اختياري)</label>
        <input class="input" id="username" name="username" type="text" value="<?= e(old('username')) ?>">
    </div>

    <div class="field">
        <label for="password">كلمة المرور (8 أحرف على الأقل)</label>
        <input class="input" id="password" name="password" type="password" autocomplete="new-password" required>
    </div>

    <div class="field">
        <label for="password_confirmation">تأكيد كلمة المرور</label>
        <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>

    <button type="submit" class="btn btn--primary btn--block">إنشاء الحساب والدخول</button>
</form>
