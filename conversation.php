<?php
/** مراسلاتك مع العضو: every private message between you and one member, newest first. */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$me = (int) $user['id'];
$withId = get_int('with');
$other = db_row('SELECT id, username FROM users WHERE id = ?', 'i', [$withId]);
if (!$other) {
    fail_page('العضو غير موجود.', 404);
}
if ($withId === $me) {
    fail_page('لا توجد مراسلات مع نفسك.', 400);
}

// opening the conversation marks what they sent you as read
db_exec('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND receiver_deleted = 0', 'ii', [$withId, $me]);

$msgs = db_all(
    'SELECT m.id, m.sender_id, m.subject, m.body, m.created_at
       FROM messages m
      WHERE (m.sender_id = ? AND m.receiver_id = ? AND m.sender_deleted = 0)
         OR (m.sender_id = ? AND m.receiver_id = ? AND m.receiver_deleted = 0)
      ORDER BY m.id DESC LIMIT 100',
    'iiii',
    [$me, $withId, $withId, $me]
);
$iBlocked = is_blocked_by($me, $withId);
$theyBlocked = is_blocked_by($withId, $me);

page_header('مراسلاتك مع ' . $other['username'], 'inbox');
breadcrumbs([['الرسائل', 'inbox.php'], [$other['username'], 'profile.php?id=' . $withId], ['المراسلات', null]]);
?>
<div class="thread-page-head"><h1 class="thread-page-title">مراسلاتك مع <?= e($other['username']) ?></h1></div>

<div class="action-bar">
    <a class="btn btn-primary" href="<?= e(url('send_message.php?to=' . rawurlencode($other['username']))) ?>"><?= icon('mail') ?> رسالة جديدة</a>
    <a class="btn" href="<?= e(url('profile.php?id=' . $withId)) ?>"><?= icon('card') ?> صفحة العضو</a>
    <?php $ux = user_extras(); if (!empty($ux['live_msgs'])): ?><button type="button" class="btn" data-live-open="<?= $withId ?>" data-name="<?= e($other['username']) ?>"><?= icon('chat') ?> محادثة فورية</button><?php endif; ?>
    <form method="post" action="<?= e(url('block.php')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= $withId ?>">
        <input type="hidden" name="action" value="<?= $iBlocked ? 'unblock' : 'block' ?>">
        <input type="hidden" name="next" value="<?= e(safe_next(current_app_path())) ?>">
        <button type="submit" class="btn"><?= icon($iBlocked ? 'unlock' : 'lock') ?> <?= $iBlocked ? 'إلغاء منعه من مراسلتك' : 'منعه من مراسلتك' ?></button>
    </form>
</div>
<?php if ($theyBlocked && !is_staff()): ?>
    <p class="notice notice-info">هذا العضو منعك من مراسلته، لذلك لن تصله رسائلك الجديدة.</p>
<?php endif; ?>

<?php if (!$msgs): ?>
    <div class="panel empty"><p>لا توجد رسائل بينكما بعد.</p></div>
<?php else: ?>
    <?php foreach ($msgs as $m): ?>
        <?php $mine = (int) $m['sender_id'] === $me; ?>
        <article class="panel convo-msg<?= $mine ? ' is-mine' : '' ?>">
            <p class="meta">
                <?= $mine ? 'أنت' : user_link($other['id'], $other['username']) ?>
                <span class="when"><?= e(fmt_when($m['created_at'])) ?></span>
            </p>
            <h3><a href="<?= e(url('message.php?id=' . $m['id'])) ?>"><?= e($m['subject']) ?></a></h3>
            <div class="post-body"><?= post_html($m['body']) ?></div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php page_footer();
