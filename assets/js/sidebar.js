/* Sidebar toggle: desktop = collapse/expand (remembered), mobile = slide-in drawer. */
(function () {
    'use strict';
    var root = document.documentElement, btn = document.getElementById('sbToggle'), ov = document.getElementById('sbOverlay');
    if (!btn) { return; }
    var mobile = function () { return window.matchMedia('(max-width: 900px)').matches; };
    btn.addEventListener('click', function () {
        if (mobile()) { root.classList.toggle('sb-open'); return; }
        var c = root.classList.toggle('sb-collapsed');
        try { localStorage.setItem('sb', c ? '0' : '1'); } catch (e) {}
    });
    ov.addEventListener('click', function () { root.classList.remove('sb-open'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { root.classList.remove('sb-open'); } });
    var up = document.createElement('div');
    up.className = 'scroll-btns';
    var chev = function (d) { return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' + d + '"/></svg>'; };
    up.innerHTML = '<button type="button" aria-label="أعلى الصفحة" data-to="top">' + chev('m4.5 15.75 7.5-7.5 7.5 7.5') + '</button><button type="button" aria-label="أسفل الصفحة" data-to="bottom">' + chev('m19.5 8.25-7.5 7.5-7.5-7.5') + '</button>';
    document.body.appendChild(up);
    up.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('button[data-to]') : null;
        if (b) { window.scrollTo({ top: b.getAttribute('data-to') === 'top' ? 0 : document.documentElement.scrollHeight, behavior: 'smooth' }); }
    });
    function check() { up.classList.toggle('show', document.documentElement.scrollHeight > window.innerHeight * 1.4); }
    window.addEventListener('load', check); window.addEventListener('resize', check); document.addEventListener('DOMContentLoaded', check);
    setInterval(check, 1500);
})();

/* Pagination for long lists: [data-paginate="N"] shows N rows per table with page buttons and "show all". */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var wrap = document.querySelector('[data-paginate]');
        if (!wrap) { return; }
        var size = parseInt(wrap.getAttribute('data-paginate'), 10) || 5;
        Array.prototype.forEach.call(wrap.querySelectorAll('table'), function (tb) {
            var rows = Array.prototype.slice.call(tb.querySelectorAll('tbody > tr'));
            if (rows.length <= size) { return; }
            var pages = Math.ceil(rows.length / size), page = 1, all = false;
            var bar = document.createElement('div'); bar.className = 'pager';
            tb.parentNode.insertBefore(bar, tb.nextSibling);
            function render() {
                rows.forEach(function (r, i) { r.hidden = !all && (i < (page - 1) * size || i >= page * size); });
                bar.innerHTML = '';
                function btn(label, fn, on, dis) {
                    var b = document.createElement('button'); b.type = 'button'; b.textContent = label;
                    if (on) { b.className = 'on'; } if (dis) { b.disabled = true; }
                    b.addEventListener('click', fn); bar.appendChild(b);
                }
                if (!all) {
                    btn('السابق', function () { page--; render(); }, false, page === 1);
                    for (var i = 1; i <= pages; i++) { (function (n) { btn(String(n), function () { page = n; render(); }, n === page); })(i); }
                    btn('التالي', function () { page++; render(); }, false, page === pages);
                    btn('عرض الكل (' + rows.length + ')', function () { all = true; render(); });
                } else {
                    btn('عرض ' + size + ' فقط', function () { all = false; page = 1; render(); });
                }
            }
            render();
        });
    });
})();

/* Floating sidebar handle (screens up to 900px) + dark mode switch. */
(function () {
    'use strict';
    var root = document.documentElement;
    document.addEventListener('DOMContentLoaded', function () {
        var fab = document.createElement('button');
        fab.type = 'button'; fab.className = 'sb-fab'; fab.setAttribute('aria-label', 'إظهار / إخفاء القائمة الجانبية');
        var menu = 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5', close = 'M6 18 18 6M6 6l12 12';
        function paint() {
            var open = root.classList.contains('sb-open');
            fab.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' + (open ? close : menu) + '"/></svg>';
            fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        fab.addEventListener('click', function () { root.classList.toggle('sb-open'); paint(); });
        new MutationObserver(paint).observe(root, { attributes: true, attributeFilter: ['class'] });
        document.body.appendChild(fab); paint();

        var t = document.getElementById('themeToggle');
        if (t) {
            t.addEventListener('click', function () {
                var d = root.classList.toggle('dark');
                try { localStorage.setItem('theme', d ? 'dark' : 'light'); } catch (e) {}
            });
        }
    });
})();

/* Staff info popover on the home page (small screens). */
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.staff-info-btn') : null;
    var all = document.querySelectorAll('.staff-pop');
    for (var i = 0; i < all.length; i++) {
        var show = btn && btn.nextElementSibling === all[i] && all[i].hidden;
        all[i].hidden = !show;
    }
    if (btn) { btn.setAttribute('aria-expanded', btn.nextElementSibling && !btn.nextElementSibling.hidden ? 'true' : 'false'); }
});

