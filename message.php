<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = get_int('id');

$msg = db_row(
    'SELECT m.id, m.sender_id, m.receiver_id, m.subject, m.body, m.is_read, m.created_at,
            m.sender_deleted, m.receiver_deleted,
            s.username AS sender, r.username AS receiver
       FROM messages m
       JOIN users s ON s.id = m.sender_id
       JOIN users r ON r.id = m.receiver_id
      WHERE m.id = ?',
    'i',
    [$id]
);

$uid = (int) $user['id'];
$isReceiver = $msg && (int) $msg['receiver_id'] === $uid && !$msg['receiver_deleted'];
$isSender = $msg && (int) $msg['sender_id'] === $uid && !$msg['sender_deleted'];
if (!$msg || (!$isReceiver && !$isSender)) {
    // Same answer for "missing" and "not yours" so message ids cannot be probed.
    fail_page('الرسالة غير موجودة.', 404, 'الرسالة غير موجودة');
}

if (is_post()) {
    csrf_verify();
    if (post_str('action') === 'delete') {
        // Each side deletes only their own copy; the row goes once both have.
        if ($isReceiver) {
            db_exec('UPDATE messages SET receiver_deleted = 1 WHERE id = ?', 'i', [$id]);
        }
        if ($isSender) {
            db_exec('UPDATE messages SET sender_deleted = 1 WHERE id = ?', 'i', [$id]);
        }
        db_exec('DELETE FROM messages WHERE id = ? AND sender_deleted = 1 AND receiver_deleted = 1', 'i', [$id]);
        flash('success', 'تم حذف الرسالة.');
        redirect($isReceiver ? 'inbox.php' : 'inbox.php?box=sent');
    }
}

if ($isReceiver && !$msg['is_read']) {
    db_exec('UPDATE messages SET is_read = 1 WHERE id = ?', 'i', [$id]);
}

page_header($msg['subject'], 'inbox');
breadcrumbs([['الرسائل', 'inbox.php'], [mb_strimwidth($msg['subject'], 0, 50, '…'), null]]);
?>
<article class="panel message-view">
    <h1><?= e($msg['subject']) ?></h1>
    <dl class="facts">
        <div><dt>من</dt><dd><?= user_link($msg['sender_id'], $msg['sender']) ?></dd></div>
        <div><dt>إلى</dt><dd><?= user_link($msg['receiver_id'], $msg['receiver']) ?></dd></div>
        <div><dt>التاريخ</dt><dd><time><?= e(fmt_time($msg['created_at'])) ?></time></dd></div>
    </dl>
    <div class="post-body"><?= post_html($msg['body']) ?></div>
    <div class="form-actions">
        <?php if ($isReceiver): ?>
            <a class="btn btn-primary" href="<?= e(url('send_message.php?reply=' . (int) $msg['id'])) ?>">رد</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('message.php?id=' . (int) $msg['id'])) ?>" class="inline-form" data-confirm="هل تريد حذف هذه الرسالة؟">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger">حذف</button>
        </form>
    </div>
</article>
<?php page_footer();
