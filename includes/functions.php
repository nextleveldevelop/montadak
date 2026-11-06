<?php
/** Shared helpers. Loaded by bootstrap.php. */

// ---------------------------------------------------------------- output

/** Escape for HTML output. */
function e($value)
{
    return htmlspecialchars((string) ($value === null ? '' : $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute URL path for an app-relative path such as 'thread.php?id=3'. */
function url($path = '')
{
    return BASE . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/** App-relative path + query of the current request, e.g. 'thread.php?id=3'. */
function current_app_path()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $path = ltrim(substr($script, strlen(BASE)), '/');
    $query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    return $query !== '' ? $path . '?' . $query : $path;
}

/** Only allow redirecting to simple internal pages ("next" parameter). */
function safe_next($next)
{
    if (is_string($next) && preg_match('~^[a-z0-9_/]+\.php(\?[a-zA-Z0-9_=&%.\-+]*)?(#[\w\-]*)?$~', $next)) {
        return $next;
    }
    return 'index.php';
}

/** Turn plain text into safe HTML: escapes, links http(s) URLs, keeps line breaks. */
/** Classic forum smileys, applied to text outside tags only. */
function smileys($html)
{
    static $map = null;
    if ($map === null) {
        $map = [':)' => '🙂', ':-)' => '🙂', ':(' => '🙁', ':-(' => '🙁', ';)' => '😉', ':D' => '😃', ':P' => '😛', ':p' => '😛', ':o' => '😮', ':O' => '😮',
            ':*' => '😘', ':/' => '😕', 'B)' => '😎', ':3' => '😺', '&lt;3' => '❤️', ':cry:' => '😢', ':lol:' => '😂', ':angry:' => '😠', ':love:' => '😍', ':ok:' => '👍'];
    }
    $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $inPre = false;
    foreach ($parts as $i => $part) {
        if ($part !== '' && $part[0] === '<') {
            if (preg_match('/^<pre/i', $part)) { $inPre = true; } elseif (preg_match('/^<\/pre/i', $part)) { $inPre = false; }
            continue;
        }
        if ($inPre || $part === '') { continue; }
        $parts[$i] = preg_replace_callback('/(?<=^|[\s>])(' . implode('|', array_map(function ($k) { return preg_quote($k, '/'); }, array_keys($map))) . ')(?=$|[\s<.,!؟?])/u',
            function ($m) use ($map) { return $map[$m[1]]; }, $part);
    }
    return implode('', $parts);
}

function post_html($text)
{
    return smileys(post_html_base($text));
}

function post_html_base($text)
{
    if (is_rich($text)) {
        $h = sanitize_rich(substr($text, strlen(RICH_MARK)));
        $opens = preg_match_all('~<p>\[اقتباس(?: من [^\]<]{1,40})?\]</p>~u', $h);
        $closes = substr_count($h, '<p>[/اقتباس]</p>');
        if ($opens > 0 && $opens === $closes && $opens <= 5) {
            $h = preg_replace('~<p>\[اقتباس من ([^\]<]{1,40})\]</p>~u', '<blockquote class="quote"><cite>$1</cite>', $h);
            $h = preg_replace('~<p>\[اقتباس\]</p>~u', '<blockquote class="quote">', $h);
            $h = str_replace('<p>[/اقتباس]</p>', '</blockquote>', $h);
        }
        return '<div class="rich-body">' . $h . '</div>';
    }
    $safe = e($text);
    $safe = preg_replace_callback('~https?://[^\s<]+~iu', function ($m) {
        $url = $m[0];
        $trail = '';
        if (preg_match('~(?:&quot;|&#039;|&lt;|&gt;|&amp;|[.,;:!?)\]])+$~u', $url, $t)) {
            $trail = $t[0];
            $url = substr($url, 0, -strlen($trail));
        }
        if ($url === '') {
            return $m[0];
        }
        return '<a href="' . $url . '" target="_blank" rel="nofollow noopener ugc">' . $url . '</a>' . $trail;
    }, $safe);
    // Quotes: [اقتباس من اسم] ... [/اقتباس], only when opening and closing tags match.
    $opens = preg_match_all('~\[اقتباس(?: من [^\]\n]{1,40})?\]~u', $safe);
    $closes = substr_count($safe, '[/اقتباس]');
    if ($opens > 0 && $opens === $closes && $opens <= 5) {
        $safe = preg_replace('~\[اقتباس من ([^\]\n]{1,40})\]~u', '<blockquote class="quote"><cite>$1</cite>', $safe);
        $safe = preg_replace('~\[اقتباس\]~u', '<blockquote class="quote">', $safe);
        $safe = str_replace('[/اقتباس]', '</blockquote>', $safe);
    }
    return nl2br($safe, false);
}


// ---------------------------------------------------------------- rich text (Quill)
const RICH_MARK = '<!--rt-->';

/** Whitelist sanitizer for Quill HTML. Returns safe HTML (no marker). */
function sanitize_rich($html)
{
    if (trim($html) === '') {
        return '';
    }
    $allowed = ['p' => 1, 'br' => 1, 'strong' => 1, 'b' => 1, 'em' => 1, 'i' => 1, 'u' => 1, 's' => 1, 'strike' => 1,
        'blockquote' => 1, 'code' => 1, 'h1' => 1, 'ol' => 1, 'ul' => 1, 'li' => 1, 'a' => 1, 'span' => 1, 'h2' => 1, 'h3' => 1, 'pre' => 1, 'sub' => 1, 'sup' => 1,
        'table' => 1, 'thead' => 1, 'tbody' => 1, 'tr' => 1, 'td' => 1, 'th' => 1];
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="rt-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('rt-root');
    if (!$root) {
        return e(strip_tags($html));
    }
    $clean = function (DOMNode $node) use (&$clean, $allowed, $doc) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if ($tag === 'iframe') { // only YouTube embeds, rebuilt from the video id
                    if (preg_match('~^https://www\.youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})$~', $child->getAttribute('src'), $mm)) {
                        $new = $doc->createElement('iframe');
                        $new->setAttribute('class', 'ql-video');
                        $new->setAttribute('src', 'https://www.youtube-nocookie.com/embed/' . $mm[1]);
                        $new->setAttribute('frameborder', '0');
                        $new->setAttribute('allowfullscreen', 'true');
                        $new->setAttribute('loading', 'lazy');
                        $new->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                        $new->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups');
                        $node->replaceChild($new, $child);
                    } else {
                        $node->removeChild($child);
                    }
                    continue;
                }
                if ($tag === 'img') { // only images uploaded through upload_image.php
                    $src = $child->getAttribute('src');
                    $prefix = url('assets/uploads/img/');
                    if (strncmp($src, $prefix, strlen($prefix)) === 0 && preg_match('~^[a-f0-9]{24}\.(?:jpg|png)$~', substr($src, strlen($prefix)))) {
                        $new = $doc->createElement('img');
                        $new->setAttribute('src', $src);
                        $new->setAttribute('alt', '');
                        $new->setAttribute('loading', 'lazy');
                        $new->setAttribute('class', 'post-img');
                        $wd = $child->getAttribute('width');
                        if (preg_match('/^\d{2,4}$/', $wd) && (int) $wd >= 20 && (int) $wd <= 1200) {
                            $new->setAttribute('width', $wd);
                        }
                        $node->replaceChild($new, $child);
                    } else {
                        $node->removeChild($child);
                    }
                    continue;
                }
                if ($tag === 'div' && $child->getAttribute('class') === 'ql-tbl') {
                    foreach (iterator_to_array($child->attributes) as $attr) {
                        if ($attr->name !== 'class') {
                            $child->removeAttribute($attr->name);
                        }
                    }
                    $clean($child);
                    continue;
                }
                if (!isset($allowed[$tag])) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'textarea', 'select', 'button'], true)) {
                        $node->removeChild($child);
                    } else { // unwrap unknown tag, keep its text/children
                        $clean($child);
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                    }
                    continue;
                }
                $keep = [];
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $n = strtolower($attr->name);
                    $v = $attr->value;
                    if ($n === 'class' && preg_match('/^(ql-(align|direction|indent|syntax|size)[a-z0-9-]*)( ql-[a-z0-9-]+)*$/', $v)) {
                        $keep['class'] = $v;
                    } elseif ($n === 'style' && $tag === 'span') {
                        $st = [];
                        foreach (explode(';', $v) as $decl) {
                            if (preg_match('/^\s*(color|background-color)\s*:\s*(#[0-9a-f]{3,6}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*$/i', $decl, $mm)) {
                                $st[] = strtolower($mm[1]) . ':' . $mm[2];
                            }
                        }
                        if ($st) {
                            $keep['style'] = implode(';', $st);
                        }
                    } elseif ($n === 'href' && $tag === 'a' && preg_match('~^(https?://|mailto:)[^\s"<>]+$~i', $v)) {
                        $keep['href'] = $v;
                    } elseif ($n === 'style' && in_array($tag, ['table', 'tr', 'td', 'th'], true)) {
                        $cs = sanitize_cell_style($v);
                        if ($cs !== '') {
                            $keep['style'] = $cs;
                        }
                    } elseif (($n === 'colspan' || $n === 'rowspan') && in_array($tag, ['td', 'th'], true) && preg_match('/^[1-9]\d?$/', $v) && (int) $v <= 12) {
                        $keep[$n] = $v;
                    } elseif ($n === 'data-list' && $tag === 'li' && in_array($v, ['ordered', 'bullet'], true)) {
                        $keep['data-list'] = $v;
                    }
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $child->removeAttribute($attr->name);
                }
                foreach ($keep as $k => $v) {
                    $child->setAttribute($k, $v);
                }
                if ($tag === 'a') {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'nofollow noopener ugc');
                }
                $clean($child);
            }
        }
    };
    $clean($root);
    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

