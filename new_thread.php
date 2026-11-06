<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$forumId = get_int('forum_id');
$forum = db_row('SELECT id, category, name, access FROM forums WHERE id = ?', 'i', [$forumId]);
if ($forum && !forum_access_ok($forum)) {
    $forum = null;
}
if (!$forum) {
    fail_page('المنتدى الذي تحاول الكتابة فيه غير موجود.', 404, 'المنتدى غير موجود');
}

$errors = [];
$title = '';
$content = '';
$hidden = 0;
$pollOn = false; $pollQ = ''; $pollOpts = ''; $pollMulti = 0; $pollList = [];

if (is_post()) {
    csrf_verify();
    $title = post_str('title');
    $content = rich_str('content');
    $hidden = 0; // only staff can hide a thread
    $pollOn = post_str('poll_on') === '1';
    $pollQ = post_str('poll_question');
    $pollOpts = post_str('poll_options');
    $pollMulti = post_str('poll_multiple') === '1' ? 1 : 0;
    if ($pollOn) {
        if (mb_strlen($pollQ) < 3 || mb_strlen($pollQ) > 200) {
            $errors[] = 'سؤال الاستفتاء مطلوب (من 3 إلى 200 حرف).';
        }
        foreach (preg_split('~\n+~u', $pollOpts, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $pollList[] = mb_substr($line, 0, 150);
            }
        }
        $pollList = array_values(array_unique($pollList));
        if (count($pollList) < 2 || count($pollList) > 10) {
            $errors[] = 'الاستفتاء يحتاج من 2 إلى 10 خيارات، كل خيار في سطر.';
        }
    }

    foreach ([validate_title($title), validate_body($content, 'نص الموضوع'), posting_block_message($forumId), limit_message($forumId, 'threads')] as $err) {
        if ($err !== null) {
            $errors[] = $err;
            break;
        }
    }

    if (!$errors) {
        $threadId = db_transaction(function () use ($forumId, $user, $title, $content, $hidden, $pollOn, $pollQ, $pollMulti, $pollList) {
            db_exec(
                'INSERT INTO threads (forum_id, user_id, title, content, last_post_user_id, is_hidden) VALUES (?, ?, ?, ?, ?, ?)',
                'iissii',
                [$forumId, $user['id'], $title, $content, $user['id'], $hidden]
            );
            $newId = db_insert_id();
            if ($pollOn) {
                db_exec('INSERT INTO polls (thread_id, question, multiple) VALUES (?, ?, ?)', 'isi', [$newId, $pollQ, $pollMulti]);
                foreach ($pollList as $i => $label) {
                    db_exec('INSERT INTO poll_options (thread_id, label, sort_order) VALUES (?, ?, ?)', 'isi', [$newId, $label, $i]);
                }
            }
            return $newId;
        });
        mark_action();
        mark_thread_read($threadId);
        if (!$hidden) {
            notify_new_thread($threadId, $forumId, $user, $title);
        }
        flash('success', $hidden ? 'تم نشر الموضوع كموضوع مخفي. افتحه لمن تريد من صفحة الموضوع.' : 'تم نشر الموضوع.');
        redirect('thread.php?id=' . $threadId);
    }
}

page_header('موضوع جديد');
breadcrumbs([['الرئيسية', 'index.php'], [$forum['name'], 'forum.php?id=' . $forumId], ['موضوع جديد', null]]);
?>
<div class="panel form-panel">
    <h1>موضوع جديد في «<?= e($forum['name']) ?>»</h1>
    <?php foreach ($errors as $err): ?>
        <div class="notice notice-error" role="alert"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('new_thread.php?forum_id=' . $forumId)) ?>">
        <?= csrf_field() ?>
        <label for="title">عنوان الموضوع</label>
        <input type="text" id="title" name="title" value="<?= e($title) ?>" required maxlength="200" autofocus>

        <label for="content">نص الموضوع</label>
        <textarea id="content" name="content" rows="10" data-rich="1"><?= e($content) ?></textarea>

        <details class="poll-edit"<?= $pollOn ? ' open' : '' ?>>
            <summary><?= icon('poll') ?> إضافة استفتاء إلى الموضوع (اختياري)</summary>
            <label class="check"><input type="checkbox" name="poll_on" value="1"<?= $pollOn ? ' checked' : '' ?>> تفعيل الاستفتاء</label>
            <label for="poll_question">سؤال الاستفتاء</label>
            <input type="text" id="poll_question" name="poll_question" value="<?= e($pollQ) ?>" maxlength="200">
            <label for="poll_options">الخيارات (كل خيار في سطر، من 2 إلى 10)</label>
            <textarea id="poll_options" name="poll_options" rows="5"><?= e($pollOpts) ?></textarea>
            <label class="check"><input type="checkbox" name="poll_multiple" value="1"<?= $pollMulti ? ' checked' : '' ?>> السماح باختيار أكثر من خيار</label>
        </details>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">نشر الموضوع</button>
            <a class="btn" href="<?= e(url('forum.php?id=' . $forumId)) ?>">إلغاء</a>
        </div>
    </form>
</div>
<?php page_footer();
