<?php
/**
 * Page chrome. Pages run their logic first, then call page_header() ... page_footer(),
 * so redirects always happen before any HTML is sent.
 */

function page_header($title = '', $active = '')
{
    global $viewingForum; // pages that show one forum set this before calling page_header()
    $u = current_user();
    if ($u !== null) {
        $vf = isset($viewingForum) && (int) $viewingForum > 0 ? (int) $viewingForum : null;
        db_exec('UPDATE users SET in_forum_id = ? WHERE id = ? AND NOT (in_forum_id <=> ?)', 'iii', [$vf, $u['id'], $vf]);
    }
    $unread = 0;
    if ($u !== null) {
        $unread = (int) db_val('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0', 'i', [$u['id']]);
    }
    $fullTitle = ($title !== '' ? $title . ' — ' : '') . site_name() . ($title === '' && site_tagline() !== '' ? ' — ' . site_tagline() : '');
    $flashes = take_flashes();
    $staff = $u !== null && is_staff();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;700&amp;display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <script>try{if(localStorage.getItem('sb')==='0')document.documentElement.className+=' sb-collapsed';var th=localStorage.getItem('theme');if(th==='dark'||(!th&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.className+=' dark';}catch(e){}</script>
</head>
<body>
<?= icon_sprite() ?>
<a class="skip-link" href="#content">انتقل إلى المحتوى</a>
<header class="site-header" id="sidebar">
    <nav class="wrap toolbar" aria-label="أدوات المنتدى">
        <?= tool_link('home', 'الرئيسية', 'index.php', $active === 'home') ?>
        <?php if ($u !== null): ?><?= tool_link('bell', 'متابعاتي', 'follows.php', $active === 'follows') ?><?php endif; ?>
        <?= tool_link('users', 'الأعضاء', 'members.php', $active === 'members') ?>
        <?= tool_link('users', 'المتواجدون الآن', 'online.php', $active === 'online') ?>
        <?php if ($u !== null): ?><?= tool_link('mail', 'الرسائل', 'inbox.php', $active === 'inbox', $unread) ?><?php endif; ?>
        <?= tool_link('search', 'بحث', 'search.php', $active === 'search') ?>
        <?php if ($u !== null): ?><?= tool_link('bookmark', 'المحفظة الشخصية', 'wallet.php', $active === 'wallet') ?><?php endif; ?>
        <?php if ($u !== null): ?><?= tool_link('card', 'بياناتك', 'settings.php', $active === 'settings') ?><?php endif; ?>
        <?php if ($staff): ?><?= tool_link('shield', 'لوحة الرقابة', 'moderation.php', $active === 'moderation') ?><?php endif; ?>
        <?php if ($u !== null && $u['role'] === 'admin'): ?>
            <?= tool_link('sliders', 'الإدارة', 'admin/index.php', $active === 'admin') ?>
        <?php endif; ?>
        <?php if ($u !== null): ?>
            <form method="post" action="<?= e(url('logout.php')) ?>" class="tool-form">
                <?= csrf_field() ?>
                <button type="submit" class="tool"><?= icon('door') ?><span>خروج</span></button>
            </form>
        <?php else: ?>
            <?= tool_link('key', 'دخول', 'login.php', $active === 'login') ?>
            <?= tool_link('users', 'عضوية جديدة', 'register.php', $active === 'register') ?>
        <?php endif; ?>
    </nav>
</header>
<div class="sb-overlay" id="sbOverlay"></div>
<div class="topbar">
    <button type="button" class="sb-toggle" id="sbToggle" aria-controls="sidebar" aria-label="إظهار / إخفاء القائمة الجانبية"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg></button>
    <button type="button" class="sb-toggle" id="themeToggle" aria-label="الوضع الداكن / الفاتح" title="الوضع الداكن / الفاتح"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.75 15.5A9.75 9.75 0 0 1 8.5 2.25a9.75 9.75 0 1 0 13.25 13.25Z"/></svg></button>
    <?php if ($u !== null): $nc = notif_unread_count(); ?>
    <a class="sb-toggle bell-btn" data-notif-api="<?= e(url('notif_api.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-all="<?= e(url('notifications.php')) ?>" href="<?= e(url('notifications.php')) ?>" aria-label="الإشعارات" title="الإشعارات"><?= icon('bell') ?><b class="bell-badge"<?= $nc > 0 ? '' : ' hidden' ?>><?= $nc > 99 ? '99+' : $nc ?></b></a>
    <?php endif; ?>
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e(site_name()) ?> - الرئيسية">
        <?php
        $customLogo = null;
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'] as $logoExt => $logoMime) {
            if (is_file(__DIR__ . '/../assets/images/forum_logo_custom.' . $logoExt)) {
                $customLogo = 'assets/images/forum_logo_custom.' . $logoExt;
                break;
            }
        }
        ?>
        <?php $brandMode = site_brand_mode(); ?>
        <?php if ($brandMode !== 'name'): ?><img src="<?= e(url($customLogo ?: 'assets/images/forum_logo.png')) ?>" alt=""><?php endif; ?>
        <?php if ($brandMode !== 'logo'): ?><span class="brand-text"><span class="brand-name"><?= e(site_name()) ?></span><?php if (site_tagline() !== '' && site_show_tagline()): ?><small class="brand-tag"><?= e(site_tagline()) ?></small><?php endif; ?></span><?php endif; ?>
    </a>
            <?php if ($u !== null): ?>
            <div class="user-chip">
                <a class="uc-main" href="<?= e(url('profile.php?id=' . $u['id'])) ?>" title="ملفي الشخصي">
                    <?php $ux = user_extras(); ?><?php if (!empty($ux['avatar'])): ?><?= avatar_html($ux['avatar'], 'uc-avatar uc-avatar-img', 34) ?><?php else: ?><span class="uc-avatar" aria-hidden="true"><?= e(mb_substr($u['username'], 0, 1)) ?></span><?php endif; ?>
                    <span class="uc-text">
                        <span class="user-chip-name"><?= e($u['username']) ?></span>
                    </span>
                </a>
                <a class="uc-btn" href="<?= e(url('settings.php')) ?>" title="الإعدادات" aria-label="الإعدادات"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg></a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="uc-btn uc-out" title="خروج" aria-label="خروج"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg></button>
                </form>
            </div>
        <?php endif; ?>
</div>
<main id="content" class="wrap">
<?php foreach ($flashes as $f): ?>
    <div class="notice notice-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div>
<?php endforeach; ?>
<?php
}

function page_footer()
{
    ?>
</main>
<footer class="site-footer">
    <div class="wrap">
        <p>© <?= date('Y') ?> <?= e(site_name()) ?>. جميع الحقوق محفوظة.</p>
    </div>
    <div class="wrap">
        <p>Powered by Montadak v1.0</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/editor.js')) ?>"></script>
<script src="<?= e(url('assets/js/sidebar.js')) ?>"></script>
<?= messenger_widget() ?>
<script src="<?= e(url('assets/js/messenger.js')) ?>"></script>
</body>
</html>
<?php
}

/** Full-page error / notice, then stop. */
function fail_page($message, $code = 400, $title = 'تعذّر إكمال الطلب')
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    page_header($title);
    ?>
    <div class="panel narrow">
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <p><a class="btn" href="<?= e(url('index.php')) ?>">العودة إلى الرئيسية</a></p>
    </div>
    <?php
    page_footer();
    exit;
}

