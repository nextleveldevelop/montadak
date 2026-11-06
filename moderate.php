<?php
/**
 * POST-only endpoint for actions on threads and replies.
 *   مراقب (moderator) : pin, lock, HIDE, open hidden topics for members (by member number), delete replies
 *   مشرف (supervisor) : + delete threads, archive, move
 *   Members            : delete their own replies, delete their own thread while it has no replies.
 *                        Members can NOT hide a thread: only moderators, supervisors and admins can.
 * Every action may carry a "next" page to return to (used by the options column in the forum list).
 */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
$user = require_login();
csrf_verify();

$action = post_str('action');
$id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);


/** Go back to the page the request came from, or to $default. */
function done($default)
{
    $n = post_str('next');
    if ($n !== '' && ($s = safe_next($n)) !== 'index.php') {
        redirect($s);
    }
    redirect($default);
}

if ($action === 'delete_reply') {
    $reply = db_row(
        'SELECT r.id, r.thread_id, r.user_id, t.forum_id, t.is_locked, t.is_archived
           FROM replies r JOIN threads t ON t.id = r.thread_id WHERE r.id = ?',
        'i',
        [$id]
    );
    if (!$reply) {
        fail_page('الرد غير موجود.', 404);
    }
    $isOwn = (int) $reply['user_id'] === (int) $user['id'] && !$reply['is_locked'] && !$reply['is_archived'];
    if (!is_admin() && !$isOwn) {
        fail_page('حذف الردود من صلاحيات المدير فقط، ويمكنك حذف ردك أنت.', 403);
    }
    db_transaction(function () use ($id, $reply) {
        db_exec('DELETE FROM replies WHERE id = ?', 'i', [$id]);
        db_exec('DELETE FROM post_likes WHERE reply_id = ?', 'i', [$id]);
        recalc_thread((int) $reply['thread_id']);
    });
    flash('success', 'تم حذف الرد.');
    redirect('thread.php?id=' . (int) $reply['thread_id']);
}

if ($action === 'hide_reply' || $action === 'unhide_reply') {
    $rr = db_row('SELECT r.id, r.thread_id, t.forum_id FROM replies r JOIN threads t ON t.id = r.thread_id WHERE r.id = ?', 'i', [$id]);
    if (!$rr) {
        fail_page('الرد غير موجود.', 404);
    }
    if (forum_level((int) $rr['forum_id']) < 2) {
        fail_page('إخفاء الردود للمدير ومشرف المنتدى فقط.', 403);
    }
    db_exec('UPDATE replies SET is_hidden = ? WHERE id = ?', 'ii', [$action === 'hide_reply' ? 1 : 0, $id]);
    flash('success', $action === 'hide_reply' ? 'تم إخفاء الرد.' : 'تم إظهار الرد.');
    redirect('thread.php?id=' . (int) $rr['thread_id']);
}

$thread = db_row('SELECT id, forum_id, user_id, reply_count, is_hidden FROM threads WHERE id = ?', 'i', [$id]);
if (!$thread) {
    fail_page('الموضوع غير موجود.', 404);
}
$forumId = (int) $thread['forum_id'];
$level = forum_level($forumId);
$isAuthor = (int) $thread['user_id'] === (int) $user['id'];
$back = 'thread.php?id=' . $id;
$denied = 'ليس لديك صلاحية تنفيذ هذا الإجراء.';

switch ($action) {
    case 'pin':
    case 'unpin':
    case 'lock':
    case 'unlock':
        if ($level < 1) {
            fail_page($denied, 403);
        }
        $changes = [
            'pin'    => ['is_pinned', 1, 'تم تثبيت الموضوع.'],
            'unpin'  => ['is_pinned', 0, 'تم إلغاء تثبيت الموضوع.'],
            'lock'   => ['is_locked', 1, 'تم إغلاق الموضوع.'],
            'unlock' => ['is_locked', 0, 'تم فتح الموضوع.'],
        ];
        list($column, $value, $message) = $changes[$action];
        db_exec('UPDATE threads SET ' . $column . ' = ? WHERE id = ?', 'ii', [$value, $id]); // column comes from the fixed list above
        flash('success', $message);
        done($back);

    case 'hide':
    case 'unhide':
        // Only staff (moderator, supervisor, admin) can hide a thread, any thread in their forum.
        if ($level < 1) {
            fail_page($denied, 403);
        }
        db_exec('UPDATE threads SET is_hidden = ? WHERE id = ?', 'ii', [$action === 'hide' ? 1 : 0, $id]);
        flash('success', $action === 'hide'
            ? 'أصبح الموضوع مخفيًا: لا يراه إلا كاتبه والمشرفون والمراقبون ومن تفتح له الموضوع.'
            : 'أصبح الموضوع ظاهرًا للجميع.');
        done($back);

    case 'move':
        $to = (int) (isset($_POST['to_forum']) ? $_POST['to_forum'] : 0);
        if ($level < 2) {
            fail_page('ليس لديك صلاحية نقل المواضيع.', 403);
        }
        if (db_val('SELECT 1 FROM forums WHERE id = ?', 'i', [$to]) === null) {
            flash('error', 'اختر منتدى صالحًا.');
            redirect($back);
        }
        db_exec('UPDATE threads SET forum_id = ? WHERE id = ?', 'ii', [$to, $id]);
        flash('success', 'تم نقل الموضوع.');
        redirect($back);

    case 'delete_thread':
        if (!is_admin() && !$isAuthor) { // only the administrator deletes others' topics; authors delete their own
            fail_page('حذف المواضيع من صلاحيات المدير فقط، ويمكنك حذف موضوعك أنت.', 403);
        }
        db_exec('DELETE FROM threads WHERE id = ?', 'i', [$id]); // replies, poll, favourites and reports go with it
        flash('success', 'تم حذف الموضوع.');
        redirect('forum.php?id=' . $forumId);

    case 'grant':
        // Open a hidden thread for members, by member number (or username). Staff only.
        if ($level < 1) {
            fail_page($denied, 403);
        }
        $found = resolve_members(post_str('members'));
        $granted = 0;
        foreach ($found['ids'] as $uid) {
            if ($uid !== (int) $thread['user_id']) {
                $granted += db_exec('INSERT IGNORE INTO thread_access (thread_id, user_id) VALUES (?, ?)', 'ii', [$id, $uid]);
            }
        }
        if ($granted > 0) {
            flash('success', 'تم فتح الموضوع لـ ' . $granted . ' عضو.');
        }
        if ($found['missing']) {
            flash('error', 'لم يتم العثور على: ' . implode('، ', array_map(function ($t) { return mb_substr($t, 0, 30); }, $found['missing'])));
        }
        if ($granted === 0 && !$found['missing']) {
            flash('info', 'لم يتغير شيء: الأعضاء المذكورون يملكون الصلاحية بالفعل.');
        }
        redirect($back . '#access');

    case 'revoke':
        if ($level < 1) {
            fail_page($denied, 403);
        }
        db_exec('DELETE FROM thread_access WHERE thread_id = ? AND user_id = ?', 'ii', [$id, (int) (isset($_POST['user_id']) ? $_POST['user_id'] : 0)]);
        flash('success', 'تم سحب الصلاحية.');
        redirect($back . '#access');
}

fail_page('إجراء غير معروف.', 400);
