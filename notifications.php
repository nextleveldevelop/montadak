<?php
/** الإشعارات: likes, replies, messages and everything you follow. */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];

if (is_post()) {
    csrf_verify();
    db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', 'i', [$uid]);
    redirect('notifications.php');
}
// opening one notification marks it read and goes to its page
$go = get_int('go');
if ($go > 0) {
    $n = db_row('SELECT id, url FROM notifications WHERE id = ? AND user_id = ?', 'ii', [$go, $uid]);
    if ($n) {
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ?', 'i', [$n['id']]);
        $s = safe_next(ltrim($n['url'], '/'));
        redirect($s);
    }
    redirect('notifications.php');
}
db_exec('DELETE FROM notifications WHERE user_id = ? AND is_read = 1 AND created_at < (NOW() - INTERVAL 60 DAY)', 'i', [$uid]);
$perPage = 15;
$total = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ?', 'i', [$uid]);
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$rows = db_all('SELECT id, type, text, url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), 'i', [$uid]);
$icons = ['like' => 'star', 'reply' => 'pencil', 'message' => 'mail', 'follow' => 'bell'];

page_header('الإشعارات', 'notifications');
breadcrumbs([['الرئيسية', 'index.php'], ['الإشعارات', null]]);
?>
<div class="thread-page-head"><h1 class="thread-page-title">الإشعارات</h1></div>
<p class="lede">الإعجابات والردود والرسائل وجديد ما تتابعه. <a href="<?= e(url('follows.php')) ?>">ما أتابعه</a></p>
<?php if (!$rows): ?>
    <div class="panel"><p class="meta">لا توجد إشعارات بعد.</p></div>
<?php else: ?>
    <form method="post" action="<?= e(url('notifications.php')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-small">تعليم الكل كمقروء</button></form>
    <ul class="notif-list">
    <?php foreach ($rows as $r): ?>
        <li class="<?= (int) $r['is_read'] === 0 ? 'is-new' : '' ?>">
            <a href="<?= e(url('notifications.php?go=' . (int) $r['id'])) ?>">
                <?= icon(isset($icons[$r['type']]) ? $icons[$r['type']] : 'bell') ?>
                <span class="notif-text"><?= e($r['text']) ?></span>
                <small class="meta"><?= e(fmt_when($r['created_at'])) ?></small>
            </a>
        </li>
    <?php endforeach; ?>
    </ul>
    <?= pagination_html($page, $pages, 'notifications.php') ?>
<?php endif; ?>
<?php page_footer();
