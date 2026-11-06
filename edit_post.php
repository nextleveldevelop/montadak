<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = get_int('id');

if ($type === 'thread') {
    $post = db_row('SELECT id, forum_id, user_id, title, content, is_locked, is_archived FROM threads WHERE id = ?', 'i', [$id]);
    $threadId = $post ? (int) $post['id'] : 0;
} elseif ($type === 'reply') {
    $post = db_row(
        'SELECT r.id, r.thread_id, r.user_id, r.content, t.forum_id, t.is_locked, t.is_archived
           FROM replies r JOIN threads t ON t.id = r.thread_id WHERE r.id = ?',
        'i',
        [$id]
    );
    $threadId = $post ? (int) $post['thread_id'] : 0;
} else {
    $post = null;
}

if (!$post) {
    fail_page('المشاركة غير موجودة.', 404);
}

$isMod = can_moderate((int) $post['forum_id']);
$isOwner = (int) $post['user_id'] === (int) $user['id'];
if (!$isMod && !($isOwner && !$post['is_locked'] && !$post['is_archived'])) {
    fail_page('لا يمكنك تعديل هذه المشاركة.', 403);
}

$errors = [];
$title = $type === 'thread' ? $post['title'] : '';
$content = $post['content'];

if (is_post()) {
    csrf_verify();
    $content = rich_str('content');
    $err = validate_body($content);
    if ($err === null) {
        $err = mute_message();
    }
    if ($type === 'thread') {
        $title = post_str('title');
        $err = validate_title($title) !== null ? validate_title($title) : $err;
    }
    if ($err !== null) {
        $errors[] = $err;
    } else {
        if ($type === 'thread') {
            db_exec('UPDATE threads SET title = ?, content = ?, edited_at = NOW() WHERE id = ?', 'ssi', [$title, $content, $id]);
            redirect('thread.php?id=' . $id);
        }
        db_exec('UPDATE replies SET content = ?, edited_at = NOW() WHERE id = ?', 'si', [$content, $id]);
        flash('success', 'تم حفظ التعديل.');
        redirect('thread.php?id=' . $threadId . '&page=' . reply_page($threadId, $id) . '#post-' . $id);
    }
}

page_header('تعديل المشاركة');
?>
<div class="panel form-panel">
    <h1><?= $type === 'thread' ? 'تعديل الموضوع' : 'تعديل الرد' ?></h1>
    <?php foreach ($errors as $err): ?>
        <div class="notice notice-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('edit_post.php?type=' . $type . '&id=' . $id)) ?>">
        <?= csrf_field() ?>
        <?php if ($type === 'thread'): ?>
            <label for="title">عنوان الموضوع</label>
            <input type="text" id="title" name="title" value="<?= e($title) ?>" required maxlength="200">
        <?php endif; ?>
        <label for="content">النص</label>
        <textarea id="content" name="content" rows="10" data-rich="1"><?= e($content) ?></textarea>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">حفظ التعديل</button>
            <a class="btn" href="<?= e(url('thread.php?id=' . $threadId)) ?>">إلغاء</a>
        </div>
    </form>
</div>
<?php page_footer();
