<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$errors = [];
$new = ['category' => '', 'name' => '', 'description' => '', 'sort_order' => '0', 'icon' => 'chat', 'max_threads_day' => '15', 'max_replies_day' => '1000'];

if (is_post()) {
    csrf_verify();
    $action = post_str('action');
    $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);

    if ($action === 'toggle_closed') {
        db_exec('UPDATE forums SET closed = 1 - closed WHERE id = ?', 'i', [$id]);
        flash('success', 'تم تغيير حالة قفل المنتدى.');
        redirect('admin/forums.php');
    }
    if ($action === 'delete') {
        $oldImg = db_val('SELECT image FROM forums WHERE id = ?', 'i', [$id]);
        delete_forum_image($oldImg);
        db_exec('DELETE FROM forums WHERE id = ?', 'i', [$id]); // its threads and replies go with it
        flash('success', 'تم حذف المنتدى.');
        redirect('admin/forums.php');
    }

    if ($action === 'create' || $action === 'update') {
        $category = post_str('category');
        $name = post_str('name');
        $description = post_str('description');
        $sort = max(0, min(9999, (int) post_str('sort_order')));
        $maxThreads = max(0, min(1000, (int) post_str('max_threads_day')));
        $maxReplies = max(0, min(100000, (int) post_str('max_replies_day')));
        $icon = post_str('icon');
        if (!isset(forum_icon_choices()[$icon])) {
            $icon = 'chat';
        }

        if (mb_strlen($category) < 1 || mb_strlen($category) > 100) {
            $errors[] = 'اسم القسم مطلوب (100 حرف كحد أقصى).';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'اسم المنتدى مطلوب (من 2 إلى 100 حرف).';
        }
        if (mb_strlen($description) > 255) {
            $errors[] = 'الوصف طويل جدًا (255 حرفًا كحد أقصى).';
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
            if ($action === 'create') {
                db_exec('INSERT INTO forums (category, name, description, sort_order, icon, max_threads_day, max_replies_day, image, access) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssisiiss', [$category, $name, $description, $sort, $icon, $maxThreads, $maxReplies, $newImage, (in_array(post_str('access'), ['staff_all', 'admin_only'], true) ? post_str('access') : 'public')]);
                sync_category_staff();
                ensure_section_mod_forums(); // every section gets its own supervisors' forum
                flash('success', 'تم إنشاء المنتدى.');
            } else {
                $old = db_row('SELECT category, image FROM forums WHERE id = ?', 'i', [$id]);
                if ($old && $old['category'] !== $category) {
                    db_exec("DELETE FROM forum_staff WHERE forum_id = ? AND level <> 'supervisor'", 'i', [$id]);
                }
                if ($old && ($newImage !== '' || post_str('remove_image') === '1')) {
                    delete_forum_image($old['image']);
                    db_exec("UPDATE forums SET image = ? WHERE id = ?", 'si', [$newImage, $id]);
                }
                db_exec('UPDATE forums SET category = ?, name = ?, description = ?, sort_order = ?, icon = ?, max_threads_day = ?, max_replies_day = ? WHERE id = ?', 'sssisiii', [$category, $name, $description, $sort, $icon, $maxThreads, $maxReplies, $id]);
                sync_category_staff();
                flash('success', 'تم حفظ التعديلات.');
            }
            redirect('admin/forums.php');
        }
        if ($action === 'create') {
            $new = ['category' => $category, 'name' => $name, 'description' => $description, 'sort_order' => (string) $sort, 'icon' => $icon, 'max_threads_day' => (string) $maxThreads, 'max_replies_day' => (string) $maxReplies];
        }
    }
}

$perPage = 10;
$total = (int) db_val('SELECT COUNT(*) FROM forums');
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$forums = db_all(
    'SELECT f.id, f.category, f.name, f.description, f.sort_order, f.icon, f.image, f.max_threads_day, f.max_replies_day,
            (SELECT COUNT(*) FROM threads t WHERE t.forum_id = f.id) AS thread_count
       FROM forums f ORDER BY f.sort_order, f.id LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
);
$categories = db_all('SELECT DISTINCT category FROM forums ORDER BY category');

