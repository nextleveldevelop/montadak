<?php
require __DIR__ . '/includes/bootstrap.php';

$threadId = get_int('id');
$postsSql = '(SELECT COUNT(*) FROM threads x WHERE x.user_id = u.id) + (SELECT COUNT(*) FROM replies y WHERE y.user_id = u.id)';
$warnSql = "(SELECT COUNT(*) FROM sanctions s WHERE s.user_id = u.id AND s.type = 'warning')";
$likesSql = '(SELECT COALESCE(SUM(lx.like_count), 0) FROM threads lx WHERE lx.user_id = u.id) + (SELECT COALESCE(SUM(ly.like_count), 0) FROM replies ly WHERE ly.user_id = u.id)';

$thread = db_row(
    'SELECT t.id, t.forum_id, t.user_id, t.title, t.content, t.is_pinned, t.is_locked, t.is_hidden, t.is_archived,
            t.views, t.reply_count, t.like_count, t.feature_level, t.created_at, t.edited_at,
            u.username AS author, u.avatar AS author_avatar, u.created_at AS author_joined, u.country AS author_country, u.signature AS author_signature,
            ' . $postsSql . ' AS author_posts, ' . $warnSql . ' AS author_warnings, ' . $likesSql . ' AS author_likes
       FROM threads t JOIN users u ON u.id = t.user_id
      WHERE t.id = ?',
    'i',
    [$threadId]
);
if (!$thread || !can_view_thread($thread)) {
    fail_page('هذا الموضوع غير موجود، أو أنه مخفي ولم تُمنح صلاحية فتحه.', 404, 'الموضوع غير متاح');
}
$forumId = (int) $thread['forum_id'];
$forum = db_row('SELECT id, category, name, access FROM forums WHERE id = ?', 'i', [$forumId]);
if (!$forum || !forum_access_ok($forum)) {
    fail_page('هذا الموضوع غير موجود.', 404);
}

$user = current_user();
$level = forum_level($forumId);
$canModerate = $level >= 1;
$canSupervise = $level >= 2;
$canProps = $level >= 1;
$isAuthor = $user !== null && (int) $user['id'] === (int) $thread['user_id'];
$isLocked = (int) $thread['is_locked'] === 1;
$isArchived = (int) $thread['is_archived'] === 1;
$isHidden = (int) $thread['is_hidden'] === 1;
$closed = $isLocked || $isArchived;
$canReply = $user !== null && (!$closed || $canModerate);
$modMode = mod_mode_on();

// Display preferences (kept in the session): replies per page, signatures on/off.
if (isset($_GET['sigs'])) {
    $_SESSION['sigs'] = $_GET['sigs'] === '0' ? 0 : 1;
}
$showSigs = false;
$perPage = posts_per_page();

// ---- Quote button: pre-fill the reply box ----
$errors = [];
$replyText = '';
if (!is_post() && $canReply && isset($_GET['quote'])) {
    $q = (string) $_GET['quote'];
    $qAuthor = null;
    $qText = '';
    if ($q === 'op') {
        $qAuthor = $thread['author'];
        $qText = rich_plain($thread['content']);
    } elseif (ctype_digit($q)) {
        $row = db_row('SELECT r.content, u.username FROM replies r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.thread_id = ?', 'ii', [(int) $q, $threadId]);
        if ($row) {
            $qAuthor = $row['username'];
            $qText = rich_plain($row['content']);
        }
    }
    if ($qAuthor !== null) {
        $qText = preg_replace('~\[اقتباس(?: من [^\]\n]{1,40})?\].*?\[/اقتباس\]~us', '', $qText); // no quotes inside quotes
        $qText = trim(mb_substr($qText, 0, 1500));
        $replyText = '[اقتباس من ' . str_replace([']', "\n"], ' ', $qAuthor) . "]\n" . $qText . "\n[/اقتباس]\n\n";
    }
}

