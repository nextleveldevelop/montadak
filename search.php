<?php
/** Advanced search: keywords, thread starter, forum, titles only or full text. */
require __DIR__ . '/includes/bootstrap.php';

function clean_get($key, $max)
{
    return isset($_GET[$key]) && is_string($_GET[$key]) && mb_check_encoding($_GET[$key], 'UTF-8') ? mb_substr(trim($_GET[$key]), 0, $max) : '';
}

$q = clean_get('q', 100);
$author = clean_get('author', 30);
$forumId = get_int('forum');
$scope = (isset($_GET['scope']) && $_GET['scope'] === 'titles') ? 'titles' : 'all';
$perPage = 25;

$forums = db_all('SELECT id, category, name FROM forums ORDER BY sort_order, id');
$rows = [];
$total = 0;
$pages = 1;
$page = 1;
$message = null;
$searched = $q !== '' || $author !== '' || $forumId > 0;

if ($searched) {
    if ($q !== '' && mb_strlen($q) < 2) {
        $message = 'اكتب حرفين على الأقل في كلمات البحث.';
    } elseif ($q === '' && $author === '') {
        $message = 'اكتب كلمات البحث أو اسم الكاتب.';
    } else {
        $hf = hidden_filter('t');
        $un = unread_parts('t');
        $conds = ['1 = 1'];
        $types = '';
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            if ($scope === 'titles') {
                $conds[] = 't.title LIKE ?';
                $types .= 's';
                $params[] = $like;
            } else {
                $conds[] = '(t.title LIKE ? OR t.content LIKE ? OR EXISTS (SELECT 1 FROM replies r WHERE r.thread_id = t.id AND r.content LIKE ?))';
                $types .= 'sss';
                array_push($params, $like, $like, $like);
            }
        }
        if ($author !== '') {
            $conds[] = 'au.username = ?';
            $types .= 's';
            $params[] = $author;
        }
        if ($forumId > 0) {
            $conds[] = 't.forum_id = ?';
            $types .= 'i';
            $params[] = $forumId;
        }
        $where = implode(' AND ', $conds) . $hf[0];
        $whereTypes = $types . $hf[1];
        $whereParams = array_merge($params, $hf[2]);

        $total = (int) db_val(
            'SELECT COUNT(*) FROM threads t JOIN users au ON au.id = t.user_id WHERE ' . $where,
            $whereTypes,
            $whereParams
        );
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(page_param(), $pages);
        $rows = db_all(
            'SELECT ' . thread_list_columns($un[0]) . ' FROM threads t ' . thread_list_joins() . ' ' . $un[1] . '
              WHERE ' . $where . '
              ORDER BY t.last_post_at DESC, t.id DESC LIMIT ? OFFSET ?',
            $un[2] . $whereTypes . 'ii',
            array_merge($un[3], $whereParams, [$perPage, ($page - 1) * $perPage])
        );
    }
}

page_header('بحث', 'search');
?>
<?php page_hero('search', 'بحث في المنتدى', 'ابحث في العناوين والمشاركات حسب الكاتب والمنتدى'); ?>

<form class="pg-card search-advanced" action="<?= e(url('search.php')) ?>" method="get" role="search">
    <div class="grid-form">
        <div class="wide">
            <label for="q">كلمات البحث</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" maxlength="100">
        </div>
        <div>
            <label for="author">كاتب الموضوع</label>
            <input type="text" id="author" name="author" value="<?= e($author) ?>" maxlength="30">
        </div>
        <div>
            <label for="forum">المنتدى</label>
            <select id="forum" name="forum">
                <option value="0">كل المنتديات</option>
                <?php foreach ($forums as $f): ?>
                    <option value="<?= (int) $f['id'] ?>"<?= (int) $f['id'] === $forumId ? ' selected' : '' ?>><?= e($f['category'] . ' ‹ ' . $f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="scope">البحث في</label>
            <select id="scope" name="scope">
                <option value="all"<?= $scope === 'all' ? ' selected' : '' ?>>العناوين والمشاركات</option>
                <option value="titles"<?= $scope === 'titles' ? ' selected' : '' ?>>العناوين فقط</option>
            </select>
        </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('search') ?> بحث</button></div>
</form>

<?php if ($message !== null): ?>
    <p class="notice notice-info"><?= e($message) ?></p>
<?php elseif ($searched): ?>
    <?php if (!$rows): ?>
        <div class="panel empty"><p>لا توجد نتائج.</p></div>
    <?php else: ?>
        <p class="meta"><?= $total ?> موضوع</p>
        <?php thread_table($rows); ?>
        <?= pagination_html($page, $pages, 'search.php?' . http_build_query(['q' => $q, 'author' => $author, 'forum' => $forumId, 'scope' => $scope])) ?>
    <?php endif; ?>
<?php endif; ?>
<?php page_footer();
