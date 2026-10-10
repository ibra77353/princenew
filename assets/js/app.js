/* ============================================================================
   شبكة البرنس — تفاعلات الواجهة (JavaScript عادي، دون أطر عمل)
   ========================================================================= */
(function () {
    "use strict";

    /* ── الوضع الداكن / الفاتح ─────────────────────────────────────────── */
    var THEME_KEY = "prince-theme";

    function applyTheme(theme) {
        document.documentElement.classList.toggle("dark", theme === "dark");
        document.querySelectorAll("[data-theme-label]").forEach(function (el) {
            el.textContent = theme === "dark" ? "الوضع الفاتح" : "الوضع الداكن";
        });
        document.querySelectorAll("[data-theme-icon]").forEach(function (el) {
            el.textContent = theme === "dark" ? "☀" : "☾";
        });
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute("content", theme === "dark" ? "#0b1220" : "#0f172a");
        }
    }

    function currentTheme() {
        try {
            return localStorage.getItem(THEME_KEY) || "light";
        } catch (e) {
            return "light";
        }
    }

    applyTheme(currentTheme());

    document.addEventListener("click", function (event) {
        var toggle = event.target.closest("[data-theme-toggle]");
        if (!toggle) {
            return;
        }
        event.preventDefault();
        var next = currentTheme() === "dark" ? "light" : "dark";
        try {
            localStorage.setItem(THEME_KEY, next);
        } catch (e) { /* المتصفح قد يمنع التخزين */ }
        applyTheme(next);
    });

    /* ── القائمة الجانبية على الجوال ──────────────────────────────────── */
    var sidebar = document.querySelector("[data-sidebar]");
    var backdrop = document.querySelector("[data-backdrop]");

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove("is-open");
        }
        if (backdrop) {
            backdrop.hidden = true;
        }
    }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-menu-toggle]")) {
            event.preventDefault();
            if (sidebar) {
                sidebar.classList.toggle("is-open");
                if (backdrop) {
                    backdrop.hidden = !sidebar.classList.contains("is-open");
                }
            }
            return;
        }
        if (event.target.closest("[data-backdrop]")) {
            closeSidebar();
            return;
        }
        if (event.target.closest(".sidebar .nav-item")) {
            closeSidebar();
        }
    });

    /* ── النوافذ المنبثقة ─────────────────────────────────────────────── */
    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }
        modal.hidden = false;
        var focusable = modal.querySelector("input, select, textarea, button");
        if (focusable) {
            focusable.focus();
        }
    }

    function closeModal(modal) {
        if (modal) {
            modal.hidden = true;
        }
    }

    document.addEventListener("click", function (event) {
        var opener = event.target.closest("[data-modal-open]");
        if (opener) {
            event.preventDefault();
            openModal(opener.getAttribute("data-modal-open"));
            return;
        }
        var closer = event.target.closest("[data-modal-close]");
        if (closer) {
            event.preventDefault();
            closeModal(closer.closest(".modal"));
            return;
        }
        if (event.target.classList.contains("modal__overlay")) {
            closeModal(event.target.closest(".modal"));
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            document.querySelectorAll(".modal:not([hidden])").forEach(closeModal);
            closeSidebar();
        }
    });

    /* ── تعبئة نموذج التعديل من زر البيانات ─────────────────────────── */
    document.addEventListener("click", function (event) {
        var trigger = event.target.closest("[data-fill]");
        if (!trigger) {
            return;
        }
        event.preventDefault();
        var modalId = trigger.getAttribute("data-fill");
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }
        var payload;
        try {
            payload = JSON.parse(trigger.getAttribute("data-values") || "{}");
        } catch (e) {
            payload = {};
        }
        Object.keys(payload).forEach(function (name) {
            var field = modal.querySelector('[name="' + name + '"]');
            if (field) {
                field.value = payload[name] === null ? "" : payload[name];
            }
        });
        openModal(modalId);
    });

    /* ── منع تكرار إرسال النماذج ─────────────────────────────────────── */
    document.addEventListener("submit", function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (form.hasAttribute("data-allow-repeat")) {
            return;
        }
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            setTimeout(function () {
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = "جارٍ...";
            }, 0);
        }
    });

    /* ── تأكيد العمليات الحساسة (ورقة سفلية على الجوال) ──────────────── */
    var confirmDialog = document.getElementById("confirm-dialog");
    var confirmMessage = confirmDialog ? confirmDialog.querySelector("[data-confirm-message]") : null;
    var confirmAccept = confirmDialog ? confirmDialog.querySelector("[data-confirm-accept]") : null;
    var confirmTrigger = null;

    function closeConfirm() {
        if (confirmDialog) {
            confirmDialog.hidden = true;
        }
        confirmTrigger = null;
    }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-confirm-cancel]")) {
            event.preventDefault();
            closeConfirm();
            return;
        }

        var trigger = event.target.closest("[data-confirm]");
        if (!trigger) {
            return;
        }

        var message = trigger.getAttribute("data-confirm") || "هل أنت متأكد من تنفيذ هذه العملية؟";

        // مُؤكَّد من المستخدم → يُنفَّذ الإجراء الأصلي كما هو.
        if (trigger.hasAttribute("data-confirmed")) {
            trigger.removeAttribute("data-confirmed");
            return;
        }

        if (!confirmDialog) {
            // احتياطي حين لا تتوفر النافذة (صفحات بلا تخطيط عام).
            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        confirmTrigger = trigger;
        if (confirmMessage) {
            confirmMessage.textContent = message;
        }
        confirmDialog.hidden = false;
        if (confirmAccept) {
            confirmAccept.focus();
        }
    });

    if (confirmAccept) {
        confirmAccept.addEventListener("click", function () {
            var trigger = confirmTrigger;
            closeConfirm();
            if (!trigger) {
                return;
            }
            trigger.setAttribute("data-confirmed", "");
            trigger.click();
        });
    }

    /* ── البحث التلقائي بعد توقف الكتابة ──────────────────────────────── */
    document.querySelectorAll("[data-autosubmit]").forEach(function (input) {
        var timer = null;
        input.addEventListener("input", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var form = input.closest("form");
                if (form) {
                    form.submit();
                }
            }, 500);
        });
    });

    /* ── قائمة الفواتير المستحقة حسب الموزع (التحصيلات) ──────────────── */
    var collectionForm = document.querySelector("[data-collections-form]");
    if (collectionForm) {
        var distributorSelect = collectionForm.querySelector('[name="distributor_id"]');
        var saleSelect = collectionForm.querySelector('[name="sale_id"]');
        var hint = collectionForm.querySelector("[data-balance-hint]");
        var baseUrl = collectionForm.getAttribute("data-outstanding-url");

        if (distributorSelect && saleSelect && baseUrl) {
            distributorSelect.addEventListener("change", function () {
                var id = distributorSelect.value;
                saleSelect.innerHTML = '<option value="">جارٍ التحميل...</option>';
                if (!id) {
                    saleSelect.innerHTML = '<option value="">تحصيل عام (بدون فاتورة)</option>';
                    if (hint) { hint.textContent = ""; }
                    return;
                }
                fetch(baseUrl + "?distributor_id=" + encodeURIComponent(id), {
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        var options = ['<option value="">تحصيل عام (بدون فاتورة)</option>'];
                        (data.sales || []).forEach(function (sale) {
                            options.push('<option value="' + sale.id + '">' + sale.invoice_number +
                                " — متبقي " + sale.remaining + "</option>");
                        });
                        saleSelect.innerHTML = options.join("");
                        if (hint) {
                            var sales = data.sales || [];
                            hint.textContent = sales.length === 0
                                ? "لا توجد فواتير مستحقة لهذا الموزع"
                                : "الرصيد المستحق: " + data.balance;
                        }
                    })
                    .catch(function () {
                        saleSelect.innerHTML = '<option value="">تحصيل عام (بدون فاتورة)</option>';
                    });
            });
        }
    }

    /* ── أسطر الفاتورة: إضافة/حذف وحساب الإجمالي في المتصفح ─────────── */
    var saleForm = document.querySelector("[data-sale-form]");
    if (saleForm) {
        var itemsWrap = saleForm.querySelector("[data-sale-items]");
        var totalLabel = saleForm.querySelector("[data-sale-total]");
        var remainingLabel = saleForm.querySelector("[data-sale-remaining]");
        var paidInput = saleForm.querySelector('[name="paid_amount"]');
        var paymentType = saleForm.querySelector('[name="payment_type"]');
        var emptyRow = saleForm.querySelector("[data-sale-row-template]");

        function recalc() {
            var total = 0;
            itemsWrap.querySelectorAll(".sale-row").forEach(function (row) {
                var select = row.querySelector('[name="item_package_id[]"]');
                var qty = row.querySelector('[name="item_quantity[]"]');
                var option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
                var price = option ? Number(option.getAttribute("data-price") || 0) : 0;
                var quantity = Number(qty && qty.value ? qty.value : 0);
                var lineTotal = price * quantity;
                var label = row.querySelector("[data-line-total]");
                if (label) {
                    label.textContent = lineTotal.toLocaleString("en-US");
                }
                total += lineTotal;
            });

            if (totalLabel) {
                totalLabel.textContent = total.toLocaleString("en-US");
            }

            var isCash = paymentType && paymentType.value === "cash";
            if (paidInput) {
                paidInput.disabled = isCash;
                if (isCash) {
                    paidInput.value = total.toFixed(2);
                }
            }
            var paid = isCash ? total : Number(paidInput && paidInput.value ? paidInput.value : 0);
            if (remainingLabel) {
                remainingLabel.textContent = (total - paid).toLocaleString("en-US");
            }
        }

        function bindRow(row) {
            row.querySelectorAll("select, input").forEach(function (field) {
                field.addEventListener("change", recalc);
                field.addEventListener("input", recalc);
            });
            var remove = row.querySelector("[data-remove-row]");
            if (remove) {
                remove.addEventListener("click", function (event) {
                    event.preventDefault();
                    var rows = itemsWrap.querySelectorAll(".sale-row");
                    if (rows.length > 1) {
                        row.remove();
                    } else {
                        row.querySelectorAll("input, select").forEach(function (field) {
                            field.value = "";
                        });
                    }
                    recalc();
                });
            }
        }

        if (itemsWrap) {
            itemsWrap.querySelectorAll(".sale-row").forEach(bindRow);

            var addButton = saleForm.querySelector("[data-add-row]");
            if (addButton && emptyRow) {
                addButton.addEventListener("click", function (event) {
                    event.preventDefault();
                    var clone = emptyRow.content
                        ? emptyRow.content.cloneNode(true).querySelector(".sale-row")
                        : emptyRow.cloneNode(true);
                    clone.hidden = false;
                    clone.removeAttribute("data-sale-row-template");
                    itemsWrap.appendChild(clone);
                    bindRow(clone);
                    recalc();
                });
            }
        }

        saleForm.addEventListener("change", recalc);
        saleForm.addEventListener("input", recalc);
        recalc();
    }

    /* ── طباعة الفاتورة ومشاركتها ────────────────────────────────────── */
    document.addEventListener("click", function (event) {
        var printTrigger = event.target.closest("[data-print]");
        if (printTrigger) {
            event.preventDefault();
            window.print();
            return;
        }

        var shareButton = event.target.closest("[data-share]");
        if (shareButton) {
            event.preventDefault();
            var shared = document.querySelector("[data-invoice]");
            var shareText = shared ? shared.innerText.replace(/\n{2,}/g, "\n").trim() : "";
            if (navigator.share) {
                navigator.share({ title: document.title, text: shareText }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(shareText).then(function () {
                    var label = shareButton.querySelector("[data-share-label]");
                    if (!label) {
                        return;
                    }
                    var original = label.textContent;
                    label.textContent = "تم النسخ";
                    setTimeout(function () { label.textContent = original; }, 1500);
                }).catch(function () {});
            }
            return;
        }

        var shareTrigger = event.target.closest("[data-whatsapp]");
        if (shareTrigger) {
            event.preventDefault();
            var invoice = document.querySelector("[data-invoice]");
            var phone = shareTrigger.getAttribute("data-phone") || "";
            var text = invoice ? invoice.innerText.replace(/\n{2,}/g, "\n").trim() : "";
            var url = "https://wa.me/" + encodeURIComponent(phone.replace(/[^0-9]/g, "")) +
                "?text=" + encodeURIComponent(text);
            window.open(url, "_blank", "noopener");
        }
    });

    /* ── إشعارات المخزون والديون ─────────────────────────────────────── */
    document.addEventListener("click", function (event) {
        var toggle = event.target.closest("[data-notifications-toggle]");
        var panel = document.querySelector("[data-notifications-panel]");
        if (!panel) {
            return;
        }
        if (toggle) {
            event.preventDefault();
            panel.hidden = !panel.hidden;
            return;
        }
        if (!event.target.closest("[data-notifications-panel]")) {
            panel.hidden = true;
        }
    });

    /* ── تحديد كل عناصر الجدول (للطباعة) — دعم لوحة المفاتيح ────────── */
    document.querySelectorAll("[data-print-link]").forEach(function (link) {
        link.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                window.print();
            }
        });
    });
})();
