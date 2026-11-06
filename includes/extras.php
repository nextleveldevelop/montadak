<?php
/** v5 extras: saved items (wallet), share counts, member mini-topics, profile background. Tables are created automatically. */

const MINI_TAGS = ['تقنية', 'علوم', 'ثقافة', 'أدب', 'رياضة', 'صحة', 'اقتصاد', 'فن', 'سفر', 'أخرى'];
const MINI_MAX_WORDS = 300;
const BG_W = 1200;
const BG_H = 300;

function ensure_v5_schema()
{
    if (site_setting('schema_v5', '0') === '1') {
        return;
    }
    try {
        db_exec("CREATE TABLE IF NOT EXISTS saved_items (
            user_id INT UNSIGNED NOT NULL,
            item_type ENUM('thread','reply') NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, item_type, item_id),
            KEY idx_saved_item (item_type, item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db_exec('CREATE TABLE IF NOT EXISTS thread_shares (
            thread_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (thread_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec('CREATE TABLE IF NOT EXISTS user_posts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(150) NOT NULL,
            tag VARCHAR(30) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_up_user (user_id, id),
            KEY idx_up_time (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        if (db_val("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_bg' LIMIT 1") === null) {
            db_exec("ALTER TABLE users ADD COLUMN profile_bg VARCHAR(40) NOT NULL DEFAULT ''");
        }
        site_set('schema_v5', '1');
    } catch (Throwable $e) {
    }
}

// ---------------------------------------------------------------- saved items / shares

function is_saved($type, $id)
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    try {
        return db_val('SELECT 1 FROM saved_items WHERE user_id = ? AND item_type = ? AND item_id = ?', 'isi', [$u['id'], $type, (int) $id]) !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function save_total($type, $id)
{
    try {
        return (int) db_val('SELECT COUNT(*) FROM saved_items WHERE item_type = ? AND item_id = ?', 'si', [$type, (int) $id]);
    } catch (Throwable $e) {
        return 0;
    }
}

function share_total($threadId)
{
    try {
        return (int) db_val('SELECT COUNT(*) FROM thread_shares WHERE thread_id = ?', 'i', [(int) $threadId]);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Save / unsave icon button (bookmark). $labeled adds a text label. */
function save_button($type, $id, $next, $labeled = false, $count = null)
{
    if (current_user() === null) {
        return '';
    }
    $on = is_saved($type, $id);
    $title = $on ? 'إلغاء الحفظ من المحفظة' : ($type === 'reply' ? 'حفظ الرد في المحفظة الشخصية' : ($type === 'mini' ? 'حفظ المنشور في المحفظة الشخصية' : 'حفظ الموضوع في المحفظة الشخصية'));
    return '<form method="post" action="' . e(url('save.php')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="next" value="' . e($next) . '">'
        . '<button type="submit" class="icon-btn save-btn' . ($on ? ' is-saved' : '') . '" title="' . e($title) . '" aria-label="' . e($title) . '">' . icon('bookmark') . ($labeled ? ' <span>' . e($on ? 'محفوظ' : 'حفظ') . '</span>' : '') . ($count !== null ? '<span class="cnt">' . (int) $count . '</span>' : '') . '</button></form>';
}

/** Small icon-only follow / unfollow button for a member (author box). */
function follow_icon($uid)
{
    $u = current_user();
    if ($u === null || (int) $u['id'] === (int) $uid) {
        return '';
    }
    $on = is_following('user', $uid);
    $here = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $title = $on ? 'إلغاء متابعة العضو' : 'متابعة العضو';
    return '<form method="post" action="' . e(url('follow.php')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="type" value="user"><input type="hidden" name="id" value="' . (int) $uid . '">'
        . '<input type="hidden" name="next" value="' . e($here) . '">'
        . '<button type="submit" class="icon-btn follow-ico' . ($on ? ' is-on' : '') . '" title="' . e($title) . '" aria-label="' . e($title) . '">' . icon($on ? 'check' : 'bell') . '</button></form>';
}

function following_forums_count($uid)
{
    try {
        return (int) db_val("SELECT COUNT(*) FROM follows WHERE user_id = ? AND target_type = 'forum'", 'i', [(int) $uid]);
    } catch (Throwable $e) {
        return 0;
    }
}

// ---------------------------------------------------------------- member mini-topics

function words_of($html)
{
    $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(post_html((string) $html)), ENT_QUOTES, 'UTF-8')));
    return $t === '' ? [] : explode(' ', $t);
}

function mini_excerpt($html, $limit = 100)
{
    $w = words_of($html);
    return implode(' ', array_slice($w, 0, $limit)) . (count($w) > $limit ? ' …' : '');
}

/** Renders a list of mini-topic rows as cards. $showAuthor adds the author link. */
function mini_cards(array $rows, $showAuthor = true)
{
    $me = current_user();
    foreach ($rows as $r) {
        echo '<article class="mini-card"><header><a class="mini-tag" href="' . e(url('tag.php?t=' . rawurlencode($r['tag']))) . '">#' . e($r['tag']) . '</a>';
        echo '<a class="mini-title" href="' . e(url('mini.php?id=' . (int) $r['id'])) . '">' . e($r['title']) . '</a></header>';
        echo '<p>' . e(mini_excerpt($r['content'], 35)) . '</p><footer>';
        if ($showAuthor) {
            echo user_link($r['user_id'], $r['username']) . ' · ';
        }
        echo '<time>' . e(fmt_when($r['created_at'])) . '</time> <a class="mini-more" href="' . e(url('mini.php?id=' . (int) $r['id'])) . '">اقرأ المزيد</a>';
        if ($me !== null && (int) $me['id'] === (int) $r['user_id']) {
            echo '<a class="icon-btn" href="' . e(url('mini.php?id=' . (int) $r['id'] . '&edit=1')) . '" title="تعديل" aria-label="تعديل">' . icon('pencil') . '</a>';
        }
        if ($me !== null && ((int) $me['id'] === (int) $r['user_id'] || is_admin())) {
            echo '<form method="post" action="' . e(url('mini.php')) . '" class="inline-form mini-del">' . csrf_field()
                . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
                . '<input type="hidden" name="next" value="' . e(safe_next(current_app_path())) . '">'
                . '<button type="submit" class="icon-btn" title="حذف" aria-label="حذف" onclick="return confirm(\'حذف هذا الموضوع؟\')">' . icon('trash') . '</button></form>';
        }
        echo '</footer>';
        $st = mini_stats((int) $r['id']);
        $rid = (int) $r['id'];
        echo '<div class="mini-acts">';
        if ($me !== null) {
            $here = safe_next(current_app_path());
            $liked = mini_liked($rid);
            echo '<form method="post" action="' . e(url('mini_like.php')) . '" class="inline-form">' . csrf_field() . '<input type="hidden" name="id" value="' . $rid . '"><input type="hidden" name="next" value="' . e($here) . '"><button type="submit" class="icon-btn mini-act' . ($liked ? ' is-saved' : '') . '" title="' . ($liked ? 'إلغاء الإعجاب' : 'أعجبني') . ' (عدد الإعجابات: ' . (int) $st['likes'] . ')" aria-label="أعجبني">' . icon('like') . '<span class="cnt">' . (int) $st['likes'] . '</span></button></form>';
            echo save_button('mini', $rid, $here, false, (int) $st['saves']);
            echo '<a class="icon-btn mini-act" href="' . e(url('mini_share.php?id=' . $rid)) . '" title="مشاركة مع الأعضاء (عدد المشاركات: ' . (int) $st['shares'] . ')" aria-label="مشاركة">' . icon('share') . '<span class="cnt">' . (int) $st['shares'] . '</span></a>';
        } else {
            echo '<span class="mini-stat" title="الإعجابات">' . icon('like') . (int) $st['likes'] . '</span><span class="mini-stat" title="مرات الحفظ">' . icon('bookmark') . (int) $st['saves'] . '</span><span class="mini-stat" title="المشاركات">' . icon('share') . (int) $st['shares'] . '</span>';
        }
        echo '</div></article>';
    }
}

// ---------------------------------------------------------------- profile background

/** Validates and re-encodes a profile background to exactly BG_W x BG_H JPEG. Returns file name, '' (no file) or null (error). */
function save_profile_bg($file, &$err)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $err = 'تعذر رفع الصورة.';
        return null;
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 3 * 1024 * 1024) {
        $err = 'يجب ألا يتجاوز حجم الصورة 3 ميغابايت.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = class_exists('finfo') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
    $types = ['image/png' => IMAGETYPE_PNG, 'image/jpeg' => IMAGETYPE_JPEG, 'image/webp' => IMAGETYPE_WEBP];
    if (!$info || !isset($types[$mime]) || $info[2] !== $types[$mime] || !function_exists('imagecreatefromstring')) {
        $err = 'صورة غير صالحة. المسموح: PNG أو JPG أو WEBP.';
        return null;
    }
    if ($info[0] < 800 || $info[1] < 200 || $info[0] > 5000 || $info[1] > 5000) {
        $err = 'المقاس غير مناسب: الحد الأدنى 800×200 والحد الأقصى 5000×5000 بكسل.';
        return null;
    }
    $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$img) {
        $err = 'تعذرت معالجة الصورة.';
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    // center-crop to the 4:1 ratio, then scale to the fixed size
    $ratio = BG_W / BG_H;
    if ($w / $h > $ratio) {
        $cw = (int) round($h * $ratio); $ch = $h; $sx = (int) (($w - $cw) / 2); $sy = 0;
    } else {
        $cw = $w; $ch = (int) round($w / $ratio); $sx = 0; $sy = (int) (($h - $ch) / 2);
    }
    $out = imagecreatetruecolor(BG_W, BG_H);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, $sx, $sy, BG_W, BG_H, $cw, $ch);
    $dir = __DIR__ . '/../assets/images/backgrounds';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $err = 'تعذر إنشاء مجلد الصور.';
        return null;
    }
    if (!is_file($dir . '/.htaccess')) {
        @copy(__DIR__ . '/../assets/images/.htaccess', $dir . '/.htaccess');
    }
    $name = bin2hex(random_bytes(12)) . '.jpg';   // random name + re-encoded pixels only
    if (!imagejpeg($out, $dir . '/' . $name, 82)) {
        $err = 'فشل حفظ الصورة.';
        return null;
    }
    return $name;
}

function delete_profile_bg($name)
{
    if (is_string($name) && preg_match('/^[a-f0-9]{24}\.jpg$/', $name)) {
        @unlink(__DIR__ . '/../assets/images/backgrounds/' . $name);
    }
}


// ---------------------------------------------------------------- v9: tags, deputy, nominations, hidden replies

function ensure_v9_schema()
{
    if (site_setting('schema_v9', '0') === '1') {
        return;
    }
    try {
        db_exec('CREATE TABLE IF NOT EXISTS mini_tags (name VARCHAR(30) NOT NULL PRIMARY KEY, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec("CREATE TABLE IF NOT EXISTS supervisor_nominations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            target_id INT UNSIGNED NOT NULL,
            nominator_id INT UNSIGNED NOT NULL,
            forum_id INT UNSIGNED NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_nom_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $has = function ($t, $c) {
            return db_val('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1', 'ss', [$t, $c]) !== null;
        };
        if (!$has('replies', 'is_hidden')) {
            db_exec('ALTER TABLE replies ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$has('sanctions', 'forum_id')) {
            db_exec('ALTER TABLE sanctions ADD COLUMN forum_id INT UNSIGNED NULL DEFAULT NULL');
        }
        site_set('schema_v9', '1');
    } catch (Throwable $e) {
    }
}

/** Default tags plus the ones the administrator created. */
function mini_tags()
{
    static $tags = null;
    if ($tags === null) {
        $tags = MINI_TAGS;
        try {
            foreach (db_all('SELECT name FROM mini_tags ORDER BY name') as $r) {
                if (!in_array($r['name'], $tags, true)) {
                    $tags[] = $r['name'];
                }
            }
        } catch (Throwable $e) {
        }
    }
    return $tags;
}

/** The owner is account #1; nobody can touch it and a deputy can never replace it. */
function is_owner($uid)
{
    return (int) $uid === 1;
}

function user_is_deputy($uid)
{
    static $cache = [];
    $uid = (int) $uid;
    if (!isset($cache[$uid])) {
        $cache[$uid] = (int) db_val("SELECT is_deputy FROM users WHERE id = ? AND role = 'admin'", 'i', [$uid]) === 1;
    }
    return $cache[$uid];
}

/** True for a full administrator (not a deputy). */
function is_full_admin()
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin' && !user_is_deputy($u['id']);
}

function ensure_v11_schema()
{
    if (site_setting('schema_v11', '0') === '1') {
        return;
    }
    try {
        db_exec("ALTER TABLE saved_items MODIFY item_type ENUM('thread','reply','mini') NOT NULL");
        db_exec('CREATE TABLE IF NOT EXISTS mini_likes (post_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (post_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec('CREATE TABLE IF NOT EXISTS mini_shares (post_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (post_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec('CREATE TABLE IF NOT EXISTS tag_follows (user_id INT UNSIGNED NOT NULL, tag VARCHAR(30) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, tag)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        site_set('schema_v11', '1');
    } catch (Throwable $e) {
    }
}

function mini_stats($id)
{
    try {
        $r = db_row("SELECT (SELECT COUNT(*) FROM mini_likes WHERE post_id = ?) AS likes, (SELECT COUNT(*) FROM saved_items WHERE item_type = 'mini' AND item_id = ?) AS saves, (SELECT COUNT(*) FROM mini_shares WHERE post_id = ?) AS shares", 'iii', [$id, $id, $id]);
        return $r ?: ['likes' => 0, 'saves' => 0, 'shares' => 0];
    } catch (Throwable $e) {
        return ['likes' => 0, 'saves' => 0, 'shares' => 0];
    }
}

function mini_liked($id)
{
    $u = current_user();
    return $u !== null && db_val('SELECT 1 FROM mini_likes WHERE post_id = ? AND user_id = ?', 'ii', [(int) $id, $u['id']]) !== null;
}

function is_following_tag($tag)
{
    $u = current_user();
    return $u !== null && db_val('SELECT 1 FROM tag_follows WHERE user_id = ? AND tag = ?', 'is', [$u['id'], $tag]) !== null;
}

// ---------------------------------------------------------------- v12: uploads, home mode, member rating

function ensure_v12_schema()
{
    if (site_setting('schema_v12', '0') === '1') {
        return;
    }
    try {
        db_exec('CREATE TABLE IF NOT EXISTS user_uploads (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, name VARCHAR(40) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_uu_user (user_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        site_set('schema_v12', '1');
    } catch (Throwable $e) {
    }
}

/** Home page content chosen by the administrator: both | forums | posts. */
function home_mode()
{
    $m = site_setting('home_mode', 'both');
    return in_array($m, ['both', 'forums', 'posts'], true) ? $m : 'both';
}

/**
 * Member rating, 0-10 stars + "نسبة التفوق" percentage. Administrators and supervisors are not rated (returns null).
 * Only what OTHER members do counts: followers, likes, saves and shares received (never the member's own).
 */
function member_rating($uid)
{
    static $cache = [];
    $uid = (int) $uid;
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }
    $cache[$uid] = null;
    try {
        $role = db_val('SELECT role FROM users WHERE id = ?', 'i', [$uid]);
        if ($role === null || $role === 'admin' || db_val("SELECT 1 FROM forum_staff WHERE user_id = ? AND level = 'supervisor' LIMIT 1", 'i', [$uid]) !== null) {
            return null;
        }
        $T = (int) db_val('SELECT COUNT(*) FROM threads WHERE user_id = ? AND is_hidden = 0', 'i', [$uid]);
        $R = (int) db_val('SELECT COUNT(*) FROM replies WHERE user_id = ? AND is_hidden = 0', 'i', [$uid]);
        $F = (int) db_val("SELECT COUNT(DISTINCT user_id) FROM follows WHERE target_type = 'user' AND target_id = ? AND user_id <> ?", 'ii', [$uid, $uid]);
        $L = (int) db_val('SELECT COUNT(*) FROM post_likes l LEFT JOIN replies r ON (l.reply_id > 0 AND r.id = l.reply_id) LEFT JOIN threads t ON (l.reply_id = 0 AND t.id = l.thread_id) WHERE l.user_id <> ? AND (r.user_id = ? OR t.user_id = ?)', 'iii', [$uid, $uid, $uid]);
        $L += (int) db_val('SELECT COUNT(*) FROM mini_likes ml JOIN user_posts p ON p.id = ml.post_id WHERE p.user_id = ? AND ml.user_id <> ?', 'ii', [$uid, $uid]);
        $S = (int) db_val("SELECT COUNT(*) FROM saved_items s LEFT JOIN threads t ON (s.item_type = 'thread' AND t.id = s.item_id) LEFT JOIN replies r ON (s.item_type = 'reply' AND r.id = s.item_id) LEFT JOIN user_posts p ON (s.item_type = 'mini' AND p.id = s.item_id) WHERE s.user_id <> ? AND (t.user_id = ? OR r.user_id = ? OR p.user_id = ?)", 'iiii', [$uid, $uid, $uid, $uid]);
        $H = (int) db_val('SELECT COUNT(*) FROM thread_shares h JOIN threads t ON t.id = h.thread_id WHERE t.user_id = ? AND h.user_id <> ?', 'ii', [$uid, $uid]);
        $H += (int) db_val('SELECT COUNT(*) FROM mini_shares h JOIN user_posts p ON p.id = h.post_id WHERE p.user_id = ? AND h.user_id <> ?', 'ii', [$uid, $uid]);
    } catch (Throwable $e) {
        return null;
    }
    // progress toward each target (diminishing returns), then strict caps by real achievements:
    //   100 threads + tens of likes + some saves/shares -> ~50%;  500+ threads with many likes/saves -> 100%
    $p = function ($x, $target) { return min(1.0, pow(max(0, $x) / $target, 0.4)); };
    $score = 0.35 * $p($T, 500) + 0.08 * $p($R, 1000) + 0.25 * $p($L, 500) + 0.12 * $p($S, 150) + 0.08 * $p($H, 100) + 0.12 * $p($F, 200);
    $pct = (int) floor($score * 100);
    $social = $L + $S + $H + $F;
    $cap = 100;
    if ($T < 5) { $cap = 10; }
    elseif ($T < 20) { $cap = 25; }
    elseif ($T < 50) { $cap = 35; }
    elseif ($T < 100) { $cap = 49; }
    elseif ($T < 250) { $cap = 74; }
    elseif ($T <= 500) { $cap = 94; }
    if ($social === 0) { $cap = min($cap, 30); }
    if ($L < 10) { $cap = min($cap, 40); }
    elseif ($L < 30) { $cap = min($cap, 49); }
    if ($S + $H < 5) { $cap = min($cap, 69); }
    if ($cap === 100 && ($L < 300 || $S < 50 || $H < 20)) { $cap = 99; }
    $pct = max(0, min(100, min($pct, $cap)));
    return $cache[$uid] = ['stars' => (int) floor($pct / 10), 'pct' => $pct, 'F' => $F, 'L' => $L, 'S' => $S, 'H' => $H];
}

function rating_html($uid, $showPct = true)
{
    $r = member_rating($uid);
    if ($r === null) {
        return '';
    }
    $s = '';
    for ($i = 1; $i <= 10; $i++) {
        $s .= '<i class="' . ($i <= $r['stars'] ? 'on' : '') . '">★</i>';
    }
    return '<div class="rating" title="تقييم العضو: ' . $r['stars'] . ' من 10 نجوم"><span class="stars" dir="ltr">' . $s . '</span>' . ($showPct ? '<small>نسبة التفوق: <b>' . $r['pct'] . '%</b></small>' : '') . '</div>';
}

// ---------------------------------------------------------------- v14: gender, live chat default, hidden presence

function ensure_v14_schema()
{
    if (site_setting('schema_v14', '0') === '1') {
        return;
    }
    try {
        if (db_val("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender' LIMIT 1") === null) {
            db_exec("ALTER TABLE users ADD COLUMN gender VARCHAR(6) NOT NULL DEFAULT ''");
        }
        db_exec('ALTER TABLE users MODIFY live_msgs TINYINT(1) NOT NULL DEFAULT 1');
        db_exec('UPDATE users SET live_msgs = 1');   // live chat is now on by default; members can switch it off in their settings
        site_set('schema_v14', '1');
    } catch (Throwable $e) {
    }
}

/** SQL fragment hiding "hidden" members from the current viewer (alias $a = users table). */
function hidden_members_sql($a = 'u')
{
    $v = current_user();
    if ($v !== null && $v['role'] === 'admin') {
        return '';                                   // administrators and deputies see everyone
    }
    $self = $v !== null ? ' OR ' . $a . '.id = ' . (int) $v['id'] : '';
    if ($v !== null && is_staff()) {                 // supervisors see hidden regular members only
        return ' AND (' . $a . '.hide_online = 0' . $self . " OR (" . $a . ".role <> 'admin' AND NOT EXISTS (SELECT 1 FROM forum_staff hs WHERE hs.user_id = " . $a . ".id AND hs.level = 'supervisor')))";
    }
    return ' AND (' . $a . '.hide_online = 0' . $self . ')';
}

/** Can the current viewer see that this hidden member is online? */
function presence_visible($uid, $role, $hideOnline)
{
    if ((int) $hideOnline !== 1) {
        return true;
    }
    $v = current_user();
    if ($v === null) {
        return false;
    }
    if ((int) $v['id'] === (int) $uid || $v['role'] === 'admin') {
        return true;
    }
    if ($role === 'admin' || db_val("SELECT 1 FROM forum_staff WHERE user_id = ? AND level = 'supervisor' LIMIT 1", 'i', [(int) $uid]) !== null) {
        return false;
    }
    return is_staff();
}

// ---------------------------------------------------------------- feminine forms (female members)

function user_gender($uid)
{
    static $cache = [];
    $uid = (int) $uid;
    if (!isset($cache[$uid])) {
        try {
            $cache[$uid] = (string) db_val('SELECT gender FROM users WHERE id = ?', 'i', [$uid]);
        } catch (Throwable $e) {
            $cache[$uid] = '';
        }
    }
    return $cache[$uid];
}

/** Turns role / rank / status words into the feminine form (taa marbouta) for female members. */
function fem_text($text, $uid)
{
    if (user_gender($uid) !== 'female') {
        return $text;
    }
    return strtr((string) $text, [
        'عضو جديد' => 'عضوة جديدة', 'عضو نشيط' => 'عضوة نشيطة', 'عضو متميز' => 'عضوة متميزة', 'عضو مميز جدًا' => 'عضوة مميزة جدًا',
        'عضو ذهبي' => 'عضوة ذهبية', 'عضو خبير' => 'عضوة خبيرة', 'عضو بارز' => 'عضوة بارزة', 'عضو عريق' => 'عضوة عريقة',
        'عضو أسطوري' => 'عضوة أسطورية', 'عضو الشرف' => 'عضوة الشرف', 'نائب المدير' => 'نائبة المدير',
        'متصل الآن' => 'متصلة الآن', 'مشرف' => 'مشرفة', 'مدير' => 'مديرة', 'مخفي' => 'مخفية', 'غير متصل' => 'غير متصلة', 'عضو' => 'عضوة',
    ]);
}