// ---- Post a reply (then redirect, so refreshing never re-posts) ----
if (is_post()) {
    $user = require_login();
    csrf_verify();
    if (!$canReply) {
        fail_page('هذا الموضوع مغلق ولا يمكن الرد عليه.', 403);
    }
    $replyText = rich_str('content');
    $err = validate_body($replyText, 'الرد');
    if ($err === null) {
        $err = posting_block_message($forumId);
    }
    if ($err === null) {
        $err = limit_message($forumId, 'replies');
    }
    if ($err !== null) {
        $errors[] = $err;
    } else {
        $replyId = db_transaction(function () use ($threadId, $user, $replyText) {
            db_exec('INSERT INTO replies (thread_id, user_id, content) VALUES (?, ?, ?)', 'iis', [$threadId, $user['id'], $replyText]);
            $id = db_insert_id();
            db_exec('UPDATE threads SET reply_count = reply_count + 1, last_post_at = NOW(), last_post_user_id = ? WHERE id = ?', 'ii', [$user['id'], $threadId]);
            return $id;
        });
        mark_action();
        mark_thread_read($threadId);
        $rPage = reply_page($threadId, $replyId);
        notify_new_reply($threadId, $replyId, $user, $rPage);
        redirect('thread.php?id=' . $threadId . '&page=' . $rPage . '#post-' . $replyId);
    }
}

// ---- Views and read marker (GET only) ----
if (!is_post()) {
    $seen = isset($_SESSION['seen_threads']) && is_array($_SESSION['seen_threads']) ? $_SESSION['seen_threads'] : [];
    if (empty($seen[$threadId])) {
        db_exec('UPDATE threads SET views = views + 1 WHERE id = ?', 'i', [$threadId]);
        $thread['views'] = (int) $thread['views'] + 1;
        $seen[$threadId] = 1;
        $_SESSION['seen_threads'] = array_slice($seen, -200, null, true);
    }
    mark_thread_read($threadId);
}

// ---- Replies for the current page ----
$total = (int) $thread['reply_count'];
$pages = max(1, (int) ceil($total / $perPage));
$page = min(page_param(), $pages);
$offset = ($page - 1) * $perPage;
$replies = db_all(
    'SELECT r.id, r.user_id, r.content, r.is_hidden, r.like_count, r.created_at, r.edited_at,
            u.username, u.avatar, u.created_at AS joined, u.country, u.signature,
            ' . $postsSql . ' AS posts, ' . $warnSql . ' AS warnings, ' . $likesSql . ' AS likes_received
       FROM replies r JOIN users u ON u.id = r.user_id
      WHERE r.thread_id = ?
      ORDER BY r.id ASC
      LIMIT ? OFFSET ?',
    'iii',
    [$threadId, $perPage, $offset]
);

// Who liked the thread (reply_id 0) and the replies on this page
$replyIds = array_map(function ($r) { return (int) $r['id']; }, $replies);
$idList = $replyIds ? implode(',', $replyIds) : '0';
$likers = [];
$myLikes = [];
foreach (db_all(
    'SELECT pl.reply_id, pl.user_id, u.username FROM post_likes pl JOIN users u ON u.id = pl.user_id
      WHERE pl.thread_id = ? AND (pl.reply_id = 0 OR pl.reply_id IN (' . $idList . ')) ORDER BY pl.created_at DESC LIMIT 400',
    'i',
    [$threadId]
) as $lk) {
    $likers[(int) $lk['reply_id']][] = $lk;
    if ($user !== null && (int) $lk['user_id'] === (int) $user['id']) {
        $myLikes[(int) $lk['reply_id']] = true;
    }
}

// Descriptions (أوصاف) shown under each author's name: this forum's, plus those shown in every forum
$authorIds = [(int) $thread['user_id']];
foreach ($replies as $r) {
    $authorIds[] = (int) $r['user_id'];
}
$titlesBy = [];
$badgeBy = [];
$pd = poll_data($threadId);
$accessList = [];
if ($isHidden && $canModerate) {
    $accessList = db_all('SELECT u.id, u.username FROM thread_access a JOIN users u ON u.id = a.user_id WHERE a.thread_id = ? ORDER BY u.username', 'i', [$threadId]);
}
$moveTargets = ($modMode && $canSupervise) ? db_all('SELECT id, category, name FROM forums ORDER BY sort_order, id') : [];
$here = safe_next(current_app_path());

