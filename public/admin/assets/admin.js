/* Imara admin — small helpers (CSP allows only this file, no inline scripts). */
(function () {
    'use strict';
    var toggle = document.getElementById('menu-toggle');
    var sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
        document.addEventListener('click', function (e) {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) sidebar.classList.remove('open');
        });
    }

    // Show fields that only matter for some statuses: <div data-show-for="referred closed">
    var status = document.querySelector('select[name="status"][data-conditional]');
    if (status) {
        var sync = function () {
            document.querySelectorAll('[data-show-for]').forEach(function (el) {
                var list = el.getAttribute('data-show-for').split(' ');
                el.hidden = list.indexOf(status.value) === -1;
            });
            var help = document.getElementById('status-help');
            if (help) help.textContent = status.options[status.selectedIndex].getAttribute('data-help') || '';
        };
        status.addEventListener('change', sync);
        sync();
    }

    // Confirm dangerous buttons: <button data-confirm="Are you sure?">
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!window.confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
        });
    });

    // Auto-submit filters: <select data-autosubmit>
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    // Print button
    document.querySelectorAll('[data-print]').forEach(function (b) {
        b.addEventListener('click', function () { window.print(); });
    });
})();