/** $items: list of [label, app-relative url or null for the current page]. */
function breadcrumbs(array $items)
{
    echo '<nav class="crumbs" aria-label="مسار الصفحة"><ol>';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($item[1] !== null && $i !== $last) {
            echo '<li><a href="' . e(url($item[1])) . '">' . e($item[0]) . '</a></li>';
        } else {
            echo '<li aria-current="page">' . e($item[0]) . '</li>';
        }
    }
    echo '</ol></nav>';
}

/** One icon button of the toolbar. */
function tool_link($icon, $label, $href, $current = false, $badge = 0)
{
    return '<a class="tool' . ($current ? ' is-current' : '') . '" href="' . e(url($href)) . '"' . ($current ? ' aria-current="page"' : '') . '>'
        . icon($icon) . '<span>' . e($label) . '</span>'
        . ($badge > 0 ? '<b class="tool-badge">' . (int) $badge . '</b>' : '') . '</a>';
}

/**
 * Member name as a link, coloured by staff level: admin (purple), supervisor (red), moderator (yellow).
 * Pass the forum id to show the level the member holds in that forum.
 */
function user_link($id, $name, $forumId = null)
{
    $lvl = member_level($id, $forumId);
    $rank = member_rank($id, $forumId);
    if ($lvl === 0) { // not staff in THIS forum: keep the colour of the highest rank held anywhere
        $lvl = member_level($id);
        $rank = member_rank($id);
    }
    $cls = level_class($lvl);
    return '<a class="user u-' . $cls . '" href="' . e(url('profile.php?id=' . (int) $id)) . '"'
        . ($lvl > 0 ? ' title="' . e(rank_label($rank)) . '"' : '') . '>'
        . ($cls === 'admin' ? '<span class="admin-mark" aria-label="مدير">' . icon('crown') . '</span>' : ($cls === 'super' ? '<span class="super-mark" aria-label="مشرف">' . icon('check') . '</span>' : '')) . e($name) . '</a>';
}

