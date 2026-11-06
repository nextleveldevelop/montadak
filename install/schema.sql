-- Montadak schema (used by install.php). Import into an EMPTY database only.
-- Later feature tables/columns are created and upgraded automatically by the application.
-- Requires MySQL 5.6+ / MariaDB 10.1+ with InnoDB.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(30)  NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_deputy      TINYINT(1) NOT NULL DEFAULT 0,
    avatar         VARCHAR(40)  NOT NULL DEFAULT '',
    live_msgs      TINYINT(1)   NOT NULL DEFAULT 1,
    gender         VARCHAR(6)   NOT NULL DEFAULT '',
    is_banned      TINYINT(1)   NOT NULL DEFAULT 0,
    bio            VARCHAR(500) NOT NULL DEFAULT '',
    signature      VARCHAR(2000) NOT NULL DEFAULT '',
    country        VARCHAR(40)  NOT NULL DEFAULT '',
    real_name      VARCHAR(60)  NOT NULL DEFAULT '',
    city           VARCHAR(60)  NOT NULL DEFAULT '',
    social_status  VARCHAR(30)  NOT NULL DEFAULT '',
    birth_date     DATE         NULL DEFAULT NULL,
    cv             VARCHAR(1000) NOT NULL DEFAULT '',
    education      VARCHAR(255) NOT NULL DEFAULT '',
    experience     VARCHAR(1000) NOT NULL DEFAULT '',
    interests      VARCHAR(500) NOT NULL DEFAULT '',
    is_approved TINYINT(1) NOT NULL DEFAULT 1,
    timezone       VARCHAR(64)  NOT NULL DEFAULT 'Africa/Casablanca',
    current_badge_id INT UNSIGNED NULL DEFAULT NULL,
    hide_online    TINYINT(1)   NOT NULL DEFAULT 0, -- hidden members are visible to staff only
    show_real_name TINYINT(1) NOT NULL DEFAULT 0,
    show_country TINYINT(1) NOT NULL DEFAULT 0,
    show_social TINYINT(1) NOT NULL DEFAULT 0,
    show_birth TINYINT(1) NOT NULL DEFAULT 0,
    in_forum_id    INT UNSIGNED NULL DEFAULT NULL,  -- forum the member is looking at right now
    muted_until    TIMESTAMP    NULL DEFAULT NULL,
    read_all_at    TIMESTAMP    NULL DEFAULT NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen      TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forums (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category    VARCHAR(100) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    icon        VARCHAR(20)  NOT NULL DEFAULT 'chat',
    image       VARCHAR(40)  NOT NULL DEFAULT '',
    max_threads_day SMALLINT UNSIGNED NOT NULL DEFAULT 15,   -- per member per 24 h, 0 = unlimited
    max_replies_day SMALLINT UNSIGNED NOT NULL DEFAULT 1000, -- per member per 24 h, 0 = unlimited
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_forums_order (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forum staff: "supervisor" (مشرف, red) and "moderator" (مراقب, yellow).
CREATE TABLE forum_staff (
    forum_id INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    level    ENUM('supervisor','deputy_moderator','moderator') NOT NULL DEFAULT 'supervisor',
    PRIMARY KEY (forum_id, user_id),
    CONSTRAINT fk_staff_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category_staff (
    category VARCHAR(100) NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    level    ENUM('deputy_moderator','moderator') NOT NULL DEFAULT 'moderator',
    PRIMARY KEY (category, user_id),
    CONSTRAINT fk_catstaff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE threads (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    forum_id          INT UNSIGNED NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    title             VARCHAR(200) NOT NULL,
    content           MEDIUMTEXT   NOT NULL,
    is_pinned         TINYINT(1)   NOT NULL DEFAULT 0,
    is_locked         TINYINT(1)   NOT NULL DEFAULT 0,
    is_hidden         TINYINT(1)   NOT NULL DEFAULT 0,
    is_archived       TINYINT(1)   NOT NULL DEFAULT 0,
    link_level        TINYINT(1)   NOT NULL DEFAULT 0, -- 0 none, 1 normal link, 2 main link (وصلات المنتدى)
    feature_level     TINYINT(1)   NOT NULL DEFAULT 0, -- 0 none, 1 featured in forum, 2 featured everywhere (متميز)
    archive_mode      TINYINT(1)   NOT NULL DEFAULT 0, -- 0 can be archived, 1 never, 2 urgent archive request
    like_count        INT UNSIGNED NOT NULL DEFAULT 0,
    views             INT UNSIGNED NOT NULL DEFAULT 0,
    reply_count       INT UNSIGNED NOT NULL DEFAULT 0,
    last_post_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_post_user_id INT UNSIGNED NULL DEFAULT NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_threads_forum_activity (forum_id, is_pinned, last_post_at),
    KEY idx_threads_activity (last_post_at),
    KEY idx_threads_user (user_id),
    CONSTRAINT fk_threads_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE,
    CONSTRAINT fk_threads_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_threads_last  FOREIGN KEY (last_post_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE replies (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    content    MEDIUMTEXT   NOT NULL,
    like_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_replies_thread (thread_id, id),
    KEY idx_replies_user (user_id),
    CONSTRAINT fk_replies_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE,
    CONSTRAINT fk_replies_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hidden topics: who (besides the author and staff) may open a hidden thread.
CREATE TABLE thread_access (
    thread_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, user_id),
    KEY idx_access_user (user_id),
    CONSTRAINT fk_access_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE,
    CONSTRAINT fk_access_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favourites (المفضلة)
CREATE TABLE favorite_threads (
    user_id    INT UNSIGNED NOT NULL,
    thread_id  INT UNSIGNED NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, thread_id),
    CONSTRAINT fk_favt_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_favt_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorite_forums (
    user_id    INT UNSIGNED NOT NULL,
    forum_id   INT UNSIGNED NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, forum_id),
    CONSTRAINT fk_favf_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_favf_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which threads a member has already read (drives the "new" markers).
CREATE TABLE thread_reads (
    user_id   INT UNSIGNED NOT NULL,
    thread_id INT UNSIGNED NOT NULL,
    read_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, thread_id),
    KEY idx_reads_thread (thread_id),
    CONSTRAINT fk_reads_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_reads_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Moderation: warnings, mutes (وضع الرقابة) and post reports.
CREATE TABLE sanctions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    by_user_id INT UNSIGNED NULL DEFAULT NULL,
    type       ENUM('warning','mute','unmute') NOT NULL,
    reason     VARCHAR(255) NOT NULL DEFAULT '',
    until      TIMESTAMP    NULL DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sanctions_user (user_id, id),
    CONSTRAINT fk_sanc_user FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_sanc_by   FOREIGN KEY (by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id   INT UNSIGNED NOT NULL,
    reply_id    INT UNSIGNED NULL DEFAULT NULL,
    reporter_id INT UNSIGNED NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    handled_by  INT UNSIGNED NULL DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reports_status (status, id),
    CONSTRAINT fk_rep_thread   FOREIGN KEY (thread_id)   REFERENCES threads (id) ON DELETE CASCADE,
    CONSTRAINT fk_rep_reporter FOREIGN KEY (reporter_id) REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live discussion (نقاش حي)
CREATE TABLE chat_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    body       VARCHAR(500) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id        INT UNSIGNED NOT NULL,
    receiver_id      INT UNSIGNED NOT NULL,
    subject          VARCHAR(150) NOT NULL,
    body             MEDIUMTEXT   NOT NULL,
    is_read          TINYINT(1)   NOT NULL DEFAULT 0,
    sender_deleted   TINYINT(1)   NOT NULL DEFAULT 0,
    receiver_deleted TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_receiver (receiver_id, receiver_deleted, id),
    KEY idx_messages_sender (sender_id, sender_deleted, id),
    CONSTRAINT fk_messages_sender   FOREIGN KEY (sender_id)   REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip           VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guests currently browsing (hashed session id), for the "visitors" counter.
CREATE TABLE visitors (
    sid       CHAR(32)  NOT NULL,
    last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sid),
    KEY idx_visitors_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Polls (الاستفتاء): at most one per thread.
CREATE TABLE polls (
    thread_id  INT UNSIGNED NOT NULL,
    question   VARCHAR(200) NOT NULL,
    multiple   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id),
    CONSTRAINT fk_polls_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE poll_options (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id  INT UNSIGNED NOT NULL,
    label      VARCHAR(150) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_poll_options_thread (thread_id, sort_order),
    CONSTRAINT fk_popt_poll FOREIGN KEY (thread_id) REFERENCES polls (thread_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE poll_votes (
    option_id INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    thread_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (option_id, user_id),
    KEY idx_poll_votes_user (thread_id, user_id),
    CONSTRAINT fk_pvote_opt  FOREIGN KEY (option_id) REFERENCES poll_options (id) ON DELETE CASCADE,
    CONSTRAINT fk_pvote_user FOREIGN KEY (user_id)   REFERENCES users (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Badges (الأوسمة): admin creates them, supervisors/admins grant them, members choose which one to show.
CREATE TABLE badges (
    image VARCHAR(40) NOT NULL DEFAULT '',
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(60)  NOT NULL,
    icon           VARCHAR(20)  NOT NULL DEFAULT 'star',
    forum_id       INT UNSIGNED NULL DEFAULT NULL,           -- NULL = every forum (admin only)
    points         SMALLINT UNSIGNED NOT NULL DEFAULT 0,      -- distinction points (نقاط التميز)
    needs_approval TINYINT(1)   NOT NULL DEFAULT 0,           -- a grant must be approved by another staff member
    display_days   SMALLINT UNSIGNED NOT NULL DEFAULT 0,      -- how long it is shown; 0 = permanent
    status         ENUM('design','pending','open','locked') NOT NULL DEFAULT 'open',
    created_by     INT UNSIGNED NULL DEFAULT NULL,
    description    VARCHAR(255) NOT NULL DEFAULT '',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_badges_name (name),
    KEY idx_badges_forum (forum_id, status),
    CONSTRAINT fk_badges_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_badges (
    user_id     INT UNSIGNED NOT NULL,
    badge_id    INT UNSIGNED NOT NULL,
    granted_by  INT UNSIGNED NULL DEFAULT NULL,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    approved_by INT UNSIGNED NULL DEFAULT NULL,
    reason      VARCHAR(255) NOT NULL DEFAULT '',
    expires_at  TIMESTAMP    NULL DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_id),
    KEY idx_ub_badge (badge_id),
    KEY idx_ub_status (status, created_at),
    CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id) REFERENCES badges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Special points (نقاط خاصة): extra distinction points a staff member gives, approved by another staff member.
CREATE TABLE member_points (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    forum_id    INT UNSIGNED NULL DEFAULT NULL,
    points      SMALLINT     NOT NULL,
    reason      VARCHAR(255) NOT NULL DEFAULT '',
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    granted_by  INT UNSIGNED NULL DEFAULT NULL,
    approved_by INT UNSIGNED NULL DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_points_user (user_id, status),
    KEY idx_points_status (status, id),
    CONSTRAINT fk_points_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_points_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Descriptions (الأوصاف): titles such as "خبير في أجهزة Android", created per forum and given to members.
CREATE TABLE member_titles (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    forum_id   INT UNSIGNED NOT NULL,
    text       VARCHAR(100) NOT NULL,
    scope      ENUM('normal','important','all') NOT NULL DEFAULT 'normal', -- shown in its forum / important in its forum / shown in every forum
    status     ENUM('design','pending','open') NOT NULL DEFAULT 'design',
    is_locked  TINYINT(1)   NOT NULL DEFAULT 0,
    max_uses   SMALLINT UNSIGNED NOT NULL DEFAULT 0,                       -- 0 = unlimited members
    created_by INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_titles_forum (forum_id, status),
    CONSTRAINT fk_titles_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_titles (
    user_id    INT UNSIGNED NOT NULL,
    title_id   INT UNSIGNED NOT NULL,
    granted_by INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, title_id),
    KEY idx_ut_title (title_id),
    CONSTRAINT fk_ut_user  FOREIGN KEY (user_id)  REFERENCES users (id)         ON DELETE CASCADE,
    CONSTRAINT fk_ut_title FOREIGN KEY (title_id) REFERENCES member_titles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personal member lists (قوائم الأعضاء الخاصة بك)
CREATE TABLE member_lists (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(40)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lists_user (user_id),
    CONSTRAINT fk_lists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_list_items (
    list_id   INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (list_id, member_id),
    CONSTRAINT fk_li_list   FOREIGN KEY (list_id)   REFERENCES member_lists (id) ON DELETE CASCADE,
    CONSTRAINT fk_li_member FOREIGN KEY (member_id) REFERENCES users (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forum mail (بريد المنتدى): members write to the staff of a forum, staff share the inbox.
CREATE TABLE forum_mail (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    forum_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    body       MEDIUMTEXT   NOT NULL,
    status     ENUM('open','done') NOT NULL DEFAULT 'open',
    handled_by INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fmail_forum (forum_id, status, id),
    CONSTRAINT fk_fmail_forum FOREIGN KEY (forum_id) REFERENCES forums (id) ON DELETE CASCADE,
    CONSTRAINT fk_fmail_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Account activity shown to the member: successful logins and failed attempts on their account.
CREATE TABLE login_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    success    TINYINT(1)   NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    agent      VARCHAR(160) NOT NULL DEFAULT '',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loginlog_user (user_id, id),
    CONSTRAINT fk_loginlog_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Username change requests (طلب تغيير إسم العضوية), approved by an admin.
CREATE TABLE rename_requests (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    new_name   VARCHAR(30)  NOT NULL,
    reason     VARCHAR(255) NOT NULL DEFAULT '',
    old_name   VARCHAR(30)  NULL DEFAULT NULL,
    status     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    handled_by INT UNSIGNED NULL DEFAULT NULL,
    handled_at TIMESTAMP    NULL DEFAULT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rename_status (status, id),
    CONSTRAINT fk_rename_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Likes (الإعجاب) on threads (reply_id = 0) and on replies.
CREATE TABLE post_likes (
    user_id    INT UNSIGNED NOT NULL,
    thread_id  INT UNSIGNED NOT NULL,
    reply_id   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, thread_id, reply_id),
    KEY idx_likes_target (thread_id, reply_id),
    CONSTRAINT fk_likes_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_likes_thread FOREIGN KEY (thread_id) REFERENCES threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members you stopped from messaging you (منع العضو من مراسلتك). Staff can still write to you.
CREATE TABLE user_blocks (
    user_id    INT UNSIGNED NOT NULL,
    blocked_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, blocked_id),
    KEY idx_blocks_blocked (blocked_id),
    CONSTRAINT fk_blocks_user    FOREIGN KEY (user_id)    REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE site_settings (
    k VARCHAR(40) NOT NULL PRIMARY KEY,
    v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
