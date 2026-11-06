<?php
/** شكل الصفحة الرئيسية: جدول المنتديات / المنشورات القصيرة / الاثنان معًا. */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];
if (is_post()) {
    csrf_verify();
    $hm = post_str('home_mode');
    if (in_array($hm, ['both', 'forums', 'posts'], true)) {
        site_set('home_mode', $hm);
        flash('success', 'تم حفظ شكل الصفحة الرئيسية.');
        redirect('admin/home.php');
    }
    $errors[] = 'اختر خيارًا صحيحًا.';
}

page_header('الصفحة الرئيسية', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('home'); ?>

<?php foreach ($errors as $error): ?>
    <div class="notice notice-error" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="panel form-panel">
    <h2>شكل الصفحة الرئيسية</h2>
    <form method="post" action="<?= e(url('admin/home.php')) ?>">
        <?= csrf_field() ?>
        <?php foreach (['both' => 'جدول المنتديات + المنشورات القصيرة معًا', 'forums' => 'جدول المنتديات فقط (بعرض كامل)', 'posts' => 'المنشورات القصيرة فقط (بعرض كامل)'] as $k => $lbl): ?>
            <label class="check"><input type="radio" name="home_mode" value="<?= e($k) ?>"<?= home_mode() === $k ? ' checked' : '' ?>> <?= e($lbl) ?></label>
        <?php endforeach; ?>
        <div class="form-actions"><button type="submit" class="btn btn-primary">حفظ</button></div>
    </form>
</section>
<?php page_footer();