function role_label($role)
{
    return $role === 'admin' ? 'مدير' : 'عضو';
}

/** Sub-navigation for the admin pages. */
/** Modern page hero: icon + title + optional subtitle + optional right-side HTML. */
function page_hero($iconName, $title, $sub = '', $actions = '')
{
    echo '<header class="pg-hero"><span class="pg-hero-ico">' . icon($iconName) . '</span><div class="pg-hero-txt"><h1>' . e($title) . '</h1>'
        . ($sub !== '' ? '<p>' . $sub . '</p>' : '') . '</div>' . ($actions !== '' ? '<div class="pg-hero-act">' . $actions . '</div>' : '') . '</header>';
}

function admin_nav($active)
{
    $items = [
        'index'      => ['نظرة عامة', 'admin/index.php'],
        'stats'      => ['الإحصائيات', 'admin/stats.php'],
        'tags'       => ['الوسوم', 'admin/tags.php'],
        'settings'   => ['إعدادات المنتدى', 'admin/settings.php'],
        'home'       => ['الصفحة الرئيسية', 'admin/home.php'],
        'forums'     => ['المنتديات', 'admin/forums.php'],
        'moderators' => ['المشرفون', 'admin/moderators.php'],
        'users'      => ['الأعضاء', 'admin/users.php'],
        'renames'    => ['طلبات الأسماء', 'admin/renames.php'],
        'requests'   => ['طلبات الموافقة والقفل', 'admin/requests.php'],
    ];
    echo '<nav class="subnav" aria-label="أقسام الإدارة">';
    foreach ($items as $key => $item) {
        echo '<a href="' . e(url($item[1])) . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . e($item[0]) . '</a>';
    }
    echo '</nav>';
}

/**
 * Table of threads (forum page, active, mine, favourites, archive, search, home list).
 * Options: 'forum' => show the forum column (default true), 'remove_fav' => "remove from favourites" button,
 *          'options' => the الخيارات column (reply icon for members, staff icons for staff).
 */
