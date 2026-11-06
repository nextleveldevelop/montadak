<?php
/** خيارات وبيانات عضويتك: لوحة بطاقات عصرية. */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$items = [
    ['key',  'كلمة السر', 'تغيير كلمتك السرية', 'account.php?do=password'],
    ['mail', 'البريد الإلكتروني', 'تغيير بريدك الإلكتروني', 'account.php?do=email'],
    ['card', 'بياناتي الشخصية', 'تعديل بياناتك وخيارات الظهور في صفحة عضويتك', 'account.php?do=profile'],
    ['users', 'صورة العضوية', 'تغيير صورتك التي تظهر في ملفك ومشاركاتك', 'account.php?do=avatar'],
    ['card', 'خلفية العضوية', 'ارفع خلفية لصفحة عضويتك (1200×300)', 'account.php?do=bg'],
    ['bookmark', 'المحفظة الشخصية', 'المواضيع والردود التي حفظتها', 'wallet.php'],
    ['chat', 'المحادثة الفورية', 'تفعيل أو إيقاف المحادثة الفورية (مثل الماسنجر)', 'account.php?do=live'],
    ['pencil', 'تغيير الاسم', 'طلب تغيير اسم العضوية', 'account.php?do=rename'],
];

page_header('خيارات وبيانات عضويتك', 'settings');
?>
<section class="st-hero">
    <?= avatar_html($user['avatar'] ?? null, 'st-avatar', 72) ?>
    <div>
        <h1>خيارات وبيانات عضويتك</h1>
        <p><?= e($user['username']) ?> · <a href="<?= e(url('profile.php?id=' . (int) $user['id'])) ?>">عرض صفحة عضويتي</a></p>
    </div>
</section>
<div class="st-grid">
    <?php foreach ($items as $it): ?>
        <a class="st-card" href="<?= e(url($it[3])) ?>">
            <span class="st-ico"><?= icon($it[0]) ?></span>
            <b><?= e($it[1]) ?></b>
            <small><?= e($it[2]) ?></small>
        </a>
    <?php endforeach; ?>
</div>
<?php page_footer();
