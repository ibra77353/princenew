# AGENTS.md — ملاحظات التشغيل والصيانة (شبكة البرنس)

نظام إدارة شبكة البرنس: **Native PHP 8.2 + MySQL 8.0** بأسلوب MVC، بدون أي إطار عمل أو Node.js.
هذا الملف يوثّق ما ليس واضحًا من الملفات الأخرى (كيف تُشغَّل البيئة هنا وماذا يجب الحذر منه).

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

### 4. مجلد النسخ الاحتياطية
`database/backups` مجلد بيانات (volume) ويجب أن يملكه `www-data` — تكفّلت به أوامر التشغيل في `docker-compose.base44.yml`.
لا تُحذف مجلدات البيانات (volumes) لتحديث التبعيات.

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

## التحقق السريع بعد أي تعديل

```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://localhost:3000/        # 302 → /setup أو /login
curl -s http://localhost:3000/setup | grep -oE "<h1>[^<]*</h1>"                         # يظهر نموذج إعداد المدير
docker compose -f docker-compose.base44.yml logs web | grep -iE "fatal|error|warning"   # لا أخطاء PHP
docker compose -f docker-compose.base44.yml ps                                          # web/db healthy، db-init exited (0)
```

اختبار وظيفي كامل بعد إنشاء المدير: تسجيل الدخول، إضافة باقة/مخزون، بيع نقدي وآجل، تحصيل جزئي،
إلغاء فاتورة مع حركات العكس، مصروف/سحب/دفعة خط، مطابقة رصيد الصندوق والتقارير، النسخ الاحتياطي والاستعادة.
