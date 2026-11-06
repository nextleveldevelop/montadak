<?php
/** Report a post to the forum staff (تبليغ). */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$threadId = get_int('thread');
$replyId = get_int('reply');

$thread = db_row('SELECT id, forum_id, user_id, title, is_hidden FROM threads WHERE id = ?', 'i', [$threadId]);
if (!$thread || !can_view_thread($thread)) {
    fail_page('المشاركة غير موجودة.', 404);
}
$reply = null;
if ($replyId > 0) {
    $reply = db_row('SELECT id, user_id FROM replies WHERE id = ? AND thread_id = ?', 'ii', [$replyId, $threadId]);
    if (!$reply) {
        fail_page('الرد غير موجود.', 404);
    }
}
$authorId = $reply ? (int) $reply['user_id'] : (int) $thread['user_id'];

$errors = [];
$reason = '';
if (is_post()) {
    csrf_verify();
    $reason = post_str('reason');
    if ($authorId === (int) $user['id']) {
        $errors[] = 'لا يمكنك التبليغ عن مشاركتك.';
    } elseif (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
        $errors[] = 'اكتب سبب التبليغ (من 3 إلى 255 حرفًا).';
    } elseif (db_val(
        "SELECT 1 FROM reports WHERE reporter_id = ? AND thread_id = ? AND reply_id <=> ? AND status = 'open'",
        'iii',
        [$user['id'], $threadId, $reply ? $replyId : null]
    ) !== null) {
        $errors[] = 'سبق أن أبلغت عن هذه المشاركة وما زال التبليغ قيد المراجعة.';
    } else {
        db_exec(
            'INSERT INTO reports (thread_id, reply_id, reporter_id, reason) VALUES (?, ?, ?, ?)',
            'iiis',
            [$threadId, $reply ? $replyId : null, $user['id'], $reason]
        );
        flash('success', 'وصل تبليغك إلى المشرفين. شكرًا لك.');
        redirect('thread.php?id=' . $threadId);
    }
}

page_header('تبليغ عن مشاركة');
breadcrumbs([['الرئيسية', 'index.php'], [mb_strimwidth($thread['title'], 0, 50, '…'), 'thread.php?id=' . $threadId], ['تبليغ', null]]);
?>
<div class="panel form-panel narrow">
    <h1>تبليغ عن مشاركة</h1>
    <p class="hint">الموضوع: <?= e($thread['title']) ?><?= $reply ? ' (رد)' : '' ?></p>
    <?php foreach ($errors as $err): ?>
        <div class="notice notice-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('report.php?thread=' . $threadId . ($reply ? '&reply=' . $replyId : ''))) ?>">
        <?= csrf_field() ?>
        <label for="reason">سبب التبليغ</label>
        <input type="text" id="reason" name="reason" value="<?= e($reason) ?>" required maxlength="255" autofocus>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">إرسال التبليغ</button>
            <a class="btn" href="<?= e(url('thread.php?id=' . $threadId)) ?>">إلغاء</a>
        </div>
    </form>
</div>
<?php page_footer();
