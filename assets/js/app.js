/* Montadak: small behaviours, no dependencies.
   1) <form data-confirm="..."> asks before submitting (deletes and similar).
   2) Submit buttons are disabled after sending so a double click cannot post twice
      (forms marked data-ajax are skipped; they are handled by their own script).
   3) <select data-autosubmit> sends its form as soon as the choice changes.
   4) [data-print] opens the print dialog. */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.getAttribute) { return; }

        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-ajax')) { return; }

        var buttons = form.querySelectorAll('button[type="submit"]');
        setTimeout(function () {
            Array.prototype.forEach.call(buttons, function (b) { b.disabled = true; });
        }, 0);
    });

    document.addEventListener('change', function (event) {
        var el = event.target;
        if (el && el.hasAttribute && el.hasAttribute('data-autosubmit') && el.form) {
            el.form.submit();
        }
    });

    document.addEventListener('click', function (event) {
        var el = event.target && event.target.closest ? event.target.closest('[data-print]') : null;
        if (el) { window.print(); }
    });

    // Forum page "تحديث الصفحة": <div data-refresh="60"> reloads the page every N seconds (only while the tab is visible).
    var auto = document.querySelector('[data-refresh]');
    if (auto) {
        var seconds = parseInt(auto.getAttribute('data-refresh'), 10);
        if (seconds >= 15) {
            setInterval(function () {
                var typing = document.activeElement && /^(input|textarea|select)$/i.test(document.activeElement.tagName);
                if (!document.hidden && !typing) { window.location.reload(); }
            }, seconds * 1000);
        }
    }

    // Coming back with the Back button should not leave buttons disabled.
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) { return; }
        Array.prototype.forEach.call(document.querySelectorAll('button[type="submit"]:disabled'), function (b) {
            b.disabled = false;
        });
    });
})();