/** Small POST button used by the toolbars. */
function action_button($label, $action, $threadId, $confirm = '', $class = 'btn btn-small', $iconName = '')
{
    ob_start();
    ?>
    <form method="post" action="<?= e(url('moderate.php')) ?>" class="inline-form"<?= $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= e($action) ?>">
        <input type="hidden" name="id" value="<?= (int) $threadId ?>">
        <button type="submit" class="<?= e($class) ?>"><?= $iconName !== '' ? icon($iconName) . ' ' : '' ?><?= e($label) ?></button>
    </form>
    <?php
    return ob_get_clean();
}

/** One post (the opening post or a reply), with the author box on the right like the classic forums. */
function render_post(array $p)
{
    global $forumId, $modMode, $showSigs, $user, $here, $level, $canModerate;
    $authorLevel = member_level($p['author_id'], $forumId);
    list($stars, $rank) = rank_info((int) $p['posts']);
    $days = max(1, (int) ceil((time() - strtotime($p['joined'] . ' UTC')) / 86400));
    $perDay = round($p['posts'] / $days, 2);
    $isMe = $user !== null && (int) $user['id'] === (int) $p['author_id'];
    $canWarn = $modMode && !$isMe && $level >= 1 && $level > $authorLevel;
    $canMute = $modMode && !$isMe && $level >= 2 && $level > $authorLevel;
    ?>
    <?php static $dividerShown = false;
    $isOp = $p['anchor'] === 'op';
    if (!$isOp && !$dividerShown) { $dividerShown = true; ?><div class="replies-divider"><span>الردود</span></div><?php } ?>
    <article class="post <?= $isOp ? 'post-op' : 'post-reply' ?><?= $isMe ? ' is-me' : '' ?>" id="post-<?= e($p['anchor']) ?>">
        <aside class="post-author">
            <?= avatar_html($p['avatar'], 'post-avatar', 64) ?>
            <?= user_link($p['author_id'], $p['author'], $forumId) ?>
            <?php foreach (array_slice($p['titles'], 0, 2) as $tt): ?><span class="author-title<?= $tt['all'] ? ' is-global' : '' ?>"><?= e($tt['text']) ?></span><?php endforeach; ?>
            <span class="ab-follow">المتابعون: <b><?= follower_count('user', $p['author_id']) ?></b> · يتابع: <b><?= following_count($p['author_id']) ?></b></span>
            <?= follow_icon($p['author_id']) ?>
            <?= rating_html($p['author_id']) ?>

            <div class="ab-line"><b>المشاركات:</b> <?= (int) $p['posts'] ?></div>
            <?php $roles = array_slice(member_roles($p['author_id']), 0, 6); ?>
            <?php if ($roles): ?>
                <div class="ab-roles">
                    <?php foreach ($roles as $ro): ?>
                        <span class="ab-role ab-role-<?= e(level_class($ro[0])) ?>"><?= e($ro[1]) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($modMode && (int) $p['warnings'] > 0): ?><div class="ab-line ab-warn"><b>التحذيرات:</b> <?= (int) $p['warnings'] ?></div><?php endif; ?>
            <?php if ($canWarn || $canMute): ?>
                <details class="sanction-box">
                    <summary><?= icon('warn') ?> إجراءات على العضو</summary>
                    <?php
                    $sanctionForm = function ($action, $button, $extra = '') use ($p, $forumId, $here) {
                        return '<form method="post" action="' . e(url('sanction.php')) . '" class="sanction-form">' . csrf_field()
                            . '<input type="hidden" name="action" value="' . e($action) . '">'
                            . '<input type="hidden" name="user_id" value="' . (int) $p['author_id'] . '">'
                            . '<input type="hidden" name="forum_id" value="' . (int) $forumId . '">'
                            . '<input type="hidden" name="back" value="' . e($here) . '">'
                            . $extra
                            . '<button type="submit" class="btn btn-small">' . e($button) . '</button></form>';
                    };
                    $reasonField = '<input type="text" name="reason" placeholder="السبب" maxlength="255" required aria-label="السبب">';
                    if ($canWarn) {
                        echo $sanctionForm('warn', 'تحذير', $reasonField);
                    }
                    if ($canMute) {
                        echo $sanctionForm('mute', 'كتم', $reasonField
                            . '<select name="days" aria-label="مدة الكتم"><option value="1">يوم</option><option value="3">3 أيام</option><option value="7">أسبوع</option><option value="30">30 يومًا</option></select>');
                        echo $sanctionForm('unmute', 'رفع الكتم');
                    }
                    ?>
                </details>
            <?php endif; ?>
        </aside>
        <div class="post-main">
            <div class="post-bar">
                <time class="when"><?= e(fmt_when($p['created_at'])) ?></time>
                <?php if ($p['edited_at']): ?>
                    <span class="meta">عُدّل <?= e(fmt_when($p['edited_at'])) ?></span>
                <?php endif; ?>
                <?php if ((int) $p['like_count'] > 0): ?><span class="like-count">نقاط الإعجاب: <?= (int) $p['like_count'] ?></span><?php endif; ?>
                <?php $svc = save_total($p['type'], $p['id']); if ($svc > 0): ?><span class="like-count">عدد الحفظ: <?= $svc ?></span><?php endif; ?>
                <?php if ($isOp): $shc = share_total($p['thread_id']); if ($shc > 0): ?><span class="like-count">عدد المشاركات: <?= $shc ?></span><?php endif; endif; ?>
                <span class="post-actions">
                    <?php if ($user !== null && !$isMe): ?>
                        <form method="post" action="<?= e(url('like.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($p['type']) ?>">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="next" value="<?= e($here) ?>">
                            <button type="submit" class="like-btn<?= $p['liked'] ? ' is-liked' : '' ?>" aria-pressed="<?= $p['liked'] ? 'true' : 'false' ?>"><?= icon('like') ?> <?= $p['liked'] ? 'إلغاء الإعجاب' : 'أعجبني' ?></button>
                        </form>
                    <?php endif; ?>
                    <?= save_button($p['type'], $p['id'], $here) ?>
                    <button type="button" class="icon-btn" data-copy="post" data-target="post-<?= e($p['anchor']) ?>" title="نسخ <?= $isOp ? 'نص الموضوع' : 'نص الرد' ?>" aria-label="نسخ"><?= icon('copy') ?></button>
                    <?php if ($isOp): ?>
                            <?php if ($user !== null && $canModerate): ?><a class="icon-btn" href="<?= e(url('announce.php?id=' . (int) $p['thread_id'])) ?>" title="إعلان الموضوع لقائمة أعضاء" aria-label="إعلان الموضوع"><?= icon('bell') ?></a><?php endif; ?>
                        <?php if ($user !== null): ?><a class="icon-btn" href="<?= e(url('share.php?id=' . (int) $p['thread_id'])) ?>" title="مشاركة الموضوع مع الأعضاء برسالة خاصة" aria-label="مشاركة"><?= icon('share') ?></a><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($user !== null && !$isMe): ?>
                        <button type="button" class="icon-btn" data-live-open="<?= (int) $p['author_id'] ?>" data-name="<?= e($p['author']) ?>" title="محادثة فورية مع <?= e($p['author']) ?>" aria-label="محادثة فورية"><?= icon('chat') ?></button>
                        <a class="icon-btn" href="<?= e(url('send_message.php?to=' . rawurlencode($p['author']))) ?>" title="رسالة خاصة إلى <?= e($p['author']) ?>" aria-label="رسالة خاصة"><?= icon('mail') ?></a>
                        <?php if (can_moderate($forumId)): ?>
                            <a class="icon-btn icon-mod" href="<?= e(url('send_message.php?via=mod&forum=' . $forumId . '&to=' . rawurlencode($p['author']))) ?>" title="مراسلة العضو من خلال الإشراف" aria-label="مراسلة من الإشراف"><?= icon('shield') ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($p['can_reply']): ?>
                        <a class="icon-btn" href="<?= e(url('thread.php?id=' . (int) $p['thread_id'] . '&quote=' . $p['quote'] . '#reply')) ?>" title="اقتباس" aria-label="اقتباس"><?= icon('quote') ?></a>
                    <?php endif; ?>
                    <?php if ($user !== null && !$isMe): ?>
                        <a class="icon-btn" href="<?= e(url('report.php?thread=' . (int) $p['thread_id'] . ($p['type'] === 'reply' ? '&reply=' . (int) $p['id'] : ''))) ?>" title="تبليغ" aria-label="تبليغ"><?= icon('flag') ?></a>
                    <?php endif; ?>
                    <?php if ($p['can_edit']): ?>
                        <a class="icon-btn" href="<?= e(url('edit_post.php?type=' . $p['type'] . '&id=' . (int) $p['id'])) ?>" title="تعديل" aria-label="تعديل"><?= icon('pencil') ?></a>
                    <?php endif; ?>
                    <?php if ($p['type'] === 'reply' && $level >= 2): ?>
                        <form method="post" action="<?= e(url('moderate.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="<?= !empty($p['is_hidden_reply']) ? 'unhide_reply' : 'hide_reply' ?>">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="icon-btn" title="<?= !empty($p['is_hidden_reply']) ? 'إظهار الرد' : 'إخفاء الرد' ?>" aria-label="إخفاء الرد"><?= icon(!empty($p['is_hidden_reply']) ? 'eye' : 'eyeoff') ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($p['can_delete']): ?>
                        <form method="post" action="<?= e(url('moderate.php')) ?>" class="inline-form" data-confirm="سيتم حذف هذا الرد نهائيًا. هل أنت متأكد؟">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_reply">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="icon-btn" title="حذف" aria-label="حذف"><?= icon('trash') ?></button>
                        </form>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($p['is_hidden_reply'])): ?>
            <div class="hidden-banner"><?= icon('eyeoff') ?> هذا الرد مخفي ولا يراه أحد غيرك
                <form method="post" action="<?= e(url('moderate.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="unhide_reply"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn btn-small"><?= icon('eye') ?> إعادة إظهاره</button></form></div>
            <?php endif; ?>
            <div class="post-body"><?= post_html($p['content']) ?></div>
            <?php if (!empty($p['likers'])): ?>
                <div class="likers-box">
                    <div class="likers-head"><?= icon('like') ?> أعجب بهذه المشاركة <span class="likers-count"><?= count($p['likers']) ?></span></div>
                    <ul class="likers-list">
                        <?php foreach (array_slice($p['likers'], 0, 20) as $lk): ?>
                            <li><?= user_link($lk['user_id'], $lk['username'], $forumId) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($p['likers']) > 20): ?><li class="likers-more">و<?= count($p['likers']) - 20 ?> آخرين</li><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

