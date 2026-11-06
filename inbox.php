<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$box = (isset($_GET['box']) && $_GET['box'] === 'sent') ? 'sent' : 'inbox';
$perPage = 20;

if ($box === 'inbox') {
    $total = (int) db_val('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND receiver_deleted = 0', 'i', [$user['id']]);
} else {
    $total = (int) db_val('SELECT COUNT(*) FROM messages WHERE sender_id = ? AND sender_deleted = 0', 'i', [$user['id']]);
}
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$offset = ($page - 1) * $perPage;

if ($box === 'inbox') {
    $rows = db_all(
        'SELECT m.id, m.subject, m.is_read, m.created_at, u.id AS other_id, u.username AS other
           FROM messages m JOIN users u ON u.id = m.sender_id
          WHERE m.receiver_id = ? AND m.receiver_deleted = 0
          ORDER BY m.id DESC LIMIT ? OFFSET ?',
        'iii',
        [$user['id'], $perPage, $offset]
    );
} else {
    $rows = db_all(
        'SELECT m.id, m.subject, m.is_read, m.created_at, u.id AS other_id, u.username AS other
           FROM messages m JOIN users u ON u.id = m.receiver_id
          WHERE m.sender_id = ? AND m.sender_deleted = 0
          ORDER BY m.id DESC LIMIT ? OFFSET ?',
        'iii',
        [$user['id'], $perPage, $offset]
    );
}

page_header('الرسائل', 'inbox');
?>
<?php page_hero('mail', 'الرسائل', '', '<a class="btn btn-primary" href="' . e(url('send_message.php')) . '">' . icon('pencil') . ' رسالة جديدة</a>'); ?>

<nav class="pg-tabs" aria-label="صناديق الرسائل">
    <a href="<?= e(url('inbox.php')) ?>"<?= $box === 'inbox' ? ' aria-current="page"' : '' ?>><?= icon('mail') ?> الوارد</a>
    <a href="<?= e(url('inbox.php?box=sent')) ?>"<?= $box === 'sent' ? ' aria-current="page"' : '' ?>><?= icon('reply') ?> المُرسَلة</a>
</nav>

<?php if (!$rows): ?>
    <div class="panel empty"><p><?= $box === 'inbox' ? 'لا توجد رسائل واردة.' : 'لم ترسل أي رسالة بعد.' ?></p></div>
<?php else: ?>
    <div class="pro-wrap"><table class="pro-table">
        <thead>
            <tr>
                <th scope="col"><?= $box === 'inbox' ? 'من' : 'إلى' ?></th>
                <th scope="col">العنوان</th>
                <th scope="col" class="pt-c">التاريخ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $m): ?>
            <?php $unread = $box === 'inbox' && !$m['is_read']; ?>
            <tr class="<?= $unread ? 'is-unread' : '' ?>">
                <td><?= user_link($m['other_id'], $m['other']) ?></td>
                <td class="main">
                    <a class="row-title" href="<?= e(url('message.php?id=' . $m['id'])) ?>"><?= e($m['subject']) ?></a>
                    <?php if ($unread): ?><span class="pg-tag pg-tag-new">جديدة</span><?php endif; ?>
                </td>
                <td class="pt-c"><time><?= e(fmt_time($m['created_at'])) ?></time></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pagination_html($page, $pages, 'inbox.php?box=' . $box) ?>
<?php endif; ?>
<?php page_footer();