/** Whitelist for inline table styles (colors, border, padding, width, alignment only). */
function sanitize_cell_style($v)
{
    $c = '(?:#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))';
    $out = [];
    foreach (explode(';', (string) $v) as $d) {
        $d = trim($d);
        if (preg_match('/^(background-color|color|border-color)\s*:\s*(' . $c . ')$/i', $d, $m)) {
            $out[] = strtolower($m[1]) . ':' . $m[2];
        } elseif (preg_match('/^border\s*:\s*(\d{1,2})px\s+(solid|dashed|dotted)\s+(' . $c . ')$/i', $d, $m)) {
            $out[] = 'border:' . (int) $m[1] . 'px ' . strtolower($m[2]) . ' ' . $m[3];
        } elseif (preg_match('/^(border-width|padding)\s*:\s*(\d{1,2})px$/i', $d, $m)) {
            $out[] = strtolower($m[1]) . ':' . (int) $m[2] . 'px';
        } elseif (preg_match('/^text-align\s*:\s*(right|left|center)$/i', $d, $m)) {
            $out[] = 'text-align:' . strtolower($m[1]);
        } elseif (preg_match('/^width\s*:\s*(\d{1,3})%$/i', $d, $m) && (int) $m[1] <= 100) {
            $out[] = 'width:' . (int) $m[1] . '%';
        }
    }
    return implode(';', $out);
}

function is_rich($text)
{
    return strncmp((string) $text, RICH_MARK, strlen(RICH_MARK)) === 0;
}

/** Plain text of a stored body (rich or plain): used for length checks and quoting. */
function rich_plain($text)
{
    if (!is_rich($text)) {
        return (string) $text;
    }
    $h = substr($text, strlen(RICH_MARK));
    $h = preg_replace('~</(p|li|blockquote|h2|h3|pre)>~i', "\n", $h);
    $h = preg_replace('~<br\s*/?>~i', "\n", $h);
    return trim(html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8'));
}

/** Read a POST field that may hold Quill HTML; returns the value to store (marker + clean HTML, or plain text). */
function rich_str($key)
{
    $raw = post_str($key);
    if (is_rich($raw)) {
        $clean = sanitize_rich(substr($raw, strlen(RICH_MARK)));
        return trim(strip_tags($clean)) === '' ? '' : RICH_MARK . $clean;
    }
    return $raw;
}

// ---------------------------------------------------------------- input

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Trimmed string from POST, '' when missing, not a string, or not valid UTF-8. */
function post_str($key)
{
    $v = isset($_POST[$key]) ? $_POST[$key] : '';
    if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) {
        return '';
    }
    return trim(str_replace("\r\n", "\n", $v));
}

function get_int($key, $default = 0)
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? (int) $_GET[$key] : $default;
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '0.0.0.0';
}

// ---------------------------------------------------------------- CSRF & flash

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_verify()
{
    $sent = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        fail_page('انتهت صلاحية الصفحة. أعد تحميلها وحاول مرة أخرى.', 419);
    }
}

/** $type: success | error | info */
function flash($type, $message)
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $f;
}

/** Simple flood control for posting and messaging. Returns an error message or null. */
function flood_message($seconds = 8)
{
    $last = isset($_SESSION['last_action']) ? (int) $_SESSION['last_action'] : 0;
    if (time() - $last < $seconds) {
        return 'انتظر بضع ثوانٍ قبل الإرسال مرة أخرى.';
    }
    return null;
}

function mark_action()
{
    $_SESSION['last_action'] = time();
}

// ---------------------------------------------------------------- users & permissions

/** Logged-in member as an array, or null. Loaded once per request. */
function current_user()
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    if ($id > 0) {
        $user = db_row('SELECT id, username, email, role, is_banned, is_approved, bio, signature, country, real_name, city, social_status, birth_date, cv, education, experience, interests, timezone, muted_until, read_all_at, hide_online, created_at FROM users WHERE id = ?', 'i', [$id]);
        if (!$user || (int) $user['is_banned'] === 1 || (int) $user['is_approved'] === 0) {
            $user = null;
            unset($_SESSION['uid']);
        } else {
            // Keep "who is online" fresh without writing on every single request.
            db_exec('UPDATE users SET last_seen = NOW() WHERE id = ? AND (last_seen IS NULL OR last_seen < (NOW() - INTERVAL 60 SECOND))', 'i', [$id]);
        }
    }
    return $user;
}

function login_user($userId)
{
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['uid'] = (int) $userId;
}

function is_admin()
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

/**
 * Staff map, loaded once per request: who is admin, and who is supervisor / moderator where.
 * Levels: 4 = admin (مدير), 2 = supervisor (مشرف), 0 = member.
 */
function staff_map()
{
    static $map = null;
    if ($map === null) {
        $map = ['admins' => [], 'forum' => [], 'global' => []];
        $map['rank'] = [];
        $map['deputy'] = [];
        foreach (db_all("SELECT id FROM users WHERE role = 'admin'") as $r) {
            $map['admins'][(int) $r['id']] = true;
        }
        foreach (db_all("SELECT forum_id, user_id, level FROM forum_staff WHERE level = 'supervisor'") as $r) {
            $lvl = 2; // supervisor
            $uid = (int) $r['user_id'];
            $map['rank'][(int) $r['forum_id']][$uid] = $r['level'];
            $map['forum'][(int) $r['forum_id']][$uid] = $lvl;
            if (!isset($map['global'][$uid]) || $map['global'][$uid] < $lvl) {
                $map['global'][$uid] = $lvl;
            }
        }
    }
    return $map;
}

