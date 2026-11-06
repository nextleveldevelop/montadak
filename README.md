# Montadak | منتداك

## نبذة عن المشروع

**منتداك (Montadak)** هو سكربت منتدى ومنصة مجتمعية عربية يعمل باستخدام PHP وMySQL، ويدعم واجهة عربية باتجاه RTL من اليمين إلى اليسار.

يجمع المشروع بين وظائف المنتديات التقليدية وبعض خصائص المجتمعات الرقمية الحديثة، مثل ملفات الأعضاء الشخصية، والمنشورات القصيرة، والمتابعة، والإشعارات، والرسائل الخاصة، وأدوات الإشراف والإدارة.

صُمّم المشروع ليعمل على الاستضافات التقليدية التي تدعم PHP وMySQL، ولا يحتاج إلى Node.js أو خادم تطبيقات منفصل.

## أهم الخصائص

### المنتديات والنقاشات
- إنشاء تصنيفات وأقسام للمنتديات.
- إنشاء المواضيع وإضافة الردود.
- تثبيت المواضيع وإغلاقها وإخفاؤها وأرشفتها.
- التحكم في خصوصية المواضيع وإتاحة بعضها لأعضاء محددين.
- عرض المشاهدات والردود والإعجابات وآخر نشاط.
- تقسيم المواضيع والردود إلى صفحات.
- مؤشرات المواضيع الجديدة والمقروءة.
- الاستطلاعات والتصويت.
- المفضلة للأقسام والمواضيع.
- البحث والوسوم ومتابعة الوسوم.
- تنسيق محتوى المشاركات والاقتباس.

### المجتمع والأعضاء
- تسجيل الأعضاء وتسجيل الدخول والخروج.
- إعدادات الحساب والملف الشخصي.
- ملفات شخصية تتضمن النبذة والصورة الرمزية والتوقيع وبعض البيانات الاختيارية.
- صور خلفية للملفات الشخصية.
- متابعة الأعضاء وقوائم الأعضاء.
- منشورات قصيرة (مواضيع مصغرة) بحد أقصى 300 كلمة، مع الوسوم.
- الإعجاب بالمحتوى ومشاركته وحفظه.
- الرسائل الخاصة والمحادثات.
- الإشعارات.
- عرض الأعضاء المتصلين والزوار.
- نقاط الأعضاء والألقاب والشارات وبعض مؤشرات النشاط والتقييم.
- اختيار المنطقة الزمنية لكل عضو.

### الإشراف والإدارة
- صلاحيات المدير وصلاحيات طاقم الإشراف على مستوى الأقسام.
- مشرفو الأقسام ونواب المشرفين والمراقبون.
- تعيين طاقم الإشراف للأقسام والتصنيفات.
- استقبال البلاغات وإدارتها.
- التحذيرات والكتم المؤقت وسجل الإجراءات الإشرافية.
- حظر الأعضاء وإدارة الحسابات.
- إدارة الشارات وبعض خصائص الأعضاء.
- إدارة المنتديات وترتيبها وإعداداتها.
- إعدادات هوية المنتدى، مثل الاسم والوصف المختصر والشعار وطريقة عرضه.
- إحصائيات وصفحات الإدارة.

### تحسين الظهور في محركات البحث وأدوات الموقع
- إنشاء `robots.txt` بشكل ديناميكي.
- إنشاء `sitemap.xml` بشكل ديناميكي، مع إمكانية تفعيلها أو تعطيلها من لوحة الإدارة.
- بيانات وصفية وبنية مناسبة لفهرسة صفحات المنتدى.
- واجهات برمجية للنقاش الحي والإشعارات.

> تختلف الخصائص المتاحة حسب صلاحيات العضو وإعدادات المنتدى.

## التقنيات المستخدمة

- **الخلفية:** PHP
- **قاعدة البيانات:** MySQL أو MariaDB
- **الاتصال بقاعدة البيانات:** MySQLi
- **الواجهة:** HTML وCSS وJavaScript
- **اتجاه الواجهة:** العربية RTL
- **التخزين:** الملفات المحلية للصور والملفات المرفوعة
- **خادم الويب:** يوصى باستضافة Apache تدعم ملفات `.htaccess`

## المتطلبات

