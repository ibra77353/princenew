# AGENTS.md — ملاحظات التشغيل والصيانة (شبكة البرنس)

نظام إدارة شبكة البرنس: **Native PHP 8.2 + MySQL 8.0** بأسلوب MVC، بدون أي إطار عمل أو Node.js.
هذا الملف يوثّق ما ليس واضحًا من الملفات الأخرى (كيف تُشغَّل البيئة هنا وماذا يجب الحذر منه).
للدليل التشغيلي والإداري الكامل — الإعداد الأول، دورة العمل اليومية، النسخ الاحتياطي والاستعادة، الصيانة،
التحديث، استكشاف الأخطاء، وما تم اختباره وما لم يُختبر — انظر **`OPERATIONS.md`**.

---

## تشغيل البيئة (Base44 / Docker)

```bash
docker compose -f docker-compose.base44.yml up -d --build
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000/login   # متوقع 200، أو 302 إلى /setup قبل إنشاء المدير
```

- `web` — Apache + PHP 8.2 من `php:8.2-apache`، يُبنى من `Dockerfile`، والكود **مُثبَّت bind mount** من المستودع
  (لا تُنسخ الشيفرة داخل الصورة، فأي تعديل ينعكس مباشرة بدون إعادة بناء).
- `db` — MySQL 8.0 على مجلد بيانات دائم باسم `db_data` (تُحفظ البيانات بين الجلسات).
- `db-init` — خدمة **تُنفَّذ مرة واحدة** وتطبّق `database/schema.sql` على قاعدة `prince_net`.

### لماذا لا يعاد تطبيق المخطط تلقائيًا بعد تعديله؟
عند تعديل `database/schema.sql` أعِد تشغيل خدمة التهيئة يدويًا (السكربت idempotent: `IF NOT EXISTS` + `INSERT IGNORE`):

```bash
docker compose -f docker-compose.base44.yml up -d --force-recreate db-init
docker compose -f docker-compose.base44.yml logs db-init
```

### الواجهة العامة
- منفذ الواجهة العام هو **3000** (يُوصَل إلى منفذ 80 داخل حاوية `web`).
- في وضع المعاينة تُمرَّر `BASE44_PREVIEW_MODE` من المنصة كمتغير بيئة داخل الخدمة، ويجب ألّا تُثبَّت أبدًا على `1` في أي ملف.
- لا تُكتب قيم `BASE44_*` المَresolve‑ة داخل أي ملف؛ تُمرَّر دائمًا كمتغيرات بيئة.

---

## مهام تحتاج انتباهًا خاصًا

### 1. الكلمة المحجوزة `lines`
جدول الخطوط اسمه `lines` وهو **كلمة محجوزة في MySQL**، ويجب أن تكون بين backticks في كل مكان:
المخطط، الاستعلامات داخل `app/Models` و`app/Services`، وأي أداة CLI.
تركها بدون backticks ينتج: `ERROR 1064 ... near 'lines ('`. (انظر `app/Models/Line.php` و`database/schema.sql`.)

### 2. حساب المدير وحالة الإعداد الأولى
- لا توجد بيانات دخول افتراضية في المستودع إطلاقًا.
- عندما يكون جدول `users` فارغًا، يُحوِّل النظام كل الصفحات إلى `/setup` لإنشاء حساب المدير الأول.
- عند وجود مدير بالفعل يتوقف `/setup` ويعرض `/login`.
- بديل من سطر الأوامر (داخل حاوية الويب، لأن الأداة ترفض العمل من الويب):

```bash
# إنشاء/تحديث مدير بكلمة مرور محددة
docker compose -f docker-compose.base44.yml exec -T web php tools/create_admin.php "الاسم" admin@example.com "كلمة المرور"
# تغيير كلمة مرور مدير قائم بالبريد أو اسم المستخدم
docker compose -f docker-compose.base44.yml exec -T web php tools/create_admin.php --password-only admin@example.com "كلمة المرور الجديدة"
```