/** Level of any member, in one forum, or (forum null) the highest they hold anywhere. */
function member_level($userId, $forumId = null)
{
    $m = staff_map();
    $userId = (int) $userId;
    if (isset($m['admins'][$userId])) {
        return 4;
    }
    if ($forumId === null) {
        return isset($m['global'][$userId]) ? $m['global'][$userId] : 0;
    }
    return isset($m['forum'][(int) $forumId][$userId]) ? $m['forum'][(int) $forumId][$userId] : 0;
}

/** Display rank key: deputy_admin, admin, supervisor, moderator, deputy_moderator or '' (member). */
function member_rank($userId, $forumId = null)
{
    $m = staff_map();
    $userId = (int) $userId;
    if (isset($m['admins'][$userId])) {
        return 'admin';
    }
    if ($forumId !== null) {
        return isset($m['rank'][(int) $forumId][$userId]) ? $m['rank'][(int) $forumId][$userId] : '';
    }
    $order = ['' => 0, 'supervisor' => 1];
    $best = '';
    foreach ($m['rank'] as $members) {
        if (isset($members[$userId]) && $order[$members[$userId]] > $order[$best]) {
            $best = $members[$userId];
        }
    }
    return $best;
}

/** Name of the member already holding $level ('moderator' | 'deputy_moderator') in the section, other than $exceptUserId; null when free. */
function category_level_taken($category, $level, $exceptUserId)
{
    $r = db_row('SELECT u.username FROM category_staff cs JOIN users u ON u.id = cs.user_id WHERE cs.category = ? AND cs.level = ? AND cs.user_id <> ? LIMIT 1', 'ssi', [$category, $level, (int) $exceptUserId]);
    return $r ? $r['username'] : null;
}

/** Copy category-wide staff (category_staff) onto every forum of the category. */
function sync_category_staff()
{
    db_exec('INSERT INTO forum_staff (forum_id, user_id, level)
             SELECT f.id, cs.user_id, cs.level FROM forums f JOIN category_staff cs ON cs.category = f.category
             ON DUPLICATE KEY UPDATE level = VALUES(level)');
}

/** Validate, re-encode and store a forum image. Returns file name, '' when none sent, or null + $err. */
function save_forum_image($file, &$err)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $err = 'تعذر رفع الصورة.';
        return null;
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 1024 * 1024) {
        $err = 'يجب ألا يتجاوز حجم صورة المنتدى 1 ميغابايت.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = class_exists('finfo') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
    $types = ['image/png' => IMAGETYPE_PNG, 'image/jpeg' => IMAGETYPE_JPEG, 'image/gif' => IMAGETYPE_GIF];
    if (!$info || !isset($types[$mime]) || $info[2] !== $types[$mime] || $info[0] > 2000 || $info[1] > 2000 || !function_exists('imagecreatefromstring')) {
        $err = 'صورة غير صالحة. المسموح: PNG أو JPG أو GIF (حتى 2000×2000).';
        return null;
    }
    $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$img) {
        $err = 'تعذرت معالجة الصورة.';
        return null;
    }
    // Resize to 128px max and re-encode: strips any hidden payload/metadata.
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, 128 / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $dir = __DIR__ . '/../assets/images/forums';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $err = 'تعذر إنشاء مجلد الصور.';
        return null;
    }
    $name = bin2hex(random_bytes(12)) . '.png';
    if (!imagepng($out, $dir . '/' . $name)) {
        $err = 'فشل حفظ الصورة.';
        return null;
    }
    if (!is_file($dir . '/.htaccess')) {
        @copy(__DIR__ . '/../assets/images/.htaccess', $dir . '/.htaccess');
    }
    return $name;
}

function delete_forum_image($name)
{
    if (is_string($name) && preg_match('/^[a-f0-9]{24}\.(png|gif)$/', $name)) {
        @unlink(__DIR__ . '/../assets/images/forums/' . $name);
    }
}

/** Forum icon: the uploaded image when there is one, else the built-in icon. */
function forum_icon_html($f, $class = 'forum-ico')
{
    if (!empty($f['image']) && preg_match('/^[a-f0-9]{24}\.png$/', $f['image'])) {
        return '<img class="' . e($class) . ' forum-img" src="' . e(url('assets/images/forums/' . $f['image'])) . '" alt="">';
    }
    return icon(!empty($f['icon']) ? $f['icon'] : 'chat', $class);
}

/** Current visitor's level in a forum (or anywhere when null). */
function forum_level($forumId = null)
{
    $u = current_user();
    return $u === null ? 0 : member_level($u['id'], $forumId);
}

function can_moderate($forumId)
{
    return forum_level($forumId) >= 1;
}

function can_supervise($forumId)
{
    return forum_level($forumId) >= 2;
}

function is_staff()
{
    return forum_level(null) >= 1;
}

/** Forum ids the visitor is staff in; null means "all" (administrators). */
function staff_forum_ids()
{
    $u = current_user();
    if ($u === null) {
        return [];
    }
    if ($u['role'] === 'admin') {
        return null;
    }
    $ids = [];
    foreach (staff_map()['forum'] as $fid => $members) {
        if (isset($members[(int) $u['id']])) {
            $ids[] = (int) $fid;
        }
    }
    return $ids;
}

function level_class($level)
{
    $names = [2 => 'super', 4 => 'admin'];
    return isset($names[$level]) ? $names[$level] : 'member';
}

function level_label($level)
{
    $names = [2 => 'مشرف', 4 => 'مدير'];
    return isset($names[$level]) ? $names[$level] : 'عضو';
}

function rank_label($rank)
{
    $n = ['admin' => 'مدير', 'supervisor' => 'مشرف'];
    return isset($n[$rank]) ? $n[$rank] : 'عضو';
}

/** "Moderation mode" (وضع الرقابة): staff switch the extra tools on and off. */
function mod_mode_on()
{
    return is_staff();
}

/** Error text when the member is currently muted, otherwise null. */
function mute_message()
{
    $u = current_user();
    if ($u !== null && !empty($u['muted_until']) && strtotime($u['muted_until'] . ' UTC') > time()) {
        return 'تم كتم حسابك مؤقتًا ولا يمكنك المشاركة حتى ' . fmt_when($u['muted_until']) . '.';
    }
    return null;
}

/** First error among the usual "may this member post right now?" checks. */
function posting_block_message($forumId = 0)
{
    $m = mute_message();
    if ($m === null && $forumId > 0) {
        $m = forum_restriction_message($forumId);
    }
    return $m !== null ? $m : flood_message();
}

// ---- hidden topics (المواضيع المخفية) ----

/** [sql, types, params] to append to a WHERE clause so hidden threads are filtered per viewer. */
function hidden_filter($a = 't')
{
    $r = hidden_filter_base($a);
    $den = function_exists('denied_forum_ids') ? denied_forum_ids() : [];
    if ($den) {
        $r[0] .= ' AND ' . $a . '.forum_id NOT IN (' . implode(',', array_map('intval', $den)) . ')';
    }
    return $r;
}

function hidden_filter_base($a = 't')
{
    $u = current_user();
    if ($u !== null && $u['role'] === 'admin') {
        return ['', '', []];
    }
    if ($u === null) {
        return [' AND ' . $a . '.is_hidden = 0', '', []];
    }
    $id = (int) $u['id'];
    return [
        ' AND (' . $a . '.is_hidden = 0 OR ' . $a . '.user_id = ?'
        . ' OR EXISTS (SELECT 1 FROM thread_access ta WHERE ta.thread_id = ' . $a . '.id AND ta.user_id = ?)'
        . ' OR EXISTS (SELECT 1 FROM forum_staff fs WHERE fs.forum_id = ' . $a . '.forum_id AND fs.user_id = ?))',
        'iii',
        [$id, $id, $id],
    ];
}

