<?php
/**
 * بريد المنتدى: members write to the staff of a forum; the staff share one inbox.
 */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$forumId = get_int('forum');
$forum = db_row('SELECT id, name FROM forums WHERE id = ?', 'i', [$forumId]);
if (!$forum) {
    fail_page('المنتدى غير موجود.', 404);
}
$isStaff = can_moderate($forumId);
$errors = [];
$subject = '';
$body = '';

if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'done') {
        if (!$isStaff) {
            fail_page('ليس لديك صلاحية تنفيذ هذا الإجراء.', 403);
        }
        db_exec(
            "UPDATE forum_mail SET status = 'done', handled_by = ? WHERE id = ? AND forum_id = ?",
            'iii',
            [$user['id'], (int) (isset($_POST['id']) ? $_POST['id'] : 0), $forumId]
        );
        flash('success', 'تم تعليم الرسالة كمعالَجة.');
        redirect('forum_mail.php?forum=' . $forumId);
    }

    $subject = post_str('subject');
    $body = rich_str('body');
    if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
        $errors[] = 'عنوان الرسالة مطلوب (من 3 إلى 150 حرفًا).';
    }
    if (($e = validate_body($body, 'الرسالة')) !== null) {
        $errors[] = $e;
    }
    if (!$errors && ($e = posting_block_message()) !== null) {
        $errors[] = $e;
    }
    if (!$errors) {
        db_exec('INSERT INTO forum_mail (forum_id, user_id, subject, body) VALUES (?, ?, ?, ?)', 'iiss', [$forumId, $user['id'], $subject, $body]);
        mark_action();
        flash('success', 'وصلت رسالتك إلى مشرفي المنتدى.');
        redirect('forum_mail.php?forum=' . $forumId);
    }
}

$inbox = [];
if ($isStaff) {
    $inbox = db_all(
        "SELECT m.id, m.subject, m.body, m.status, m.created_at, u.id AS uid, u.username
           FROM forum_mail m JOIN users u ON u.id = m.user_id
          WHERE m.forum_id = ? ORDER BY m.status = 'open' DESC, m.id DESC LIMIT 50",
        'i',
        [$forumId]
    );
}
$mine = db_all('SELECT id, subject, status, created_at FROM forum_mail WHERE forum_id = ? AND user_id = ? ORDER BY id DESC LIMIT 10', 'ii', [$forumId, $user['id']]);

page_header('بريد المنتدى');
breadcrumbs([['الرئيسية', 'index.php'], [$forum['name'], 'forum.php?id=' . $forumId], ['بريد المنتدى', null]]);
?>
<div class="thread-page-head"><h1 class="thread-page-title">بريد المنتدى: <?= e($forum['name']) ?></h1></div>

<?php if ($isStaff): ?>
    <h2>رسائل الأعضاء إلى مشرفي المنتدى</h2>
    <?php if (!$inbox): ?>
        <div class="panel empty"><p>لا توجد رسائل.</p></div>
    <?php else: ?>
        <?php foreach ($inbox as $m): ?>
            <article class="panel mail-item<?= $m['status'] === 'done' ? ' is-done' : '' ?>">
                <h3><?= e($m['subject']) ?> <?= $m['status'] === 'open' ? '<span class="badge badge-signal">جديدة</span>' : '<span class="badge">معالَجة</span>' ?></h3>
                <p class="meta">من <?= user_link($m['uid'], $m['username'], $forumId) ?> (رقم العضو <?= (int) $m['uid'] ?>) <span class="when"><?= e(fmt_when($m['created_at'])) ?></span></p>
                <div class="post-body"><?= post_html($m['body']) ?></div>
                <div class="form-actions">
                    <a class="btn btn-small" href="<?= e(url('send_message.php?to=' . rawurlencode($m['username']))) ?>">رد برسالة خاصة</a>
                    <?php if ($m['status'] === 'open'): ?>
                        <form method="post" action="<?= e(url('forum_mail.php?forum=' . $forumId)) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="done">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="btn btn-small">تمّت المعالجة</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
    <h2>اكتب إلى زملائك المشرفين</h2>
<?php endif; ?>

<form class="panel form-panel" method="post" action="<?= e(url('forum_mail.php?forum=' . $forumId)) ?>">
    <?= csrf_field() ?>
    <h2><?= $isStaff ? 'رسالة جديدة' : 'أرسل رسالة لمشرفي هذا المنتدى' ?></h2>
    <?php foreach ($errors as $err): ?>
        <div class="notice notice-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <label for="subject">العنوان</label>
    <input type="text" id="subject" name="subject" value="<?= e($subject) ?>" required maxlength="150">
    <label for="body">الرسالة</label>
    <textarea id="body" name="body" rows="6" data-rich="1"><?= e($body) ?></textarea>
    <div class="form-actions"><button type="submit" class="btn btn-primary">إرسال</button></div>
</form>

<?php if ($mine): ?>
    <h2>رسائلك السابقة إلى هذا المنتدى</h2>
    <ul class="plain-list">
        <?php foreach ($mine as $m): ?>
            <li><?= e($m['subject']) ?> <span class="meta"><?= e(fmt_when($m['created_at'])) ?> — <?= $m['status'] === 'done' ? 'تمت معالجتها' : 'قيد المراجعة' ?></span></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php page_footer();
