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

### 5. الحماية (M4: حماية الملفات)
- `.htaccess` في الجذر يحوّل كل الطلبات إلى `index.php`، ويمنع **صراحة** المجلدات الحساسة
  (`app`, `config`, `database`, `tools`, `vendor`, `logs`, `storage`, `.git`, `.base44`) برد **403**
  حتى لو كان الملف الموجود داخلها حقيقيًا — قبل قاعدة «لا تعِد كتابة الملفات الموجودة».
- حجب الامتدادات: `sql|json|md|log|ini|bak|zip|yml|yaml|env|lock|sh|dist|conf|sqlite`،
  وحجب ملفات الإعداد بلا امتداد معروف: `Dockerfile*`, `docker-compose*`, `composer.*`, `.git*`, `.env*`.
- دفاع متعدد الطبقات: `.htaccess` داخلي بـ`Require all denied` في `app/` و`config/` و`database/` و`tools/`.
  **قاعدة ذهبية:** كل مجلد شيفرة/إعدادات جديد يُضاف إلى قائمة الحجب الجذرية **و**يحصل على `.htaccess` داخلي.
  المجلد الوحيد المتاح للويب هو `assets/`.
- كلمات مرور المستخدمين تُخزَّن بـ `password_hash()`، وكل الطلبات المُغيِّرة للبيانات محمية بـ CSRF.
- اختبار الحماية (شغّله بعد أي تعديل على `.htaccess`؛ يجب أن يبقى الوصول المشروع سليمًا):

```bash
# متوقع 403 لكل مسار حسّاس
for p in "/.git/config" "/config/database.php" "/app/Views/errors/index.php" "/Dockerfile" \
         "/docker-compose.base44.yml" "/database/schema.sql" "/.base44/environment.json" "/README.md"; do
  echo "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:3000$p)  $p"
done
# متوقع 200 لكل أصل عام
for p in "/login" "/assets/css/app.css" "/assets/js/app.js" "/assets/images/logo.svg"; do
  echo "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:3000$p)  $p"
done
```

### 5.1 معالجة الأخطاء وإخفاء التفاصيل (M5)
- `App\Services\Core\ErrorHandler::register($config['app'])` يُستدعى في `index.php` **قبل** الاتصال بقاعدة
  البيانات وأي شيء آخر: `display_errors=0` دائمًا و`log_errors=1`، مع معالجات للتحذيرات والاستثناءات
  والأخطاء القاتلة. التفاصيل تُكتب في سجل Apache: `docker compose -f docker-compose.base44.yml logs web | grep -i error`.
- المستخدم يرى صفحة عامة «حدث خطأ غير متوقع» بلا مسارات ملفات أو استعلامات أو بيانات اتصال.
  `APP_DEBUG=1` يُظهر التفاصيل في الصفحة للمطوّر فقط (غير مُفعَّل في `docker-compose.base44.yml`).
- `Controller::action()` لا يعرض رسائل `PDOException` أو `Error`/`TypeError` التقنية، بل يسجّلها ويُظهر
  رسالة عامة، ويُبقي رسائل التحقق العربية المقصودة (مثل «توجد باقة بالاسم نفسه مسبقًا») كما هي.
- `Database::pdo()` يضبط `PDO::ATTR_TIMEOUT = 5` حتى يفشل انقطاع قاعدة البيانات سريعًا (رسالة معالجة)
  بدل تعليق الصفحة دقائق.
- للتشخيص استخدم `ErrorHandler::report($e)` أو السجل؛ لا تُضِف `echo`/`var_dump`/`die` في الشيفرة.

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

### 8. الأدوار والصلاحيات (M6: مدير + موظف)

دورَان فقط ولا ثالث لهما، والقيمة المخزَّنة في `users.role = ENUM('admin','employee')`:

