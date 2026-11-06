<?php
/** Notifications + follow system (tables are created automatically on first run). */

function ensure_v35_schema()
{
    if (site_setting('schema_v35', '0') === '1') {
        return;
    }
    try {
        db_exec('CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            actor_id INT UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(20) NOT NULL,
            text VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notif_user (user_id, is_read, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec("CREATE TABLE IF NOT EXISTS follows (
            user_id INT UNSIGNED NOT NULL,
            target_type ENUM('user','forum','thread') NOT NULL,
            target_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, target_type, target_id),
            KEY idx_follow_target (target_type, target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        site_set('schema_v35', '1');
    } catch (Throwable $e) {
        // the features simply stay empty if the database user cannot create tables
    }
}

function ensure_poll_schema()
{
    if (site_setting('schema_v40', '0') === '1') {
        return;
    }
    try {
        db_exec('CREATE TABLE IF NOT EXISTS polls (thread_id INT UNSIGNED NOT NULL, question VARCHAR(200) NOT NULL, multiple TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (thread_id), CONSTRAINT fk_polls_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec('CREATE TABLE IF NOT EXISTS poll_options (id INT UNSIGNED NOT NULL AUTO_INCREMENT, thread_id INT UNSIGNED NOT NULL, label VARCHAR(150) NOT NULL, sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), KEY idx_poll_options_thread (thread_id, sort_order), CONSTRAINT fk_popt_poll FOREIGN KEY (thread_id) REFERENCES polls (thread_id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        db_exec('CREATE TABLE IF NOT EXISTS poll_votes (option_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, thread_id INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (option_id, user_id), KEY idx_poll_votes_user (thread_id, user_id), CONSTRAINT fk_pvote_opt FOREIGN KEY (option_id) REFERENCES poll_options (id) ON DELETE CASCADE, CONSTRAINT fk_pvote_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        site_set('schema_v40', '1');
    } catch (Throwable $e) {
    }
}

function ensure_v41_schema()
{
    if (site_setting('schema_v41', '0') === '1') {
        return;
    }
    try {
        $has = function ($table, $col) {
            return (int) db_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', 'ss', [$table, $col]) > 0;
        };
        if (!$has('users', 'is_approved')) {
            db_exec('ALTER TABLE users ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1');
        }
        if (!$has('forums', 'access')) {
            db_exec("ALTER TABLE forums ADD COLUMN access ENUM('public','staff_all','staff_section') NOT NULL DEFAULT 'public'");
        }
        db_exec("CREATE TABLE IF NOT EXISTS account_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            target_id INT UNSIGNED NOT NULL,
            requester_id INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_by INT UNSIGNED NULL,
            KEY idx_req_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db_exec('CREATE TABLE IF NOT EXISTS chat_typing (
            user_id INT UNSIGNED NOT NULL,
            to_id INT UNSIGNED NOT NULL,
            at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, to_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        site_set('schema_v41', '1');
    } catch (Throwable $e) {
    }
}

// ---- restricted (moderation) forums ----
function forum_access_ok($f)
{
    $acc = isset($f['access']) ? $f['access'] : 'public';
    if ($acc === 'public') {
        return true;
    }
    $u = current_user();
    if ($u === null) {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    if ($acc === 'admin_only') {
        return false; // administrators were accepted above
    }
    if ($acc === 'staff_all') {
        return is_staff();
    }
    if ($acc === 'staff_mods') {
        try {
            $uid0 = (int) $u['id'];
            return db_val("SELECT 1 FROM category_staff WHERE user_id = ? LIMIT 1", 'i', [$uid0]) !== null
                || db_val("SELECT 1 FROM forum_staff WHERE user_id = ? AND level IN ('moderator','deputy_moderator') LIMIT 1", 'i', [$uid0]) !== null;
        } catch (Throwable $e) {
            return false;
        }
    }
    try {
        $uid = (int) $u['id'];
        if (db_val('SELECT 1 FROM category_staff WHERE category = ? AND user_id = ?', 'si', [$f['category'], $uid]) !== null) {
            return true;
        }
        return db_val("SELECT 1 FROM forum_staff fs JOIN forums fo ON fo.id = fs.forum_id WHERE fo.category = ? AND fs.user_id = ? AND fs.level = 'supervisor'", 'si', [$f['category'], $uid]) !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function denied_forum_ids()
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        $u = current_user();
        if ($u === null || $u['role'] !== 'admin') {
            try {
                foreach (db_all("SELECT id, category, access FROM forums WHERE access <> 'public'") as $f) {
                    if (!forum_access_ok($f)) {
                        $ids[] = (int) $f['id'];
                    }
                }
            } catch (Throwable $e) {
            }
        }
    }
    return $ids;
}

/** One moderation forum per category, created automatically (visible to the section staff and administrators only). */
function ensure_section_mod_forums()
{
    try {
        $have = [];
        foreach (db_all("SELECT category FROM forums WHERE access = 'staff_section'") as $r) {
            $have[$r['category']] = true;
        }
        $made = false;
        foreach (db_all("SELECT DISTINCT category FROM forums WHERE access = 'public'") as $c) {
            if (!isset($have[$c['category']])) {
                db_exec("INSERT INTO forums (category, name, description, sort_order, icon, access) VALUES (?, 'المنتدى الإشرافي', 'منتدى خاص بإشراف هذا القسم: يظهر لمشرفي القسم والمدير ونائبه فقط.', 999999, 'chat', 'staff_section')", 's', [$c['category']]);
                $made = true;
            }
        }
        if ($made) {
            sync_category_staff();
        }
    } catch (Throwable $e) {
    }
}

function ensure_v47_schema()
{
    if (site_setting('schema_v47', '0') === '1') {
        return;
    }
    try {
        $has = function ($table, $col) {
            return (int) db_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', 'ss', [$table, $col]) > 0;
        };
        db_exec("ALTER TABLE forums MODIFY access ENUM('public','staff_all','staff_section','staff_mods','admin_only') NOT NULL DEFAULT 'public'");
        if (!$has('forums', 'closed')) {
            db_exec('ALTER TABLE forums ADD COLUMN closed TINYINT(1) NOT NULL DEFAULT 0');
        }
        $ct = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        db_exec('CREATE TABLE IF NOT EXISTS forum_bans (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, forum_id INT UNSIGNED NOT NULL, until DATETIME NOT NULL, reason VARCHAR(255) NOT NULL DEFAULT \'\', by_id INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_fb (user_id, forum_id, until))' . $ct);
        db_exec('CREATE TABLE IF NOT EXISTS mod_log (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, by_user_id INT UNSIGNED NULL, kind VARCHAR(20) NOT NULL, text VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_ml (id))' . $ct);
        db_exec("CREATE TABLE IF NOT EXISTS staff_nominations (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kind ENUM('supervisor','deputy_moderator') NOT NULL, nominee_id INT UNSIGNED NOT NULL, nominator_id INT UNSIGNED NOT NULL, forum_id INT UNSIGNED NULL, category VARCHAR(100) NULL, reason VARCHAR(255) NOT NULL DEFAULT '', status ENUM('pending_mod','pending_admin','approved','rejected') NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_sn (status, id))" . $ct);
        db_exec('CREATE TABLE IF NOT EXISTS announce_members (owner_id INT UNSIGNED NOT NULL, member_id INT UNSIGNED NOT NULL, PRIMARY KEY (owner_id, member_id))' . $ct);
        db_exec('CREATE TABLE IF NOT EXISTS user_files (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, stored CHAR(32) NOT NULL, orig VARCHAR(120) NOT NULL, mime VARCHAR(60) NOT NULL, size INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_uf (user_id, id))' . $ct);
        site_set('schema_v47', '1');
    } catch (Throwable $e) {
    }
}

function mod_log_add($userId, $byId, $kind, $text)
{
    try {
        db_exec('INSERT INTO mod_log (user_id, by_user_id, kind, text) VALUES (?, ?, ?, ?)', 'iiss', [(int) $userId, $byId === null ? null : (int) $byId, $kind, mb_substr($text, 0, 250)]);
    } catch (Throwable $e) {
    }
}

/** Message when the member may not post in this forum now (temporary restriction or forum closed by the administration), else null. */
function forum_restriction_message($forumId)
{
    $u = current_user();
    $forumId = (int) $forumId;
    if ($u === null || $forumId <= 0) {
        return null;
    }
    try {
        if ($u['role'] !== 'admin' && (int) db_val('SELECT closed FROM forums WHERE id = ?', 'i', [$forumId]) === 1) {
            return 'هذا المنتدى مقفل مؤقتًا من الإدارة.';
        }
        $until = db_val('SELECT MAX(until) FROM forum_bans WHERE user_id = ? AND forum_id = ? AND until > NOW()', 'ii', [(int) $u['id'], $forumId]);
        if ($until !== null) {
            return 'أنت ممنوع من الكتابة في هذا المنتدى حتى ' . fmt_when($until) . '.';
        }
    } catch (Throwable $e) {
    }
    return null;
}

/** Apply an approved nomination. Returns an error text or null. */
function apply_nomination($n)
{
    if ($n['kind'] === 'supervisor') {
        db_exec("INSERT INTO forum_staff (forum_id, user_id, level) VALUES (?, ?, 'supervisor') ON DUPLICATE KEY UPDATE level = level", 'ii', [(int) $n['forum_id'], (int) $n['nominee_id']]);
        return null;
    }
    $taken = category_level_taken($n['category'], 'deputy_moderator', (int) $n['nominee_id']);
    if ($taken !== null) {
        return 'للقسم نائب مراقب بالفعل (' . $taken . ').';
    }
    db_exec("INSERT INTO category_staff (category, user_id, level) VALUES (?, ?, 'deputy_moderator') ON DUPLICATE KEY UPDATE level = VALUES(level)", 'si', [$n['category'], (int) $n['nominee_id']]);
    sync_category_staff();
    return null;
}

function notify($to, $actorId, $type, $text, $url)
{
    $to = (int) $to;
    if ($to <= 0 || $to === (int) $actorId) {
        return;
    }
    try {
        $text = mb_substr($text, 0, 250);
        $dupe = db_val('SELECT 1 FROM notifications WHERE user_id = ? AND type = ? AND url = ? AND text = ? AND is_read = 0', 'isss', [$to, $type, $url, $text]);
        if ($dupe !== null) {
            return;
        }
        db_exec('INSERT INTO notifications (user_id, actor_id, type, text, url) VALUES (?, ?, ?, ?, ?)', 'iisss', [$to, (int) $actorId, $type, $text, $url]);
    } catch (Throwable $e) {
    }
}

function notif_unread_count()
{
    static $n = null;
    $u = current_user();
    if ($u === null) {
        return 0;
    }
    if ($n === null) {
        try {
            $n = (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$u['id']]);
        } catch (Throwable $e) {
            $n = 0;
        }
    }
    return $n;
}

function follower_ids($type, $id)
{
    try {
        $out = [];
        foreach (db_all('SELECT user_id FROM follows WHERE target_type = ? AND target_id = ?', 'si', [$type, (int) $id]) as $r) {
            $out[] = (int) $r['user_id'];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function is_following($type, $id)
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    try {
        return db_val('SELECT 1 FROM follows WHERE user_id = ? AND target_type = ? AND target_id = ?', 'isi', [$u['id'], $type, (int) $id]) !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function follower_count($type, $id)
{
    static $c = [];
    $k = $type . ':' . (int) $id;
    if (!isset($c[$k])) {
        try {
            $c[$k] = (int) db_val('SELECT COUNT(*) FROM follows WHERE target_type = ? AND target_id = ?', 'si', [$type, (int) $id]);
        } catch (Throwable $e) {
            $c[$k] = 0;
        }
    }
    return $c[$k];
}

function following_count($uid)
{
    static $c = [];
    $k = (int) $uid;
    if (!isset($c[$k])) {
        try {
            $c[$k] = (int) db_val("SELECT COUNT(*) FROM follows WHERE user_id = ? AND target_type = 'user'", 'i', [$k]);
        } catch (Throwable $e) {
            $c[$k] = 0;
        }
    }
    return $c[$k];
}

/** Follow / unfollow button (small form). */
function follow_button($type, $id, $class = 'btn')
{
    $u = current_user();
    if ($u === null || ($type === 'user' && (int) $u['id'] === (int) $id)) {
        return '';
    }
    $on = is_following($type, $id);
    $labels = ['user' => 'العضو', 'forum' => 'المنتدى', 'thread' => 'الموضوع'];
    $label = $on ? 'إلغاء متابعة ' . $labels[$type] : 'متابعة ' . $labels[$type];
    $here = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    return '<form method="post" action="' . e(url('follow.php')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="next" value="' . e($here) . '">'
        . '<button type="submit" class="' . e($class) . ($on ? ' is-on' : '') . '">' . icon('bell') . ' <span>' . e($label) . '</span></button></form>';
}

/** After a reply: tell the topic author and the followers of the topic. */
function notify_new_reply($threadId, $replyId, $actor, $page)
{
    try {
        $t = db_row('SELECT t.id, t.user_id, t.title, f.name AS fname FROM threads t JOIN forums f ON f.id = t.forum_id WHERE t.id = ?', 'i', [(int) $threadId]);
        if (!$t) {
            return;
        }
        $url = 'thread.php?id=' . (int) $threadId . '&page=' . (int) $page . '#post-' . (int) $replyId;
        $done = [];
        notify($t['user_id'], $actor['id'], 'reply', $actor['username'] . ' ردّ على موضوعك «' . $t['title'] . '» في منتدى «' . $t['fname'] . '»', $url);
        $done[(int) $t['user_id']] = true;
        foreach (follower_ids('thread', $threadId) as $f) {
            if (!isset($done[$f])) {
                notify($f, $actor['id'], 'follow', $actor['username'] . ' كتب ردًا في موضوع تتابعه «' . $t['title'] . '» (منتدى «' . $t['fname'] . '»)', $url);
                $done[$f] = true;
            }
        }
    } catch (Throwable $e) {
    }
}

/** After a new topic: tell followers of the author and of the forum. */
function notify_new_thread($threadId, $forumId, $actor, $title)
{
    try {
        $url = 'thread.php?id=' . (int) $threadId;
        $fname = (string) db_val('SELECT name FROM forums WHERE id = ?', 'i', [(int) $forumId]);
        $done = [];
        foreach (follower_ids('user', $actor['id']) as $f) {
            notify($f, $actor['id'], 'follow', $actor['username'] . ' (تتابعه) نشر موضوعًا جديدًا «' . $title . '» في منتدى «' . $fname . '»', $url);
            $done[$f] = true;
        }
        foreach (follower_ids('forum', $forumId) as $f) {
            if (!isset($done[$f])) {
                notify($f, $actor['id'], 'follow', 'موضوع جديد في منتدى «' . $fname . '» الذي تتابعه: «' . $title . '»', $url);
            }
        }
    } catch (Throwable $e) {
    }
}
