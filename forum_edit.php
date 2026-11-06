<?php
/** إدارة المنتدى: deputy moderators edit description + image; moderators (and admins) also name + icon. */
require __DIR__ . '/includes/bootstrap.php';
require_login();

$forumId = get_int('id');
$forum = db_row('SELECT id, category, name, description, icon, image FROM forums WHERE id = ?', 'i', [$forumId]);
if (!$forum) {
    fail_page('المنتدى غير موجود.', 404);
}
$level = forum_level($forumId);
if (!is_admin()) {
    fail_page('إدارة المنتدى متاحة لنائب المراقب والمراقب والمدير.', 403);
}
$errors = [];

if (is_post()) {
    csrf_verify();
    $description = post_str('description');
    $name = $forum['name'];
    $icon = $forum['icon'];
    if (mb_strlen($description) > 255) {
        $errors[] = 'الوصف طويل جدًا (255 حرفًا كحد أقصى).';
    }
    if ($level >= 3) {
        $name = post_str('name');
        $icon = post_str('icon');
        if (!isset(forum_icon_choices()[$icon])) {
            $icon = $forum['icon'];
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'اسم المنتدى مطلوب (من 2 إلى 100 حرف).';
        }
    }
    $imgErr = '';
    $newImage = '';
    if (!$errors) {
        $newImage = save_forum_image(isset($_FILES['image']) ? $_FILES['image'] : null, $imgErr);
        if ($newImage === null) {
            $errors[] = $imgErr;
            $newImage = '';
        }
    }
    if (!$errors) {
        db_exec('UPDATE forums SET name = ?, description = ?, icon = ? WHERE id = ?', 'sssi', [$name, $description, $icon, $forumId]);
        if ($newImage !== '' || post_str('remove_image') === '1') {
            delete_forum_image($forum['image']);
            db_exec('UPDATE forums SET image = ? WHERE id = ?', 'si', [$newImage, $forumId]);
        }
        flash('success', 'تم حفظ تعديلات المنتدى.');
        redirect('forum.php?id=' . $forumId);
    }
}

page_header('إدارة المنتدى');
breadcrumbs([['الرئيسية', 'index.php'], [$forum['name'], 'forum.php?id=' . $forumId], ['إدارة المنتدى', null]]);
?>
<h1 class="page-title">إدارة المنتدى: <?= e($forum['name']) ?></h1>
<?php foreach ($errors as $err): ?><div class="notice notice-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>
<form class="panel form-panel" method="post" enctype="multipart/form-data" action="<?= e(url('forum_edit.php?id=' . $forumId)) ?>">
    <?= csrf_field() ?>
    <div class="grid-form">
        <?php if ($level >= 3): ?>
            <div>
                <label for="name">اسم المنتدى</label>
                <input type="text" id="name" name="name" value="<?= e($forum['name']) ?>" required maxlength="100">
            </div>
            <div>
                <label for="icon">الأيقونة</label>
                <select id="icon" name="icon">
                    <?php foreach (forum_icon_choices() as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $k === $forum['icon'] ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="wide">
            <label for="description">الوصف</label>
            <input type="text" id="description" name="description" value="<?= e($forum['description']) ?>" maxlength="255">
        </div>
        <div>
            <label for="image">صورة المنتدى (PNG/JPG/GIF)</label>
            <?php if ($forum['image'] !== ''): ?><?= forum_icon_html($forum) ?><?php endif; ?>
            <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/gif">
            <?php if ($forum['image'] !== ''): ?><label><input type="checkbox" name="remove_image" value="1"> إزالة الصورة</label><?php endif; ?>
        </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">حفظ</button></div>
</form>
<?php page_footer();