- **`admin`** — صلاحية كاملة، بما فيها الإعدادات والنسخ الاحتياطي وإدارة المستخدمين وحذف السجلات الحساسة وإلغاء الفواتير.
- **`employee`** — تشغيل محدود: مبيعات نقدية وآجلة، تحصيلات، دفعات الخطوط، الصندوق، والبحث.
  أما **الباقات والمخزون والموزعون والخطوط فـ«عرض فقط»** للموظف: يعرضها ويختار الموزع/الباقة/الخط في
  المبيعات والتحصيلات ودفعات الخطوط، لكنه **لا** يضيف/يعدّل/يحذف فيها ولا يغيّر الأرصدة يدويًا.
  و**المصروفات وسحوبات المالك والتقارير وسجل التدقيق للمدير فقط**: شاشاتها وقوائمها مُخفاة تمامًا عن الموظف
  وتُرفض برد 403 إن طلبها مباشرة. البيع يخصم المخزون وإلغاء الفاتورة (مدير) يُرجعه تلقائيًا.

الفرض **على الخادم لا في الواجهة**: `Auth::ROLE_ADMIN`/`ROLE_EMPLOYEE` و`Auth::isAdmin()` و`Auth::requireAdmin()`
(ترد 403 «هذه العملية متاحة للمدير فقط») في `app/Services/Core/Auth.php`، ويستدعيها وسيط `admin` في
`app/Services/Core/Router.php`. المسارات الإدارية مُعلَّمة بالوسيط `$admin = ['auth','admin']` في `index.php`:

- كل مسارات `/users` و`/settings` و`/backup` (تصدير/استعادة/تنزيل/حذف).
- العمليات الحساسة (مسار مستقل لكل منها): `/packages/{id}/delete`, `/lines/{id}/delete`,
  `/distributors/{id}/delete`, `/sales/{id}/cancel`.
- **إضافة/تعديل الباقات والمخزون والموزعين والخطوط** (منع الموظف من تغيير البيانات الأساسية):
  `POST /packages`, `POST /packages/{id}/update`, `POST /packages/{id}/toggle`,
  `POST /inventory/add`, `POST /inventory/adjust`,
  `POST /distributors`, `POST /distributors/{id}/update`,
  `POST /lines`, `POST /lines/{id}/update`.
  مسارات العرض تبقى `$auth` لكل الأدوار: `GET /packages`, `GET /inventory`, `GET /distributors`,
  `GET /distributors/{id}`, `GET /lines`, `GET /lines/{id}`.
- **شاشات المصروفات وسحوبات المالك والتقارير وسجل التدقيق للمدير فقط** (طلب لاحق):
  `GET/POST /expenses`, `GET/POST /owner-withdrawals`, `GET /reports`, `GET /audit`.
  أما `/cash` و`/search` فيبقيان `$auth` للموظف مع إخفاء ما لا يُسمح به داخلهما: البحث يحذف مجموعتَي
  المصروفات والسحوبات لغير المدير، والصندوق يخفي زر «تقارير التدفق النقدي».
- أي عملية إضافة/تعديل جديدة على هذه الوحدات يجب أن تُمنح الوسيط `$admin` **وإلا** صارت متاحة للموظف (الافتراضي `$auth`).

قواعد الحماية الذاتية في `UsersController`: لا يغيّر المدير دوره، ولا يعطّل حسابه، ولا يحذفه، ولا يمكن إزالة دور
المدير عن آخر حساب مدير نشط (منعًا لإقفال النظام). أي قيمة دور غير معروفة تُعالَج كـ`employee` (أقل صلاحية).

- الواجهة تخفي فقط ما لا يُسمح به (`is_admin()` في `sidebar.php` عبر قائمة `$adminOnlyPaths`، وفي أزرار
  الإضافة/التعديل/الحذف/إلغاء الفاتورة) — الإخفاء تحسين تجربة لا حماية؛ الحماية الحقيقية هي الوسيط و`requireAdmin()`.
- إضافة مسار إداري جديد = ضعه في قائمة `$admin`، وإضافة عملية حساسة = أعطها مسارًا مستقلًا بالوسيط `admin`.
- `AuditService::log()` يسجّل هوية المنفّذ تلقائيًا (`Auth::id()` و`Auth::name()`)؛ كل عملية مالية (بيع، تحصيل،
  مصروف، دفعة خط، سحب، حركة مخزون) تُسجَّل عبره، فلا تُكتب صفوف `audit_logs` يدويًا ولا تُعدَّل.
