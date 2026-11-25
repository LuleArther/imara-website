/* Imara forms — shared helpers for every form on the site.
   Plain JavaScript (no jQuery needed) so it stays light on slow phones.

   Generic forms:  <form class="imara-form" data-imara-form="volunteer" action="api/submit.php">
                   ...fields...
                   </form>
                   <div data-success-for="volunteer" hidden>...thank-you...</div>
*/
(function () {
    'use strict';

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /* Phone: returns digits without country code, or null if invalid.
       Accepts "0772 123 456", "772123456", "+256 772 123456". */
    function normalisePhone(raw, code) {
        var digits = String(raw || '').replace(/[^\d+]/g, '');
        var cc = String(code || '+256').replace('+', '');
        if (digits.indexOf('+') === 0) {
            digits = digits.slice(1);
            if (digits.indexOf(cc) === 0) digits = digits.slice(cc.length);
        } else if (digits.indexOf(cc) === 0 && digits.length > 10) {
            digits = digits.slice(cc.length);
        }
        digits = digits.replace(/^0+/, '');
        if (cc === '256') return /^[347]\d{8}$/.test(digits) ? digits : null;
        return /^\d{7,12}$/.test(digits) ? digits : null;
    }

    function fieldWrap(el) {
        return el.closest('.field') || el.closest('.consent-box') || el.parentElement;
    }

    function setInvalid(el, bad) {
        var w = fieldWrap(el);
        if (w) w.classList.toggle('is-invalid', !!bad);
        el.setAttribute('aria-invalid', bad ? 'true' : 'false');
    }

    function isVisible(el) {
        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length) && !el.closest('[hidden]');
    }

    /* Validate the visible, required fields inside `scope`. Returns the first invalid element or null. */
    function validate(scope) {
        var first = null;
        var seenRadio = {};
        scope.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'hidden' || el.closest('.hp-field') || !isVisible(el)) return;
            var bad = false;
            var required = el.required || el.hasAttribute('data-required');
            if (el.type === 'radio') {
                if (seenRadio[el.name]) return;
                seenRadio[el.name] = true;
                var group = scope.querySelectorAll('input[name="' + el.name + '"]');
                var needOne = Array.prototype.some.call(group, function (r) { return r.required; });
                if (needOne) {
                    bad = !Array.prototype.some.call(group, function (r) { return r.checked; });
                    var w = el.closest('.field');
                    if (w) w.classList.toggle('is-invalid', bad);
                }
                if (bad && !first) first = el;
                return;
            }
            if (el.type === 'checkbox') {
                bad = required && !el.checked;
            } else {
                var v = (el.value || '').trim();
                if (required && !v) bad = true;
                if (!bad && v && el.type === 'number') {
                    var n = Number(v);
                    bad = !Number.isFinite(n) || (el.min !== '' && n < Number(el.min)) || (el.max !== '' && n > Number(el.max)) || Math.floor(n) !== n;
                }
                if (!bad && v && el.type === 'email') bad = !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
                if (!bad && v && el.type === 'tel') {
                    var codeSel = el.parentElement.querySelector('select');
                    bad = normalisePhone(v, codeSel ? codeSel.value : '+256') === null;
                }
                if (!bad && v && el.minLength > 0 && v.length < el.minLength) bad = true;
            }
            setInvalid(el, bad);
            if (bad && !first) first = el;
        });
        return first;
    }

    function showAlert(alertEl, msg) {
        if (!alertEl) return;
        var t = alertEl.querySelector('p');
        if (t) t.innerHTML = msg;
        alertEl.classList.add('show');
        alertEl.focus({ preventScroll: true });
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideAlert(alertEl) { if (alertEl) alertEl.classList.remove('show'); }

    function prepareAntiSpam(form) {
        var started = form.querySelector('[name="started_at"]');
        if (started && !started.value) started.value = String(Math.floor(Date.now() / 1000));
        var token = form.querySelector('[name="client_token"]');
        if (token && !token.value) token.value = uuid();
    }

    function setBusy(btn, busy, busyText) {
        if (!btn) return;
        if (busy) {
            btn.dataset.label = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span>' + (busyText || 'Sending…');
            btn.disabled = true;
        } else {
            if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
            btn.disabled = false;
        }
    }

    /* Static preview hosting (GitHub Pages) has no PHP backend, so forms cannot be saved there. */
    var PREVIEW = /\.github\.io$/i.test(window.location.hostname);
    function previewMessage() {
        var phone = document.querySelector('a[href^="tel:"]');
        var p = phone ? phone.textContent.trim() : '';
        return '<strong>This is a preview of the new Imara website.</strong> Online forms will open when the site goes live on imaraafrika.org. For help now, call or WhatsApp us' + (p ? ' on <strong>' + p + '</strong>' : '') + '.';
    }

    /* POST the form as FormData; resolves with the parsed JSON (or a network-error object). */
    function send(form) {
        if (PREVIEW) {
            return Promise.resolve({ ok: false, error: 'preview', message: previewMessage() });
        }
        var data = new FormData(form);
        return fetch(form.getAttribute('action'), {
            method: 'POST',
            body: data,
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false, error: 'server' }; })
                .then(function (json) { json.status = res.status; return json; });
        }).catch(function () {
            return { ok: false, error: 'network' };
        });
    }

    /* Mark fields named in a server error response. */
    function applyServerErrors(form, errors) {
        var first = null;
        Object.keys(errors || {}).forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + name + '[]"]');
            if (!el) return;
            var w = fieldWrap(el);
            if (w) {
                w.classList.add('is-invalid');
                var err = w.querySelector('.err');
                if (err && errors[name]) err.textContent = errors[name];
            }
            if (!first) first = el;
        });
        return first;
    }

    function networkMessage() {
        var phone = document.querySelector('a[href^="tel:"]');
        var p = phone ? phone.textContent.trim() : '';
        return 'We could not send your form &mdash; the connection may be weak. Your answers are still here, so please try again. You can also call or WhatsApp us' + (p ? ' on <strong>' + p + '</strong>' : '') + '.';
    }

    function clearOnInput(form) {
        form.addEventListener('input', function (e) {
            var w = fieldWrap(e.target);
            if (w && w.classList.contains('is-invalid')) w.classList.remove('is-invalid');
        });
        form.addEventListener('change', function (e) {
            var w = e.target.closest('.field') || fieldWrap(e.target);
            if (w && w.classList.contains('is-invalid')) w.classList.remove('is-invalid');
        });
    }

    /* ---------- generic single-page forms ---------- */
    function initGeneric(form) {
        var key = form.getAttribute('data-imara-form');
        var alertEl = form.parentElement.querySelector('.form-alert');
        var success = document.querySelector('[data-success-for="' + key + '"]');
        var btn = form.querySelector('[type="submit"]');
        prepareAntiSpam(form);
        clearOnInput(form);

        // "Other" pickers: <select data-other="#wrap-id">
        form.querySelectorAll('[data-other]').forEach(function (sel) {
            var wrap = document.querySelector(sel.getAttribute('data-other'));
            var sync = function () {
                var show = sel.value === 'Other';
                if (wrap) {
                    wrap.hidden = !show;
                    var input = wrap.querySelector('input, textarea');
                    if (input) input.required = show;
                }
            };
            sel.addEventListener('change', sync);
            sync();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideAlert(alertEl);
            var bad = validate(form);
            if (bad) {
                showAlert(alertEl, 'Please check the highlighted fields.');
                bad.focus({ preventScroll: true });
                return;
            }
            setBusy(btn, true);
            send(form).then(function (res) {
                setBusy(btn, false);
                if (res.ok) {
                    form.reset();
                    form.hidden = true;
                    if (alertEl) alertEl.hidden = true;
                    if (success) {
                        success.querySelectorAll('[data-fill]').forEach(function (el) {
                            var k = el.getAttribute('data-fill');
                            if (res[k] !== undefined) el.textContent = res[k];
                        });
                        success.hidden = false;
                        success.focus({ preventScroll: true });
                        success.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    document.dispatchEvent(new CustomEvent('imara:submitted', { detail: { form: key, response: res } }));
                    return;
                }
                if (res.error === 'network') { showAlert(alertEl, networkMessage()); return; }
                if (res.errors) {
                    var f = applyServerErrors(form, res.errors);
                    showAlert(alertEl, res.message || 'Please check the highlighted fields.');
                    if (f) f.focus({ preventScroll: true });
                    return;
                }
                showAlert(alertEl, res.message || networkMessage());
            });
        });
    }

    window.ImaraForms = {
        uuid: uuid,
        normalisePhone: normalisePhone,
        validate: validate,
        setInvalid: setInvalid,
        showAlert: showAlert,
        hideAlert: hideAlert,
        prepareAntiSpam: prepareAntiSpam,
        setBusy: setBusy,
        send: send,
        applyServerErrors: applyServerErrors,
        networkMessage: networkMessage,
        clearOnInput: clearOnInput
    };

    /* Prefill a field: from the URL (?partner_type=...) or from links with data-prefill="name=value" */
    function setField(form, name, value) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) return;
        if (el.tagName === 'SELECT') {
            var ok = Array.prototype.some.call(el.options, function (o) { return o.value === value; });
            if (!ok) return;
        }
        el.value = value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('form[data-imara-form]');
        forms.forEach(initGeneric);
        var params = new URLSearchParams(window.location.search);
        forms.forEach(function (form) {
            params.forEach(function (v, k) { setField(form, k, v); });
        });
        document.querySelectorAll('[data-prefill]').forEach(function (a) {
            a.addEventListener('click', function () {
                var parts = a.getAttribute('data-prefill').split('=');
                forms.forEach(function (form) { setField(form, parts[0], parts.slice(1).join('=')); });
            });
        });
    });
})();
