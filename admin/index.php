<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$stats = db_row(
    'SELECT (SELECT COUNT(*) FROM users) AS members,
            (SELECT COUNT(*) FROM users WHERE is_banned = 1) AS banned,
            (SELECT COUNT(*) FROM forums) AS forums,
            (SELECT COUNT(*) FROM threads) AS threads,
            (SELECT COUNT(*) FROM replies) AS replies,
            (SELECT COUNT(*) FROM messages) AS messages'
);
$newMembers = db_all('SELECT id, username, created_at FROM users ORDER BY id DESC LIMIT 5');

page_header('لوحة الإدارة', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('index'); ?>
<?php if (is_file(APP_ROOT . '/install.php')): ?>
    <div class="panel" style="border-color:#f2a6a6;background:#fff5f5"><b>تنبيه أمني:</b> الملف <code>install.php</code> ما زال على الخادم. احذفه الآن لحماية موقعك.</div>
<?php endif; ?>

<dl class="stat-grid">
    <div><dt>الأعضاء</dt><dd><?= (int) $stats['members'] ?></dd></div>
    <div><dt>المنتديات</dt><dd><?= (int) $stats['forums'] ?></dd></div>
    <div><dt>المواضيع</dt><dd><?= (int) $stats['threads'] ?></dd></div>
    <div><dt>الردود</dt><dd><?= (int) $stats['replies'] ?></dd></div>
    <div><dt>الرسائل الخاصة</dt><dd><?= (int) $stats['messages'] ?></dd></div>
    <div><dt>الأعضاء المحظورون</dt><dd><?= (int) $stats['banned'] ?></dd></div>
</dl>

<section>
    <h2>أحدث الأعضاء</h2>
    <ul class="plain-list">
        <?php foreach ($newMembers as $m): ?>
            <li><?= user_link($m['id'], $m['username']) ?> <span class="meta"><time><?= e(fmt_time($m['created_at'])) ?></time></span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php page_footer();