/* Instant notifications: poll every 3 s, update the bell, pop a small toast for each new one. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var bell = document.querySelector('[data-notif-api]');
        if (!bell) { return; }
        var api = bell.getAttribute('data-notif-api'), badge = bell.querySelector('.bell-badge'), last = -1, busy = false;
        var stack = document.createElement('div'); stack.className = 'toast-stack'; document.body.appendChild(stack);
        var csrf = bell.getAttribute('data-csrf'), allUrl = bell.getAttribute('data-all');
        var panel = document.createElement('div'); panel.className = 'notif-panel'; panel.hidden = true;
        panel.innerHTML = '<div class="np-head"><strong>الإشعارات</strong><button type="button" class="np-readall">تعليم الكل كمقروء</button></div><ul class="np-list"></ul><a class="np-foot" href="' + allUrl + '">عرض كل الإشعارات</a>';
        document.body.appendChild(panel);
        var npList = panel.querySelector('.np-list');
        var ICONS = { like: 'M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11 5.52.44c.5.04.7.66.32.99l-4.2 3.6 1.28 5.38a.56.56 0 0 1-.84.61L12 16.8l-4.73 2.83a.56.56 0 0 1-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 0 1 .32-.99l5.52-.44 2.13-5.11Z', reply: 'm16.86 4.49 1.69-1.69a1.87 1.87 0 1 1 2.65 2.65L10.58 16.07a4.5 4.5 0 0 1-1.9 1.13L6 18l.8-2.69a4.5 4.5 0 0 1 1.13-1.9L16.86 4.49Z', message: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.24l-9.75 6.01-9.75-6.01v-.24', follow: 'M14.86 17.08a23.85 23.85 0 0 0 5.45-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.31 6.02c1.73.64 3.56 1.09 5.45 1.31m5.72 0a24.26 24.26 0 0 1-5.72 0m5.72 0a3 3 0 1 1-5.72 0' };
        function ico(t) { return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="' + (ICONS[t] || ICONS.follow) + '"/></svg>'; }
        function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        function loadPanel() {
            fetch(api + '?a=list', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
                var l = d.list || [];
                if (!l.length) { npList.innerHTML = '<li class="np-empty">لا توجد إشعارات بعد.</li>'; return; }
                npList.innerHTML = l.map(function (n) {
                    return '<li class="' + (n.read ? '' : 'is-new') + '"><a href="' + n.url + '">' + ico(n.type) + '<span class="np-text">' + esc(n.text) + '<small>' + esc(n.when) + '</small></span></a></li>';
                }).join('');
            }).catch(function () { npList.innerHTML = '<li class="np-empty">تعذر تحميل الإشعارات.</li>'; });
        }
        bell.addEventListener('click', function (e) {
            e.preventDefault();
            panel.hidden = !panel.hidden;
            if (!panel.hidden) { loadPanel(); }
        });
        panel.querySelector('.np-readall').addEventListener('click', function () {
            var fd = new FormData(); fd.append('csrf', csrf);
            fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () { badge.hidden = true; loadPanel(); });
        });
        document.addEventListener('click', function (e) { if (!panel.hidden && !panel.contains(e.target) && !bell.contains(e.target)) { panel.hidden = true; } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { panel.hidden = true; } });
        function toast(n) {
            var a = document.createElement('a'); a.className = 'toast'; a.href = n.url; a.textContent = n.text;
            stack.appendChild(a); setTimeout(function () { if (a.parentNode) { a.parentNode.removeChild(a); } }, 8000);
        }
        function tick() {
            if (busy || document.hidden) { return; }
            busy = true;
            fetch(api + '?after=' + (last < 0 ? 0 : last), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    busy = false;
                    var first = last < 0;
                    if (typeof d.max === 'number') { last = Math.max(last, d.max); }
                    badge.textContent = d.unread > 99 ? '99+' : d.unread; badge.hidden = !(d.unread > 0);
                    if (!first) { (d.items || []).forEach(toast); if ((d.items || []).length && !panel.hidden) { loadPanel(); } }
                }).catch(function () { busy = false; });
        }
        tick(); setInterval(tick, 3000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { tick(); } });
    });
})();

/* Copy a post / a whole topic to the clipboard. */
document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-copy]') : null;
    if (!b) { return; }
    var art = document.getElementById(b.getAttribute('data-target'));
    if (!art) { return; }
    var body = art.querySelector('.post-body') || art;
    var text = (body.innerText || body.textContent || '').trim();
    if (b.getAttribute('data-copy') === 'thread') {
        var h = document.querySelector('.thread-page-title');
        text = (h ? h.textContent.trim() + '\n\n' : '') + text + '\n\n' + location.href.split('#')[0];
    }
    function done(ok) {
        var old = b.getAttribute('title'); b.classList.add(ok ? 'copied' : 'copy-failed');
        b.setAttribute('title', ok ? 'تم النسخ ✓' : 'تعذر النسخ');
        setTimeout(function () { b.classList.remove('copied', 'copy-failed'); b.setAttribute('title', old); }, 1600);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
    } else {
        var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select();
        var ok = false; try { ok = document.execCommand('copy'); } catch (x) {} document.body.removeChild(ta); done(ok);
    }
});

