# دليل النشر على استضافة مجانية (InfinityFree) 🚀

هذا الدليل ينشر متجر نداف على **[InfinityFree](https://infinityfree.com)** — استضافة PHP مجانية بدون إعلانات، تدعم Laravel وMySQL وشهادة SSL مجانية. **لا حاجة لأي تعديل على الكود** — نفس المشروع يعمل محليًا على SQLite وعلى الاستضافة على MySQL بمجرد تغيير ملف الإعدادات.

> ⚠️ ملاحظة صريحة: الاستضافة المجانية لها حدود استخدام يومية وسرعة أقل. مثالية للإطلاق والبداية، وعند نمو المتجر تنتقل لاستضافة مدفوعة رخيصة (2-3$/شهر) بنفس الكود ونفس خطوات الرفع تقريبًا.

---

## الخطوة 1 — إنشاء الحساب والاستضافة (10 دقائق)

1. افتح [infinityfree.com](https://infinityfree.com) وسجّل حسابًا مجانيًا (بريد إلكتروني فقط).
2. من لوحة التحكم اختر **Create Account** لإنشاء موقع:
   - **Domain**: اختر نطاقًا فرعيًا مجانيًا مثل `nadaf.free.nf` أو `nadafstore.great-site.net`.
   - اختر PHP **8.3** إن طُلب.
3. بعد الإنشاء، افتح تفاصيل الحساب واحتفظ بهذه البيانات (ستجدها في صفحة الحساب):
   - **FTP** : Hostname / Username / Password
   - **MySQL**: Database Name / User / Host / Password (أنشئ قاعدة من تبويب **MySQL Databases** أولًا)
   - **Control Panel URL** (VistaPanel)

## الخطوة 2 — تجهيز حزمة النشر على جهازك

المشروع في `A:\nadaf` و PHP في `E:\tools\php83` (الأوامر أدناه مكتوبة على هذا الأساس).

### أ) حدّث إعدادات الإنتاج

أنشئ نسخة إعدادات للنشر — عدّل `.env` مؤقتًا أو أنشئ `.env.production`.

> **الأسهل:** انسخ القالب الجاهز `‎.env.production.example` (مرفق في المشروع، وكل قيمه
> placeholders بلا أي سرّ) ثم عدّل القيم بين `< >`. ما يلي هو نفس محتواه للمرجعية:

```env
APP_NAME="NADAF | نداف"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nadaf.free.nf        # رابط موقعك الفعلي

APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# نفس المفاتيح المحلية — انسخها من .env المحلي
APP_KEY=base64:xxxxxxxx              # نفس مفتاحك المحلي أو ولّد جديدًا

# قاعدة بيانات الاستضافة (من صفحة MySQL Databases)
DB_CONNECTION=mysql
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=ifX_XXXXXXX_nadaf
DB_USERNAME=ifX_XXXXXXX
DB_PASSWORD=كلمة-مرور-قاعدة-البيانات

FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync

# البريد — مثال Brevo المجاني (300 رسالة/يوم)
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=بريدك@في-brevo
MAIL_PASSWORD=مفتاح-smtp-من-brevo
MAIL_FROM_ADDRESS=store@yourdomain.com
MAIL_FROM_NAME="NADAF"
```

### ب) تأكد من بناء الأصول محليًا

```bash
npm run build        # يملأ مجلد public/build
```

### ج) افحص الجاهزية قبل الضغط

```bash
E:\tools\php83\php.exe artisan deploy:check
```

يفحص الأمر **البيئة نفسها** لا الكود: `APP_ENV` و`APP_DEBUG` و`APP_KEY` و`APP_URL`
وإعداد البريد واتصال قاعدة البيانات وصلاحيات المجلدات، ويُبلّغ عن نسخ
`‎.env.bak*` المتروكة في الجذر وعن متجر بلا منتجات.

- الفحوص **الحرجة** تُخرج الأمر برمز فشل (1) — أصلحها قبل المتابعة.
- التحذيرات لا تمنع النشر لكن تستحق النظر.

يمكن إيقاف النشر آليًا عند أي فشل:

```bash
E:\tools\php83\php.exe artisan deploy:check || exit 1
```

### د) أنشئ ملف ZIP للرفع

مضمون الحزمة — **كل محتويات المشروع** باستثناء: `node_modules` و `.git` و `storage/logs/*` و `tests` و `E:\tools`.

> ⚠️ **استثنِ هذه تحديدًا — كلها لا تخصّ الزائر وتزيد زمن الرفع:**
> - `‎.env.bak*` — نسخ احتياطية تحوي `APP_KEY` وأسرارًا. رفعها يعني نشر مفاتيحك.
> - `database/backups/` — نسخ قاعدة البيانات (فيها جلسات وطلبات).
> - `.workbuddy-ai/` و `.kilo/` و `.mimosa/` و `.zcode/` — أدوات التطوير.
> - `PROJECT-ANALYSIS.html` و `_m_home.png` و `NADAF-Chat-Concept.html`
>   و `admin-preview.html` و `_legacy-views/` — تقارير ومعاينات قديمة
>   (حوالي نصف ميغابايت لا يحتاجها الزائر).

أسهل طريقة: انسخ المشروع لمجلد جديد واحذف المستثنيات ثم اضغطه، أو استخدم أي أداة ضغط. **مهم جدًا: مجلد `vendor` يجب أن يُرفع كاملًا** (الاستضافة المجانية لا تدير Composer).

> ⚠️ **مجلد `public/storage` يجب أن يُرفع أيضًا** — فهو يحوي كل الصور والوسائط المرفوعة
> (صور المنتجات والأقسام والسلايدر وأيقونات وسائل الدفع). لا تستثنِه أبدًا وإلا
> ظهرت المنتجات بدون صور. هذا المجلد **حقيقي** (ليس رابطًا رمزيًا) لأن استضافات
> FTP المجانية لا تنشئ روابط رمزية — ولهذا يكتب التطبيق فيه مباشرةً.

> ملاحظة: ملف `.env` — ارفعه أيضًا (بعد تعديله لقيم الإنتاج أعلاه). ملف `.htaccess` الموجود في `public/` يُدير الروابط تلقائيًا.

## الخطوة 3 — الرفع عبر FTP

1. نزّل برنامج **FileZilla** (مجاني).
2. اتصل ببيانات FTP من الخطوة 1 (المنفذ 21).
3. في الموقع البعيد: افتح مجلد **`htdocs`** — هذا هو جذر الموقع.
4. **انقل محتويات مجلد `public` من الحزمة إلى `htdocs` مباشرة** (index.php, .htaccess, build/, images/, icons/, manifest.webmanifest, sw.js, offline.html, favicon.svg ...).
5. **انقل باقي مجلدات المشروع** إلى المجلد الأب لـ htdocs (مثل `htdocs/../` أي المستوى الأعلى):
   - `app`, `bootstrap`, `config`, `database`, `lang`, `resources`, `routes`, `storage`, `vendor`, `composer.json`, `.env`
6. النتيجة النهائية على الاستضافة:

```
/  (المستوى الأعلى)
├── htdocs/          ← محتويات public
│   ├── index.php
│   ├── .htaccess
│   ├── build/ ...
├── app/
├── bootstrap/
├── config/
├── database/        (database.sqlite غير مطلوب)
├── lang/
├── resources/
├── routes/
├── storage/
├── vendor/
└── .env
```

7. عدّل ملف **`htdocs/index.php`** ليطابق المسارات الجديدة:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
```

> ⚠️ تنبيه: الدالة الصحيحة هي `handleRequest()` — وليس `runImmediateHandle()` (غير موجودة في Laravel وستُنتج خطأ فادح).

> إن اختلف هيكل الملف عن نسختك، يكفي تعديل المسارين: `__DIR__.'/../vendor/autoload.php'` و `__DIR__.'/../bootstrap/app.php'`.

## الخطوة 4 — إعداد قاعدة البيانات

على جهازك المحلي، ولّد SQL للمخطط والبيانات ثم استوردها على الاستضافة:

### أ) صدّر المخطط والبيانات من SQLite محليًا

```bash
E:\tools\php83\php.exe artisan migrate:fresh --seed --force   # يهيّئ قاعدة البيانات المحلية فقط
```

> ⚠️ لا يوجد أمر `artisan migration:generate` في Laravel افتراضيًا — تصدير المخطط إلى SQL يحتاج حزمة خارجية. **الطريقة الأسهل والأنجح مع InfinityFree: استخدم phpMyAdmin في VistaPanel وأنشئ الجداول عبر استيراد ملف SQL جاهز.** الأسهل عمليًا هو **الخيار (ب) أدناه**: دع Laravel ينفّذ migrations بنفسه من صفحة `setup.php` المؤقتة — نفس المخطط، بدون تصدير أو تحويل.

### ب) البديل العملي الموصى به (بدون تعقيد تصدير)

بما أن InfinityFree **يمنع SSH/artisan**، الأسهل إعداد حزمة تهيئة ويب مرة واحدة:

1. ارفع مؤقتًا ملف `setup.php` في `htdocs` بمحتوى يدير الترحيلات عبر الويب (استخدم حزمة مثل `laravel/setup` أو شغّل الترحيلات من صفحة مؤقتة بسيطة تستدعي `Artisan::call('migrate')` و `Artisan::call('db:seed')`).
2. افتح `https://nadaf.free.nf/setup.php` من المتصفح مرة واحدة.
3. **احذف الملف فورًا بعد نجاحه.**

نموذج `setup.php` (احذفه بعد الاستخدام!):

```php
<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
echo Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
echo "DONE - احذف هذا الملف الآن!";
```

## الخطوة 5 — الإعدادات النهائية على الاستضافة

1. **شهادة SSL**: من VistaPanel → **Free SSL Certificates** → فعّل Let's Encrypt لنطاقك، ثم من **HTTPS Enforcement** فعّل التحويل لـ HTTPS (مطلوب لـ PWA والإشعارات).
2. **إصدار PHP**: تأكد أن الحساب على PHP **8.1+** (يفضل 8.3).
3. **سخّن التخزين المؤقت** — يُسرّع كل صفحة بشكل ملحوظ (إعدادات، مسارات، قوالب،
   أيقونات). الاستضافة المجانية بلا SSH، فاستخدم ملفًا مؤقتًا بنمط الخطوة 4:

   ```php
   <?php
   // optimize-temp.php في الجذر — شغّله مرة ثم احذفه
   require __DIR__.'/vendor/autoload.php';
   $app = require_once __DIR__.'/bootstrap/app.php';
   $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   header('Content-Type: text/plain; charset=utf-8');
   echo Illuminate\Support\Facades\Artisan::call('optimize');
   echo "\nDONE - احذف هذا الملف الآن!";
   ```

   > ⚠️ التخزين المؤقت **يجمّد** `.env`: أي تعديل لاحق على الإعدادات لا يُقرأ
   > حتى تمسحه. بعد أي تعديل أنشئ ملفًا يستدعي `optimize:clear` أو احذف
   > `bootstrap/cache/config.php` و `bootstrap/cache/routes-*.php`.

4. افتح موقعك: `https://nadaf.free.nf` — يجب أن يعمل المتجر.
5. لوحة الأدمن: `https://nadaf.free.nf/admin` بنفس بيانات الأدمن المحلية.
6. من **الإعدادات العامة** في اللوحة: فعّل إشعارات تيليجرام (اختبرها) والبريد، وعدّل بيانات الدفع والتواصل الحقيقية.

## الخطوة 6 — ما بعد الإطلاق

- **جرّب طلبًا حقيقيًا** من هاتفك وتأكد من وصول إشعار تيليجرام والبريد.
- **PWA**: افتح المتجر من هاتف أندرويد/كمبيوتر → سيظهر زر «تثبيت التطبيق» في المتصفح.
- **النسخ الاحتياطي**: نزّل نسخة دورية من قاعدة البيانات (phpMyAdmin → Export) وملفات `public/storage` (الصور المرفوعة: المنتجات والأقسام والسلايدر).
- **حدود المجاني**: راقب استهلاكك من لوحة InfinityFree (Daily Hits) — إن اقتربت من الحد أو تباطأ الموقع، هذا موعد الترقية لخطة مدفوعة.

## مشاكل شائعة وحلولها

| المشكلة | الحل |
|---------|------|
| صفحة بيضاء أو 500 | تأكد من تعديل مسارات `htdocs/index.php` ومن رفع `.env` |
| خطأ اتصال قاعدة البيانات | تحقق من DB_HOST/DB_DATABASE/DB_USERNAME من صفحة MySQL في VistaPanel |
| الصور المرفوعة لا تظهر | تأكد من **رفع مجلد `public/storage` كاملًا** إلى `htdocs` (يحوي صور المنتجات والأقسام والسلايدر). لو رفعت المجلد فارغًا أو كرابط رمزي انكسر كل ما رُفع سابقًا — أعد رفعه من حزمتك |
| 419 Page Expired | تأكد أن دومينك في `APP_URL` وأن HTTPS مفعل |
|Livewire لا يعمل (لا ردّ للأزرار)| تأكد من رفع مجلد `vendor` كاملًا ومن SSL (Livewire يتطلب HTTPS أو localhost) |
| رفع ملفات كبيرة يفشل | راجع القسم أدناه — المجاني يحدّ الرفع ~10MB |

## ⚠️ رفع فيديو السلايدر — الحدود الأربعة

فيديو يصل إلى دقيقة قد يبلغ عشرات الميغابايت، وهو يمرّ بأربع طبقات، وأصغرها
هو الذي يحكم. وكانت هذه الطبقات متباعدة (١٢ و١٦ و١٢ و٢٠ ميغا)، فيُقبل الملف
في الواجهة ثم يُرفض في الخلفية **بلا رسالة تُذكر السبب** — وهذا هو الغموض
الذي أُصلح.

| الطبقة | الملف | الحد المضبوط |
|---|---|---|
| PHP — الملف الواحد | `php.ini` أو `public/.user.ini` | `upload_max_filesize = 128M` |
| PHP — الطلب كاملًا | نفسه | `post_max_size = 136M` |
| Livewire | `config/livewire.php` | `max:131072` (كيلوبايت) |
| Filament | `SlideResource::MAX_UPLOAD_MB` | 128 |

**القاعدة**: `post_max_size` أكبر من `upload_max_filesize` دائمًا — الطلب يحمل
الملف مع بقية الحقول، ولو تساويا لرُفض الطلب بسبب تلك الزيادة وحدها.

**على استضافة لا تتيح تعديل `php.ini`**: ارفع `public/.user.ini` (يعمل مع
PHP-FPM و CGI). ولا تستخدم `php_value` في `.htaccess` — تُسقط الطلب بـ500 إن
كان PHP يعمل كـFPM لا كوحدة أباتشي.

**على InfinityFree تحديدًا**: الحد ~10MB ولا يمكن رفع الملف، فلا فيديو دقيقة
هناك. الخيارات: ضع الفيديو على خدمة خارجية وضع رابطها في حقل «الرابط عند
النقر»، أو استخدم صورة بدل الفيديو، أو ارتقِ لاستضافة مدفوعة.

**كيف تعرف الحدّ الفعلي؟** افتح **السلايدر ← شريحة ← الوسيط** في اللوحة: إن
كان حدّ السيرفر أقل من المطلوب يظهر تنبيه صريح بالحدّ الحقيقي.


## ملخص التكلفة: 0$ ✅

| العنصر | الخدمة | التكلفة |
|--------|--------|---------|
| الاستضافة + MySQL + SSL | InfinityFree | مجاني |
| النطاق | نطاق فرعي من InfinityFree | مجاني (يمكن ربط دومين مدفوع لاحقًا) |
| البريد الإلكتروني | Brevo أو Gmail SMTP | مجاني حتى 300/يوم |
| إشعارات الطلبات | تيليجرام بوت | مجاني دائمًا |
| البنية التقنية | Laravel + Filament + Livewire | مفتوح المصدر |