page_header('إدارة المنتديات', 'admin');
?>
<h1 class="page-title">لوحة الإدارة</h1>
<?php admin_nav('forums'); ?>

<?php foreach ($errors as $err): ?>
    <div class="notice notice-error" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<datalist id="category-list">
    <?php foreach ($categories as $c): ?><option value="<?= e($c['category']) ?>"><?php endforeach; ?>
</datalist>

<form class="panel form-panel" method="post" enctype="multipart/form-data" action="<?= e(url('admin/forums.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2>منتدى جديد</h2>
    <div class="grid-form">
        <div>
            <label for="new-category">القسم</label>
            <input type="text" id="new-category" name="category" list="category-list" value="<?= e($new['category']) ?>" required maxlength="100">
        </div>
        <div>
            <label for="new-name">اسم المنتدى</label>
            <input type="text" id="new-name" name="name" value="<?= e($new['name']) ?>" required maxlength="100">
        </div>
        <div class="wide">
            <label for="new-description">الوصف</label>
            <input type="text" id="new-description" name="description" value="<?= e($new['description']) ?>" maxlength="255">
        </div>
        <div>
            <label for="new-sort">الترتيب</label>
            <input type="number" id="new-sort" name="sort_order" value="<?= e($new['sort_order']) ?>" min="0" max="9999">
        </div>
        <div>
            <label for="new-icon">الأيقونة</label>
            <label for="new-access">نوع المنتدى</label>
            <select id="new-access" name="access"><option value="public">يراه الجميع</option><option value="staff_all">يراه المشرفون والمدير ونائبه</option><option value="admin_only">يراه المدير ونائبه فقط</option></select>
            <select id="new-icon" name="icon">
                <?php foreach (forum_icon_choices() as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $k === $new['icon'] ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="new-image">صورة / أيقونة المنتدى (PNG/JPG/GIF)</label>
            <input type="file" id="new-image" name="image" accept="image/png,image/jpeg,image/gif">
        </div>
        <div>
            <label for="new-mt">الحد الأقصى للمواضيع لكل عضو في 24 ساعة (0 = بلا حد)</label>
            <input type="number" id="new-mt" name="max_threads_day" value="<?= e($new['max_threads_day']) ?>" min="0" max="1000">
        </div>
        <div>
            <label for="new-mr">الحد الأقصى للردود لكل عضو في 24 ساعة (0 = بلا حد)</label>
            <input type="number" id="new-mr" name="max_replies_day" value="<?= e($new['max_replies_day']) ?>" min="0" max="100000">
        </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary">إنشاء المنتدى</button></div>
</form>

<h2>المنتديات الحالية</h2>
<?php if (!$forums): ?>
    <div class="panel empty"><p>لا توجد منتديات بعد.</p></div>
<?php endif; ?>

<?php foreach ($forums as $f): ?>
    <div class="panel forum-edit">
        <form method="post" enctype="multipart/form-data" action="<?= e(url('admin/forums.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <div class="grid-form">
                <div>
                    <label for="cat-<?= (int) $f['id'] ?>">القسم</label>
                    <input type="text" id="cat-<?= (int) $f['id'] ?>" name="category" list="category-list" value="<?= e($f['category']) ?>" required maxlength="100">
                </div>
                <div>
                    <label for="name-<?= (int) $f['id'] ?>">اسم المنتدى</label>
                    <input type="text" id="name-<?= (int) $f['id'] ?>" name="name" value="<?= e($f['name']) ?>" required maxlength="100">
                </div>
                <div class="wide">
                    <label for="desc-<?= (int) $f['id'] ?>">الوصف</label>
                    <input type="text" id="desc-<?= (int) $f['id'] ?>" name="description" value="<?= e($f['description']) ?>" maxlength="255">
                </div>
                <div>
                    <label for="sort-<?= (int) $f['id'] ?>">الترتيب</label>
                    <input type="number" id="sort-<?= (int) $f['id'] ?>" name="sort_order" value="<?= (int) $f['sort_order'] ?>" min="0" max="9999">
                </div>
                <div>
                    <label for="icon-<?= (int) $f['id'] ?>">الأيقونة</label>
                    <select id="icon-<?= (int) $f['id'] ?>" name="icon">
                        <?php foreach (forum_icon_choices() as $k => $label): ?>
                            <option value="<?= e($k) ?>"<?= $k === $f['icon'] ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="img-<?= (int) $f['id'] ?>">صورة المنتدى</label>
                    <?php if (!empty($f['image'])): ?><?= forum_icon_html($f) ?><?php endif; ?>
                    <input type="file" id="img-<?= (int) $f['id'] ?>" name="image" accept="image/png,image/jpeg,image/gif">
                    <?php if (!empty($f['image'])): ?><label><input type="checkbox" name="remove_image" value="1"> إزالة الصورة</label><?php endif; ?>
                </div>
                <div>
                    <label for="mt-<?= (int) $f['id'] ?>">مواضيع/24 ساعة (0 = بلا حد)</label>
                    <input type="number" id="mt-<?= (int) $f['id'] ?>" name="max_threads_day" value="<?= (int) $f['max_threads_day'] ?>" min="0" max="1000">
                </div>
                <div>
                    <label for="mr-<?= (int) $f['id'] ?>">ردود/24 ساعة (0 = بلا حد)</label>
                    <input type="number" id="mr-<?= (int) $f['id'] ?>" name="max_replies_day" value="<?= (int) $f['max_replies_day'] ?>" min="0" max="100000">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a class="btn" href="<?= e(url('forum.php?id=' . $f['id'])) ?>"><?= (int) $f['thread_count'] ?> موضوع — عرض المنتدى</a>
            </div>
        </form>
        <form method="post" action="<?= e(url('admin/forums.php')) ?>" class="inline-form"
              data-confirm="سيتم حذف المنتدى «<?= e($f['name']) ?>» وكل مواضيعه وردوده نهائيًا. هل أنت متأكد؟">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <button type="submit" class="btn btn-danger btn-small">حذف المنتدى</button>
        </form>
    </div>
<?php endforeach; ?>
<?= pagination_html($page, $pages, 'admin/forums.php') ?>
<section class="panel form-panel">
    <h2>قفل المنتديات أو حذفها نهائيًا</h2>
    <p class="hint">القفل يمنع الأعضاء من كتابة مواضيع وردود جديدة (المدير يستثنى). الحذف نهائي ويحذف معه كل مواضيع المنتدى.</p>
    <ul class="follow-list">
    <?php foreach (db_all('SELECT id, category, name, closed FROM forums ORDER BY category, sort_order, id') as $fz): ?>
        <li><span><?= e($fz['category'] . ' ‹ ' . $fz['name']) ?><?= (int) $fz['closed'] === 1 ? ' <span class="badge badge-locked">مقفل</span>' : '' ?></span>
        <span>
            <form method="post" action="<?= e(url('admin/forums.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_closed"><input type="hidden" name="id" value="<?= (int) $fz['id'] ?>"><button class="btn btn-small" type="submit"><?= (int) $fz['closed'] === 1 ? 'فتح' : 'قفل' ?></button></form>
            <form method="post" action="<?= e(url('admin/forums.php')) ?>" class="inline-form" onsubmit="return confirm('حذف المنتدى نهائيًا مع كل مواضيعه؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $fz['id'] ?>"><button class="btn btn-small btn-danger" type="submit">حذف نهائي</button></form>
        </span></li>
    <?php endforeach; ?>
    </ul>
</section>
<?php page_footer();
