<?php
/** صفحة العضو: بطاقة عضو بتصميم جديد (غلاف + صورة + إحصائيات + نبذة + آخر المواضيع). */
require __DIR__ . '/includes/bootstrap.php';

ensure_privacy_cols();
$profileId = get_int('id');
$profile = db_row(
    'SELECT id, username, role, avatar, bio, country, real_name, city, social_status, birth_date,
            hide_online, gender, is_banned, is_deputy, created_at, last_seen, profile_bg, show_real_name, show_country, show_social, show_birth,
            (SELECT COUNT(*) FROM threads WHERE user_id = users.id) AS thread_count,
            (SELECT COUNT(*) FROM replies WHERE user_id = users.id) AS reply_count
       FROM users WHERE id = ?',
    'i',
    [$profileId]
);
if (!$profile) {
    fail_page('هذا العضو غير موجود.', 404, 'العضو غير موجود');
}

$me = current_user();
$isSelf = $me !== null && (int) $me['id'] === (int) $profile['id'];
$pid = (int) $profile['id'];
$postCount = (int) $profile['thread_count'] + (int) $profile['reply_count'];
list($pStars, $pRank) = rank_info($postCount);

$miniPage = max(1, get_int('page', 1));
$miniTotal = (int) db_val('SELECT COUNT(*) FROM user_posts WHERE user_id = ?', 'i', [$pid]);
$miniPages = max(1, (int) ceil($miniTotal / 10));
$miniPage = min($miniPage, $miniPages);
$miniRows = db_all('SELECT p.id, p.user_id, p.title, p.tag, p.content, p.created_at, u.username FROM user_posts p JOIN users u ON u.id = p.user_id WHERE p.user_id = ? ORDER BY p.id DESC LIMIT 10 OFFSET ' . (($miniPage - 1) * 10), 'i', [$pid]);
$recent = db_all(
    'SELECT t.id, t.title, t.created_at, f.name AS forum_name
       FROM threads t JOIN forums f ON f.id = t.forum_id
      WHERE t.user_id = ? AND t.is_hidden = 0 AND t.is_archived = 0
      ORDER BY t.id DESC LIMIT 8',
    'i',
    [$pid]
);

$seenRecently = $profile['last_seen'] !== null
    && (time() - strtotime($profile['last_seen'] . ' UTC')) < ONLINE_WINDOW_MINUTES * 60;
$isHiddenOnline = $seenRecently && (int) $profile['hide_online'] === 1;
$canSeePresence = presence_visible($pid, $profile['role'], $profile['hide_online']);
$isOnline = $seenRecently && $canSeePresence;
$iBlocked = ($me !== null && !$isSelf) ? is_blocked_by($me['id'], $pid) : false;
$roles = member_roles($pid);

$vis = function ($flag) use ($isSelf, $profile) { return $isSelf || (int) $profile[$flag] === 1; };
$details = [];
if ($profile['gender'] === 'male' || $profile['gender'] === 'female') { $details[] = ['الجنس', $profile['gender'] === 'male' ? 'ذكر' : 'أنثى']; }
if ($vis('show_real_name')) { $details[] = ['الاسم', $profile['real_name']]; }
$details[] = ['المدينة', $profile['city']];
if ($vis('show_country')) { $details[] = ['الدولة', $profile['country']]; }
if ($vis('show_social')) { $details[] = ['الحالة الاجتماعية', $profile['social_status']]; }
if ($vis('show_birth')) { $details[] = ['تاريخ الميلاد', $profile['birth_date'] !== null ? str_replace('-', '/', $profile['birth_date']) : '']; }

