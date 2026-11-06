<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];
$imagesDir = __DIR__ . '/../assets/images';
$allowed = [
    'image/png'  => ['ext' => 'png', 'type' => IMAGETYPE_PNG],
    'image/jpeg' => ['ext' => 'jpg', 'type' => IMAGETYPE_JPEG],
    'image/gif'  => ['ext' => 'gif', 'type' => IMAGETYPE_GIF],
    'image/webp' => ['ext' => 'webp', 'type' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1],
];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'identity') {
        $nm = trim(preg_replace('/\s+/u', ' ', post_str('site_name')));
        $tg = trim(post_str('site_tagline'));
        $md = post_str('brand_mode');
        if (mb_strlen($nm) < 2 || mb_strlen($nm) > 60) {
            $errors[] = 'اسم المنتدى مطلوب (من 2 إلى 60 حرفًا).';
        } elseif (mb_strlen($tg) > 120) {
            $errors[] = 'عنوان المنتدى طويل (120 حرفًا كحد أقصى).';
        } elseif (!in_array($md, ['both', 'logo', 'name'], true)) {
            $errors[] = 'اختر طريقة عرض الهوية.';
        } else {
            site_set('site_name', $nm);
            site_set('site_tagline', $tg);
            site_set('brand_mode', $md);
            site_set('show_tagline', post_str('show_tagline') === '1' ? '1' : '0');
            site_set('sitemap_enabled', post_str('sitemap_enabled') === '1' ? '1' : '0');
            if (!$errors) {
                sync_robots_sitemap();
                flash('success', 'تم حفظ إعدادات المنتدى.');
                redirect('admin/settings.php');
            }
        }
    }

    if ($action === 'robots' || $action === 'robots_default') {
        $raw = $action === 'robots_default' ? default_robots_txt() : (string) (isset($_POST['robots']) && is_string($_POST['robots']) ? $_POST['robots'] : '');
        $txt = str_replace("\r", '', $raw);
        $txt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $txt);
        if (!is_string($txt) || !mb_check_encoding($txt, 'UTF-8')) {
            $errors[] = 'النص يحتوي رموزًا غير صالحة (يجب أن يكون UTF-8).';
        } elseif (strlen($txt) > 5000) {
            $errors[] = 'ملف robots.txt طويل جدًا (5000 حرف كحد أقصى).';
        } else {
            // The Sitemap line is never stored: it is added live when /robots.txt is requested.
            $keep = [];
            foreach (preg_split('/\r\n|\r|\n/', $txt) as $ln) {
                if (stripos(ltrim($ln), 'sitemap:') !== 0) {
                    $keep[] = $ln;
                }
            }
            site_set('robots_txt', rtrim(implode("\n", $keep)));
            sync_robots_sitemap(); // removes a leftover physical robots.txt so only one source remains
            flash('success', $action === 'robots_default' ? 'تمت استعادة robots.txt الافتراضي (SEO).' : 'تم حفظ robots.txt.');
            redirect('admin/settings.php');
        }
    }

    if ($action === 'remove') {
        foreach (['png', 'jpg', 'gif', 'webp'] as $ext) {
            $file = $imagesDir . '/forum_logo_custom.' . $ext;
            if (is_file($file)) {
                @unlink($file);
            }
        }
        flash('success', 'تمت إزالة الشعار المخصص والعودة إلى الشعار الافتراضي.');
        redirect('admin/settings.php');
    }

    if ($action === 'upload') {
        if (!isset($_FILES['forum_logo']) || !is_array($_FILES['forum_logo'])) {
            $errors[] = 'اختر صورة لرفعها.';
        } else {
            $file = $_FILES['forum_logo'];
            if (!isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) {
                $errors[] = 'بيانات الملف غير صالحة.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = ($file['error'] === UPLOAD_ERR_NO_FILE) ? 'اختر صورة أولًا.' : 'تعذر رفع الصورة. تحقق من حد الرفع في الاستضافة.';
            } elseif ((int) $file['size'] < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = 'مصدر الملف غير صالح.';
            } else {
                $imageInfo = @getimagesize($file['tmp_name']);
                $mime = '';
                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = (string) $finfo->file($file['tmp_name']);
                }
                if (!$imageInfo || !isset($allowed[$mime]) || $imageInfo[2] !== $allowed[$mime]['type']) {
                    $errors[] = 'نوع الصورة غير مدعوم. استخدم PNG أو JPG أو GIF أو WebP فقط.';
                } elseif ($imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
                    $errors[] = 'أبعاد الصورة كبيرة جدًا؛ الحد الأقصى 4000 × 4000 بكسل.';
                } elseif (!is_dir($imagesDir) || !is_writable($imagesDir)) {
                    $errors[] = 'مجلد الصور غير قابل للكتابة. تحقق من صلاحيات assets/images.';
                } else {
                    if (!function_exists('imagecreatefromstring')) {
                        $errors[] = 'امتداد GD غير متوفر على الخادم، لا يمكن معالجة الصورة بأمان.';
                    } else {
                        $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
                        if (!$img) {
                            $errors[] = 'تعذرت معالجة الصورة.';
                        } else {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $scale = min(1, 512 / max($w, $h));
                            $nw = max(1, (int) round($w * $scale));
                            $nh = max(1, (int) round($h * $scale));
                            $out = imagecreatetruecolor($nw, $nh);
                            $isJpg = $mime === 'image/jpeg';
                            if ($isJpg) {
                                imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
                            } else {
                                imagealphablending($out, false);
                                imagesavealpha($out, true);
                                imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
                            }
                            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                            $ext = $isJpg ? 'jpg' : 'png';
                            $destination = $imagesDir . '/forum_logo_custom.' . $ext;
                            $ok = $isJpg ? imagejpeg($out, $destination, 88) : imagepng($out, $destination, 6);
                            if (!$ok) {
                                $errors[] = 'فشل حفظ الصورة. تحقق من صلاحيات المجلد.';
                            } else {
                                foreach (['png', 'jpg', 'gif', 'webp'] as $oldExt) {
                                    if ($oldExt !== $ext) {
                                        $old = $imagesDir . '/forum_logo_custom.' . $oldExt;
                                        if (is_file($old)) { @unlink($old); }
                                    }
                                }
                                flash('success', 'تم تحديث شعار المنتدى بنجاح.');
                                redirect('admin/settings.php');
                            }
                        }
                    }
                }
            }
        }
    }
}