function can_view_thread(array $t)
{
    if (!(int) $t['is_hidden']) {
        return true;
    }
    $u = current_user();
    if ($u === null) {
        return false;
    }
    if ((int) $u['id'] === (int) $t['user_id'] || forum_level($t['forum_id']) >= 1) {
        return true;
    }
    return db_val('SELECT 1 FROM thread_access WHERE thread_id = ? AND user_id = ?', 'ii', [$t['id'], $u['id']]) !== null;
}

// ---- "new" markers ----

/** Oldest timestamp that can still count as "new": 30 days, or the last "mark all read". */
function unread_since()
{
    $u = current_user();
    $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
    if ($u !== null && !empty($u['read_all_at']) && $u['read_all_at'] > $since) {
        $since = $u['read_all_at'];
    }
    return $since;
}

/** [select expression, join sql, types, params]; alias must be the threads table alias. */
function unread_parts($a = 't')
{
    $u = current_user();
    if ($u === null) {
        return ['0', '', '', []];
    }
    return [
        '(' . $a . '.last_post_at > ? AND (tr.read_at IS NULL OR tr.read_at < ' . $a . '.last_post_at))',
        'LEFT JOIN thread_reads tr ON tr.thread_id = ' . $a . '.id AND tr.user_id = ?',
        'si',
        [unread_since(), (int) $u['id']],
    ];
}

function mark_thread_read($threadId)
{
    $u = current_user();
    if ($u !== null) {
        db_exec('INSERT INTO thread_reads (user_id, thread_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()', 'ii', [$u['id'], (int) $threadId]);
    }
}

// ---- favourites & display preferences ----

function is_favorite($type, $id)
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    $table = $type === 'forum' ? 'favorite_forums' : 'favorite_threads';
    $col = $type === 'forum' ? 'forum_id' : 'thread_id';
    return db_val('SELECT 1 FROM ' . $table . ' WHERE user_id = ? AND ' . $col . ' = ?', 'ii', [$u['id'], (int) $id]) !== null;
}

/** Replies per page: the visitor's choice (10, 15, 30 or 50), remembered in the session. */
function posts_per_page()
{
    $v = isset($_SESSION['pp']) ? (int) $_SESSION['pp'] : POSTS_PER_PAGE;
    return in_array($v, [10, 15, 30, 50], true) ? $v : POSTS_PER_PAGE;
}

function countries()
{
    return ['المغرب', 'الجزائر', 'تونس', 'ليبيا', 'مصر', 'السودان', 'موريتانيا', 'السعودية', 'الإمارات', 'قطر', 'الكويت', 'البحرين', 'عُمان',
            'اليمن', 'العراق', 'سوريا', 'لبنان', 'الأردن', 'فلسطين', 'الصومال', 'جيبوتي', 'جزر القمر', 'فرنسا', 'إسبانيا', 'ألمانيا', 'بريطانيا', 'كندا', 'الولايات المتحدة', 'أخرى'];
}
function require_login()
{
    $u = current_user();
    if ($u === null) {
        flash('info', 'سجّل الدخول للمتابعة.');
        redirect('login.php?next=' . rawurlencode(current_app_path()));
    }
    return $u;
}

function require_admin()
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        fail_page('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 403);
    }
    return $u;
}

// ---------------------------------------------------------------- time

function user_timezone()
{
    static $tz = null;
    if ($tz === null) {
        $u = current_user();
        $name = $u !== null && $u['timezone'] ? $u['timezone'] : (!empty($_SESSION['tz']) ? $_SESSION['tz'] : DEFAULT_TIMEZONE);
        try {
            $tz = new DateTimeZone($name);
        } catch (Exception $e) {
            $tz = new DateTimeZone('UTC');
        }
    }
    return $tz;
}

/** Format a UTC timestamp from the database in the viewer's timezone. */
function fmt_time($utc, $format = 'Y-m-d H:i')
{
    if (!$utc) {
        return '—';
    }
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(user_timezone());
        return $d->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

/** Timezones offered in settings (key => Arabic label). */
function timezone_choices()
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [];
    $mins = [];
    for ($h = -12; $h <= 14; $h++) {
        $mins[] = $h * 60;
    }
    foreach ([-570, -210, 210, 270, 330, 345, 390, 525, 570, 630, 765] as $odd) {
        $mins[] = $odd;
    }
    sort($mins);
    foreach (array_unique($mins) as $m) {
        $sign = $m < 0 ? '-' : '+';
        $a = abs($m);
        $key = sprintf('%s%02d:%02d', $sign, intdiv($a, 60), $a % 60);
        $label = $m === 0 ? 'GMT 0' : 'GMT' . $sign . intdiv($a, 60) . ($a % 60 ? ':' . sprintf('%02d', $a % 60) : '');
        $list[$m === 0 ? '+00:00' : $key] = $label;
    }
    return $list;
}

/** The choice key (e.g. "+01:00") matching a timezone's current offset. */
function timezone_key_for(DateTimeZone $tz)
{
    $off = $tz->getOffset(new DateTime('now', new DateTimeZone('UTC')));
    $a = abs($off);
    return sprintf('%s%02d:%02d', $off < 0 ? '-' : '+', intdiv($a, 3600), intdiv($a % 3600, 60));
}

// ---------------------------------------------------------------- pagination

function page_param()
{
    return max(1, get_int('page', 1));
}

/** $base is an app-relative path such as 'forum.php?id=3'. */
function pagination_html($page, $pages, $base, $param = 'page')
{
    if ($pages <= 1) {
        return '';
    }
    $sep = strpos($base, '?') === false ? '?' : '&';
    $link = function ($p, $label, $class = '') use ($base, $sep, $param) {
        return '<a class="' . $class . '" href="' . e(url($base . $sep . $param . '=' . $p)) . '">' . $label . '</a>';
    };
    $out = '<nav class="pagination" aria-label="الصفحات">';
    if ($page > 1) {
        $out .= $link($page - 1, 'السابق', 'pg-step');
    }
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1) {
        $out .= $link(1, '1');
        if ($start > 2) {
            $out .= '<span class="pg-gap">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $out .= $i === $page
            ? '<span class="pg-current" aria-current="page">' . $i . '</span>'
            : $link($i, (string) $i);
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $out .= '<span class="pg-gap">…</span>';
        }
        $out .= $link($pages, (string) $pages);
    }
    if ($page < $pages) {
        $out .= $link($page + 1, 'التالي', 'pg-step');
    }
    return $out . '</nav>';
}

// ---------------------------------------------------------------- forum logic

/** Re-derive a thread's reply count and last-post info (used after deleting a reply). */
function recalc_thread($threadId)
{
    db_exec(
        'UPDATE threads t SET
            t.reply_count = (SELECT COUNT(*) FROM replies r WHERE r.thread_id = t.id),
            t.last_post_at = COALESCE((SELECT MAX(r.created_at) FROM replies r WHERE r.thread_id = t.id), t.created_at),
            t.last_post_user_id = COALESCE((SELECT r.user_id FROM replies r WHERE r.thread_id = t.id ORDER BY r.id DESC LIMIT 1), t.user_id)
         WHERE t.id = ?',
        'i',
        [(int) $threadId]
    );
}

/** Which page of a thread a given reply is on. */
function reply_page($threadId, $replyId)
{
    $pos = (int) db_val('SELECT COUNT(*) FROM replies WHERE thread_id = ? AND id <= ?', 'ii', [(int) $threadId, (int) $replyId]);
    return max(1, (int) ceil($pos / posts_per_page()));
}

/** Validate a post body; returns an error message or null. */
function validate_body($text, $label = 'النص')
{
    if (mb_strlen($text) > MAX_POST_LENGTH * 4) {
        return $label . ' طويل جدًا.';
    }
    $len = mb_strlen(rich_plain($text));
    if ($len < 2) {
        return $label . ' قصير جدًا.';
    }
    if ($len > MAX_POST_LENGTH) {
        return $label . ' طويل جدًا (الحد الأقصى ' . MAX_POST_LENGTH . ' حرف).';
    }
    return null;
}