page_header($profile['username']);
?>
<article class="mc">
    <?php $bg = preg_match('/^[a-f0-9]{24}\.jpg$/', (string) $profile['profile_bg']) ? $profile['profile_bg'] : ''; ?>
    <div class="mc-cover" aria-hidden="true"<?= $bg !== '' ? ' style="background-image:url(\'' . e(url('assets/images/backgrounds/' . $bg)) . '\')"' : '' ?>></div>
    <header class="mc-head">
        <div class="mc-avatar<?= $isOnline ? ' is-on' : '' ?>"><?= avatar_html($profile['avatar'], 'mc-avatar-img', 120) ?><i title="<?= e(fem_text($isOnline ? 'متصل الآن' : 'غير متصل', $pid)) ?>"></i></div>
        <div class="mc-id">
            <h1 class="mc-name"><?= e($profile['username']) ?></h1>
            <div class="mc-chips">
                <?php foreach ($roles as $ro): ?><span class="mc-chip mc-chip-<?= e(level_class($ro[0])) ?>"><?= e($ro[1]) ?></span><?php endforeach; ?>
                <?php if (!$roles): ?><span class="mc-chip"><?= e(fem_text($pRank, $pid)) ?></span><?php endif; ?>
            </div>
            <?= rating_html($pid) ?>
        </div>
        <div class="mc-actions">
            <?php if ($isSelf): ?>
                <a class="mc-btn mc-btn-main" href="<?= e(url('account.php?do=profile')) ?>" title="تعديل بياناتي"><?= icon('pencil') ?><span>تعديل</span></a>
                <a class="mc-btn" href="<?= e(url('account.php?do=avatar')) ?>" title="تغيير الصورة"><?= icon('card') ?></a>
                <a class="mc-btn" href="<?= e(url('account.php?do=bg')) ?>" title="تغيير صورة خلفية صفحتي"><?= icon('image') ?></a>
                <?php if (!is_admin()): ?><a class="mc-btn" href="<?= e(url('lock_request.php')) ?>" title="طلب قفل عضويتي"><?= icon('lock') ?></a><?php endif; ?>
            <?php elseif ($me !== null): ?>
                <a class="mc-btn mc-btn-main" href="<?= e(url('send_message.php?to=' . rawurlencode($profile['username']))) ?>" title="رسالة"><?= icon('mail') ?><span>رسالة</span></a>
                <button type="button" class="mc-btn pp-livechat" data-live-open="<?= (int) $pid ?>" data-name="<?= e($profile['username']) ?>" title="محادثة فورية"><?= icon('chat') ?></button>
                <?= follow_button('user', $pid, 'mc-btn') ?>
                <form method="post" action="<?= e(url('block.php')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $pid ?>">
                    <input type="hidden" name="action" value="<?= $iBlocked ? 'unblock' : 'block' ?>">
                    <input type="hidden" name="next" value="<?= e(safe_next(current_app_path())) ?>">
                    <button type="submit" class="mc-btn" title="<?= $iBlocked ? 'السماح له بمراسلتك' : 'منع العضو من مراسلتك' ?>"><?= icon($iBlocked ? 'unlock' : 'lock') ?></button>
                </form>
            <?php endif; ?>
            <a class="mc-btn" href="<?= e(url('search.php?author=' . rawurlencode($profile['username']))) ?>" title="مواضيع العضو"><?= icon('search') ?></a>
        </div>
    </header>


    <?php if (!$isSelf && $me !== null && (is_admin() || supervised_forums())): ?>
    <?php $tIsAdm = $profile['role'] === 'admin'; $back = safe_next(current_app_path());
    $adm = function ($act, $ico, $title, $confirm) use ($pid, $back) {
        return '<form method="post" action="' . e(url('member_admin.php')) . '" class="inline-form" onsubmit="return confirm(\'' . $confirm . '\')">' . csrf_field()
            . '<input type="hidden" name="user_id" value="' . $pid . '"><input type="hidden" name="back" value="' . e($back) . '"><input type="hidden" name="action" value="' . $act . '">'
            . '<button type="submit" class="mc-btn" title="' . e($title) . '" aria-label="' . e($title) . '">' . icon($ico) . '</button></form>';
    }; ?>
    <div class="mc-staff" role="toolbar" aria-label="أدوات الإشراف">
        <span><?= icon('shield') ?> الإشراف:</span>
        <a class="mc-btn" href="<?= e(url('member_log.php?id=' . $pid)) ?>" title="سجل العقوبات (الحالية والسابقة) والكتم والتحذير"><?= icon('clock') ?></a>
        <?php if (!$tIsAdm): ?>
            <a class="mc-btn" href="<?= e(url('member_log.php?id=' . $pid . '#nominate')) ?>" title="<?= is_admin() ? 'تعيين مشرف' : 'ترشيح للإشراف' ?>"><?= icon('users') ?></a>
            <?php if (is_admin()): ?>
                <?= $adm((int) $profile['is_banned'] === 1 ? 'unlock' : 'lock', 'lock', (int) $profile['is_banned'] === 1 ? 'إعادة فتح العضوية' : 'قفل العضوية', 'تأكيد الإجراء؟') ?>
                <?php if (is_full_admin()): ?><?= $adm('make_deputy', 'crown', 'ترقية إلى نائب مدير', 'ترقية هذا العضو إلى نائب مدير؟') ?><?php endif; ?>
            <?php else: ?>
                <a class="mc-btn" href="<?= e(url('lock_request.php?id=' . $pid)) ?>" title="طلب قفل العضوية من المدير"><?= icon('lock') ?></a>
            <?php endif; ?>
        <?php elseif (is_full_admin() && (int) $profile['is_deputy'] === 1 && $pid !== 1): ?>
            <?= $adm('remove_deputy', 'crown', 'إزالة نيابة المدير', 'إزالة نيابة المدير عن هذا العضو؟') ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <section class="mc-stats" aria-label="إحصائيات">
        <div><b><?= $postCount ?></b><span>مشاركة</span></div>
        <div><b><?= (int) $profile['thread_count'] ?></b><span>موضوع</span></div>
        <div><b><?= (int) $profile['reply_count'] ?></b><span>رد</span></div>
        <div><b><?= follower_count('user', $pid) ?></b><span>متابِع</span></div>
        <div><b><?= following_count($pid) ?></b><span>يتابع أعضاء</span></div>
        <div><b><?= following_forums_count($pid) ?></b><span>يتابع منتديات</span></div>
    </section>

    <div class="mc-grid">
        <section class="mc-card">
            <h2><?= icon('info') ?> نبذة</h2>
            <p class="mc-bio"><?= trim((string) $profile['bio']) !== '' ? nl2br(e($profile['bio'])) : '<span class="pp-none">لا توجد نبذة.</span>' ?></p>
            <dl class="mc-dl">
                <?php foreach ($details as $d): if ($d[1] === '' || $d[1] === null) { continue; } ?>
                    <div><dt><?= e($d[0]) ?></dt><dd><?= e($d[1]) ?></dd></div>
                <?php endforeach; ?>
                <div><dt>تاريخ التسجيل</dt><dd><?= e(fmt_time($profile['created_at'], 'Y/m/d')) ?></dd></div>
                <div><dt>آخر زيارة</dt><dd><?= $isHiddenOnline && !$canSeePresence ? '—' : ($isOnline ? e(fem_text('متصل الآن', $pid)) : e(fmt_when($profile['last_seen']))) ?></dd></div>
            </dl>
        </section>

        <section class="mc-card">
            <h2><?= icon('doc') ?> آخر المواضيع</h2>
            <?php if (!$recent): ?>
                <p class="pp-none">لا توجد مواضيع.</p>
            <?php else: ?>
                <ul class="mc-list">
                    <?php foreach ($recent as $t): ?>
                        <li><a href="<?= e(url('thread.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a>
                            <small><?= e($t['forum_name']) ?> · <?= e(fmt_when($t['created_at'])) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <section class="mc-mini" id="mini">
            <h2><?= icon('news') ?>منشورات قصيرة</h2>
        <?php if ($isSelf): ?>
            <?php $draft = isset($_SESSION['mini_draft']) ? $_SESSION['mini_draft'] : ['title' => '', 'tag' => '', 'content' => '']; unset($_SESSION['mini_draft']); ?>
            <details class="mini-compose" id="mini-form"<?= $draft['title'] !== '' ? ' open' : '' ?>>
                <summary><?= icon('pencil') ?> اكتب موضوعًا صغيرًا في صفحتك</summary>
                <form method="post" action="<?= e(url('mini.php')) ?>">
                    <?= csrf_field() ?>
                    <label for="mini_title">العنوان</label>
                    <input type="text" id="mini_title" name="title" maxlength="150" required value="<?= e($draft['title']) ?>">
                    <label for="mini_tag">المجال (وسم)</label>
                    <select id="mini_tag" name="tag" required>
                        <option value="">— اختر —</option>
                        <?php foreach (mini_tags() as $tg): ?><option value="<?= e($tg) ?>"<?= $draft['tag'] === $tg ? ' selected' : '' ?>><?= e($tg) ?></option><?php endforeach; ?>
                    </select>
                    <label for="mini_content">النص (أقل من <?= MINI_MAX_WORDS ?> كلمة)</label>
                    <textarea id="mini_content" name="content" rows="6" data-rich="1"><?= e($draft['content']) ?></textarea>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">نشر</button></div>
                </form>
            </details>
        <?php endif; ?>
        <?php if (!$miniRows): ?><p class="pp-none">لا توجد مواضيع بعد.</p><?php else: ?>
            <div class="mini-grid"><?php mini_cards($miniRows, false); ?></div>
            <?= pagination_html($miniPage, $miniPages, 'profile.php?id=' . $pid . '&anchor=1') ?>
        <?php endif; ?>
    </section>
</article>
<?php page_footer();