$currentLogo = null;
foreach (['png', 'jpg', 'gif', 'webp'] as $ext) {
    if (is_file($imagesDir . '/forum_logo_custom.' . $ext)) {
        $currentLogo = 'assets/images/forum_logo_custom.' . $ext;
        break;
    }
}

$robotsText = (string) site_setting('robots_txt', '');
if (trim($robotsText) === '') {
    $robotsText = default_robots_txt();
}
$robotsLegacy = is_file(__DIR__ . '/../robots.txt');
$modeNow = site_brand_mode();
page_header('إعدادات المنتدى', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('settings'); ?>

<?php foreach ($errors as $error): ?>
    <div class="notice notice-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="panel form-panel">
    <h2>اسم المنتدى وعنوانه</h2>
    <form method="post" action="<?= e(url('admin/settings.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="identity">
        <label for="site_name">اسم المنتدى</label>
        <input type="text" id="site_name" name="site_name" value="<?= e(site_name()) ?>" maxlength="60" required>
        <label for="site_tagline">عنوان المنتدى (وصف قصير يظهر تحت الاسم وفي عنوان الصفحة الرئيسية)</label>
        <input type="text" id="site_tagline" name="site_tagline" value="<?= e(site_tagline()) ?>" maxlength="120">
        <label for="brand_mode">ما الذي يظهر في أعلى الصفحات؟</label>
        <select id="brand_mode" name="brand_mode">
            <option value="both"<?= $modeNow === 'both' ? ' selected' : '' ?>>الشعار والاسم معًا</option>
            <option value="logo"<?= $modeNow === 'logo' ? ' selected' : '' ?>>الشعار فقط</option>
            <option value="name"<?= $modeNow === 'name' ? ' selected' : '' ?>>الاسم فقط</option>
        </select>
        <label class="check"><input type="checkbox" name="show_tagline" value="1"<?= site_show_tagline() ? ' checked' : '' ?>> إظهار العنوان (النص الصغير) تحت اسم المنتدى — ألغِ التحديد ليظهر الاسم وحده</label>
        <p class="hint">رابط الموقع يُكتشف تلقائيًا: <bdi dir="ltr"><?= e(site_url_base()) ?></bdi></p>
        <label class="check"><input type="checkbox" name="sitemap_enabled" value="1"<?= site_setting('sitemap_enabled', '0') === '1' ? ' checked' : '' ?>> تفعيل خريطة الموقع (sitemap.xml) وإضافة رابطها إلى robots.txt تلقائيًا</label>
        <?php if (site_setting('sitemap_enabled', '0') === '1'): ?><p class="hint">رابط الخريطة: <bdi dir="ltr"><?= e(site_url_base() . '/sitemap.xml') ?></bdi></p><?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button></div>
    </form>
</section>

<section class="panel form-panel">
    <h2>ملف robots.txt</h2>
    <p class="hint">يتحكم في ما يفهرسه محرك البحث. هذا هو الملف الوحيد: يُحفظ في قاعدة البيانات ويُعرض مباشرة على الرابط <bdi dir="ltr"><a href="<?= e(site_url_base() . '/robots.txt') ?>" target="_blank" rel="noopener"><?= e(site_url_base() . '/robots.txt') ?></a></bdi>، ويُضاف إليه سطر <code>Sitemap:</code> تلقائيًا عند تفعيل خريطة الموقع (لا تكتبه بنفسك).</p>
    <?php if ($robotsLegacy): ?><p class="hint" style="color:#b3261e">يوجد ملف robots.txt قديم على الخادم. سيُحذف تلقائيًا عند أول حفظ، أو احذفه يدويًا.</p><?php endif; ?>
    <form method="post" action="<?= e(url('admin/settings.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="robots">
        <textarea name="robots" rows="8" dir="ltr" maxlength="5000" aria-label="robots.txt"><?= e($robotsText) ?></textarea>
        <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ robots.txt</button>
            <button class="btn" type="submit" name="action" value="robots_default" onclick="return confirm('استبدال المحتوى الحالي بالإعداد الافتراضي المناسب لمحركات البحث؟')">استعادة الافتراضي (SEO)</button></div>
    </form>
</section>

<section class="panel form-panel">
    <h2>شعار المنتدى</h2>
    <p>ارفع صورة صغيرة لتظهر بجانب اسم المنتدى في أعلى الصفحات. الأنواع المقبولة: PNG وJPG وGIF وWebP، بحد أقصى 2 ميغابايت.</p>
    <div class="logo-preview" style="display:flex;align-items:center;gap:1rem;margin:1rem 0">
        <img src="<?= e(url($currentLogo ?: 'assets/images/forum_logo.png')) ?>" alt="معاينة شعار المنتدى" width="72" height="72" style="object-fit:contain;border:1px solid #ddd;border-radius:6px;padding:4px">
        <strong><?= e(site_name()) ?></strong>
    </div>
    <form method="post" action="<?= e(url('admin/settings.php')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <label for="forum-logo">اختر صورة الشعار</label>
        <input type="file" id="forum-logo" name="forum_logo" accept="image/png,image/jpeg,image/gif,image/webp" required>
        <p class="hint">لأفضل نتيجة، استخدم صورة مربعة أو مستطيلة بخلفية مناسبة.</p>
        <p class="hint">المقاس الذي ننصح به للصورة هو 500 × 200 بكسل (العرض × الارتفاع).</p>
        <div class="form-actions"><button class="btn btn-primary" type="submit">رفع الشعار وحفظه</button></div>
    </form>
    <?php if ($currentLogo !== null): ?>
        <hr>
        <form method="post" action="<?= e(url('admin/settings.php')) ?>" onsubmit="return confirm('هل تريد إزالة الشعار المخصص؟');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <button class="btn btn-danger" type="submit">العودة إلى الشعار الافتراضي</button>
        </form>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