function thread_table(array $rows, array $opts = [])
{
    $showForum = !isset($opts['forum']) || $opts['forum'];
    $showOptions = !empty($opts['options']);
    $curForum = isset($opts['current_forum']) ? (int) $opts['current_forum'] : 0;
    $next = safe_next(current_app_path());
    ?>
    <div class="tt-wrap">
    <table class="tt">
        <thead>
            <tr>
                <th scope="col" class="tt-ico" title="الحالة"><?= icon('tfolder') ?><span class="sr-only">الحالة</span></th>
                <th scope="col" class="tt-topic" title="الموضوع"><?= icon('doc') ?><span class="sr-only">الموضوع</span></th>
                <th scope="col" class="tt-num" title="الردود"><?= icon('chat') ?><span class="sr-only">الردود</span></th>
                <th scope="col" class="tt-num" title="القراءات"><?= icon('eye') ?><span class="sr-only">القراءات</span></th>
                <th scope="col" class="tt-last" title="آخر مشاركة"><?= icon('clock') ?><span class="sr-only">آخر مشاركة</span></th>
                <?php if ($showOptions): ?><th scope="col" class="tt-opt" title="الخيارات"><?= icon('sliders') ?><span class="sr-only">الخيارات</span></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
            <?php
            if ($t['is_pinned']) { $st = 'pin'; $stTitle = 'مثبّت'; }
            elseif ($t['is_locked'] || $t['is_archived']) { $st = 'lock'; $stTitle = 'مغلق'; }
            elseif ($t['is_hidden']) { $st = 'eyeoff'; $stTitle = 'مخفي'; }
            elseif ((int) $t['is_new'] === 1) { $st = 'new'; $stTitle = 'جديد'; }
            else { $st = 'tfolder'; $stTitle = 'موضوع'; }
            $fid = (int) $t['forum_id'];
            $feat = (int) $t['feature_level'];
            ?>
            <tr class="<?= $t['is_pinned'] ? 'is-pinned' : '' ?><?= (int) $t['is_new'] === 1 ? ' is-unread' : '' ?><?= ($t['is_hidden'] || $t['is_locked']) ? ' is-dim' : '' ?>">
                <td class="tt-ico" title="<?= e($stTitle) ?>"><?= icon($st) ?></td>
                <td class="tt-topic">
                    <?php if ($feat === 2): ?><span class="title-mark" title="متميز على كل المنتديات"><?= icon('medal', 'title-ico') ?></span><?php elseif ($feat === 1): ?><span class="title-mark" title="متميز في المنتدى"><?= icon('redstar', 'title-ico') ?></span><?php endif; ?>
                    <a class="row-title" href="<?= e(url('thread.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a>
                    <?php if ($t['is_locked']): ?><span class="tt-flag" title="مقفول"><?= icon('lock') ?></span><?php endif; ?>
                    <?php if (!empty($t['has_poll'])): ?><span class="tt-flag" title="استفتاء"><?= icon('poll') ?></span><?php endif; ?>
                    <?php if ($t['is_hidden']): ?><span class="tt-flag" title="مخفي"><?= icon('eyeoff') ?></span><?php endif; ?>
                    <?= thread_pages($t) ?>
                    <div class="tt-meta">
                        <?= user_link($t['author_id'], $t['author'], $fid) ?>
                        <span class="when-ok"><?= e(fmt_when($t['created_at'])) ?></span>
                        <?php if ($showForum || ($curForum > 0 && $fid !== $curForum)): ?><a class="tt-forum" href="<?= e(url('forum.php?id=' . $fid)) ?>"><?= icon('folder') ?><?= e($t['forum_name']) ?></a><?php endif; ?>
                        <?php if ((int) $t['like_count'] > 0): ?><span class="tt-likes" title="نقاط الإعجاب"><?= icon('like') ?><?= (int) $t['like_count'] ?></span><?php endif; ?>
                        <?php if (!empty($t['save_count'])): ?><span class="tt-likes" title="عدد مرات الحفظ"><?= icon('bookmark') ?><?= (int) $t['save_count'] ?></span><?php endif; ?>
                        <?php if (!empty($t['share_count'])): ?><span class="tt-likes" title="عدد المشاركات"><?= icon('share') ?><?= (int) $t['share_count'] ?></span><?php endif; ?>
                    </div>
                </td>
                <td class="tt-num"><?= (int) $t['reply_count'] ?></td>
                <td class="tt-num"><?= (int) $t['views'] ?></td>
                <td class="tt-last">
                    <?php if ((int) $t['reply_count'] > 0): ?>
                        <?= $t['last_user_id'] ? user_link($t['last_user_id'], $t['last_username'], $fid) : '—' ?>
                        <span class="when"><?= e(fmt_when($t['last_post_at'])) ?></span>
                    <?php endif; ?>
                </td>
                <?php if ($showOptions): ?><td class="tt-opt"><?= thread_options($t, $next) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
}