function validate_title($text)
{
    $len = mb_strlen($text);
    if ($len < 3) {
        return 'عنوان الموضوع قصير جدًا (3 أحرف على الأقل).';
    }
    if ($len > 200) {
        return 'عنوان الموضوع طويل جدًا (200 حرف كحد أقصى).';
    }
    return null;
}

// ---------------------------------------------------------------- classic-forum extras

/** "15:21 - اليوم", "15:21 - أمس", otherwise "2026/10/05 - 15:21" (viewer's timezone). */
function fmt_when($utc)
{
    if (!$utc) {
        return '—';
    }
    try {
        $tz = user_timezone();
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone($tz);
        $today = new DateTime('now', $tz);
        $today->setTime(0, 0, 0);
        $day = clone $d;
        $day->setTime(0, 0, 0);
        $diff = (int) $today->diff($day)->format('%r%a');
        $time = $d->format('H:i:s');
        if ($diff === 0) {
            return $time . ' • اليوم';
        }
        if ($diff === -1) {
            return $time . ' • يوم أمس';
        }
        // this year: "10/08 - 01:34:58"; older: "2025/10/08 - 01:34:58"
        return ($d->format('Y') === $today->format('Y') ? $d->format('m/d') : $d->format('Y/m/d')) . ' • ' . $time;
    } catch (Exception $e) {
        return '—';
    }
}

/** Stars and title from the number of posts: [stars, title]. */
function rank_levels()
{
    // [minimum posts, stars, title]
    return [[0, 0, 'عضو جديد'], [10, 1, 'عضو'], [50, 2, 'عضو نشيط'], [150, 3, 'عضو متميز'], [300, 4, 'عضو مميز جدًا'], [600, 5, 'عضو ذهبي'],
            [1000, 6, 'عضو خبير'], [2500, 7, 'عضو بارز'], [6000, 8, 'عضو عريق'], [15000, 9, 'عضو أسطوري'], [30000, 10, 'عضو الشرف']];
}

function rank_info($posts)
{
    $levels = rank_levels();
    $stars = 0;
    $title = 'عضو جديد';
    foreach ($levels as $l) {
        if ($posts >= $l[0]) {
            $stars = $l[1];
            $title = $l[2];
        }
    }
    return [$stars, $title];
}

function stars_html($n, $cls = 'member')
{
    return '';
    $n = (int) $n;
    if ($n <= 0) {
        return '';
    }
    $rows = '';
    for ($i = 0; $i < $n; $i += 5) { // rows of five stars, like the classic forums
        $rows .= '<span class="stars-row">' . str_repeat('★', min(5, $n - $i)) . '</span>';
    }
    return '<span class="stars stars-' . e($cls) . '" role="img" aria-label="' . $n . ' نجوم">' . $rows . '</span>';
}

/**
 * Guests have no account, so remember them by a hashed session id.
 * Written at most once a minute per visitor; old rows are cleaned up now and then.
 */
function track_visitor()
{
    if (isset($_SESSION['uid'])) {
        return;
    }
    $now = time();
    if (isset($_SESSION['visit_ping']) && $now - (int) $_SESSION['visit_ping'] < 60) {
        return;
    }
    $_SESSION['visit_ping'] = $now;
    db_exec('INSERT INTO visitors (sid, last_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()', 's', [md5(session_id())]);
    if (mt_rand(1, 50) === 1) {
        db_exec('DELETE FROM visitors WHERE last_seen < (NOW() - INTERVAL 1 HOUR)');
    }
}

/** Members and guests seen in the last few minutes. */
function online_counts()
{
    static $counts = null;
    if ($counts === null) {
        $window = (int) ONLINE_WINDOW_MINUTES;
        $hidden = hidden_members_sql('u');
        $counts = [
            'members' => (int) db_val('SELECT COUNT(*) FROM users u WHERE u.last_seen >= (NOW() - INTERVAL ' . $window . ' MINUTE)' . $hidden),
            'staff'   => (int) db_val("SELECT COUNT(*) FROM users u WHERE u.last_seen >= (NOW() - INTERVAL " . $window . " MINUTE) AND (u.role = 'admin' OR EXISTS (SELECT 1 FROM forum_staff s WHERE s.user_id = u.id))" . $hidden),
            'guests'  => (int) db_val('SELECT COUNT(*) FROM visitors WHERE last_seen >= (NOW() - INTERVAL ' . $window . ' MINUTE)'),
        ];
    }
    return $counts;
}

// ---------------------------------------------------------------- shared thread lists

/**
 * Column list for lists of threads (aliases: t threads, f forums, au author, lu last poster).
 */
function thread_list_columns($unreadExpr)
{
    return "t.id, t.forum_id, t.title, t.is_pinned, t.is_locked, t.is_hidden, t.is_archived, t.views, t.reply_count,
            t.created_at, t.last_post_at, t.like_count, t.feature_level,
            (SELECT COUNT(*) FROM saved_items si WHERE si.item_type = 'thread' AND si.item_id = t.id) AS save_count,
            (SELECT COUNT(*) FROM thread_shares ts WHERE ts.thread_id = t.id) AS share_count,
            (SELECT COUNT(*) FROM polls pl WHERE pl.thread_id = t.id) AS has_poll,
            f.name AS forum_name,
            au.id AS author_id, au.username AS author,
            lu.id AS last_user_id, lu.username AS last_username, "
        . $unreadExpr . ' AS is_new';
}

function thread_list_joins()
{
    return 'JOIN forums f ON f.id = t.forum_id JOIN users au ON au.id = t.user_id LEFT JOIN users lu ON lu.id = t.last_post_user_id';
}

// ---------------------------------------------------------------- v4: member numbers, polls, logs

/**
 * Turn "12, 57 Ahmed" into member ids. Numbers are member numbers; anything else is tried as a username.
 * Returns ['ids' => [...], 'missing' => [...]].
 */
function resolve_members($text, $max = 20)
{
    $ids = [];
    $missing = [];
    $tokens = preg_split('~[\s,،;]+~u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($tokens, 0, $max) as $tok) {
        if (ctype_digit($tok)) {
            $row = db_row('SELECT id FROM users WHERE id = ?', 'i', [(int) $tok]);
        } else {
            $row = db_row('SELECT id FROM users WHERE username = ?', 's', [$tok]);
        }
        if ($row) {
            $ids[(int) $row['id']] = true;
        } else {
            $missing[] = $tok;
        }
    }
    return ['ids' => array_keys($ids), 'missing' => $missing];
}

/** Poll of a thread with vote counts, or null. */
function poll_data($threadId)
{
    $poll = db_row('SELECT thread_id, question, multiple FROM polls WHERE thread_id = ?', 'i', [(int) $threadId]);
    if (!$poll) {
        return null;
    }
    $options = db_all(
        'SELECT o.id, o.label, (SELECT COUNT(*) FROM poll_votes v WHERE v.option_id = o.id) AS votes
           FROM poll_options o WHERE o.thread_id = ? ORDER BY o.sort_order, o.id',
        'i',
        [(int) $threadId]
    );
    $voters = (int) db_val('SELECT COUNT(DISTINCT user_id) FROM poll_votes WHERE thread_id = ?', 'i', [(int) $threadId]);
    $mine = [];
    $u = current_user();
    if ($u !== null) {
        foreach (db_all('SELECT option_id FROM poll_votes WHERE thread_id = ? AND user_id = ?', 'ii', [(int) $threadId, $u['id']]) as $r) {
            $mine[(int) $r['option_id']] = true;
        }
    }
    return ['poll' => $poll, 'options' => $options, 'voters' => $voters, 'mine' => $mine];
}