- شاشة المستخدمين `/users` (للمدير فقط): إضافة/تعديل/حذف الحسابات، تغيير الدور والحالة، وتغيير كلمة المرور
  (تُترك فارغة للإبقاء عليها). الدور الافتراضي للحساب الجديد `employee`.

**ترقية قاعدة قائمة** (المخطط الجديد لا يُطبَّق تلقائيًا على قاعدة موجودة، نفّذه مرة واحدة):
```bash
docker compose -f docker-compose.base44.yml exec -T db mysql --default-character-set=utf8mb4 \
  -u root -pprince_root_dev prince_net -e "ALTER TABLE users MODIFY role ENUM('admin','employee') NOT NULL DEFAULT 'employee';"
```

**اختبار الصلاحيات** (متوقع 403 لمسار الإدارة و200 للمسارات التشغيلية بحساب موظف):
```bash
# أنشئ حساب موظف من /users ثم سجّل دخوله، واختبر برمز الجلسة
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar http://localhost:3000/settings   # 403
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar http://localhost:3000/users      # 403
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar http://localhost:3000/sales      # 200
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar http://localhost:3000/packages   # 200 (عرض)
```
الطلبات المُغيِّرة تُرفض بالوسيط قبل CSRF (403 بلا رمز)، وحتى إرسال طلب مباشر إلى `/users` بغرض إنشاء مدير
أو رفع صلاحية لا يمرّ.

**اختبار تقييد الموظف على البيانات الأساسية** (بعد إضافة/تعديل الباقات والمخزون والموزعين إلى `$admin`):
الحماية تُختبر **برمز CSRF صحيح** للتأكد أن الرفض سببُه الدور لا الرمز — المتوقع 403 برسالة
«هذه العملية متاحة للمدير فقط» لكل مسار أدناه بحساب موظف، و302 للمسارات التشغيلية:

```bash
E=http://localhost:3000
for p in /packages /packages/1/update /packages/1/toggle /packages/1/delete \
         /inventory/add /inventory/adjust /distributors /distributors/1/update /distributors/1/delete; do
  echo "$(curl -s -o /dev/null -w '%{http_code}' -b emp.jar -X POST "$E$p" --data-urlencode "_token=$TOKEN")  POST $p"   # 403
done
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar -X POST "$E/sales" --data-urlencode "_token=$TOKEN"      # 302 (لا 403)
```
(`$TOKEN` = رمز CSRF من صفحة داخل الجلسة، مثل نموذج تسجيل الخروج في القائمة الجانبية.)

**اختبار تقييد الموظف على الخطوط والشاشات الإدارية** (طلب لاحق): الخطوط للموظف عرض فقط، والمصروفات
وسحوبات المالك والتقارير وسجل التدقيق تُرفض له حتى في طلبات GET:

```bash
E=http://localhost:3000
for p in /lines /lines/1/update; do
  echo "$(curl -s -o /dev/null -w '%{http_code}' -b emp.jar -X POST "$E$p" --data-urlencode "_token=$TOKEN")  POST $p"   # 403
done
for p in /expenses /owner-withdrawals /reports /audit; do
  echo "$(curl -s -o /dev/null -w '%{http_code}' -b emp.jar "$E$p")  GET $p"   # 403
done
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar "$E/lines"        # 200 (عرض)
curl -s -o /dev/null -w '%{http_code}\n' -b emp.jar "$E/line-payments" # 200 (دفعات الخطوط تبقى للموظف)
```

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
- ملفات الأصول تُخدَم برقم نسخة من `filemtime` (`asset()` يضيف `?v=...`) فلا يبقى في المتصفح
  ملف JS/CSS قديم بعد أي تعديل (لا يوجد live reload في هذا المشروع).
- شبكة أمان على الخادم: `PackagesController::store()` يفحص حقل `id` المخفي، فإن وُجد يكمّل العملية
  كتعديل (`update()`) بدل رفضها كاسم مكرر — يحمي من صفحة قديمة يرسل فيها نموذج التعديل إلى مسار الإضافة.

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

