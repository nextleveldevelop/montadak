<?php
/** المواضيع الصغيرة في صفحة العضوية: إنشاء / حذف / عرض كامل. */
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    $user = require_login();
    csrf_verify();
    $action = post_str('action');
    if ($action === 'delete') {
        $id = (int) post_str('id');
        $row = db_row('SELECT id, user_id FROM user_posts WHERE id = ?', 'i', [$id]);
        if ($row && ((int) $row['user_id'] === (int) $user['id'] || is_admin())) {
            db_exec('DELETE FROM user_posts WHERE id = ?', 'i', [$id]);
            flash('success', 'تم حذف الموضوع.');
        }
        redirect(safe_next(post_str('next')));
    }
    if ($action === 'update') {
        $id = (int) post_str('id');
        $row = db_row('SELECT id, user_id FROM user_posts WHERE id = ?', 'i', [$id]);
        if (!$row || (int) $row['user_id'] !== (int) $user['id']) {
            fail_page('لا يمكنك تعديل هذا الموضوع.', 403);
        }
        $title = post_str('title'); $tag = post_str('tag'); $content = rich_str('content');
        $words = count(words_of($content));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 150 || !in_array($tag, mini_tags(), true) || $words < 3 || $words > MINI_MAX_WORDS || mb_strlen($content) > 8000) {
            flash('error', 'تحقق من العنوان والوسم والنص (أقل من ' . MINI_MAX_WORDS . ' كلمة).');
            redirect('mini.php?id=' . $id . '&edit=1');
        }
        db_exec('UPDATE user_posts SET title = ?, tag = ?, content = ? WHERE id = ?', 'sssi', [$title, $tag, $content, $id]);
        flash('success', 'تم تعديل الموضوع.');
        redirect('mini.php?id=' . $id);
    }
    // create
    $title = post_str('title');
    $tag = post_str('tag');
    $content = rich_str('content');
    $err = null;
    $words = count(words_of($content));
    if (mute_message() !== null) {
        $err = mute_message();
    } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $err = 'العنوان من 3 إلى 150 حرفًا.';
    } elseif (!in_array($tag, mini_tags(), true)) {
        $err = 'اختر مجال الموضوع من القائمة.';
    } elseif ($words < 3) {
        $err = 'النص قصير جدًا.';
    } elseif ($words > MINI_MAX_WORDS || mb_strlen($content) > 8000) {
        $err = 'النص طويل: الحد الأقصى ' . MINI_MAX_WORDS . ' كلمة.';
    } elseif ((int) db_val('SELECT COUNT(*) FROM user_posts WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)', 'i', [$user['id']]) >= 2) {
        $err = 'تمهّل قليلًا قبل نشر موضوع آخر.';
    }
    if ($err !== null) {
        flash('error', $err);
        $_SESSION['mini_draft'] = ['title' => $title, 'tag' => $tag, 'content' => $content];
        redirect('profile.php?id=' . (int) $user['id'] . '#mini-form');
    }
    db_exec('INSERT INTO user_posts (user_id, title, tag, content) VALUES (?, ?, ?, ?)', 'isss', [$user['id'], $title, $tag, $content]);
    $newId = db_insert_id();
    foreach (db_all("SELECT user_id FROM follows WHERE target_type = 'user' AND target_id = ?", 'i', [$user['id']]) as $f) {
        notify($f['user_id'], $user['id'], 'mini', $user['username'] . ' نشر موضوعًا في صفحة عضويته: «' . mb_substr($title, 0, 60) . '»', 'mini.php?id=' . $newId);
    }
    $already = array_column(db_all("SELECT user_id FROM follows WHERE target_type = 'user' AND target_id = ?", 'i', [$user['id']]), 'user_id');
    foreach (db_all('SELECT user_id FROM tag_follows WHERE tag = ?', 's', [$tag]) as $tf) {
        if ((int) $tf['user_id'] !== (int) $user['id'] && !in_array($tf['user_id'], $already)) {
            notify($tf['user_id'], $user['id'], 'mini', 'منشور جديد في الوسم #' . $tag . ': «' . mb_substr($title, 0, 60) . '»', 'mini.php?id=' . $newId);
        }
    }
    flash('success', 'تم نشر الموضوع في صفحة عضويتك.');
    redirect('profile.php?id=' . (int) $user['id']);
}

$id = get_int('id');
$p = db_row('SELECT p.id, p.user_id, p.title, p.tag, p.content, p.created_at, u.username FROM user_posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?', 'i', [$id]);
if (!$p) {
    fail_page('الموضوع غير موجود.', 404);
}
page_header($p['title']);
$me = current_user();
if (isset($_GET['edit']) && $me !== null && (int) $me['id'] === (int) $p['user_id']):
?>
<section class="mini-full">
    <h1>تعديل الموضوع</h1>
    <form method="post" action="<?= e(url('mini.php')) ?>" class="mini-compose-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <label for="t">العنوان</label><input type="text" id="t" name="title" maxlength="150" required value="<?= e($p['title']) ?>">
        <label for="g">المجال</label>
        <select id="g" name="tag"><?php foreach (mini_tags() as $tg): ?><option value="<?= e($tg) ?>"<?= $tg === $p['tag'] ? ' selected' : '' ?>><?= e($tg) ?></option><?php endforeach; ?></select>
        <label for="c">النص</label><textarea id="c" name="content" rows="8" data-rich="1"><?= e($p['content']) ?></textarea>
        <div class="form-actions"><button class="btn btn-primary" type="submit">حفظ</button></div>
    </form>
</section>
<?php page_footer(); exit; endif; ?>
<article class="mini-full">
    <a class="acc-back" href="<?= e(url('profile.php?id=' . (int) $p['user_id'])) ?>"><?= icon('reply') ?> صفحة <?= e($p['username']) ?></a>
    <a class="mini-tag" href="<?= e(url('tag.php?t=' . rawurlencode($p['tag']))) ?>">#<?= e($p['tag']) ?></a>
    <h1><?= e($p['title']) ?></h1>
    <p class="meta"><?= user_link($p['user_id'], $p['username']) ?> · <time><?= e(fmt_when($p['created_at'])) ?></time></p>
    <div class="post-body"><?= post_html($p['content']) ?></div>
</article>
<?php page_footer();