### 3. إعادة النظام إلى حالة «الإعداد الأولى» (إزالة البيانات التجريبية)
للتسليم، الحالة النظيفة = جدول `users` فارغ (فيظهر `/setup`) + الباقات الافتراضية الخمس (100/200/250/500/1000) + صف واحد في `settings`.
الحذف بترتيب الأبناء قبل الآباء (بسبب المفاتيح الأجنبية):

```sql
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM audit_logs; DELETE FROM cash_transactions; DELETE FROM sale_items;
DELETE FROM collections; DELETE FROM sales; DELETE FROM inventory_movements;
DELETE FROM line_payments; DELETE FROM expenses; DELETE FROM owner_withdrawals;
DELETE FROM backups; DELETE FROM `lines`; DELETE FROM distributors; DELETE FROM users;
DELETE FROM packages WHERE id NOT IN (1,2,3,4,5);
UPDATE packages SET status='active';
UPDATE settings SET opening_balance=0.00, admin_email=NULL, low_stock_threshold=10 WHERE id=1;
SET FOREIGN_KEY_CHECKS = 1;
```

تنفيذ الاستعلام داخل الحاوية:

```bash
docker compose -f docker-compose.base44.yml exec -T db mysql -u root -pprince_root_dev prince_net -e "SELECT COUNT(*) FROM users;"
```

### 4. مجلد النسخ الاحتياطية وتكامل الاستعادة
`database/backups` مجلد بيانات (volume) ويجب أن يملكه `www-data` — تكفّلت به أوامر التشغيل في `docker-compose.base44.yml`.
لا تُحذف مجلدات البيانات (volumes) لتحديث التبعيات.

- `BackupService::TABLES` هي الجداول الأساسية (14). أما `inventory_stock` فجدول **مشتق** (`DERIVED_TABLES`):
  يُصدَّر داخل الملف للعلم، ويُعاد بناؤه من `inventory_movements` بعد كل استعادة عبر `rebuildStock()`،
  فلا ينفصل رصيد المخزون عن سجل الحركات أبدًا. لا تعدّل الرصيد يدويًا.
- `BackupService::restore()` تتحقق أولًا (`assertPayloadIsRestorable()`) من الأعمدة والقيم الإلزامية
  وتفرّد المفاتيح وسلامة المفاتيح الخارجية **قبل أي كتابة** (الرفض لا يترك أي أثر)، ثم تُنفِّذ الاستعادة
  داخل معاملة واحدة باستخدام `DELETE` (لا `TRUNCATE`) مع تفعيل المفاتيح الخارجية عند الإدراج، فأي فشل
  غير متوقع يُرجِع قاعدة البيانات بالكامل إلى حالتها.
- نسخة قديمة بصيغة v1 (بلا `inventory_stock`) تبقى قابلة للاستعادة، ويُعاد بناء الرصيد بعدها.

### 5. الحماية
- `.htaccess` في الجذر يحوّل كل الطلبات إلى `index.php` ويمنع الوصول إلى `\.(sql|json|md|log|ini|bak|zip)$`.
- لذلك أي ملف توثيقي/JSON داخل المستودع غير متاح من الويب (بما فيه `database/` و`config/`).
- كلمات مرور المستخدمين تُخزَّن بـ `password_hash()`، وكل الطلبات المُغيِّرة للبيانات محمية بـ CSRF.

### 6. ترميز النصوص العربية عند استخدام عميل `mysql`
عميل `mysql` داخل الحاوية يعمل افتراضيًا بترميز **latin1** (`@@character_set_client = latin1`)، وعند تمرير نص عربي
عبره (سكربت SQL يدوي أو استعلام `UPDATE`) يُخزَّن النص **مُشفَّرًا مرتين** ويظهر في الواجهة كـ `Ø±.ÙŠ` بدل `ر.ي`.
لذلك مرّر دائمًا:

```bash
docker compose -f docker-compose.base44.yml exec -T db mysql --default-character-set=utf8mb4 -u root -pprince_root_dev prince_net -e "SELECT ...;"
```