- PHP بإصدار **7.4 أو أحدث**.
- الإضافات المطلوبة في PHP: `mysqli` و`mbstring` و`json` و`session`.
- إضافات موصى بها: `gd` و`fileinfo` لمعالجة الصور والتحقق من الملفات المرفوعة.
- MySQL بإصدار **5.6 أو أحدث** أو MariaDB بإصدار **10.1 أو أحدث**، مع دعم InnoDB.
- يوصى باستضافة Apache تدعم `.htaccess`.
- يوصى بشدة باستخدام HTTPS في الموقع الفعلي.

يتحقق معالج التثبيت من إصدار PHP والإضافات المطلوبة وصلاحيات المجلدات والاتصال بقاعدة البيانات وتوافق إصدارها.

## طريقة التثبيت

1. أنشئ قاعدة بيانات MySQL **جديدة وفارغة** ومستخدمًا لها من لوحة تحكم الاستضافة.
2. ارفع ملفات منتداك إلى الاستضافة. يمكن تثبيته في المجلد الرئيسي للدومين أو داخل مجلد فرعي.
3. تأكد من أن PHP يستطيع الكتابة داخل المجلدات التالية. تكون الصلاحيات غالبًا `755` أو `775` حسب الاستضافة:
   - `config/`
   - `assets/images/avatars/`
   - `assets/images/forums/`
   - `assets/images/backgrounds/`
   - `assets/uploads/`
4. افتح الرابط التالي من المتصفح: `https://your-domain.example/install.php`.
5. اتبع خطوات معالج التثبيت:
   - فحص متطلبات الخادم.
   - إدخال بيانات الاتصال بقاعدة البيانات.
   - إدخال هوية المنتدى وإنشاء حساب المدير الأول.
6. بعد انتهاء التثبيت، سجّل الدخول باستخدام حساب المدير الذي أنشأته.

يقوم معالج التثبيت بإنشاء `config/config.php`، وتجهيز جداول قاعدة البيانات، وإنشاء حساب المدير الأول، ثم إنشاء ملف `config/installed.lock`. ويحاول حذف `install.php` بعد نجاح التثبيت. **إذا بقي ملف التثبيت على الخادم، فيجب حذفه يدويًا.**

إذا فتحت صفحات المنتدى قبل اكتمال الإعداد، فسيتم تحويلك إلى صفحة التثبيت متى كان ملف التثبيت متوفرًا.

## الإعدادات

ينشئ معالج التثبيت ملف `config/config.php` تلقائيًا. ويوجد الملف `config/config.sample.php` داخل المشروع كنموذج مرجعي.

تتضمن الإعدادات:
- خادم قاعدة البيانات والمنفذ واسم القاعدة واسم المستخدم وكلمة المرور.
- اسم الموقع الأولي.
- المنطقة الزمنية الافتراضية.
- وضع عرض الأخطاء.
- إمكانية تحديد رابط أساسي مخصص للموقع.

لا ترفع ملف `config/config.php` إلى GitHub ولا تشاركه علنًا، لأنه يحتوي على بيانات الاتصال بقاعدة البيانات. يجب إبقاء `APP_DEBUG` معطلًا في الموقع الفعلي.

## قاعدة البيانات والتحديثات

- يحتوي `install/schema.sql` على البنية الأولية لقاعدة البيانات، ويجب استخدامه مع قاعدة بيانات **جديدة وفارغة فقط**.
- يتضمن التطبيق إجراءات لفحص بنية قاعدة البيانات وإضافة الجداول والأعمدة الخاصة بالخصائص اللاحقة أثناء تشغيل التطبيق.
- قبل تحديث نسخة موجودة، احتفظ بنسخة احتياطية من الملفات وقاعدة البيانات.
- عند التحديث، احتفظ بملف `config/config.php` وملف قفل التثبيت الحاليين.
- لا تستورد المخطط الأولي فوق قاعدة بيانات تحتوي على بيانات منتدى حقيقي.

## الأمان والنشر

