<?php
/** لوحة الرقابة: reports, hidden topics, sanctions, latest posts. For staff (مراقب / مشرف / مدير). */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
if (!is_staff()) {
    fail_page('لوحة الرقابة متاحة للمشرفين والمراقبين فقط.', 403);
}

// Staff see only their own forums; administrators see everything.
$ids = staff_forum_ids();
if ($ids === null) {
    $scope = '';
} elseif ($ids) {
    $scope = ' AND t.forum_id IN (' . implode(',', array_map('intval', $ids)) . ')';
} else {
    $scope = ' AND 1 = 0';
}

if (is_post()) {
    csrf_verify();
    if (post_str('action') === 'close_report') {
        $rid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $rep = db_row('SELECT r.id, t.forum_id FROM reports r JOIN threads t ON t.id = r.thread_id WHERE r.id = ?', 'i', [$rid]);
        if ($rep && can_moderate((int) $rep['forum_id'])) {
            db_exec("UPDATE reports SET status = 'closed', handled_by = ? WHERE id = ?", 'ii', [$user['id'], $rid]);
            flash('success', 'تم إغلاق التبليغ.');
        }
    }
    redirect('moderation.php?tab=reports');
}

$tabs = ['reports' => 'التبليغات', 'mail' => 'بريد المنتدى', 'sanctions' => 'سجل العقوبات'];
$forumFilter = get_int('forum');
$userFilter = get_int('user');
$scopeR = $scope . ($forumFilter > 0 ? ' AND t.forum_id = ' . $forumFilter : '')
    . ($userFilter > 0 ? ' AND COALESCE(rr.user_id, t.user_id) = ' . $userFilter : '');
$scopeMail = str_replace('t.forum_id', 'm.forum_id', $scope);
$tab = (isset($_GET['tab']) && is_string($_GET['tab']) && isset($tabs[$_GET['tab']])) ? $_GET['tab'] : 'reports';

$openReports = (int) db_val("SELECT COUNT(*) FROM reports rp JOIN threads t ON t.id = rp.thread_id WHERE rp.status = 'open'" . $scope);
$openMail = (int) db_val("SELECT COUNT(*) FROM forum_mail m WHERE m.status = 'open'" . $scopeMail);

$reports = $hidden = $posts = $sanctions = $muted = $mails = $archiveReq = $modLog = [];
if ($tab === 'reports') {
    $reports = db_all(
        "SELECT rp.id, rp.reason, rp.created_at, rp.thread_id, rp.reply_id, t.title, f.name AS forum_name,
                ru.id AS reporter_id, ru.username AS reporter,
                (SELECT COUNT(*) FROM replies x WHERE x.thread_id = rp.thread_id AND x.id <= rp.reply_id) AS pos
           FROM reports rp
           JOIN threads t ON t.id = rp.thread_id
           JOIN forums f ON f.id = t.forum_id
           JOIN users ru ON ru.id = rp.reporter_id
           LEFT JOIN replies rr ON rr.id = rp.reply_id
          WHERE rp.status = 'open'" . $scopeR . '
          ORDER BY rp.id DESC LIMIT 50'
    );
} elseif ($tab === 'sanctions') {
    $modLog = db_all('SELECT l.kind, l.text, l.created_at, u.id AS uid, u.username, b.username AS by_name FROM mod_log l JOIN users u ON u.id = l.user_id LEFT JOIN users b ON b.id = l.by_user_id ORDER BY l.id DESC LIMIT 50');
    $sanctions = db_all(
        'SELECT s.type, s.reason, s.until, s.created_at, u.id AS uid, u.username, b.username AS by_name
           FROM sanctions s JOIN users u ON u.id = s.user_id LEFT JOIN users b ON b.id = s.by_user_id
          ORDER BY s.id DESC LIMIT 50'
    );
} elseif ($tab === 'mail') {
    $mails = db_all(
        "SELECT m.id, m.forum_id, m.subject, m.created_at, f.name AS forum_name, u.id AS uid, u.username
           FROM forum_mail m JOIN forums f ON f.id = m.forum_id JOIN users u ON u.id = m.user_id
          WHERE m.status = 'open'" . $scopeMail . ' ORDER BY m.id DESC LIMIT 50'
    );
}

page_header('لوحة الرقابة', 'moderation');
?>
<div class="thread-page-head"><h1 class="thread-page-title">لوحة الرقابة</h1></div>
<p class="lede">
    <?= $ids === null ? 'تشاهد كل المنتديات.' : 'تشاهد المنتديات التي تشرف عليها فقط.' ?>
</p>

