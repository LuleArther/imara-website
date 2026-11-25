/* Find a Specialist / Find a Hospital — loads the list staff manage in Admin › Directory. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-directory]');
        if (!root) return;
        var kind = root.getAttribute('data-directory');
        var list = root.querySelector('[data-dir-list]');
        var search = root.querySelector('[data-dir-search]');
        var empty = root.querySelector('[data-dir-empty]');
        var items = [];

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function card(it) {
            var icon = kind === 'facility' ? 'fa-hospital' : 'fa-user-doctor';
            var facts = '';
            if (it.location) facts += '<li><i class="fa-solid fa-location-dot"></i><span>' + esc(it.location) + '</span></li>';
            if (it.services) facts += '<li><i class="fa-solid fa-stethoscope"></i><span>' + esc(it.services) + '</span></li>';
            if (it.hours) facts += '<li><i class="fa-solid fa-clock"></i><span>' + esc(it.hours) + '</span></li>';
            if (it.phone) facts += '<li><i class="fa-solid fa-phone"></i><a href="tel:' + esc(it.phone.replace(/\s/g, '')) + '">' + esc(it.phone) + '</a></li>';
            var linkParams = kind === 'facility' ? 'facility=' + encodeURIComponent(it.name) : 'service=' + encodeURIComponent('Other');
            return '<div class="col-lg-4 col-md-6"><div class="dir-card">' +
                '<div class="dir-icon"><i class="fa-light ' + icon + '"></i></div>' +
                '<h3>' + esc(it.name) + '</h3>' +
                (it.category ? '<div class="dir-meta">' + esc(it.category) + '</div>' : '') +
                (it.description ? '<p>' + esc(it.description) + '</p>' : '<p></p>') +
                (facts ? '<ul class="dir-facts">' + facts + '</ul>' : '') +
                '<a class="rts-btn btn-secondary" href="get-care.html?' + linkParams + '#care-form">Ask Imara to link me</a>' +
                '</div></div>';
        }

        function render() {
            var q = (search && search.value || '').toLowerCase().trim();
            var shown = items.filter(function (it) {
                return !q || [it.name, it.category, it.location, it.services, it.description].join(' ').toLowerCase().indexOf(q) !== -1;
            });
            list.innerHTML = shown.map(card).join('');
            if (empty) empty.hidden = shown.length > 0;
        }

        fetch('api/directory.php?kind=' + encodeURIComponent(kind), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok) return; // keep the static fallback already in the page
                items = data.items || [];
                root.classList.add('is-live');
                render();
            })
            .catch(function () { /* static fallback stays */ });

        if (search) search.addEventListener('input', function () { if (root.classList.contains('is-live')) render(); else filterStatic(); });

        function filterStatic() {
            var q = search.value.toLowerCase().trim();
            list.querySelectorAll('.col-lg-4').forEach(function (col) {
                col.hidden = q && col.textContent.toLowerCase().indexOf(q) === -1;
            });
        }
    });
})();