/**
 * Optional housekeeping: threads quiet for ARCHIVE_AFTER_DAYS move to the archive, unless pinned, featured,
 * or marked "cannot be archived". Off unless you define ARCHIVE_AFTER_DAYS in config.php.
 */
function auto_archive()
{
    $days = defined('ARCHIVE_AFTER_DAYS') ? (int) ARCHIVE_AFTER_DAYS : 0;
    if ($days > 0) {
        db_exec('UPDATE threads SET is_archived = 1 WHERE is_archived = 0 AND archive_mode = 0 AND is_pinned = 0 AND feature_level = 0 AND last_post_at < (NOW() - INTERVAL ' . $days . ' DAY)');
    }
}

/** Record a login (success or a failed attempt on an existing account) for the member's own history. */
function log_login($userId, $success)
{
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 160) : '';
    db_exec('INSERT INTO login_log (user_id, success, ip, agent) VALUES (?, ?, ?, ?)', 'iiss', [(int) $userId, $success ? 1 : 0, anon_ip(client_ip()), $agent]);
    if (mt_rand(1, 40) === 1) {
        db_exec('DELETE FROM login_log WHERE created_at < (NOW() - INTERVAL 180 DAY)');
    }
}

/** Radio-group labels used by the "change thread properties" page. */
function link_levels()
{
    return [0 => 'لا يظهر', 1 => 'وصلة عادية', 2 => 'وصلة أساسية'];
}

function feature_levels()
{
    return [0 => 'غير متميز', 1 => 'متميز على المنتدى', 2 => 'متميز على كل المنتديات'];
}

function archive_modes()
{
    return [0 => 'يمكن نقله للأرشيف', 1 => 'لا يمكن نقله للأرشيف', 2 => 'طلب نقل عاجل للأرشيف'];
}


// ---------------------------------------------------------------- v5: hidden members, daily limits, page boxes

/** Hidden members ("إخفاء تواجدي") can be seen by staff only: moderators, supervisors and admins. */
function can_see_hidden_members()
{
    return is_staff();
}

/**
 * Daily posting limit of a forum (shown on the forum card). $kind: 'threads' or 'replies'.
 * Returns an error message when the member has reached it; staff are never limited.
 */
function limit_message($forumId, $kind)
{
    $u = current_user();
    if ($u === null || forum_level($forumId) >= 1) {
        return null;
    }
    $f = db_row('SELECT max_threads_day, max_replies_day FROM forums WHERE id = ?', 'i', [(int) $forumId]);
    if (!$f) {
        return null;
    }
    $max = (int) ($kind === 'threads' ? $f['max_threads_day'] : $f['max_replies_day']);
    if ($max <= 0) {
        return null;
    }
    $used = $kind === 'threads' ? daily_thread_count($forumId, $u['id']) : daily_reply_count($forumId, $u['id']);
    if ($used >= $max) {
        return 'وصلت إلى الحد الأقصى في هذا المنتدى خلال 24 ساعة (' . $max . ' ' . ($kind === 'threads' ? 'موضوع' : 'رد') . '). حاول لاحقًا.';
    }
    return null;
}

function daily_thread_count($forumId, $userId)
{
    return (int) db_val('SELECT COUNT(*) FROM threads WHERE forum_id = ? AND user_id = ? AND created_at >= (NOW() - INTERVAL 24 HOUR)', 'ii', [(int) $forumId, (int) $userId]);
}

function daily_reply_count($forumId, $userId)
{
    return (int) db_val(
        'SELECT COUNT(*) FROM replies r JOIN threads t ON t.id = r.thread_id WHERE t.forum_id = ? AND r.user_id = ? AND r.created_at >= (NOW() - INTERVAL 24 HOUR)',
        'ii',
        [(int) $forumId, (int) $userId]
    );
}

/** Small numbered boxes under a thread title (pages of a long thread), like [1][2][3] … [9]. */
function thread_pages(array $t)
{
    $pages = (int) ceil(((int) $t['reply_count']) / posts_per_page());
    if ($pages < 2) {
        return '';
    }
    $link = function ($p) use ($t) {
        return '<a href="' . e(url('thread.php?id=' . (int) $t['id'] . '&page=' . $p)) . '">' . $p . '</a>';
    };
    $out = '<span class="page-boxes">' . icon('doc', 'mini');
    for ($i = 1; $i <= min($pages, 5); $i++) {
        $out .= $link($i);
    }
    if ($pages > 5) {
        $out .= '<span class="pg-gap">…</span>' . $link($pages);
    }
    return $out . '</span>';
}


// ---------------------------------------------------------------- v6: distinction points, badges, descriptions

/** نقاط التميز: points of approved badges plus approved special points. */
function distinction_points($userId)
{
    $a = (int) db_val(
        "SELECT COALESCE(SUM(b.points), 0) FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ? AND ub.status = 'approved'",
        'i',
        [(int) $userId]
    );
    $b = (int) db_val("SELECT COALESCE(SUM(points), 0) FROM member_points WHERE user_id = ? AND status = 'approved'", 'i', [(int) $userId]);
    return $a + $b;
}

/**
 * May the current member approve something that $grantedBy proposed in $forumId?
 * Admins always; forum staff (moderator or supervisor) yes, but never their own proposal.
 */
function can_approve($forumId, $grantedBy)
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    if ($forumId === null || (int) $forumId <= 0) {
        return false;
    }
    return forum_level((int) $forumId) >= 1 && (int) $grantedBy !== (int) $u['id'];
}

/** Labels of the definition states (badges and descriptions). */
function def_states()
{
    return ['design' => 'تحت التصميم', 'pending' => 'تنتظر الموافقة', 'open' => 'مفتوحة', 'locked' => 'مقفولة'];
}

function title_scopes()
{
    return ['normal' => 'عادي في منتداه فقط', 'important' => 'مهم في منتداه فقط', 'all' => 'يظهر في جميع المنتديات'];
}

function social_statuses()
{
    return ['أعزب', 'متزوج', 'مطلق', 'أرمل'];
}

/** Forums where the visitor may create badges and descriptions: supervised ones (admins: all). [id => name] */
function supervised_forums()
{
    $u = current_user();
    if ($u === null) {
        return [];
    }
    $rows = db_all('SELECT id, category, name FROM forums ORDER BY sort_order, id');
    $out = [];
    foreach ($rows as $r) {
        if (forum_level((int) $r['id']) >= 1) { // supervisor, deputy moderator, moderator, administrator
            $out[(int) $r['id']] = $r['category'] . ' ‹ ' . $r['name'];
        }
    }
    return $out;
}

/** Forums where the visitor is any kind of staff: [id => name]. */
function staffed_forums()
{
    $out = [];
    foreach (db_all('SELECT id, category, name FROM forums ORDER BY sort_order, id') as $r) {
        if (forum_level((int) $r['id']) >= 1) {
            $out[(int) $r['id']] = $r['category'] . ' ‹ ' . $r['name'];
        }
    }
    return $out;
}


// ---------------------------------------------------------------- v7: roles line, blocking

/** [forum id => forum name], loaded once per request. */
function forum_names()
{
    static $names = null;
    if ($names === null) {
        $names = [];
        foreach (db_all('SELECT id, name FROM forums') as $r) {
            $names[(int) $r['id']] = $r['name'];
        }
    }
    return $names;
}

/**
 * Staff roles of a member as [level, forum name] pairs, supervisors first.
 * Admins get one entry with level 3. Used for the "مشرف Android" lines in the author box and on profiles.
 */