<nav class="subnav" aria-label="أقسام الرقابة">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e(url('moderation.php?tab=' . $k)) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?><?= $k === 'reports' && $openReports > 0 ? ' (' . $openReports . ')' : '' ?><?= $k === 'mail' && $openMail > 0 ? ' (' . $openMail . ')' : '' ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'reports'): ?>
    <?php if (!$reports): ?>
        <div class="panel empty"><p>لا توجد تبليغات مفتوحة.</p></div>
    <?php else: ?>
        <table class="board-table">
            <thead><tr><th scope="col">المشاركة</th><th scope="col">السبب</th><th scope="col" class="author-col">المُبلِّغ</th><th scope="col" class="last">التاريخ</th><th scope="col"><span class="sr-only">إجراء</span></th></tr></thead>
            <tbody>
            <?php foreach ($reports as $r): ?>
                <?php
                $pg = max(1, (int) ceil((int) $r['pos'] / posts_per_page()));
                $link = $r['reply_id'] ? 'thread.php?id=' . $r['thread_id'] . '&page=' . $pg . '#post-' . $r['reply_id'] : 'thread.php?id=' . $r['thread_id'];
                ?>
                <tr>
                    <td class="main"><a class="row-title" href="<?= e(url($link)) ?>"><?= e($r['title']) ?></a> <span class="meta"><?= e($r['forum_name']) ?><?= $r['reply_id'] ? ' (رد)' : '' ?></span></td>
                    <td><?= e($r['reason']) ?></td>
                    <td class="author-col"><?= user_link($r['reporter_id'], $r['reporter']) ?></td>
                    <td class="last"><span class="when"><?= e(fmt_when($r['created_at'])) ?></span></td>
                    <td>
                        <form method="post" action="<?= e(url('moderation.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="close_report">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-small">إغلاق</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php elseif ($tab === 'sanctions'): ?>
    <?php if (!$sanctions): ?>
        <div class="panel empty"><p>لا توجد عقوبات مسجلة.</p></div>
    <?php else: ?>
        <table class="board-table">
            <thead><tr><th scope="col">العضو</th><th scope="col">الإجراء</th><th scope="col">السبب</th><th scope="col" class="author-col">بواسطة</th><th scope="col" class="last">التاريخ</th></tr></thead>
            <tbody>
            <?php foreach ($sanctions as $sc): ?>
                <tr>
                    <td><?= user_link($sc['uid'], $sc['username']) ?></td>
                    <td><?= e(['warning' => 'تحذير', 'mute' => 'كتم', 'unmute' => 'رفع الكتم'][$sc['type']]) ?><?= $sc['until'] ? ' حتى ' . e(fmt_when($sc['until'])) : '' ?></td>
                    <td><?= e($sc['reason']) ?></td>
                    <td class="author-col"><?= e($sc['by_name']) ?></td>
                    <td class="last"><span class="when"><?= e(fmt_when($sc['created_at'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($modLog): ?>
        <h2 class="log-title">سجل الرقابة وقفل العضويات</h2>
        <table class="board-table">
            <thead><tr><th scope="col">العضو</th><th scope="col">النوع</th><th scope="col">التفاصيل</th><th scope="col" class="author-col">بواسطة</th><th scope="col" class="last">التاريخ</th></tr></thead>
            <tbody>
            <?php foreach ($modLog as $ml): ?>
                <tr>
                    <td><?= user_link($ml['uid'], $ml['username']) ?></td>
                    <td><?= e(['forum_ban' => 'منع من منتدى', 'lock' => 'قفل عضوية', 'unlock' => 'فتح عضوية', 'nominate' => 'ترشيح إشراف'][$ml['kind']] ?? $ml['kind']) ?></td>
                    <td><?= e($ml['text']) ?></td>
                    <td class="author-col"><?= e($ml['by_name']) ?></td>
                    <td class="last"><?= e(fmt_when($ml['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php elseif ($tab === 'mail'): ?>
    <?php if (!$mails): ?>
        <div class="panel empty"><p>لا توجد رسائل جديدة في بريد المنتديات.</p></div>
    <?php else: ?>
        <table class="board-table">
            <thead><tr><th scope="col">العنوان</th><th scope="col" class="author-col">المنتدى</th><th scope="col" class="author-col">المرسل</th><th scope="col" class="last">التاريخ</th></tr></thead>
            <tbody>
            <?php foreach ($mails as $m): ?>
                <tr>
                    <td class="main"><a class="row-title" href="<?= e(url('forum_mail.php?forum=' . $m['forum_id'])) ?>"><?= e($m['subject']) ?></a></td>
                    <td class="author-col"><?= e($m['forum_name']) ?></td>
                    <td class="author-col"><?= user_link($m['uid'], $m['username'], (int) $m['forum_id']) ?></td>
                    <td class="last"><span class="when"><?= e(fmt_when($m['created_at'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>
<?php page_footer();
