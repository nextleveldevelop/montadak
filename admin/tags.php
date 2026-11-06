<?php
/** إدارة وسوم المواضيع الصغيرة: الوسوم الجديدة تظهر تلقائيًا في قائمة الاختيار. */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
if (is_post()) {
    csrf_verify();
    $name = mb_substr(trim(post_str('name')), 0, 30);
    if (post_str('action') === 'delete') {
        db_exec('DELETE FROM mini_tags WHERE name = ?', 's', [$name]);
        flash('success', 'تم حذف الوسم.');
    } elseif (mb_strlen($name) < 2 || preg_match('/[<>"\'#\\\/]/u', $name)) {
        flash('error', 'اسم الوسم غير صالح (حرفان على الأقل بدون رموز).');
    } elseif (in_array($name, mini_tags(), true)) {
        flash('error', 'هذا الوسم موجود.');
    } else {
        db_exec('INSERT INTO mini_tags (name) VALUES (?)', 's', [$name]);
        flash('success', 'تمت إضافة الوسم.');
    }
    redirect('admin/tags.php');
}
$custom = array_column(db_all('SELECT name FROM mini_tags ORDER BY name'), 'name');
page_header('الوسوم', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('tags'); ?>
<section class="pg-card">
    <h2><?= icon('news') ?> إضافة وسم جديد</h2>
    <form method="post" class="pg-bar"><?= csrf_field() ?>
        <input type="text" name="name" maxlength="30" placeholder="اسم الوسم" required>
        <button class="btn btn-primary" type="submit">إضافة</button>
    </form>
</section>
<section class="pg-card">
    <h2>الوسوم الحالية</h2>
    <div class="tag-cloud">
        <?php foreach (mini_tags() as $tg): ?>
            <span class="mini-tag">#<?= e($tg) ?>
            <?php if (in_array($tg, $custom, true)): ?>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($tg) ?>"><button class="icon-btn" title="حذف" onclick="return confirm('حذف الوسم؟')"><?= icon('trash') ?></button></form>
            <?php endif; ?></span>
        <?php endforeach; ?>
    </div>
</section>
<?php page_footer();