- الكتابة من **التطبيق** سليمة (PDO مع `charset=utf8mb4`) — المشكلة في الاستعلامات اليدوية فقط.
- سكربت `database/schema.sql` يبدأ بـ `SET NAMES utf8mb4;` لذلك تهيئة `db-init` تحفظ العربية بشكل صحيح ولا تحتاج أي تغيير.
- للتحقق من سلامة مخزون النص: `SELECT HEX(network_name) FROM settings;` وتوقّع البداية `D8B4D8A8D983D8A9` («شبكة»).

---

### 7. سياسة كفاية الصندوق الموحّدة (تعديل لاحق)

- الفحص **مركزي واحد** في `CashService::assertSufficient()`، والمصادر الخاضعة له قائمة
  `CashService::GUARDED_OUT_SOURCES = ['expense', 'line_payment', 'owner_withdrawal']`.
  أي حركة خارجة جديدة يجب أن تُسجَّل عبر `CashService::record()` (لا `INSERT` مباشر في `cash_transactions`)،
  ويُضاف مصدرها للقائمة إن أرادت الخضوع للفحص. لا تُكرِّر الفحص داخل `OutflowService` أو أي خدمة أخرى.
- الحركات **العكسية** (`source = 'reversal'`) مستثناة من الفحص: تُلغي أثر حركة سابقة ورفضها يُفسد اتساق الصندوق.
- الفحص يقرأ الرصيد عبر `CashService::lockedBalance()` التي تأخذ قفلًا تسلسليًا
  (`SELECT id FROM settings WHERE id = 1 FOR UPDATE`) داخل معاملة العملية، فلا يستطيع خروجان متزامنان تجاوز الرصيد.
  أبقِ استدعاءه داخل معاملة `Database::transaction` وإلا صار القفل بلا أثر (autocommit).
- الرسالة موحّدة وتُظهر المتاح والمطلوب: «رصيد الصندوق غير كافٍ: المتاح X والمطلوب Y.»
- شاشات المصروفات ودفعات الخطوط وسحوبات المالك تعرض «الرصيد المتاح في الصندوق» قبل الحفظ.
- تحقق سريع بعد أي تعديل مسّ الصندوق: نفّذ مصروفًا أكبر من الرصيد (يجب أن يُرفض ويُترك الصندوق كما هو)،
  ثم مصروفًا في حدود الرصيد (يجب أن ينجح)، وألغِ فاتورة مدفوعة نقديًا (يجب أن تنجح الحركة العكسية ولو نفد الرصيد).

---

## التحقق السريع بعد أي تعديل

> لا يوجد خادم تطوير بـ live reload: Apache يقدّم ملفات PHP/CSS من الـ bind mount مباشرة،
> فبعد أي تعديل أعد تحميل المعاينة (reload_preview) لرؤية النتيجة. لا تعتمد على تحديث تلقائي.

```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost:3000/        # 302 → /setup أو /login
curl -s http://localhost:3000/setup | grep -oE "<h1>[^<]*</h1>"                         # يظهر نموذج إعداد المدير
docker compose -f docker-compose.base44.yml logs web | grep -iE "fatal|error|warning"   # لا أخطاء PHP
docker compose -f docker-compose.base44.yml ps                                          # web/db healthy، db-init exited (0)
```

اختبار وظيفي كامل بعد إنشاء المدير: تسجيل الدخول، إضافة باقة/مخزون، بيع نقدي وآجل، تحصيل جزئي،
إلغاء فاتورة مع حركات العكس، مصروف/سحب/دفعة خط، مطابقة رصيد الصندوق والتقارير، النسخ الاحتياطي والاستعادة.

---

## رصيد المخزون وسعر الشدة (تعديلات لاحقة)

- **جدول `inventory_stock`** هو الرصيد النشط للمخزون: صف **واحد لكل باقة** (المفتاح الأساسي `package_id`
  يمنع تكرار السجل). `inventory_movements` يبقى سجل الحركات التاريخي، وكل حركة تُحدِّث الرصيد تراكميًا
  داخل معاملة (`InventoryService::movement()` عبر `InventoryMovement::applyDelta()`). إضافة كمية لا تُصفّر
  الرصيد ولا تنشئ سجلًا جديدًا؛ لذلك قراءة المخزون (لوحة التحكم/التقارير/المبيعات) تعتمد على الرصيد المجمّع.
