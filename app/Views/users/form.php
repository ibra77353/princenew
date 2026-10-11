<?php
/**
 * حقول نموذج المستخدم (مشتركة بين الإضافة والتعديل).
 * @var array<string,mixed>|null $user
 * @var bool $isEdit
 */
$isEdit = $isEdit ?? false;
$currentRole = (string) ($user['role'] ?? old('role', 'employee'));
$currentStatus = (string) ($user['status'] ?? old('status', 'active'));
?>
<div class="grid grid--form">
    <div class="field grid--full">
        <label for="<?= $isEdit ? 'user-edit-name' : 'user-new-name' ?>">الاسم</label>
        <input class="input" id="<?= $isEdit ? 'user-edit-name' : 'user-new-name' ?>" name="name" type="text" required
               value="<?= e($user['name'] ?? old('name')) ?>" placeholder="مثال: محمد أحمد">
    </div>

    <div class="field">
        <label for="<?= $isEdit ? 'user-edit-email' : 'user-new-email' ?>">البريد الإلكتروني</label>
        <input class="input" id="<?= $isEdit ? 'user-edit-email' : 'user-new-email' ?>" name="email" type="email" required
               value="<?= e($user['email'] ?? old('email')) ?>" placeholder="user@example.com">
    </div>

    <div class="field">
        <label for="<?= $isEdit ? 'user-edit-username' : 'user-new-username' ?>">اسم المستخدم (اختياري)</label>
        <input class="input" id="<?= $isEdit ? 'user-edit-username' : 'user-new-username' ?>" name="username" type="text"
               value="<?= e($user['username'] ?? old('username')) ?>" placeholder="للدخول البديل عن البريد">
    </div>

    <div class="field">
        <label for="<?= $isEdit ? 'user-edit-role' : 'user-new-role' ?>">الدور</label>
        <select class="select" id="<?= $isEdit ? 'user-edit-role' : 'user-new-role' ?>" name="role">
            <option value="employee" <?= $currentRole === 'employee' ? 'selected' : '' ?>>موظف — تشغيل محدود</option>
            <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>مدير — صلاحية كاملة</option>
        </select>
    </div>

    <div class="field">
        <label for="<?= $isEdit ? 'user-edit-status' : 'user-new-status' ?>">الحالة</label>
        <select class="select" id="<?= $isEdit ? 'user-edit-status' : 'user-new-status' ?>" name="status">
            <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>نشط</option>
            <option value="disabled" <?= $currentStatus === 'disabled' ? 'selected' : '' ?>>معطّل</option>
        </select>
    </div>

    <div class="field">
        <label for="<?= $isEdit ? 'user-edit-password' : 'user-new-password' ?>">
            كلمة المرور<?= $isEdit ? ' الجديدة (اتركها فارغة للإبقاء على الحالية)' : '' ?>
        </label>
        <input class="input" id="<?= $isEdit ? 'user-edit-password' : 'user-new-password' ?>" name="password"
               type="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>
               minlength="8" placeholder="8 أحرف على الأقل">
    </div>

    <div class="field">
        <label for="<?= $isEdit ? 'user-edit-password-confirm' : 'user-new-password-confirm' ?>">تأكيد كلمة المرور</label>
        <input class="input" id="<?= $isEdit ? 'user-edit-password-confirm' : 'user-new-password-confirm' ?>"
               name="password_confirmation" type="password" autocomplete="new-password"
               <?= $isEdit ? '' : 'required' ?> minlength="8">
    </div>
</div>
