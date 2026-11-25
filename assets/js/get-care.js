/* Get Care — Imara Health Bridge Gulu patient request form (3 steps).
   Without JavaScript the whole form shows on one page and posts normally. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var F = window.ImaraForms;
        var form = document.getElementById('care-request');
        if (!form || !F) return;

        var card = document.getElementById('care-card');
        var steps = Array.prototype.slice.call(form.querySelectorAll('.form-step'));
        var indicators = card.querySelectorAll('[data-step-ind]');
        var stepper = card.querySelector('.form-steps');
        var btnBack = document.getElementById('btn-back');
        var btnNext = document.getElementById('btn-next');
        var btnSubmit = document.getElementById('btn-submit');
        var alertEl = document.getElementById('form-alert');
        var confirmEl = document.getElementById('care-confirm');
        var current = 1;

        F.prepareAntiSpam(form);
        F.clearOnInput(form);

        /* ---------- prefill from the home page quick-start bar or service links ---------- */
        var params = new URLSearchParams(window.location.search);
        ['service', 'location', 'urgency', 'facility'].forEach(function (name) {
            var v = params.get(name);
            if (!v) return;
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            if (el.type === 'radio') {
                form.querySelectorAll('[name="' + name + '"]').forEach(function (r) { r.checked = (r.value === v); });
            } else if (Array.prototype.some.call(el.options, function (o) { return o.value === v; })) {
                el.value = v;
            }
        });

        /* ---------- conditional sections ---------- */
        var age = document.getElementById('age');
        var guardianBox = document.getElementById('guardian-box');
        function syncGuardian() {
            var n = parseInt(age.value, 10);
            var child = Number.isFinite(n) && n >= 0 && n < 18;
            guardianBox.hidden = !child;
            guardianBox.querySelectorAll('[data-guardian]').forEach(function (el) { el.required = child; });
        }
        age.addEventListener('input', syncGuardian);

        function toggleOther(selectId, wrapId) {
            var sel = document.getElementById(selectId);
            var wrap = document.getElementById(wrapId);
            var sync = function () {
                var show = sel.value === 'Other';
                wrap.hidden = !show;
                wrap.querySelector('input').required = show;
            };
            sel.addEventListener('change', sync);
            sync();
        }
        toggleOther('location', 'location-other-wrap');
        toggleOther('service', 'service-other-wrap');

        var urgentWarning = document.getElementById('urgent-warning');
        form.querySelectorAll('input[name="urgency"]').forEach(function (r) {
            if (r.value.indexOf('Urgent') === 0) r.closest('.choice-pill').classList.add('urgent');
        });
        function syncUrgent() {
            var sel = form.querySelector('input[name="urgency"]:checked');
            urgentWarning.hidden = !(sel && sel.value.indexOf('Urgent') === 0);
        }
        form.addEventListener('change', function (e) { if (e.target.name === 'urgency') syncUrgent(); });

        var prevWrap = document.getElementById('previous-details-wrap');
        form.addEventListener('change', function (e) {
            if (e.target.name === 'previous_care') prevWrap.hidden = e.target.value !== 'Yes';
        });

        var concern = document.getElementById('concern');
        var count = document.getElementById('concern-count');
        function syncCount() { count.textContent = concern.value.length + ' / 600'; }
        concern.addEventListener('input', syncCount);

        syncGuardian();
        syncUrgent();
        syncCount();

        /* ---------- steps ---------- */
        stepper.hidden = false;
        btnNext.hidden = false;

        function show(n, focus) {
            current = n;
            steps.forEach(function (s) { s.hidden = Number(s.dataset.step) !== n; });
            indicators.forEach(function (li) {
                var i = Number(li.dataset.stepInd);
                li.classList.toggle('active', i === n);
                li.classList.toggle('done', i < n);
                if (i === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
            });
            btnBack.hidden = n === 1;
            btnNext.hidden = n === steps.length;
            btnSubmit.hidden = n !== steps.length;
            F.hideAlert(alertEl);
            if (focus) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var legend = steps[n - 1].querySelector('legend');
                if (legend) { legend.setAttribute('tabindex', '-1'); legend.focus({ preventScroll: true }); }
            }
        }

        btnNext.addEventListener('click', function () {
            var bad = F.validate(steps[current - 1]);
            if (bad) {
                F.showAlert(alertEl, 'Please check the highlighted fields before continuing.');
                bad.focus({ preventScroll: true });
                return;
            }
            show(current + 1, true);
        });
        btnBack.addEventListener('click', function () { show(current - 1, true); });

        // if the URL jumps here from the quick-start bar, start at step 1 with choices kept
        show(1, false);

        /* ---------- submit ---------- */
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            F.hideAlert(alertEl);
            // validate every step; jump to the first one with a problem
            for (var i = 0; i < steps.length; i++) {
                steps[i].hidden = false;
                var bad = F.validate(steps[i]);
                steps[i].hidden = (i + 1) !== current;
                if (bad) {
                    show(i + 1, true);
                    F.validate(steps[i]);
                    F.showAlert(alertEl, 'Please check the highlighted fields.');
                    return;
                }
            }

            F.setBusy(btnSubmit, true, 'Sending your request…');
            F.send(form).then(function (res) {
                F.setBusy(btnSubmit, false);
                if (res.ok) { return showConfirmation(res); }
                if (res.error === 'network') { F.showAlert(alertEl, F.networkMessage()); return; }
                if (res.errors) {
                    // find which step holds the first server error
                    var names = Object.keys(res.errors);
                    for (var s = 0; s < steps.length; s++) {
                        if (names.some(function (n) { return steps[s].querySelector('[name="' + n + '"]'); })) { show(s + 1, true); break; }
                    }
                    F.applyServerErrors(form, res.errors);
                    F.showAlert(alertEl, res.message || 'Please check the highlighted fields.');
                    return;
                }
                F.showAlert(alertEl, res.message || F.networkMessage());
            });
        });

        function showConfirmation(res) {
            confirmEl.querySelector('[data-confirm="name"]').textContent = res.first_name || 'friend';
            confirmEl.querySelector('[data-confirm="reference"]').textContent = res.reference;
            confirmEl.querySelector('[data-confirm="duplicate"]').hidden = !res.duplicate;
            var wa = confirmEl.querySelector('[data-confirm="whatsapp"]');
            var msg = 'Hello Imara, my request reference is ' + res.reference + '.';
            wa.href = wa.href.split('?')[0] + '?text=' + encodeURIComponent(msg);

            form.reset();
            form.hidden = true;
            stepper.hidden = true;
            card.querySelector('.form-card-head').hidden = true;
            confirmEl.hidden = false;
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            confirmEl.focus({ preventScroll: true });
        }
    });
})();