- **سعر الشدة موحّد** ومصدره الوحيد حقل `packages.price`. شاشة المخزون لا تقبل سعرًا مستقلًا: الخادم
  يتجاهل أي `unit_price` مُرسَل ويحفظ سعر الباقة وقت الحركة في `inventory_movements.unit_cost`.
  تعديل سعر الباقة لا يغيّر الفواتير أو الحركات السابقة (الأسعار محفوظة لقطةً في `sale_items.unit_price`
  و`inventory_movements.unit_cost`).
- **منع تكرار اسم الباقة**: قيد `UNIQUE KEY uq_packages_name (name)` على جدول `packages`، مع تحقق على
  الخادم في `PackagesController::assertNameIsUnique()` (يشذّب المسافات الطرفية، والمقارنة بترميز
  utf8mb4_unicode_ci غير حساسة لحالة الأحرف). التعديل إلى الاسم الحالي مسموح.
- **تنسيق الأسعار**: `money()` يعرض المبالغ كأعداد صحيحة، و`amount()` يُخرج عددًا صحيحًا بلا منازل عشرية
  عندما تكون القيمة صحيحة (تُستخدم في حقول النماذج وسمات `data-price`/`data-values`).

### توجيه نماذج التعديل (`data-action`)

أزرار التعديل في القوائم (الباقات/الخطوط/الموزعين) وإلغاء الفاتورة تحمل `data-fill` (معرّف النافذة)
و`data-values` (القيم) و`data-action` (قالب المسار مثل `/packages/{id}/update`، وإلغاء الفاتورة
`/sales/{sale_id}/cancel`). معالج `[data-fill]` في `assets/js/app.js` يملأ الحقول **ثم** يستبدل
`{المفتاح}` من `data-values` داخل `data-action` ويضعه في `form.action` قبل فتح النافذة.

- لا تُضِف سكربتًا محليًا داخل الصفحة لتوجيه النموذج: كان النموذج يُرسل إلى مسار الإضافة
  (`POST /packages`) فيظهر خطأ «توجد باقة بالاسم ... مسبقًا» عند التعديل بلا تغيير الاسم.
- إضافة زر تعديل جديد = `data-fill` + `data-values` (يجب أن تحتوي المفتاح المستخدم في القالب) + `data-action`.
- ملفات الأصول (`app.js`/`app.css`) تُخدَم بلا نسخة في الرابط؛ المتصفح يعيد التحقق عبر ETag، لذلك
  تظهر التعديلات بعد إعادة تحميل الصفحة الواحدة (لا يوجد live reload في هذا المشروع).

### تطبيق مخطط جديد على قاعدة قائمة
عند إضافة جداول/قيود جديدة إلى `database/schema.sql` لا يعيد `db-init` تطبيقها على قاعدة موجودة
(`CREATE TABLE IF NOT EXISTS` لا يعدّل جدولًا قائمًا). لذلك طُبّق التالي يدويًا مرة واحدة على قاعدة قائمة
(انسخه إن احتجت على بيئة أخرى بعد التأكد من عدم وجود أسماء باقات مكررة):

```bash
docker compose -f docker-compose.base44.yml exec -T db mysql --default-character-set=utf8mb4 -u root -pprince_root_dev prince_net -e "
CREATE TABLE IF NOT EXISTS inventory_stock (
    package_id INT UNSIGNED NOT NULL,
    quantity   INT          NOT NULL DEFAULT 0,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (package_id),
    CONSTRAINT fk_stock_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE packages ADD UNIQUE KEY uq_packages_name (name);"
```

لتهيئة أرصدة المخزون من الحركات القائمة: `INSERT IGNORE INTO inventory_stock (package_id, quantity, updated_at)
SELECT package_id, SUM(CASE ... END), NOW() FROM inventory_movements GROUP BY package_id;` (مضمّن في `schema.sql`).
عند حذف باقات لا حاجة لحذف صفوف `inventory_stock` يدويًا (مفتاح خارجي `ON DELETE CASCADE`).