---

## مطابقة أرقام البحث والصندوق (تعديل لاحق)

قاعدة المطابقة المتفق عليها: **الصف يبقى معروضًا بإجماليه الأصلي، والأرقام المالية تتبع حالة الفاتورة**
(الملغاة لا تدخل في أي مجموع، والمتبقي = الإجمالي − المدفوع − التحصيلات النشطة).

- **شاشة البحث** (`SearchService::search()` + `search/index.php`): صف الفاتورة يعرض الآن شارة الحالة
  (نشطة/ملغاة) والمتبقي عبر `Sale::remainingSql('s')` — نفس معادلة قائمة المبيعات وصفحة الفاتورة وصفحة
  الموزع، فلا تظهر فاتورة ملغاة بإجماليها بلا بيان حالتها. لا تُعِد حساب المتبقي داخل العرض.
- **شاشة الصندوق**: `CashTransaction::excludeCancelledSql()` تستبعد من **الأرقام** أثر العمليات الملغاة:
  الحركة العكسية (لا تُنشأ إلا عند الإلغاء)، ومقبوضات فاتورة أصبحت ملغاة، وتحصيل أصبح معكوسًا.
  تُستخدم في `CashTransaction::summary()` (الإجماليات + «الحركات حسب المصدر») وفي بطاقات «الكل»
  (`CashController` يمرّر `summary()` بلا فلاتر عبر `$allSummary`) وفي `CashService::totals()`.
  - الشرط يقارن نوع المرجع عبر `COALESCE(reference_type, '')`: حركة بلا مرجع (`reference_type = NULL`)
    لو قارنّاها مباشرة تُنتج `NULL` ويُستبعد الصف كله خطأً من الأرقام، والمقصود استبعاد أثر الملغاة فقط.
  - **العدد وترقيم الصفحات وسجل الحركات لم تتغيّر**: كل الحركات تُعرض وتُصدَّر وتُنسخ احتياطيًا كما هي،
    والاستبعاد للعرض الرقمي فقط (لذلك قد لا يساوي مجموع الصفوف الظاهرة إجمالي الفترة المعروضة).
  - **`CashService::balance()` لم يتغيّر**: الرصيد الفعلي هو نفسه لأن كل حركة مُستبعدة تقابلها حركة عكسية
    مُستبعدة بالمثل — فلا تُغيّر `balance()` لاحقًا وإلا انفصل الرصيد عن حركات السجل.
  - **لوحة التحكم**: بطاقتا «مقبوضات الصندوق» و«مدفوعات الصندوق» تقرآن `CashService::totals()`، وقد صار
    هذا التابع يطبّق نفس شرط الاستبعاد (`CashTransaction::excludeCancelledSql()`) — فلا يفترق رقما اللوحة
    عن بطاقات شاشة الصندوق. بطاقة «ديون الموزعين» تُصفّي الفواتير النشطة أصلًا عبر `Distributor::balances()`.
    لا تُعِد إلى `totals()` قراءة بلا استبعاد، ولا تُلغِ الاستبعاد من `summary()` وإلا انفصلت اللوحة عن الصندوق.
- تحقق سريع بعد أي تعديل مسّ هذه الشاشات: أنشئ فاتورة نقدية ثم ألغِها — يجب أن تختفي مقبوضاتها وحركتها
  العكسية من بطاقات الصندوق ومن جدول المصادر (وتبقى الحركتان ظاهرتين في سجل الحركات)، ويبقى «الرصيد
  الحالي» كما هو قبل الإلغاء، وتظهر الفاتورة في البحث بشارة «ملغاة» ومتبقي 0. ويجب أن يتساوى رقما
  «مقبوضات/مدفوعات الصندوق» في **لوحة التحكم** مع بطاقتي «إجمالي المقبوضات/المدفوعات (بدون الملغاة)»
  في شاشة الصندوق بعد الإلغاء، وأن ينقص «ديون الموزعين» بمقدار متبقي الفاتورة الملغاة.
