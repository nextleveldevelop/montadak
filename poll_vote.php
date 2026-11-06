<?php
/** POST-only: cast a vote in a thread's poll (one vote per member; several options only when the poll allows). */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
$user = require_login();
csrf_verify();

$threadId = (int) (isset($_POST['thread_id']) ? $_POST['thread_id'] : 0);
$thread = db_row('SELECT id, forum_id, user_id, is_hidden, is_locked, is_archived FROM threads WHERE id = ?', 'i', [$threadId]);
if (!$thread || !can_view_thread($thread)) {
    fail_page('الموضوع غير موجود.', 404);
}
$pd = poll_data($threadId);
if (!$pd) {
    fail_page('لا يوجد استفتاء في هذا الموضوع.', 404);
}
if ($thread['is_locked'] || $thread['is_archived']) {
    flash('error', 'التصويت مغلق في هذا الموضوع.');
    redirect('thread.php?id=' . $threadId . '#poll');
}
if ($pd['mine']) {
    flash('info', 'سبق أن صوّتّ في هذا الاستفتاء.');
    redirect('thread.php?id=' . $threadId . '#poll');
}

$valid = [];
foreach ($pd['options'] as $o) {
    $valid[(int) $o['id']] = true;
}
$picked = [];
$raw = isset($_POST['option']) ? (array) $_POST['option'] : [];
foreach (array_filter($raw, 'is_scalar') as $v) {
    $oid = (int) $v;
    if (isset($valid[$oid])) {
        $picked[$oid] = true;
    }
}
$picked = array_keys($picked);
if (!$pd['poll']['multiple']) {
    $picked = array_slice($picked, 0, 1);
}
if (!$picked) {
    flash('error', 'اختر خيارًا للتصويت.');
    redirect('thread.php?id=' . $threadId . '#poll');
}
foreach ($picked as $oid) {
    db_exec('INSERT IGNORE INTO poll_votes (option_id, user_id, thread_id) VALUES (?, ?, ?)', 'iii', [$oid, $user['id'], $threadId]);
}
flash('success', 'تم تسجيل صوتك.');
redirect('thread.php?id=' . $threadId . '#poll');