function member_roles($userId)
{
    $m = staff_map();
    $names = forum_names();
    $cats = [];
    foreach (db_all('SELECT id, category FROM forums') as $r) {
        $cats[(int) $r['id']] = $r['category'];
    }
    $userId = (int) $userId;
    $out = [];
    if (isset($m['admins'][$userId])) {
        $out[] = [4, user_is_deputy($userId) ? 'نائب المدير' : 'مدير'];
    }
    $seen = [];
    foreach ($m['forum'] as $fid => $members) {
        $fid = (int) $fid;
        if (!isset($members[$userId]) || !isset($names[$fid])) {
            continue;
        }
        $out[] = [2, 'مشرف ' . $names[$fid]];
    }
    foreach ($out as $k => $o) {
        $out[$k][1] = fem_text($o[1], $userId);
    }
    usort($out, function ($a, $b) {
        return $a[0] === $b[0] ? strcmp($a[1], $b[1]) : $b[0] - $a[0];
    });
    return $out;
}

/** Has $blockerId stopped $blockedId from messaging them? */
function is_blocked_by($blockerId, $blockedId)
{
    // administrators can always write to anyone
    if ((int) db_val("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'admin'", 'i', [(int) $blockedId]) > 0) {
        return false;
    }
    return db_val('SELECT 1 FROM user_blocks WHERE user_id = ? AND blocked_id = ?', 'ii', [(int) $blockerId, (int) $blockedId]) !== null;
}

/** Elegant "log in to take part" card shown to visitors instead of action buttons. */
function guest_cta($nextPath, $text = 'للمشاركة وكتابة المواضيع والردود يلزمك حساب في المنتدى.')
{
    ob_start(); ?>
<aside class="guest-cta" role="note">
    <span class="gc-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg></span>
    <div class="gc-text"><strong>أهلًا بك زائرنا الكريم</strong><p><?= e($text) ?></p></div>
    <div class="gc-actions">
        <a class="btn btn-primary" href="<?= e(url('login.php?next=' . rawurlencode($nextPath))) ?>">تسجيل الدخول</a>
        <a class="btn" href="<?= e(url('register.php')) ?>">عضوية جديدة</a>
    </div>
</aside>
<?php
    return ob_get_clean();
}


/** Validate, square-crop, resize (256px) and re-encode a member avatar. Returns file name, '' when none sent, null + $err on failure. */
function save_avatar($file, &$err)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $err = 'تعذر رفع الصورة.';
        return null;
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
        $err = 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = class_exists('finfo') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
    $types = ['image/png' => IMAGETYPE_PNG, 'image/jpeg' => IMAGETYPE_JPEG, 'image/gif' => IMAGETYPE_GIF];
    if (!$info || !isset($types[$mime]) || $info[2] !== $types[$mime] || $info[0] > 4000 || $info[1] > 4000 || $info[0] < 32 || $info[1] < 32 || !function_exists('imagecreatefromstring')) {
        $err = 'صورة غير صالحة. المسموح: PNG أو JPG أو GIF.';
        return null;
    }
    $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$img) {
        $err = 'تعذرت معالجة الصورة.';
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $side = min($w, $h);
    $out = imagecreatetruecolor(256, 256);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopyresampled($out, $img, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
    $dir = __DIR__ . '/../assets/images/avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $err = 'تعذر إنشاء مجلد الصور.';
        return null;
    }
    if (!is_file($dir . '/.htaccess')) {
        @copy(__DIR__ . '/../assets/images/.htaccess', $dir . '/.htaccess');
    }
    $name = bin2hex(random_bytes(12)) . '.png';   // random name; re-encoded pixels only, so no hidden payload survives
    if (!imagepng($out, $dir . '/' . $name)) {
        $err = 'فشل حفظ الصورة.';
        return null;
    }
    return $name;
}

function delete_avatar($name)
{
    if (is_string($name) && preg_match('/^[a-f0-9]{24}\.png$/', $name)) {
        @unlink(__DIR__ . '/../assets/images/avatars/' . $name);
    }
}

function avatar_html($avatar, $class = 'avatar', $size = 64)
{
    $src = (is_string($avatar) && preg_match('/^[a-f0-9]{24}\.png$/', $avatar)) ? 'assets/images/avatars/' . $avatar : 'assets/images/default_avatar.svg';
    return '<img class="' . e($class) . '" src="' . e(url($src)) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy">';
}


/** avatar + live-messenger flag of the signed-in member (one query per request). */
function user_extras()
{
    static $x = null;
    $u = current_user();
    if ($u === null) {
        return ['avatar' => '', 'live_msgs' => 0];
    }
    if ($x === null) {
        $x = db_row('SELECT avatar, live_msgs FROM users WHERE id = ?', 'i', [(int) $u['id']]);
        if (!$x) {
            $x = ['avatar' => '', 'live_msgs' => 0];
        }
    }
    return $x;
}

/** Messenger-style live chat box, printed in the footer for members who enabled it. */
function messenger_widget()
{
    $u = current_user();
    $x = user_extras();
    if ($u === null || empty($x['live_msgs'])) {
        return '';
    }
    ob_start(); ?>
<div id="messenger" class="messenger" data-api="<?= e(url('live_api.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-me="<?= (int) $u['id'] ?>" dir="rtl">
    <button type="button" class="mg-launcher" aria-label="المحادثات الفورية"><?= icon('chat') ?><b class="mg-badge" hidden>0</b></button>
    <section class="mg-box" hidden>
        <header class="mg-head">
            <button type="button" class="mg-back" aria-label="رجوع" hidden>›</button>
            <strong class="mg-title">المحادثات الفورية</strong>
            <button type="button" class="mg-close" aria-label="إغلاق">×</button>
        </header>
        <div class="mg-list"></div>
        <div class="mg-chat" hidden>
            <div class="mg-msgs"></div>
            <form class="mg-form" autocomplete="off">
                <input type="text" class="mg-input" maxlength="1000" placeholder="اكتب رسالة..." aria-label="رسالة">
                <button type="submit" class="mg-send">إرسال</button>
            </form>
        </div>
    </section>
</div>
<?php
    return ob_get_clean();
}


// ---------------------------------------------------------------- site identity settings
function site_setting($key, $default = '', $override = null)
{
    static $all = null;
    if ($all === null) {
        $all = [];
        try {
            foreach (db_all('SELECT k, v FROM site_settings') as $r) {
                $all[$r['k']] = $r['v'];
            }
        } catch (Throwable $e) {
            $all = [];
        }
    }
    if ($override !== null) {
        $all[$key] = $override;
    }
    return isset($all[$key]) ? $all[$key] : $default;
}

function site_set($key, $value)
{
    site_setting($key, '', (string) $value);
    db_exec('INSERT INTO site_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', 'ss', [$key, $value]);
}

function site_name()
{
    $n = trim((string) site_setting('site_name', ''));
    return $n !== '' ? $n : SITE_NAME;
}

function site_tagline()
{
    return trim((string) site_setting('site_tagline', ''));
}

function site_show_tagline()
{
    return site_setting('show_tagline', '1') !== '0';
}

/** Public address of the forum (no trailing slash): detected automatically from the request. */
function site_url_base()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-zA-Z0-9\.\-:\[\]]/', '', (string) $_SERVER['HTTP_HOST']) : 'localhost';
    return ($https ? 'https://' : 'http://') . $host . BASE;
}