يتضمن المشروع مجموعة من إجراءات الحماية، من بينها:
- تشفير كلمات المرور باستخدام واجهات PHP المخصصة لتجزئة كلمات المرور.
- التحقق من رموز CSRF في النماذج والعمليات التي تغيّر البيانات.
- استخدام الاستعلامات المجهزة في عمليات قاعدة البيانات.
- تقوية إعدادات الجلسات والتحقق من بصمة الجلسة.
- التحقق من مصدر الطلبات وتحديد أساسي لمعدل طلبات POST لكل جلسة.
- ترويسات HTTP أمنية.
- دوال مساعدة لمعالجة النصوص وإخراجها بأمان.
- التحقق من الصور وإعادة معالجتها في مسارات رفع الصور المدعومة.
- تقييد الوصول إلى المجلدات الحساسة عبر `.htaccess`.

هذه الإجراءات لا تغني عن إعداد الخادم بصورة آمنة أو مراجعة الأمان بشكل مستمر. قبل إطلاق المنتدى:
- فعّل HTTPS.
- حدّث PHP وقاعدة البيانات باستمرار.
- استخدم كلمة مرور قوية وفريدة لحساب المدير.
- حافظ على سرية ملفات الإعدادات والنسخ الاحتياطية.
- أنشئ نسخًا احتياطية منتظمة لقاعدة البيانات والملفات المرفوعة.
- راجع صلاحيات مجلدات الرفع وحدود الاستضافة.
- اختبر التسجيل والنشر ورفع الصور والإشراف على استضافتك قبل فتح المنتدى للجمهور.

## بنية المشروع

```text
montadak/
├── admin/                 # صفحات لوحة الإدارة
├── assets/
│   ├── css/               # ملفات التنسيق
│   ├── js/                # ملفات JavaScript
│   ├── images/             # الصور الرمزية وصور الأقسام والخلفيات والشعار
│   └── uploads/            # الملفات المرفوعة
├── config/                # إعدادات التطبيق الخاصة
├── includes/              # التهيئة والاتصال بقاعدة البيانات والدوال والتخطيط والإشعارات
├── install/               # مخطط قاعدة البيانات الأولي
├── install.php            # معالج التثبيت
├── index.php              # الصفحة الرئيسية للمنتدى
├── forum.php              # صفحة القسم
├── thread.php             # صفحة الموضوع والردود
├── profile.php            # الملف الشخصي للعضو
└── README.md
```




---

**Montadak — منتداك**  
A forum platform for building Arabic communities.  
منصة لبناء المجتمعات العربية.





An Arabic-first, RTL community forum platform built with PHP and MySQL.  
منصة منتديات ومجتمع رقمي عربية، تدعم اتجاه الكتابة من اليمين إلى اليسار.

---

# 1. English

## Overview

**Montadak** is a self-hosted PHP forum script designed for Arabic-speaking communities. It combines familiar discussion-forum functionality with selected social-community features, member profiles, moderation tools, and administration controls.

The application is built for traditional PHP hosting environments. It does not require Node.js or a separate application server.

## Main Features

### Forums and discussions
- Forum categories and individual forums.
- Discussion topics and threaded replies.
- Pinned, locked, hidden, and archived topics.
- Topic visibility controls, including access restricted to selected members.
- Topic views, replies, likes, and activity information.
- Pagination and topic read/unread indicators.
- Polls and voting.
- Topic and forum favourites.
- Search, tags, and tag following.
- Rich-text post content and quoting.

### Community and member features
- Member registration, login, logout, and account settings.
- Member profiles with biography, avatar, signature, and optional profile details.
- Profile background images.
- Follow relationships and member lists.
- Short community posts (mini-topics), with a 300-word limit and tags.
- Likes, shares, and saved content.
- Private messages and conversations.
- Notifications.
- Online-member and visitor indicators.
- Member points, titles, badges, and activity/rating-related features.
- Per-member timezone preferences.

### Moderation and administration
- Administrator and forum-level staff permissions.
- Forum supervisors, deputy moderators, and moderators.
- Forum and category staff assignment.
- Reports and moderation queues.
- Warnings, temporary mutes, and moderation history.
- Member blocking and account-management tools.
- Badge and member-related administration.
- Forum management, ordering, and forum-specific settings.
- Site identity settings, including the forum name, tagline, logo display mode, and logo upload.
- Administrative statistics and management pages.