/**
 * The "الخيارات" cell of a thread row: members get the reply icon; staff get, in order,
 * lock, hide, pin, properties (supervisors), edit, reply. Actions return to the current page.
 */
function thread_options(array $t, $next)
{
    $fid = (int) $t['forum_id'];
    $tid = (int) $t['id'];
    $lvl = forum_level($fid);
    $out = '';
    $btn = function ($action, $iconName, $title) use ($tid, $next) {
        return '<form method="post" action="' . e(url('moderate.php')) . '" class="inline-form">' . csrf_field()
            . '<input type="hidden" name="action" value="' . e($action) . '">'
            . '<input type="hidden" name="id" value="' . $tid . '">'
            . '<input type="hidden" name="next" value="' . e($next) . '">'
            . '<button type="submit" class="icon-btn" title="' . e($title) . '" aria-label="' . e($title) . '">' . icon($iconName) . '</button></form>';
    };
    $link = function ($href, $iconName, $title) {
        return '<a class="icon-btn" href="' . e(url($href)) . '" title="' . e($title) . '" aria-label="' . e($title) . '">' . icon($iconName) . '</a>';
    };
    if ($lvl >= 1) {
        $out .= $btn($t['is_locked'] ? 'unlock' : 'lock', $t['is_locked'] ? 'unlock' : 'lock', $t['is_locked'] ? 'فتح الموضوع' : 'قفل الموضوع');
        $out .= $btn($t['is_hidden'] ? 'unhide' : 'hide', $t['is_hidden'] ? 'eye' : 'eyeoff', $t['is_hidden'] ? 'إظهار الموضوع' : 'إخفاء الموضوع');
        $out .= $btn($t['is_pinned'] ? 'unpin' : 'pin', 'pin', $t['is_pinned'] ? 'إلغاء التثبيت' : 'تثبيت الموضوع');
        $out .= $link('edit_post.php?type=thread&id=' . $tid, 'pencil', 'تعديل الموضوع');
    }
    $out .= $link('thread.php?id=' . $tid . '#reply', 'reply', 'الرد على الموضوع');
    if (current_user() !== null) {
        $out .= '<form method="post" action="' . e(url('like.php')) . '" class="inline-form">' . csrf_field()
            . '<input type="hidden" name="type" value="thread"><input type="hidden" name="id" value="' . $tid . '">'
            . '<input type="hidden" name="next" value="' . e($next) . '">'
            . '<button type="submit" class="icon-btn" title="أعجبني" aria-label="أعجبني">' . icon('like') . '</button></form>';
        $out .= save_button('thread', $tid, $next);
        $out .= $link('share.php?id=' . $tid, 'share', 'مشاركة الموضوع مع الأعضاء');
        if (can_moderate((int) $fid)) {
            $out .= $link('announce.php?id=' . $tid, 'bell', 'إعلان الموضوع لقائمة أعضاء');
        }
    }
    return '<span class="opts"><button type="button" class="opts-toggle" aria-label="خيارات الموضوع" title="خيارات الموضوع" aria-expanded="false">' . icon('sliders') . '</button><span class="opts-list">' . $out . '</span></span>';
}