/** SEO-ready default robots.txt (crawlers may index public content; private/account/admin pages are blocked). */
function default_robots_txt()
{
    return "# robots.txt — generated by the forum. The Sitemap line is added automatically when the sitemap is enabled.\n"
        . "User-agent: *\n"
        . "Allow: /\n"
        . "Disallow: /admin/\n"
        . "Disallow: /config/\n"
        . "Disallow: /install/\n"
        . "Disallow: /includes/\n"
        . "Disallow: /install.php\n"
        . "Disallow: /login.php\n"
        . "Disallow: /logout.php\n"
        . "Disallow: /register.php\n"
        . "Disallow: /account.php\n"
        . "Disallow: /settings.php\n"
        . "Disallow: /inbox.php\n"
        . "Disallow: /message.php\n"
        . "Disallow: /conversation.php\n"
        . "Disallow: /send_message.php\n"
        . "Disallow: /notifications.php\n"
        . "Disallow: /wallet.php\n"
        . "Disallow: /follows.php\n"
        . "Disallow: /moderation.php\n"
        . "Disallow: /member_log.php\n"
        . "Disallow: /new_thread.php\n"
        . "Disallow: /edit_post.php\n"
        . "Disallow: /report.php\n"
        . "Disallow: /search.php\n"
        . "Disallow: /online.php\n"
        . "Disallow: /*?*sort=\n"
        . "Disallow: /*?*q=\n"
        . "\n"
        . "# Aggressive SEO scrapers that add load without benefit\n"
        . "User-agent: AhrefsBot\nDisallow: /\n\n"
        . "User-agent: SemrushBot\nDisallow: /\n\n"
        . "User-agent: MJ12bot\nDisallow: /\n\n"
        . "User-agent: DotBot\nDisallow: /\n";
}

/** Remove old Sitemap lines from a robots.txt text and add the current one when the sitemap is enabled (no regex). */
function robots_with_sitemap($txt)
{
    $keep = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $txt) as $line) {
        if (stripos(ltrim($line), 'sitemap:') === 0) {
            continue;
        }
        $keep[] = $line;
    }
    $txt = rtrim(implode("\n", $keep));
    if (site_setting('sitemap_enabled', '0') === '1') {
        $txt .= "\n\nSitemap: " . site_url_base() . '/sitemap.xml';
    }
    return $txt . "\n";
}

/** Atomic file write (temp file + rename): never blocks on file locks and never leaves a half-written file. */
function write_file_atomic($path, $content)
{
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $content) === false) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0644);
    if (@rename($tmp, $path)) {
        return true;
    }
    @unlink($tmp);
    return @file_put_contents($path, $content) !== false;
}

/** The one and only robots.txt: text saved by the administrator (or the SEO default) + the live Sitemap line. */
function robots_txt_output()
{
    $t = (string) site_setting('robots_txt', '');
    if (trim($t) === '') {
        $t = default_robots_txt();
    }
    return robots_with_sitemap($t);
}

/** Kept for compatibility: robots.txt is now generated on the fly, so a leftover physical file is removed. */
function sync_robots_sitemap()
{
    $file = APP_ROOT . '/robots.txt';
    if (is_file($file)) {
        @unlink($file);
    }
    return true;
}

function site_brand_mode()
{
    $m = site_setting('brand_mode', 'both');
    return in_array($m, ['both', 'logo', 'name'], true) ? $m : 'both';
}

// ---------------------------------------------------------------- security / privacy
/** Returns an error message when the password is too weak, else null. Needs letters, digits and symbols. */
function password_policy_error($pw)
{
    if (strlen($pw) < 10) {
        return 'كلمة السر قصيرة: 10 أحرف على الأقل.';
    }
    if (strlen($pw) > 72) {
        return 'كلمة السر طويلة جدًا (72 بايت كحد أقصى).';
    }
    if (!preg_match('/\p{L}/u', $pw) || !preg_match('/\d/', $pw) || !preg_match('/[^\p{L}\d\s]/u', $pw)) {
        return 'كلمة السر يجب أن تحتوي على حروف وأرقام ورموز (مثل ! @ # $ %).';
    }
    return null;
}

/** Privacy: keep only the network part of an IP address (a.b.c.0 / first 3 IPv6 groups). */
function anon_ip($ip)
{
    if (strpos($ip, ':') !== false) {
        return implode(':', array_slice(explode(':', $ip), 0, 3)) . '::';
    }
    $p = explode('.', $ip);
    return count($p) === 4 ? $p[0] . '.' . $p[1] . '.' . $p[2] . '.0' : '';
}

/** Throttling keys are salted hashes, so raw IPs and usernames are not kept in the attempts table. */
function throttle_key($prefix, $value)
{
    return $prefix . ':' . substr(hash('sha256', $prefix . '|' . mb_strtolower($value) . '|' . __DIR__), 0, 40);
}

/** Badge image: exactly 100x100, max 50 KB, PNG/JPG/GIF; GIF kept as is (animated), others re-encoded to PNG. Returns file name, '' (none) or null + $err. */
function save_badge_image($file, &$err)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $err = 'تعذر رفع صورة الوسام.';
        return null;
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 50 * 1024) {
        $err = 'حجم صورة الوسام يجب ألا يتجاوز 50 كيلوبايت.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true)) {
        $err = 'صورة الوسام غير صالحة. المسموح: PNG أو JPG أو GIF.';
        return null;
    }
    if ($info[0] !== 100 || $info[1] !== 100) {
        $err = 'مقاس صورة الوسام يجب أن يكون 100×100 بكسل بالضبط (مقاس صورتك ' . (int) $info[0] . '×' . (int) $info[1] . ').';
        return null;
    }
    $raw = (string) file_get_contents($file['tmp_name']);
    $isGif = ($info[2] === IMAGETYPE_GIF);
    $img = null;
    if (!$isGif) {
        if (!function_exists('imagecreatefromstring')) {
            $err = 'معالجة صور PNG/JPG تحتاج مكتبة GD في الاستضافة؛ استخدم صورة GIF.';
            return null;
        }
        $img = @imagecreatefromstring($raw);
        if (!$img) {
            $err = 'تعذرت معالجة الصورة.';
            return null;
        }
    }
    $dir = __DIR__ . '/../assets/images/badges';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $err = 'تعذر إنشاء مجلد صور الأوسمة.';
        return null;
    }
    if (!is_file($dir . '/.htaccess')) {
        @copy(__DIR__ . '/../assets/images/.htaccess', $dir . '/.htaccess');
    }
    if ($isGif) {
        // keep the original file so the animation is not lost
        $name = bin2hex(random_bytes(12)) . '.gif';
        if (@file_put_contents($dir . '/' . $name, $raw) === false) {
            $err = 'فشل حفظ الصورة.';
            return null;
        }
        return $name;
    }
    $out = imagecreatetruecolor(100, 100);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopy($out, $img, 0, 0, 0, 0, 100, 100);
    $name = bin2hex(random_bytes(12)) . '.png';
    if (!imagepng($out, $dir . '/' . $name)) {
        $err = 'فشل حفظ الصورة.';
        return null;
    }
    return $name;
}

function delete_badge_image($name)
{
    if (is_string($name) && preg_match('/^[a-f0-9]{24}\.png$/', $name)) {
        @unlink(__DIR__ . '/../assets/images/badges/' . $name);
    }
}

/** Badge picture: the uploaded image when present, else the chosen Heroicon. */
function badge_icon_html($icon, $image, $class)
{
    if (is_string($image) && preg_match('/^[a-f0-9]{24}\.(png|gif)$/', $image)) {
        return '<img class="' . e($class) . ' badge-img" src="' . e(url('assets/images/badges/' . $image)) . '" alt="" width="100" height="100" loading="lazy">';
    }
    return icon($icon !== '' && $icon !== null ? $icon : 'star', $class);
}

/** Privacy switches for the profile page (real name, country, social status, birth date). Created on first use. */
function ensure_privacy_cols()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!empty($_SESSION['privacy_cols'])) {
        return;
    }
    try {
        if (db_val("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'show_real_name' LIMIT 1") === null) {
            db_exec('ALTER TABLE users ADD COLUMN show_real_name TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN show_country TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN show_social TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN show_birth TINYINT(1) NOT NULL DEFAULT 0');
        }
        $_SESSION['privacy_cols'] = 1;
    } catch (Throwable $e) {
    }
}
