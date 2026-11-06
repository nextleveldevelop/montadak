<?php
/** POST-only: like or un-like a thread (the opening post) or a reply. Pressing again removes the like. */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
$user = require_login();
csrf_verify();

$type = post_str('type');
$id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);

if ($type === 'thread') {
    $t = db_row('SELECT id, forum_id, user_id, is_hidden FROM threads WHERE id = ?', 'i', [$id]);
    $threadId = $id;
    $replyId = 0;
    $authorId = $t ? (int) $t['user_id'] : 0;
    $anchor = 'op';
} elseif ($type === 'reply') {
    $r = db_row(
        'SELECT r.id, r.user_id, r.thread_id, t.forum_id, t.user_id AS thread_user, t.is_hidden
           FROM replies r JOIN threads t ON t.id = r.thread_id WHERE r.id = ?',
        'i',
        [$id]
    );
    $t = $r ? ['id' => $r['thread_id'], 'forum_id' => $r['forum_id'], 'user_id' => $r['thread_user'], 'is_hidden' => $r['is_hidden']] : null;
    $threadId = $r ? (int) $r['thread_id'] : 0;
    $replyId = $id;
    $authorId = $r ? (int) $r['user_id'] : 0;
    $anchor = (string) $id;
} else {
    fail_page('طلب غير صالح.', 400);
}
if (!$t || !can_view_thread($t)) {
    fail_page('المشاركة غير موجودة.', 404);
}
if ($authorId === (int) $user['id']) {
    flash('error', 'لا يمكنك الإعجاب بمشاركتك.');
} else {
    $liked = db_transaction(function () use ($user, $threadId, $replyId) {
        $has = db_val('SELECT 1 FROM post_likes WHERE user_id = ? AND thread_id = ? AND reply_id = ?', 'iii', [$user['id'], $threadId, $replyId]);
        if ($has !== null) {
            db_exec('DELETE FROM post_likes WHERE user_id = ? AND thread_id = ? AND reply_id = ?', 'iii', [$user['id'], $threadId, $replyId]);
        } else {
            db_exec('INSERT IGNORE INTO post_likes (user_id, thread_id, reply_id) VALUES (?, ?, ?)', 'iii', [$user['id'], $threadId, $replyId]);
        }
        // recount instead of +1 / -1, so the counters can never drift
        if ($replyId === 0) {
            db_exec('UPDATE threads SET like_count = (SELECT COUNT(*) FROM post_likes WHERE thread_id = ? AND reply_id = 0) WHERE id = ?', 'ii', [$threadId, $threadId]);
        } else {
            db_exec('UPDATE replies SET like_count = (SELECT COUNT(*) FROM post_likes WHERE reply_id = ?) WHERE id = ?', 'ii', [$replyId, $replyId]);
        }
        return $has === null;
    });
    if ($liked) {
        $nUrl = 'thread.php?id=' . $threadId . '#post-' . $anchor;
        notify($authorId, $user['id'], 'like', $user['username'] . ' أُعجب بمشاركتك', $nUrl);
    }
}

$n = post_str('next');
$back = ($n !== '' && ($s = safe_next($n)) !== 'index.php') ? $s : 'thread.php?id=' . $threadId;
redirect($back . '#post-' . $anchor);