/* Thread options: one icon on phones opens a small panel with every option (like the bell). */
(function () {
    'use strict';
    function closeAll(except) {
        var o = document.querySelectorAll('.opts.open');
        for (var i = 0; i < o.length; i++) { if (o[i] !== except) { o[i].classList.remove('open'); o[i].querySelector('.opts-toggle').setAttribute('aria-expanded', 'false'); } }
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('.opts-toggle') : null;
        if (!t) { if (!(e.target.closest && e.target.closest('.opts-list'))) { closeAll(null); } return; }
        var o = t.parentNode, l = o.querySelector('.opts-list');
        closeAll(o);
        if (!l.getAttribute('data-ready')) {
            var items = l.querySelectorAll('.icon-btn');
            for (var i = 0; i < items.length; i++) {
                var s = document.createElement('span'); s.className = 'opt-label'; s.textContent = items[i].getAttribute('title') || ''; items[i].appendChild(s);
            }
            l.setAttribute('data-ready', '1');
        }
        var open = !o.classList.contains('open');
        o.classList.toggle('open', open);
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var r = t.getBoundingClientRect(), w = l.offsetWidth || 230, h = l.offsetHeight || 200;
            l.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
            l.style.top = Math.max(8, Math.min(r.bottom + 4, window.innerHeight - h - 8)) + 'px';
        }
    });
    window.addEventListener('scroll', function () { closeAll(null); }, { passive: true });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(null); } });
})();

/* Staff popover: fixed positioning so the last forum of a section is never clipped by the table. */
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.staff-info-btn') : null;
    if (!btn) { return; }
    var pop = btn.nextElementSibling;
    if (!pop || pop.hidden) { return; }
    var r = btn.getBoundingClientRect(), w = Math.min(230, window.innerWidth * 0.78);
    pop.style.position = 'fixed';
    pop.style.width = w + 'px';
    pop.style.insetInlineEnd = 'auto';
    pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
    var below = r.bottom + 6, h = pop.offsetHeight || 120;
    pop.style.top = (below + h > window.innerHeight - 8 ? Math.max(8, r.top - h - 6) : below) + 'px';
    pop.style.zIndex = 96;
});
window.addEventListener('scroll', function () {
    var all = document.querySelectorAll('.staff-pop');
    for (var i = 0; i < all.length; i++) { all[i].hidden = true; }
}, { passive: true });

/* Thread page: hide / show the members' info boxes and the moderator tools to read the content only. */
(function () {
    'use strict';
    var btn = document.getElementById('toggleMeta');
    if (!btn) { return; }
    var root = document.documentElement, label = btn.querySelector('span');
    function paint() { var on = root.classList.contains('hide-meta'); label.textContent = on ? 'إظهار معلومات العضوية' : 'إخفاء معلومات العضوية'; }
    try { if (localStorage.getItem('hideMeta') === '1') { root.classList.add('hide-meta'); } } catch (e) {}
    paint();
    btn.addEventListener('click', function () {
        var on = root.classList.toggle('hide-meta');
        try { localStorage.setItem('hideMeta', on ? '1' : '0'); } catch (e) {}
        paint();
    });
})();
