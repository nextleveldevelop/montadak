<?php
require __DIR__ . '/includes/bootstrap.php';

$perPage = 30;
$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 30) : '';
$orders = [
    'name'   => ['الاسم', 'u.username ASC'],
    'posts'  => ['عدد المشاركات', 'posts DESC, u.username ASC'],
    'joined' => ['الأحدث انضمامًا', 'u.id DESC'],
];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($orders[$_GET['sort']])) ? $_GET['sort'] : 'posts';

$where = 'u.is_banned = 0';
$types = '';
$params = [];
if ($q !== '') {
    $where .= ' AND u.username LIKE ?';
    $types = 's';
    $params = ['%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'];
}

$total = (int) db_val('SELECT COUNT(*) FROM users u WHERE ' . $where, $types, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);

$members = db_all(
    'SELECT u.id, u.username, u.avatar, u.country, u.created_at, u.last_seen,
            (SELECT COUNT(*) FROM threads t WHERE t.user_id = u.id) + (SELECT COUNT(*) FROM replies r WHERE r.user_id = u.id) AS posts
       FROM users u WHERE ' . $where . ' ORDER BY ' . $orders[$sort][1] . ' LIMIT ? OFFSET ?',
    $types . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

page_header('الأعضاء', 'members');
?>
<?php page_hero('users', 'الأعضاء (' . $total . ')', 'تصفّح أعضاء المنتدى وابحث عنهم'); ?>

<form class="pg-bar" action="<?= e(url('members.php')) ?>" method="get" role="search">
    <label class="pg-search"><?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" maxlength="30" placeholder="ابحث باسم العضو"></label>
    <label class="sr-only" for="sort">الترتيب</label>
    <select id="sort" name="sort">
        <?php foreach ($orders as $k => $o): ?><option value="<?= e($k) ?>"<?= $k === $sort ? ' selected' : '' ?>><?= e($o[0]) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">تطبيق</button>
</form>

<div class="pro-wrap">
<table class="pro-table">
    <thead>
        <tr>
            <th scope="col" class="pt-n">#</th>
            <th scope="col">العضو</th>
            <th scope="col">الرتبة</th>
            <th scope="col" class="pt-c">المشاركات</th>
            <th scope="col" class="pt-hide">الدولة</th>
            <th scope="col" class="pt-c">الانضمام</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($members as $i => $m): ?>
        <?php
        $lvl = member_level($m['id']);
        list($stars, $rank) = rank_info((int) $m['posts']);
        ?>
        <tr>
            <td class="pt-n"><?= ($page - 1) * $perPage + $i + 1 ?></td>
            <td><span class="pt-user"><?= avatar_html($m['avatar'], 'pt-av', 36) ?><?= user_link($m['id'], $m['username']) ?></span></td>
            <td><span class="pt-rank"><?= $lvl > 0 ? e(level_label($lvl)) : e($rank) ?></span></td>
            <td class="pt-c"><b><?= (int) $m['posts'] ?></b></td>
            <td class="pt-hide"><?= e($m['country']) ?></td>
            <td class="pt-c"><time><?= e(fmt_time($m['created_at'], 'Y/m/d')) ?></time></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= pagination_html($page, $pages, 'members.php?q=' . rawurlencode($q) . '&sort=' . $sort) ?>
<?php page_footer();
