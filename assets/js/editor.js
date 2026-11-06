/* Quill rich-text editor (Arabic / RTL) for textareas marked data-rich. Content is stored as "<!--rt-->" + HTML and sanitized again on the server. */
(function () {
    'use strict';
    var MARK = '<!--rt-->';
    if (typeof Quill !== 'undefined') {
        var BlockEmbed = Quill.import('blots/block/embed');
        function TableBlot() { return Reflect.construct(BlockEmbed, arguments, TableBlot); }
        TableBlot.prototype = Object.create(BlockEmbed.prototype); TableBlot.prototype.constructor = TableBlot; Object.setPrototypeOf(TableBlot, BlockEmbed);
        TableBlot.create = function (html) { var n = BlockEmbed.create.call(this); n.setAttribute('contenteditable', 'false'); n.innerHTML = html; return n; };
        TableBlot.value = function (n) { return n.innerHTML; };
        TableBlot.blotName = 'tbl'; TableBlot.tagName = 'div'; TableBlot.className = 'ql-tbl';
        Quill.register(TableBlot);
        // allow an explicit LTR direction besides RTL
        var Parchment = Quill.import('parchment');
        var Dir = new Parchment.Attributor.Class('direction', 'ql-direction', { scope: Parchment.Scope.BLOCK, whitelist: ['rtl', 'ltr'] });
        Quill.register(Dir, true);
        // Toolbar icons: Heroicons (MIT, https://heroicons.com), outline 24x24
        var ic = Quill.import('ui/icons');
        var hi = function (d) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"/></svg>'; };
        ic.bold = hi('M6.75 3.744h-.753v8.25h7.125a4.125 4.125 0 0 0 0-8.25H6.75Zm0 0v.38m0 16.122h6.747a4.5 4.5 0 0 0 0-9.001h-7.5v9h.753Zm0 0v-.37m0-15.751h6a3.75 3.75 0 1 1 0 7.5h-6m0-7.5v7.5m0 0v8.25m0-8.25h6.375a4.125 4.125 0 0 1 0 8.25H6.75m.747-15.38h4.875a3.375 3.375 0 0 1 0 6.75H7.497v-6.75Zm0 7.5h5.25a3.75 3.75 0 0 1 0 7.5h-5.25v-7.5Z');
        ic.italic = hi('M5.248 20.246H9.05m0 0h3.696m-3.696 0 5.893-16.502m0 0h-3.675m3.675 0H18.75');
        ic.underline = hi('M17.995 3.744v7.5a6 6 0 1 1-12 0v-7.5m-2.25 16.502h16.5');
        ic.strike = hi('M12 12a8.912 8.912 0 0 1-.318-.079c-1.585-.424-2.904-1.247-3.76-2.236-.873-1.009-1.265-2.19-.968-3.301.59-2.2 3.663-3.29 6.863-2.432A8.186 8.186 0 0 1 16.5 5.21M6.42 17.81c.857.99 2.176 1.812 3.761 2.237 3.2.858 6.274-.23 6.863-2.431.233-.868.044-1.779-.465-2.617M3.75 12h16.5');
        ic.color = hi('M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z');
        ic.background = hi('M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42');
        ic.list = { ordered: hi('M8.242 5.992h12m-12 6.003H20.24m-12 5.999h12M4.117 7.495v-3.75H2.99m1.125 3.75H2.99m1.125 0H5.24m-1.92 2.577a1.125 1.125 0 1 1 1.591 1.59l-1.83 1.83h2.16M2.99 15.745h1.125a1.125 1.125 0 0 1 0 2.25H3.74m0-.002h.375a1.125 1.125 0 0 1 0 2.25H2.99'), bullet: hi('M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z') };
        ic.indent = { '+1': hi('m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5'), '-1': hi('m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5') };
        var barsR = hi('M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25'), barsL = hi('M3.75 6.75h16.5M3.75 12H12m-8.25 5.25h16.5'), bars = hi('M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5');
        ic.align = { '': barsR, right: barsR, left: barsL, center: bars, justify: bars };
        ic.direction = { '': hi('M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18'), rtl: hi('M6.75 15.75 3 12m0 0 3.75-3.75M3 12h18'), ltr: hi('M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3') };
        ic.link = hi('M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244');
        ic.code = hi('M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5');
        ic['code-block'] = hi('m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z');
        ic.blockquote = hi('M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z');
        ic.image = hi('m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z');
        ic.video = hi('m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z');
        ic.table = hi('M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5');
        ic.undo = hi('M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3');
        ic.redo = hi('m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3');
        ic.clean = hi('M6 18 18 6M6 6l12 12');
    }
    function esc(t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function plainToHtml(t) {
        return t.split(/\n/).map(function (l) { return '<p>' + (l.trim() === '' ? '<br>' : esc(l)) + '</p>'; }).join('');
    }
    var CSRF = function (form) { var t = (form && form.querySelector('input[name="csrf"]')) || document.querySelector('input[name="csrf"]'); return t ? t.value : ''; };
    var UPLOAD = document.baseURI.replace(/[^\/]*([?#].*)?$/, '') + 'upload_image.php';
    function ytId(u) {
        var m = String(u).match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|v\/))([A-Za-z0-9_-]{11})/);
        return m ? m[1] : null;
    }
    function tableDialog(done, init) {
        init = init || {};
        var ov = document.createElement('div'); ov.className = 'tbl-dlg';
        ov.innerHTML = '<div class="tbl-box" dir="rtl"><h3>' + (init.edit ? 'تعديل الجدول' : 'إدراج جدول') + '</h3>' +
            '<div class="tbl-row"><label>الصفوف <input type="number" id="t-r" min="1" max="10" value="' + (init.r || 3) + '"></label><label>الأعمدة <input type="number" id="t-c" min="1" max="8" value="' + (init.c || 3) + '"></label>' +
            '<label><input type="checkbox" id="t-h"' + (init.edit && !init.header ? '' : ' checked') + '> صف عناوين</label><label><input type="checkbox" id="t-z"' + (init.edit && !init.zebra ? '' : ' checked') + '> صفوف متبادلة</label></div>' +
            '<div class="tbl-row"><label>لون العناوين <input type="color" id="t-hc" value="' + (init.hc || '#0f766e') + '"></label><label>لون نص العناوين <input type="color" id="t-ht" value="' + (init.ht || '#ffffff') + '"></label>' +
            '<label>لون الإطار <input type="color" id="t-bc" value="' + (init.bc || '#9fc5bb') + '"></label><label>سمك الإطار <select id="t-bw"><option>1</option><option selected>2</option><option>3</option></select></label>' +
            '<label>لون التبديل <input type="color" id="t-zc" value="' + (init.zc || '#eef6f3') + '"></label><label>المحاذاة <select id="t-al"><option value="right">يمين</option><option value="center">وسط</option><option value="left">يسار</option></select></label></div>' +
            '<div class="tbl-grid" id="t-g"></div><div class="tbl-act"><button type="button" class="btn btn-primary" id="t-ok">' + (init.edit ? 'تحديث' : 'إدراج') + '</button> <button type="button" class="btn" id="t-no">إلغاء</button></div></div>';
        document.body.appendChild(ov);
        var $ = function (id) { return ov.querySelector('#' + id); };
        function grid() {
            var r = Math.max(1, Math.min(10, +$('t-r').value || 1)), c = Math.max(1, Math.min(8, +$('t-c').value || 1)), g = $('t-g'), old = {};
            Array.prototype.forEach.call(g.querySelectorAll('input'), function (i) { old[i.getAttribute('data-k')] = i.value; });
            g.innerHTML = ''; g.style.gridTemplateColumns = 'repeat(' + c + ',1fr)';
            for (var y = 0; y < r; y++) { for (var x = 0; x < c; x++) {
                var i = document.createElement('input'); i.type = 'text'; i.maxLength = 80; i.setAttribute('data-k', y + '-' + x); i.value = old[y + '-' + x] !== undefined ? old[y + '-' + x] : ((init.vals && init.vals[y + '-' + x]) || ''); i.placeholder = (y === 0 && $('t-h').checked) ? 'عنوان' : 'نص'; g.appendChild(i);
            } }
        }
        ['t-r', 't-c', 't-h'].forEach(function (id) { $(id).addEventListener('input', grid); $(id).addEventListener('change', grid); });
        grid();
        $('t-no').onclick = function () { document.body.removeChild(ov); };
        $('t-ok').onclick = function () {
            var r = +$('t-r').value, c = +$('t-c').value, bw = $('t-bw').value, bc = $('t-bc').value, al = $('t-al').value;
            var cellSt = 'border:' + bw + 'px solid ' + bc + ';padding:6px;text-align:' + al;
            var h = '<table style="width:100%;border:' + bw + 'px solid ' + bc + '"><tbody>';
            for (var y = 0; y < r; y++) {
                var head = y === 0 && $('t-h').checked, bg = (!head && $('t-z').checked && y % 2 === 0) ? ';background-color:' + $('t-zc').value : '';
                h += '<tr>';
                for (var x = 0; x < c; x++) {
                    var v = esc(ov.querySelector('[data-k="' + y + '-' + x + '"]').value);
                    h += head ? '<th style="' + cellSt + ';background-color:' + $('t-hc').value + ';color:' + $('t-ht').value + '">' + v + '</th>' : '<td style="' + cellSt + bg + '">' + v + '</td>';
                }
                h += '</tr>';
            }
            h += '</tbody></table>';
            document.body.removeChild(ov); done(h);
        };
    }
    function init(ta) {
        if (typeof Quill === 'undefined') { return; } // CDN blocked: the plain textarea keeps working
        var wrap = document.createElement('div');
        wrap.className = 'rich-wrap';
        var box = document.createElement('div');
        box.setAttribute('dir', 'rtl');
        wrap.appendChild(box);
        ta.parentNode.insertBefore(wrap, ta);
        ta.style.display = 'none';
        var form0 = ta.form;
        var q;
        var hist = function () { return q.history || q.getModule('history'); };
        var handlers = {
            undo: function () { hist().undo(); },
            redo: function () { hist().redo(); },
            table: function () {
                var r = q.getSelection(true);
                tableDialog(function (html) { q.insertEmbed(r.index, 'tbl', html, 'user'); q.setSelection(r.index + 1, 0); });
            },
            link: function (value) {
                if (!value) { q.format('link', false, 'user'); return; }
                var r = q.getSelection(true);
                var url = window.prompt('أدخل الرابط (http أو https):', 'https://');
                if (!url) { return; }
                if (!/^https?:\/\/[^\s<>"']+$/i.test(url)) { window.alert('الرابط يجب أن يبدأ بـ http:// أو https://'); return; }
                if (r.length > 0) { q.formatText(r.index, r.length, 'link', url, 'user'); }
                else { q.insertText(r.index, url, 'link', url, 'user'); q.setSelection(r.index + url.length, 0); }
            },
            video: function () {
                var u = window.prompt('ألصق رابط فيديو يوتيوب:');
                if (!u) { return; }
                var id = ytId(u);
                if (!id) { window.alert('الرابط ليس رابط يوتيوب صالحًا.'); return; }
                var r = q.getSelection(true);
                q.insertEmbed(r.index, 'video', 'https://www.youtube-nocookie.com/embed/' + id, 'user'); q.setSelection(r.index + 1, 0);
            },
            image: function () {
                var inp = document.createElement('input'); inp.type = 'file'; inp.accept = 'image/png,image/jpeg,image/webp';
                inp.onchange = function () {
                    var f = inp.files && inp.files[0]; if (!f) { return; }
                    if (f.size > 2 * 1024 * 1024) { window.alert('الحد الأقصى لحجم الصورة 2 ميغابايت.'); return; }
                    var fd = new FormData(); fd.append('image', f); fd.append('csrf', CSRF(form0));
                    var r = q.getSelection(true);
                    fetch(UPLOAD, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.json(); }).then(function (d) {
                        if (d.url) { q.insertEmbed(r.index, 'image', d.url, 'user'); q.setSelection(r.index + 1, 0); } else { window.alert(d.error || 'تعذر رفع الصورة.'); }
                    }).catch(function () { window.alert('تعذر رفع الصورة.'); });
                };
                inp.click();
            }
        };
        q = new Quill(box, {
            theme: 'snow',
            placeholder: 'اكتب هنا...',
            modules: { toolbar: { container: [
                [{ header: [1, 2, 3, false] }, { size: ['small', false, 'large', 'huge'] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                [{ align: [] }, { direction: 'rtl' }, { direction: 'ltr' }],
                ['blockquote', 'code', 'code-block', 'link'],
                ['image', 'video', 'table'],
                ['undo', 'redo'],
                ['clean']
            ], handlers: handlers } }
        });

        // ---- click a table to edit it; click an image to resize it
        var wrap = box.parentNode; wrap.style.position = 'relative';
        var bar = document.createElement('div'); bar.className = 'img-bar'; bar.hidden = true; wrap.appendChild(bar);
        var curImg = null;
        function rgb2hex(c) { var m = String(c).match(/\d+/g); if (!m || m.length < 3) { return null; } return '#' + [0, 1, 2].map(function (i) { return ('0' + (+m[i]).toString(16)).slice(-2); }).join(''); }
        function hideBar() { bar.hidden = true; curImg = null; }
        function setWidth(px) {
            if (!curImg) { return; }
            var blot = Quill.find(curImg); if (!blot) { return; }
            var idx = q.getIndex(blot), max = Math.max(60, q.root.clientWidth - 24);
            px = Math.max(40, Math.min(max, Math.round(px)));
            q.formatText(idx, 1, 'width', String(px), 'user');
            showBar(curImg);
        }
        function showBar(img) {
            curImg = img;
            var r = img.getBoundingClientRect(), p = wrap.getBoundingClientRect();
            bar.style.top = Math.max(0, r.top - p.top - 40) + 'px'; bar.style.left = Math.max(0, r.left - p.left) + 'px'; bar.hidden = false;
        }
        [['25%', 25], ['50%', 50], ['75%', 75], ['100%', 100]].forEach(function (b) {
            var btn = document.createElement('button'); btn.type = 'button'; btn.textContent = b[0]; btn.title = 'عرض الصورة ' + b[0];
            btn.onclick = function (e) { e.preventDefault(); setWidth(q.root.clientWidth * b[1] / 100); }; bar.appendChild(btn);
        });
        [['−', 0.85, 'تصغير'], ['+', 1.18, 'تكبير']].forEach(function (b) {
            var btn = document.createElement('button'); btn.type = 'button'; btn.textContent = b[0]; btn.title = b[2];
            btn.onclick = function (e) { e.preventDefault(); if (curImg) { setWidth(curImg.clientWidth * b[1]); } }; bar.appendChild(btn);
        });
        q.root.addEventListener('click', function (e) {
            var t = e.target;
            var tbl = t.closest ? t.closest('.ql-tbl') : null;
            if (tbl) {
                hideBar();
                var rows = tbl.querySelectorAll('tr'), vals = {}, cols = 0;
                Array.prototype.forEach.call(rows, function (tr, y) {
                    var cells = tr.children; cols = Math.max(cols, cells.length);
                    Array.prototype.forEach.call(cells, function (td, x) { vals[y + '-' + x] = td.textContent; });
                });
                var first = rows[0] && rows[0].firstElementChild, init = { edit: true, r: rows.length, c: cols, vals: vals, header: !!(first && first.tagName === 'TH') };
                if (first) { var cs = window.getComputedStyle(first); init.bc = rgb2hex(cs.borderTopColor) || undefined; if (init.header) { init.hc = rgb2hex(cs.backgroundColor) || undefined; init.ht = rgb2hex(cs.color) || undefined; } }
                if (rows[2] && rows[2].firstElementChild) { var z = window.getComputedStyle(rows[2].firstElementChild).backgroundColor; if (rgb2hex(z) && z !== 'rgba(0, 0, 0, 0)') { init.zebra = true; init.zc = rgb2hex(z); } }
                tableDialog(function (html) {
                    var blot = Quill.find(tbl); if (!blot) { return; }
                    var idx = q.getIndex(blot); q.deleteText(idx, 1, 'user'); q.insertEmbed(idx, 'tbl', html, 'user');
                }, init);
                return;
            }
            if (t.tagName === 'IMG') { showBar(t); return; }
            hideBar();
        });
        q.on('text-change', function () { if (curImg && !document.body.contains(curImg)) { hideBar(); } });

        // block pasted/dropped inline (base64) images: only uploaded images are allowed
        q.clipboard.addMatcher('IMG', function (node, delta) {
            var src = (node.getAttribute && node.getAttribute('src')) || '';
            return src.indexOf('assets/uploads/img/') !== -1 ? delta : new (Quill.import('delta'))();
        });
        var v = ta.value || '';
        if (v.indexOf(MARK) === 0) { q.clipboard.dangerouslyPasteHTML(v.slice(MARK.length)); }
        else if (v.trim() !== '') { q.clipboard.dangerouslyPasteHTML(plainToHtml(v)); }
        var form = ta.form;
        var panel = ta.closest ? ta.closest('.form-panel') : null;
        if (panel) { panel.classList.add('wide'); }
        var actions = form ? form.querySelector('.form-actions') : null;
        if (actions && !form.querySelector('.btn-preview')) {
            var pv = document.createElement('div'); pv.className = 'post-preview'; pv.hidden = true;
            wrap.parentNode.insertBefore(pv, wrap.nextSibling);
            var pb = document.createElement('button'); pb.type = 'button'; pb.className = 'btn btn-preview'; pb.textContent = 'معاينة';
            actions.insertBefore(pb, actions.firstChild);
            pb.addEventListener('click', function () {
                if (q.getLength() <= 1) { pv.hidden = true; return; }
                var tok = form.querySelector('input[name="csrf"]');
                var fd = new FormData(); fd.append('content', MARK + q.root.innerHTML); fd.append('csrf', tok ? tok.value : '');
                pb.disabled = true;
                fetch(form.getAttribute('data-preview') || (document.baseURI.replace(/[^\/]*([?#].*)?$/, '') + 'preview.php'), { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        pb.disabled = false;
                        pv.innerHTML = '<div class="pv-head">معاينة قبل النشر</div><div class="pv-body"></div>';
                        pv.querySelector('.pv-body').innerHTML = d.html || '';
                        pv.hidden = false; pv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }).catch(function () { pb.disabled = false; });
            });
        }
        if (form) {
            form.addEventListener('submit', function () {
                ta.value = q.getLength() <= 1 ? '' : MARK + q.root.innerHTML;
            });
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('textarea[data-rich]'), init);
    });
})();