### SEO and site utilities
- Dynamically generated `robots.txt`.
- Dynamically generated `sitemap.xml`, with an administration setting to enable or disable it.
- Search-engine-friendly site metadata and forum structure.
- Live discussion/chat endpoints and notification APIs.

> Feature availability and permissions depend on the signed-in member's role and the forum's configuration.

## Technology

- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Database access:** MySQLi
- **Frontend:** HTML, CSS, and JavaScript
- **Layout:** Arabic RTL interface
- **Storage:** Local filesystem for uploaded images and assets
- **Web server:** Apache-compatible hosting with `.htaccess` support recommended

## Requirements

- PHP **7.4 or newer**.
- Required PHP extensions: `mysqli`, `mbstring`, `json`, and `session`.
- Recommended extensions: `gd` and `fileinfo` for safer image processing and uploads.
- MySQL **5.6+** or MariaDB **10.1+**, using InnoDB.
- Apache hosting with `.htaccess` support is recommended.
- HTTPS is strongly recommended for production.

The installer checks the PHP version, required extensions, writable directories, database connection, and database compatibility.

## Installation

1. Create a **new, empty** MySQL database and a database user through your hosting control panel.
2. Upload the Montadak files to your web hosting account. The forum can be installed in the domain root or a subdirectory.
3. Ensure the following directories are writable by PHP (commonly `755` or `775`, depending on the hosting provider):
   - `config/`
   - `assets/images/avatars/`
   - `assets/images/forums/`
   - `assets/images/backgrounds/`
   - `assets/uploads/`
4. Visit `https://your-domain.example/install.php`.
5. Follow the installer:
   - Server requirements check.
   - Database connection details.
   - Forum identity and initial administrator account.
6. After installation, sign in with the administrator account you created.

The installer creates `config/config.php`, installs the database schema, prepares the initial administrator, and writes `config/installed.lock`. It attempts to remove `install.php` after a successful installation. **If the installer file remains on the server, delete `install.php` manually.**

If the application is opened before configuration is complete, it redirects to the installer when the installer is available.

## Configuration

The installer generates `config/config.php` automatically. The repository includes `config/config.sample.php` as a reference.

Configuration includes:
- Database host, port, database name, username, and password.
- Initial site name.
- Default timezone.
- Debug mode.
- Optional base URL override.

Do not commit or publicly share `config/config.php`. It contains database credentials and must remain private. Keep `APP_DEBUG` disabled on a production site.

## Database and upgrades

- `install/schema.sql` contains the initial database schema and is intended for a **new, empty database only**.
- The application includes schema-check/upgrade routines that add later feature tables and columns during application startup.
- For an existing installation, back up both the files and database before replacing application files.
- Preserve your existing `config/config.php` and installation lock file when upgrading.
- Do not import the initial schema over a database containing live forum data.

## Security and deployment notes

The codebase includes several security measures, including:
- Password hashing through PHP's password-hashing API.
- CSRF-token checks in state-changing forms/actions.
- Prepared database statements in application database operations.
- Session hardening and session fingerprint checks.
- Same-origin checks and a basic per-session POST flood limit.
- HTTP security headers.
- Output escaping helpers.
- Image validation and image re-encoding in supported upload flows.
- Access restrictions for sensitive directories through `.htaccess`.

These measures do not replace secure server configuration or ongoing security review. Before going live:
- Enable HTTPS.
- Keep PHP and the database server updated.
- Use a strong, unique administrator password.
- Keep configuration files and backups private.
- Back up the database and uploaded files regularly.
- Review upload-directory permissions and hosting limits.
- Test registration, posting, uploads, and moderation workflows on your hosting environment.

## Project structure

```text
montadak/
├── admin/                 # Administration pages
├── assets/
│   ├── css/               # Stylesheets
│   ├── js/                # Frontend scripts
│   ├── images/             # Avatars, forum images, backgrounds, logo
│   └── uploads/            # Uploaded content
├── config/                # Private application configuration
├── includes/              # Bootstrap, database, helpers, layout, notifications
├── install/               # Initial SQL schema
├── install.php            # Web-based installer
├── index.php              # Forum homepage
├── forum.php              # Forum listing
├── thread.php             # Topic and replies
├── profile.php            # Member profile
└── README.md
```

