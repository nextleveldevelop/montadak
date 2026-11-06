<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$errors = [];
$to = '';
$subject = '';
$body = '';

// Pre-fill: ?to=username (from a profile) or ?reply=message-id (from a message you received).
$viaMod = (isset($_GET['via']) && $_GET['via'] === 'mod');
$modForumId = $viaMod ? get_int('forum') : 0;
if (is_post()) {
    $viaMod = (post_str('via') === 'mod');
    $modForumId = $viaMod ? (int) post_str('forum') : 0;
}
if ($viaMod && !($modForumId > 0 && can_moderate($modForumId))) {
    fail_page('هذه الميزة للإشراف على هذا المنتدى فقط.', 403);
}
$modForumName = $viaMod ? (string) db_val('SELECT name FROM forums WHERE id = ?', 'i', [$modForumId]) : '';
if (!is_post() && $viaMod && $subject === '') {
    $subject = mb_substr('رسالة من الإشراف — ' . $modForumName, 0, 150);
}
if (!is_post()) {
    if (isset($_GET['to']) && is_string($_GET['to'])) {
        $to = mb_substr(trim($_GET['to']), 0, 30);
    }
    $replyTo = get_int('reply');
    if ($replyTo > 0) {
        $orig = db_row(
            'SELECT m.subject, s.username AS sender
               FROM messages m JOIN users s ON s.id = m.sender_id
              WHERE m.id = ? AND m.receiver_id = ? AND m.receiver_deleted = 0',
            'ii',
            [$replyTo, $user['id']]
        );
        if ($orig) {
            $to = $orig['sender'];
            $subject = mb_strpos($orig['subject'], 'رد: ') === 0 ? $orig['subject'] : 'رد: ' . $orig['subject'];
            $subject = mb_substr($subject, 0, 150);
        }
    }
}

if (is_post()) {
    csrf_verify();
    $to = post_str('to');
    $subject = post_str('subject');
    $body = rich_str('body');

    $recipient = $to !== '' ? db_row('SELECT id, is_banned FROM users WHERE username = ?', 's', [$to]) : null;

    if (!$recipient) {
        $errors[] = 'لا يوجد عضو بهذا الاسم.';
    } elseif ((int) $recipient['id'] === (int) $user['id']) {
        $errors[] = 'لا يمكنك إرسال رسالة إلى نفسك.';
    } elseif (is_blocked_by($recipient['id'], $user['id']) && !is_staff() && !$viaMod) {
        $errors[] = 'هذا العضو منعك من مراسلته.';
    }
    if (mb_strlen($subject) < 1 || mb_strlen($subject) > 150) {
        $errors[] = 'عنوان الرسالة مطلوب (150 حرفًا كحد أقصى).';
    }
    $bodyErr = validate_body($body, 'الرسالة');
    if ($bodyErr !== null) {
        $errors[] = $bodyErr;
    }
    if (!$errors && ($flood = posting_block_message()) !== null) {
        $errors[] = $flood;
    }

    if (!$errors && $viaMod && mb_strpos($subject, '[إشراف]') !== 0) {
        $subject = mb_substr('[إشراف] ' . $subject, 0, 150);
    }
    if (!$errors) {
        db_exec(
            'INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)',
            'iiss',
            [$user['id'], $recipient['id'], $subject, $body]
        );
        mark_action();
        notify($recipient['id'], $user['id'], 'message', $user['username'] . ' راسلك: «' . mb_substr($subject, 0, 60) . '»', 'inbox.php');
        flash('success', 'تم إرسال رسالتك.');
        redirect('inbox.php?box=sent');
    }
}

page_header('رسالة جديدة', 'inbox');
breadcrumbs([['الرسائل', 'inbox.php'], ['رسالة جديدة', null]]);
?>
<div class="panel form-panel">
    <h1>رسالة جديدة</h1>
    <?php foreach ($errors as $err): ?>
        <div class="notice notice-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('send_message.php')) ?>">
        <?= csrf_field() ?>
        <label for="to">إلى (اسم العضوية)</label>
        <?php if ($viaMod): ?><input type="hidden" name="via" value="mod"><input type="hidden" name="forum" value="<?= (int) $modForumId ?>"><p class="hint">تُرسل هذه الرسالة باسم الإشراف على «<?= e($modForumName) ?>».</p><?php endif; ?>
        <input type="text" id="to" name="to" value="<?= e($to) ?>" required maxlength="30">

        <label for="subject">العنوان</label>
        <input type="text" id="subject" name="subject" value="<?= e($subject) ?>" required maxlength="150">

        <label for="body">الرسالة</label>
        <textarea id="body" name="body" rows="8" data-rich="1"><?= e($body) ?></textarea>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">إرسال</button>
            <a class="btn" href="<?= e(url('inbox.php')) ?>">إلغاء</a>
        </div>
    </form>
</div>
<?php page_footer();