$viewingForum = $forumId; // for the "members in this forum" counters
page_header($thread['title']);
breadcrumbs([
    ['الرئيسية', 'index.php'],
    [$forum['name'], 'forum.php?id=' . $forum['id']],
    [mb_strimwidth($thread['title'], 0, 50, '…'), null],
]);
?>
<div class="thread-page-head">
    <h1 class="thread-page-title">
        <?php if ($thread['is_pinned']): ?><span class="badge badge-signal">مثبّت</span><?php endif; ?>
        <?php if ($isLocked): ?><span class="badge">مغلق</span><?php endif; ?>
        <?php if ($isArchived): ?><span class="badge">في الأرشيف</span><?php endif; ?>
        <?php if ($isHidden): ?><span class="badge badge-danger">مخفي</span><?php endif; ?>
        <?php if ((int) $thread['feature_level'] === 2): ?><span title="متميز على كل المنتديات"><?= icon('medal', 'title-ico') ?></span><?php elseif ((int) $thread['feature_level'] === 1): ?><span title="متميز في المنتدى"><?= icon('redstar', 'title-ico') ?></span><?php endif; ?>
        <?= e($thread['title']) ?>
    </h1>
</div>

<div class="action-bar">
    <?php if ($canReply): ?><a class="btn btn-primary" href="#reply"><?= icon('reply') ?> أضف رد</a><?php endif; ?>
    <?php if ($user !== null): ?><a class="btn" href="<?= e(url('new_thread.php?forum_id=' . $forumId)) ?>"><?= icon('pencil') ?> موضوع جديد</a><?php endif; ?>
    <button type="button" class="btn" id="toggleMeta" title="إخفاء أو إظهار معلومات العضوية وأدوات الموضوع"><?= icon('eye') ?> <span>إخفاء معلومات العضوية</span></button>
    <?php if ($user !== null): ?>
        <?= follow_button('thread', $threadId, 'btn') ?>
        <?php if ($canModerate && !$modMode): ?>
            <?= action_button($isHidden ? 'إظهار الموضوع' : 'إخفاء الموضوع', $isHidden ? 'unhide' : 'hide', $threadId, '', 'btn', $isHidden ? 'eye' : 'eyeoff') ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($modMode && $canModerate): ?>
    <div class="mod-bar" role="toolbar" aria-label="أدوات الرقابة">
        <span class="mod-bar-label">وضع الرقابة</span>
        <?= action_button($thread['is_pinned'] ? 'إلغاء التثبيت' : 'تثبيت', $thread['is_pinned'] ? 'unpin' : 'pin', $threadId, '', 'btn btn-small', 'pin') ?>
        <?= action_button($isLocked ? 'فتح الموضوع' : 'إغلاق الموضوع', $isLocked ? 'unlock' : 'lock', $threadId, '', 'btn btn-small', 'lock') ?>
        <?= action_button($isHidden ? 'إظهار' : 'إخفاء', $isHidden ? 'unhide' : 'hide', $threadId, '', 'btn btn-small', $isHidden ? 'eye' : 'eyeoff') ?>
        <?php if ($canSupervise): ?>
            <form method="post" action="<?= e(url('moderate.php')) ?>" class="inline-form move-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="id" value="<?= $threadId ?>">
                <label class="sr-only" for="to_forum">نقل إلى منتدى</label>
                <select id="to_forum" name="to_forum">
                    <?php foreach ($moveTargets as $mt): ?>
                        <option value="<?= (int) $mt['id'] ?>"<?= (int) $mt['id'] === $forumId ? ' selected' : '' ?>><?= e($mt['category'] . ' ‹ ' . $mt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-small"><?= icon('move') ?> نقل</button>
            </form>
        <?php endif; ?>
        <?php if (is_admin()): ?>
            <?= action_button('حذف الموضوع', 'delete_thread', $threadId, 'سيتم حذف الموضوع وكل ردوده نهائيًا. هل أنت متأكد؟', 'btn btn-small btn-danger', 'trash') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($user !== null && $isAuthor && !is_admin()): ?>
    <div class="mod-bar"><?= action_button('حذف موضوعي', 'delete_thread', $threadId, 'سيتم حذف موضوعك وكل ردوده نهائيًا. هل أنت متأكد؟', 'btn btn-small btn-danger', 'trash') ?></div>
<?php endif; ?>

<?php if ($isHidden && $canModerate): ?>
    <section class="panel access-panel" id="access">
        <h2><?= icon('eyeoff') ?> موضوع مخفي</h2>
        <p class="hint">لا يراه إلا كاتبه والمشرفون والمراقبون ومن تفتح له الموضوع هنا. اكتب <strong>رقم العضو</strong> (تجده في ملفه وفي صندوق كاتب المشاركة)، ويمكنك كتابة عدة أرقام مفصولة بفاصلة.</p>
        <?php if ($accessList): ?>
            <ul class="chip-list">
                <?php foreach ($accessList as $a): ?>
                    <li><?= user_link($a['id'], $a['username'], $forumId) ?> <span class="meta">#<?= (int) $a['id'] ?></span>
                        <form method="post" action="<?= e(url('moderate.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="id" value="<?= $threadId ?>">
                            <input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
                            <button type="submit" class="link-btn danger" aria-label="سحب الصلاحية من <?= e($a['username']) ?>">سحب</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?= e(url('moderate.php')) ?>" class="search-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="grant">
            <input type="hidden" name="id" value="<?= $threadId ?>">
            <label class="sr-only" for="grant-user">رقم العضو</label>
            <input type="text" id="grant-user" name="members" placeholder="رقم العضو، مثال: 15 أو 15, 27, 40" maxlength="120" inputmode="numeric" required>
            <button type="submit" class="btn">فتح الموضوع</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($pd): ?>
    <?php
    $canVote = $user !== null && !$pd['mine'] && !$closed;
    $showResults = !$canVote || isset($_GET['results']);
    $voteTotal = 0;
    foreach ($pd['options'] as $o) {
        $voteTotal += (int) $o['votes'];
    }
    ?>
    <section class="panel poll-box" id="poll">
        <h2><?= icon('poll') ?> <?= e($pd['poll']['question']) ?></h2>
        <?php if ($canVote && !$showResults): ?>
            <form method="post" action="<?= e(url('poll_vote.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                <?php foreach ($pd['options'] as $o): ?>
                    <label class="poll-option">
                        <input type="<?= $pd['poll']['multiple'] ? 'checkbox' : 'radio' ?>" name="option[]" value="<?= (int) $o['id'] ?>"> <?= e($o['label']) ?>
                    </label>
                <?php endforeach; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">تصويت</button>
                    <a class="btn" href="<?= e(url('thread.php?id=' . $threadId . '&results=1#poll')) ?>">عرض النتائج</a>
                </div>
            </form>
        <?php else: ?>
            <ul class="poll-results">
                <?php foreach ($pd['options'] as $o): ?>
                    <?php $pct = $voteTotal > 0 ? round(100 * (int) $o['votes'] / $voteTotal) : 0; ?>
                    <li<?= isset($pd['mine'][(int) $o['id']]) ? ' class="is-mine"' : '' ?>>
                        <span class="poll-label"><?= e($o['label']) ?><?= isset($pd['mine'][(int) $o['id']]) ? ' ✓' : '' ?></span>
                        <span class="poll-bar"><span style="width: <?= (int) $pct ?>%"></span></span>
                        <span class="poll-num"><?= (int) $o['votes'] ?> (<?= (int) $pct ?>%)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="meta">عدد المصوّتين: <?= (int) $pd['voters'] ?><?= $user === null ? ' — سجّل الدخول للتصويت.' : ($closed && !$pd['mine'] ? ' — التصويت مغلق.' : '') ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="posts">
<?php
if ($page === 1) {
    render_post([
        'anchor'     => 'op',
        'type'       => 'thread',
        'id'         => $thread['id'],
        'thread_id'  => $threadId,
        'quote'      => 'op',
        'author_id'  => $thread['user_id'],
        'avatar'     => $thread['author_avatar'],
        'author'     => $thread['author'],
        'joined'     => $thread['author_joined'],
        'country'    => $thread['author_country'],
        'signature'  => $thread['author_signature'],
        'posts'      => $thread['author_posts'],
        'warnings'   => $thread['author_warnings'],
        'like_count' => $thread['like_count'],
        'liked'      => isset($myLikes[0]),
        'likers'     => isset($likers[0]) ? $likers[0] : [],
        'likes_received' => $thread['author_likes'],
        'titles'     => isset($titlesBy[(int) $thread['user_id']]) ? $titlesBy[(int) $thread['user_id']] : [],
        'badge'      => isset($badgeBy[(int) $thread['user_id']]) ? $badgeBy[(int) $thread['user_id']]['name'] : null,
        'badge_icon' => isset($badgeBy[(int) $thread['user_id']]) ? $badgeBy[(int) $thread['user_id']]['icon'] : '',
        'badge_image' => isset($badgeBy[(int) $thread['user_id']]) ? $badgeBy[(int) $thread['user_id']]['image'] : '',
        'badge_global' => isset($badgeBy[(int) $thread['user_id']]) && $badgeBy[(int) $thread['user_id']]['global'],
        'content'    => $thread['content'],
        'created_at' => $thread['created_at'],
        'edited_at'  => $thread['edited_at'],
        'can_reply'  => $canReply,
        'can_edit'   => $canModerate || ($isAuthor && !$closed),
        'can_delete' => false, // threads are deleted from the toolbar / "delete my thread" below
    ]);
} else {
    echo '<p class="meta"><a href="' . e(url('thread.php?id=' . $threadId)) . '">العودة إلى المشاركة الأولى</a></p>';
}

foreach ($replies as $r) {
    if (!empty($r['is_hidden']) && !is_admin()) {
        continue; // hidden replies disappear completely for everyone except administrators
    }
    $isOwn = $user !== null && (int) $user['id'] === (int) $r['user_id'];
    render_post([
        'anchor'     => (string) $r['id'],
        'type'       => 'reply',
        'id'         => $r['id'],
        'thread_id'  => $threadId,
        'quote'      => (string) $r['id'],
        'author_id'  => $r['user_id'],
        'avatar'     => $r['avatar'],
        'author'     => $r['username'],
        'joined'     => $r['joined'],
        'country'    => $r['country'],
        'signature'  => $r['signature'],
        'posts'      => $r['posts'],
        'warnings'   => $r['warnings'],
        'like_count' => $r['like_count'],
        'liked'      => isset($myLikes[(int) $r['id']]),
        'likers'     => isset($likers[(int) $r['id']]) ? $likers[(int) $r['id']] : [],
        'likes_received' => $r['likes_received'],
        'titles'     => isset($titlesBy[(int) $r['user_id']]) ? $titlesBy[(int) $r['user_id']] : [],
        'badge'      => isset($badgeBy[(int) $r['user_id']]) ? $badgeBy[(int) $r['user_id']]['name'] : null,
        'badge_icon' => isset($badgeBy[(int) $r['user_id']]) ? $badgeBy[(int) $r['user_id']]['icon'] : '',
        'badge_image' => isset($badgeBy[(int) $r['user_id']]) ? $badgeBy[(int) $r['user_id']]['image'] : '',
        'badge_global' => isset($badgeBy[(int) $r['user_id']]) && $badgeBy[(int) $r['user_id']]['global'],
        'content'    => $r['content'],
        'created_at' => $r['created_at'],
        'edited_at'  => $r['edited_at'],
        'can_reply'  => $canReply,
        'can_edit'   => $canModerate || ($isOwn && !$closed),
        'can_delete' => is_admin() || ($isOwn && !$closed),
        'is_hidden_reply' => !empty($r['is_hidden']),
    ]);
}
?>
</div>

<?= pagination_html($page, $pages, 'thread.php?id=' . $threadId) ?>

<section class="reply-box" id="reply">
    <?php if ($user === null): ?>
        <?= guest_cta('thread.php?id=' . $threadId, 'سجّل الدخول أو أنشئ عضوية لتتمكن من الرد على هذا الموضوع.') ?>
    <?php elseif (!$canReply): ?>
        <p class="notice notice-info"><?= $isArchived ? 'هذا الموضوع في الأرشيف ولا يقبل ردودًا جديدة.' : 'هذا الموضوع مغلق ولا يقبل ردودًا جديدة.' ?></p>
    <?php else: ?>
        <h2>أضف ردًا</h2>
        <?php foreach ($errors as $err): ?>
            <div class="notice notice-error" role="alert"><?= e($err) ?></div>
        <?php endforeach; ?>
        <?php if (($m = mute_message()) !== null && !$errors): ?>
            <div class="notice notice-error" role="alert"><?= e($m) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('thread.php?id=' . $threadId)) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="content">نص الرد</label>
            <textarea id="content" name="content" rows="7" data-rich="1"><?= e($replyText) ?></textarea>
            <button type="submit" class="btn btn-primary">إرسال الرد</button>
        </form>
    <?php endif; ?>
</section>
<?php page_footer();
