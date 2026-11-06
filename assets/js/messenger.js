/* Messenger-style live private chat. Polls live_api.php every few seconds; no page refresh needed. */
(function () {
    'use strict';
    var root = document.getElementById('messenger');
    if (!root) { return; }
    var api = root.getAttribute('data-api'), csrf = root.getAttribute('data-csrf');
    var $ = function (s) { return root.querySelector(s); };
    var box = $('.mg-box'), list = $('.mg-list'), chat = $('.mg-chat'), msgs = $('.mg-msgs'), form = $('.mg-form'), input = $('.mg-input');
    var title = $('.mg-title'), back = $('.mg-back'), badge = $('.mg-badge');
    var since = -1, cur = 0, after = 0, busy = false, seenUpto = 0, shown = {};

    function get(q) {
        return fetch(api + '?' + q, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); });
    }
    // turn http(s) URLs into safe clickable links (DOM based, no innerHTML)
    function linkify(el, text) {
        var re = /https?:\/\/[^\s<>"']+/gi, last = 0, mt;
        text = String(text);
        while ((mt = re.exec(text)) !== null) {
            var url = mt[0].replace(/[.,;:!?)\]]+$/, '');
            if (mt.index > last) { el.appendChild(document.createTextNode(text.slice(last, mt.index))); }
            var a = document.createElement('a'); a.href = url; a.textContent = url; a.target = '_blank'; a.rel = 'noopener noreferrer nofollow';
            el.appendChild(a);
            last = mt.index + url.length; re.lastIndex = last;
        }
        if (last < text.length) { el.appendChild(document.createTextNode(text.slice(last))); }
    }
    function setBadge(n) { badge.textContent = n > 99 ? '99+' : n; badge.hidden = !(n > 0); }
    function fmt(ts) { var d = new Date(ts * 1000); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
    function paintSeen() {
        var els = msgs.querySelectorAll('.mg-msg.mine');
        for (var i = 0; i < els.length; i++) {
            var id = parseInt(els[i].getAttribute('data-id'), 10), tk = els[i].querySelector('.mg-tick');
            if (!tk) { continue; }
            var seen = id <= seenUpto;
            tk.textContent = seen ? '\u2713\u2713' : '\u2713';
            tk.className = 'mg-tick' + (seen ? ' seen' : '');
            tk.title = seen ? 'تمت المشاهدة' : 'تم الإرسال';
        }
    }
    function addMsg(m) {
        if (shown[m.id]) { return; }
        shown[m.id] = true;
        if (m.id > after) { after = m.id; }
        if (m.mine && m.read && m.id > seenUpto) { seenUpto = m.id; }
        var d = document.createElement('div');
        d.className = 'mg-msg' + (m.mine ? ' mine' : '');
        d.setAttribute('data-id', m.id);
        var t = document.createElement('span'); linkify(t, m.text);
        var s = document.createElement('small'); s.textContent = fmt(m.ts);
        if (m.mine) { var k = document.createElement('i'); k.className = 'mg-tick'; s.appendChild(k); }
        d.appendChild(t); d.appendChild(s); msgs.appendChild(d);
        msgs.scrollTop = msgs.scrollHeight;
        paintSeen();
    }
    function setSeen(n) { n = parseInt(n, 10) || 0; if (n > seenUpto) { seenUpto = n; } paintSeen(); }
    function show(view) {
        list.hidden = view !== 'list'; chat.hidden = view !== 'chat'; back.hidden = view !== 'chat';
        if (view === 'list') { title.textContent = 'المحادثات الفورية'; cur = 0; }
    }
    function openBox() { box.hidden = false; }
    function loadList() {
        get('a=list').then(function (d) {
            list.innerHTML = '';
            if (!d.list || !d.list.length) { list.innerHTML = '<p class="mg-empty">لا توجد محادثات بعد. افتح مراسلاتك مع أي عضو واضغط «محادثة فورية».</p>'; return; }
            d.list.forEach(function (c) {
                var b = document.createElement('div'); b.className = 'mg-item'; b.setAttribute('role', 'button'); b.tabIndex = 0;
                var img = document.createElement('img'); img.src = c.avatar; img.alt = '';
                var w = document.createElement('span'); w.className = 'mg-item-text';
                var n = document.createElement('strong'); n.textContent = c.name;
                var p = document.createElement('small'); p.textContent = c.text;
                w.appendChild(n); w.appendChild(p); b.appendChild(img); b.appendChild(w);
                if (c.unread > 0) { var u = document.createElement('b'); u.className = 'mg-badge'; u.textContent = c.unread; b.appendChild(u); }
                var del = document.createElement('button'); del.type = 'button'; del.className = 'mg-del'; del.textContent = '\u2715'; del.title = 'حذف المحادثة'; del.setAttribute('aria-label', 'حذف المحادثة');
                del.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!confirm('حذف هذه المحادثة من قائمتك؟')) { return; }
                    var fd = new FormData(); fd.append('csrf', csrf); fd.append('act', 'delete'); fd.append('to', c.id);
                    fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () { loadList(); poll(true); });
                });
                b.appendChild(del);
                b.addEventListener('click', function () { openChat(c.id, c.name); });
                b.addEventListener('keydown', function (e) { if (e.key === 'Enter') { openChat(c.id, c.name); } });
                list.appendChild(b);
            });
        });
    }
    function openChat(id, name) {
        cur = id; after = 0; seenUpto = 0; shown = {}; msgs.innerHTML = ''; title.textContent = name;
        show('chat'); openBox();
        get('a=history&with=' + id).then(function (d) { (d.msgs || []).forEach(addMsg); setSeen(d.seen_upto); input.focus(); poll(true); });
    }
    function poll(force) {
        if ((busy && force !== true) || document.hidden) { return; }
        busy = true;
        get('a=poll&since=' + since + '&with=' + (box.hidden ? 0 : cur) + '&after=' + after).then(function (d) {
            busy = false;
            if (d.error) { return; }
            var first = since < 0;
            since = d.max_id; setBadge(d.unread);
            if (!box.hidden) { (d.msgs || []).forEach(addMsg); setSeen(d.seen_upto); }
            if (window.__mgExtras) { window.__mgExtras(d); }
            if (!first && d.incoming && d.incoming.length) {
                // never pop the window open: just light up the launcher; refresh the list if it is showing
                root.classList.add('has-new');
                if (!box.hidden && list.hidden === false) { loadList(); }
            }
        }).catch(function () { busy = false; });
    }
    $('.mg-launcher').addEventListener('click', function () {
        root.classList.remove('has-new');
        if (box.hidden) { openBox(); if (!cur) { show('list'); loadList(); } else { openChat(cur, title.textContent); } } else { box.hidden = true; }
    });
    $('.mg-close').addEventListener('click', function () { box.hidden = true; });
    back.addEventListener('click', function () { show('list'); loadList(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { box.hidden = true; } });
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-live-open]') : null;
        if (b) { openChat(parseInt(b.getAttribute('data-live-open'), 10), b.getAttribute('data-name') || ''); }
    });
    var err = document.createElement('div'); err.className = 'mg-err'; err.hidden = true; form.parentNode.insertBefore(err, form);
    function showErr(t) { err.textContent = t; err.hidden = false; }
    function send() {
        var text = input.value.trim();
        if (!text || !cur) { return; }
        err.hidden = true;
        var fd = new FormData(); fd.append('csrf', csrf); fd.append('to', cur); fd.append('body', text);
        input.value = '';
        var ctl = window.AbortController ? new AbortController() : null;
        var timer = setTimeout(function () { if (ctl) { ctl.abort(); } }, 12000);
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store', signal: ctl ? ctl.signal : undefined })
            .then(function (r) { return r.text().then(function (t) { var d = null; try { d = JSON.parse(t); } catch (e) {} return { status: r.status, d: d, raw: t }; }); })
            .then(function (x) {
                clearTimeout(timer);
                if (x.d && x.d.ok) { addMsg(x.d.msg); }
                else { input.value = text; showErr((x.d && x.d.error) || ('تعذر الإرسال (رمز ' + x.status + ')')); }
            })
            .catch(function () { clearTimeout(timer); input.value = text; showErr('تعذر الاتصال بالخادم، أعد المحاولة.'); })
            .then(function () { try { input.focus(); } catch (e) {} });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); send(); });

    /* --- extras: typing indicator, online dot, emoji picker, sound switch --- */
    var typingEl = document.createElement('div'); typingEl.className = 'mg-typing'; typingEl.hidden = true; typingEl.textContent = 'يكتب الآن…';
    form.parentNode.insertBefore(typingEl, err);
    var dot = document.createElement('i'); dot.className = 'mg-dot'; title.parentNode.insertBefore(dot, title);
    var lastTypingPing = 0;
    input.addEventListener('input', function () {
        var now = Date.now();
        if (!cur || now - lastTypingPing < 2500) { return; }
        lastTypingPing = now;
        var fd = new FormData(); fd.append('csrf', csrf); fd.append('act', 'typing'); fd.append('to', cur);
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
    });
    var emojis = ['😀','😂','😍','🥰','😎','🤔','😢','😡','👍','👏','🙏','💪','🎉','❤️','🔥','✅','⭐','🌹','☕','😴'];
    var eb = document.createElement('button'); eb.type = 'button'; eb.className = 'mg-emoji-btn'; eb.textContent = '😊'; eb.setAttribute('aria-label', 'رموز تعبيرية');
    var ep = document.createElement('div'); ep.className = 'mg-emoji-pop'; ep.hidden = true;
    emojis.forEach(function (em) { var b = document.createElement('button'); b.type = 'button'; b.textContent = em; b.addEventListener('click', function () { input.value += em; input.focus(); ep.hidden = true; }); ep.appendChild(b); });
    form.insertBefore(eb, form.firstChild); form.parentNode.insertBefore(ep, form);
    eb.addEventListener('click', function () { ep.hidden = !ep.hidden; });
    var sound = true; try { sound = localStorage.getItem('mgSound') !== '0'; } catch (e) {}
    var sw = document.createElement('button'); sw.type = 'button'; sw.className = 'mg-sound'; sw.title = 'صوت التنبيه';
    function paintSound() { sw.textContent = sound ? '🔔' : '🔕'; }
    paintSound(); sw.addEventListener('click', function () { sound = !sound; try { localStorage.setItem('mgSound', sound ? '1' : '0'); } catch (e) {} paintSound(); });
    var closeBtn = $('.mg-close'); if (closeBtn) { closeBtn.parentNode.insertBefore(sw, closeBtn); }
    function beep() {
        if (!sound) { return; }
        try { var C = window.AudioContext || window.webkitAudioContext, c = new C(), o = c.createOscillator(), g = c.createGain(); o.connect(g); g.connect(c.destination); o.frequency.value = 880; g.gain.value = 0.05; o.start(); setTimeout(function () { o.stop(); c.close(); }, 140); } catch (e) {}
    }
    window.__mgExtras = function (d) {
        typingEl.hidden = !(d.typing && !box.hidden);
        dot.className = 'mg-dot' + (d.online ? ' on' : '');
        dot.title = d.online ? 'متصل الآن' : 'غير متصل';
        if (d.incoming && d.incoming.length) { beep(); }
    };
    var sb = form.querySelector('.mg-send');
    if (sb) { sb.addEventListener('click', function (e) { e.preventDefault(); send(); }); }
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); send(); } });
    poll();
    setInterval(poll, 3000);
})();
