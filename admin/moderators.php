<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'add_supervisor') {
        $fid = (int) (isset($_POST['forum_id']) ? $_POST['forum_id'] : 0);
        $target = db_row('SELECT id FROM users WHERE username = ?', 's', [post_str('username')]);
        if (!db_val('SELECT 1 FROM forums WHERE id = ?', 'i', [$fid])) {
            $errors[] = 'اختر منتدى صالحًا.';
        } elseif (!$target) {
            $errors[] = 'لا يوجد عضو بهذا الاسم.';
        } else {
            db_exec("INSERT INTO forum_staff (forum_id, user_id, level) VALUES (?, ?, 'supervisor') ON DUPLICATE KEY UPDATE level = IF(level = 'moderator', level, 'supervisor')", 'ii', [$fid, $target['id']]);
            flash('success', 'تم تعيين المشرف على المنتدى.');
            redirect('admin/moderators.php');
        }
    } elseif ($action === 'remove_supervisor') {
        db_exec("DELETE FROM forum_staff WHERE level = 'supervisor' AND forum_id = ? AND user_id = ?", 'ii', [(int) $_POST['forum_id'], (int) $_POST['user_id']]);
        flash('success', 'تمت إزالة المشرف.');
        redirect('admin/moderators.php');
    }
}

$forumList = db_all('SELECT id, category, name FROM forums ORDER BY sort_order, id');
$supers = db_all("SELECT fs.forum_id, fs.user_id, u.username FROM forum_staff fs JOIN users u ON u.id = fs.user_id WHERE fs.level = 'supervisor' ORDER BY u.username");
$supBy = [];
foreach ($supers as $r) { $supBy[$r['forum_id']][] = $r; }

page_header('المشرفون', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('moderators'); ?>

<?php foreach ($errors as $err): ?>
    <div class="notice notice-error" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>


<?php if (!$forumList): ?>
    <div class="panel empty"><p>أنشئ منتدى أولًا ثم عيّن له مشرفين.</p></div>
<?php else: ?>
    <form class="panel form-panel" method="post" action="<?= e(url('admin/moderators.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_supervisor">
        <h2>تعيين مشرف على منتدى</h2>
        <div class="grid-form">
            <div><label for="sv-forum">المنتدى</label>
                <select id="sv-forum" name="forum_id"><?php foreach ($forumList as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['category'] . ' ‹ ' . $f['name']) ?></option><?php endforeach; ?></select></div>
            <div><label for="sv-user">اسم العضو</label><input type="text" id="sv-user" name="username" required maxlength="30"></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">تعيين مشرف</button></div>
        <?php foreach ($forumList as $f): foreach (isset($supBy[$f['id']]) ? $supBy[$f['id']] : [] as $m): ?>
            <p><?= user_link($m['user_id'], $m['username'], (int) $f['id']) ?> <span class="meta">مشرف <?= e($f['name']) ?></span>
            <button type="submit" form="rm-sv-<?= (int) $f['id'] ?>-<?= (int) $m['user_id'] ?>" class="link-btn danger">إزالة</button></p>
        <?php endforeach; endforeach; ?>
    </form>
    <?php foreach ($forumList as $f): foreach (isset($supBy[$f['id']]) ? $supBy[$f['id']] : [] as $m): ?>
        <form id="rm-sv-<?= (int) $f['id'] ?>-<?= (int) $m['user_id'] ?>" method="post" action="<?= e(url('admin/moderators.php')) ?>" data-confirm="إزالة <?= e($m['username']) ?>؟">
            <?= csrf_field() ?><input type="hidden" name="action" value="remove_supervisor"><input type="hidden" name="forum_id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
        </form>
    <?php endforeach; endforeach; ?>

<?php endif; ?>
<?php page_footer();
