<?php
/**
 * نافذة تأكيد العمليات الحساسة — تُعرض كورقة سفلية على الجوال.
 * يملؤها assets/js/app.js من رسالة العنصر الذي يحمل data-confirm.
 */
?>
<div class="modal" id="confirm-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title">
    <div class="modal__overlay" data-confirm-cancel></div>
    <div class="modal__panel modal__panel--confirm">
        <div class="modal__head">
            <h3 id="confirm-dialog-title"><?= icon('alert') ?> تأكيد العملية</h3>
            <button type="button" class="modal__close" data-confirm-cancel aria-label="إغلاق">&times;</button>
        </div>
        <div class="modal__body">
            <p class="text-sm" data-confirm-message>هل أنت متأكد من تنفيذ هذه العملية؟</p>
            <div class="confirm__actions">
                <button type="button" class="btn btn--outline" data-confirm-cancel>إلغاء</button>
                <button type="button" class="btn btn--danger" data-confirm-accept><?= icon('check') ?> تأكيد</button>
            </div>
        </div>
    </div>
</div>
