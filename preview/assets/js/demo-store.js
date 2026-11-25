/* Imara — TEST MODE for the GitHub Pages preview only.
   GitHub Pages cannot run the PHP backend, so on the preview every form is saved
   in THIS BROWSER (localStorage) instead of the Imara server. The test dashboard
   (demo-admin/) reads the same storage. Nothing is ever sent to Imara.
   This file is added to the site only by tools/publish_preview.py. */
(function () {
    'use strict';

    var KEY = 'imara-test-data-v1';
    var PHONE = '+256 777 106608';

    function now() { return new Date().toISOString(); }
    function pad(n) { return ('000' + n).slice(-4); }

    function sample() {
        var d = function (h) { return new Date(Date.now() - h * 3600 * 1000).toISOString(); };
        var base = { gender: 'Prefer not to say', insurance: 'No', duration: '1–3 days', assistance: 'I am not sure — advise me', preferred_facility: 'Any — advise me', previous_care: '', previous_details: '', heard: 'Medical camp', consent_care: 1, consent_whatsapp: 0, location_other: '', service_other: '', guardian: null, assigned_to: '', recommended_facility: '', receiving_institution: '', referral_date: '', reviewed_by: '', follow_up_date: '', accessed_care: 'unknown', closed_reason: '', outcome: '', first_contact_at: null, linked_at: null, closed_at: null, sample: true };
        var mk = function (o) { var r = {}; Object.keys(base).forEach(function (k) { r[k] = base[k]; }); Object.keys(o).forEach(function (k) { r[k] = o[k]; }); return r; };
        return [
            mk({ id: 1, reference: 'IMARA-0001', created_at: d(50), status: 'linked', is_urgent: 0, urgency: 'This week', name: 'Akello Grace (sample)', phone: '256772000001', age: 34, is_minor: 0, gender: 'Female', location: 'Pece', service: 'General Doctor', concern: 'Fever and headache for three days', assigned_to: 'Test Admin', receiving_institution: "St. Mary's Hospital Lacor", referral_date: d(40).slice(0, 10), reviewed_by: 'Dr. Sample Reviewer', accessed_care: 'yes', outcome: 'Seen at Lacor OPD, treated for malaria.', first_contact_at: d(46), linked_at: d(20),
                events: [{ at: d(50), by: 'Website', type: 'created', note: 'Submitted online' }, { at: d(46), by: 'Test Admin', type: 'status', from: 'new', to: 'contacted', note: 'Called patient, booked at Lacor OPD.' }, { at: d(40), by: 'Test Admin', type: 'status', from: 'contacted', to: 'referred', note: '' }, { at: d(20), by: 'Test Admin', type: 'status', from: 'referred', to: 'linked', note: 'Patient confirmed she was seen.' }] }),
            mk({ id: 2, reference: 'IMARA-0002', created_at: d(26), status: 'contacted', is_urgent: 0, urgency: 'This month', name: 'Opio Peter (sample)', phone: '256701000002', age: 45, is_minor: 0, gender: 'Male', location: 'Kitgum', service: 'Eye Doctor', concern: 'Blurred vision in the right eye', first_contact_at: d(20),
                events: [{ at: d(26), by: 'Website', type: 'created', note: 'Submitted online' }, { at: d(20), by: 'Test Admin', type: 'status', from: 'new', to: 'contacted', note: 'Will refer to the eye clinic.' }] }),
            mk({ id: 3, reference: 'IMARA-0003', created_at: d(5), status: 'new', is_urgent: 1, urgency: 'Urgent — need help today', name: 'Okello John (sample)', phone: '256780000003', age: 7, is_minor: 1, location: 'Laroo', service: 'Paediatrician (Child Health)', concern: 'High fever since morning', guardian: { name: 'Aber Mary', relationship: 'Mother', phone: '256780000003' },
                events: [{ at: d(5), by: 'Website', type: 'created', note: 'Submitted online — marked URGENT by patient' }] }),
            mk({ id: 4, reference: 'IMARA-0004', created_at: d(2), status: 'new', is_urgent: 0, urgency: 'Just seeking advice', name: 'Lamunu Sarah (sample)', phone: '256752000004', age: 28, is_minor: 0, gender: 'Female', location: 'Bardege', service: 'Mental Health Support', concern: 'Trouble sleeping and feeling low', heard: 'TikTok',
                events: [{ at: d(2), by: 'Website', type: 'created', note: 'Submitted online' }] })
        ];
    }

    function load() {
        var s = null;
        try { s = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { s = null; }
        if (!s || !s.requests) {
            s = { requests: sample(), submissions: [], counters: { request: 4, VOL: 0, PTN: 0, CAMP: 0, MSG: 0, DON: 0 }, audit: [] };
            save(s);
        }
        return s;
    }
    function save(s) { try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) { /* storage full or blocked */ } }
    function reset() { localStorage.removeItem(KEY); return load(); }

    function formObject(form) {
        var o = {};
        new FormData(form).forEach(function (v, k) {
            if (k.slice(-2) === '[]') { k = k.slice(0, -2); (o[k] = o[k] || []).push(v); }
            else o[k] = v;
        });
        return o;
    }

    function e164(raw, code) {
        var F = window.ImaraForms;
        var digits = F ? F.normalisePhone(raw, code || '+256') : String(raw || '').replace(/\D/g, '');
        return digits ? String(code || '+256').replace('+', '') + digits : '';
    }

    function submitRequest(o) {
        var s = load();
        if (o.client_token) {
            var dup = s.requests.filter(function (r) { return r.client_token === o.client_token; })[0];
            if (dup) return { ok: true, demo: true, duplicate: true, reference: dup.reference, first_name: String(dup.name).split(' ')[0] };
        }
        var age = parseInt(o.age, 10) || 0;
        var minor = age < 18 ? 1 : 0;
        var urgent = String(o.urgency || '').indexOf('Urgent') === 0 ? 1 : 0;
        s.counters.request += 1;
        var id = s.counters.request;
        var r = {
            id: id, reference: 'IMARA-' + pad(id), created_at: now(), status: 'new', is_urgent: urgent, urgency: o.urgency || '',
            name: (o.full_name || '').trim(), phone: e164(o.phone, o.phone_code), concern: o.concern || '', age: age, is_minor: minor,
            gender: o.gender || '', location: o.location || '', location_other: o.location === 'Other' ? (o.location_other || '') : '',
            insurance: o.insurance || '', service: o.service || '', service_other: o.service === 'Other' ? (o.service_other || '') : '',
            duration: o.duration || '', assistance: o.assistance || '', preferred_facility: o.facility || '', previous_care: o.previous_care || '',
            previous_details: o.previous_care === 'Yes' ? (o.previous_details || '') : '', heard: o.heard || '',
            consent_care: o.consent_care === '1' ? 1 : 0, consent_whatsapp: o.consent_whatsapp === '1' ? 1 : 0,
            guardian: minor ? { name: o.guardian_name || '', relationship: o.guardian_relationship || '', phone: e164(o.guardian_phone, o.guardian_phone_code) } : null,
            client_token: o.client_token || '', assigned_to: '', recommended_facility: '', receiving_institution: '', referral_date: '', reviewed_by: '',
            follow_up_date: '', accessed_care: 'unknown', closed_reason: '', outcome: '', first_contact_at: null, linked_at: null, closed_at: null,
            events: [{ at: now(), by: 'Website', type: 'created', note: urgent ? 'Submitted online — marked URGENT by patient' : 'Submitted online' }]
        };
        s.requests.push(r);
        save(s);
        return { ok: true, demo: true, duplicate: false, reference: r.reference, first_name: r.name.split(' ')[0] || 'friend', urgent: !!urgent };
    }

    var TYPES = {
        volunteer: { prefix: 'VOL', label: 'Volunteer registration', name: function (o) { return o.full_name; }, summary: function (o) { return (o.role === 'Other' ? o.role_other : o.role) + (o.district ? ' · ' + o.district : ''); } },
        partner: { prefix: 'PTN', label: 'Partnership application', name: function (o) { return o.contact_name; }, summary: function (o) { return o.org_name + ' · ' + o.partner_type; } },
        camp: { prefix: 'CAMP', label: 'Medical camp request', name: function (o) { return o.contact_name; }, summary: function (o) { return o.org_name + ' · ' + o.district + (o.people ? ' · ~' + o.people + ' people' : ''); } },
        contact: { prefix: 'MSG', label: 'Contact message', name: function (o) { return o.name; }, summary: function (o) { return o.subject || 'General enquiry'; } },
        donation: { prefix: 'DON', label: 'Donation pledge', name: function (o) { return o.anonymous ? 'Anonymous donor' : o.donor_name; }, summary: function (o) { return 'UGX ' + Number(o.amount || 0).toLocaleString('en-US') + ' · ' + o.purpose + ' · ' + o.method + (o.anonymous ? ' · anonymous' : ''); } }
    };

    function submitForm(type, o) {
        var t = TYPES[type];
        if (!t) return { ok: false, message: 'Unknown form.' };
        if (type === 'donation' && o.amount === 'custom') o.amount = o.amount_custom;
        var s = load();
        if (o.client_token) {
            var dup = s.submissions.filter(function (x) { return x.client_token === o.client_token; })[0];
            if (dup) return { ok: true, demo: true, duplicate: true, reference: dup.reference, first_name: String(dup.name).split(' ')[0] };
        }
        ['website', 'started_at'].forEach(function (k) { delete o[k]; });
        s.counters[t.prefix] = (s.counters[t.prefix] || 0) + 1;
        var name = (t.name(o) || '').trim() || 'Someone';
        var sub = { id: s.submissions.length + 1, type: type, label: t.label, reference: t.prefix + '-' + pad(s.counters[t.prefix]), created_at: now(), status: 'new', name: name, summary: t.summary(o), payload: o, notes: '', client_token: o.client_token || '' };
        s.submissions.push(sub);
        save(s);
        return { ok: true, demo: true, duplicate: false, reference: sub.reference, first_name: name.split(' ')[0] };
    }

    /* Called by forms.js on the preview instead of posting to the server. */
    function submit(form) {
        var o = formObject(form);
        var action = form.getAttribute('action') || '';
        var res;
        if (action.indexOf('patient-request') !== -1) res = submitRequest(o);
        else res = submitForm((action.match(/type=([a-z]+)/) || [])[1], o);
        if (res.ok) setTimeout(markConfirmation, 0);
        return Promise.resolve(res);
    }

    /* ---------- visible test-mode labels ---------- */
    var NOTICE = '<strong>Test mode.</strong> This is a preview of the new Imara website. Anything you send here is saved only in this browser to test the system. It is <strong>not sent to Imara</strong> and nobody will contact you. For real help, call or WhatsApp <a href="tel:+256777106608">' + PHONE + '</a>.';

    function noticeEl(extra) {
        var d = document.createElement('div');
        d.className = 'notice danger test-mode-notice';
        d.innerHTML = '<i class="fa-solid fa-flask"></i><p>' + NOTICE + (extra || '') + '</p>';
        return d;
    }

    function markConfirmation() {
        var visible = document.querySelectorAll('#care-confirm:not([hidden]), [data-success-for]:not([hidden])');
        visible.forEach(function (box) {
            // the notice above the (now hidden) form is no longer needed: one notice, in the confirmation
            var card = box.closest('.form-card') || box.parentElement;
            card.querySelectorAll('.test-mode-notice').forEach(function (n) { if (!box.contains(n)) n.remove(); });
            if (box.querySelector('.test-mode-notice')) return;
            var n = noticeEl(' <br><a href="demo-admin/" class="test-dash-link">Open the test dashboard to see this request &rarr;</a>');
            n.style.textAlign = 'left';
            box.insertBefore(n, box.firstChild);
        });
    }

    function addBanners() {
        if (/\/demo-admin\//.test(window.location.pathname)) return; // the dashboard has its own test strip
        document.querySelectorAll('form#care-request, form[data-imara-form]').forEach(function (form) {
            if (form.parentElement.querySelector('.test-mode-notice')) return;
            form.parentElement.insertBefore(noticeEl(), form);
        });
        if (!document.querySelector('.test-ribbon')) {
            var a = document.createElement('a');
            a.className = 'test-ribbon';
            a.href = 'demo-admin/';
            a.innerHTML = '<i class="fa-solid fa-flask"></i> Preview · Test dashboard';
            document.body.appendChild(a);
        }
        var st = document.createElement('style');
        st.textContent = '.test-mode-notice{margin-bottom:22px}.test-mode-notice a{color:#7a1a12;text-decoration:underline;font-weight:700}' +
            '.test-ribbon{position:fixed;left:14px;bottom:14px;z-index:9990;background:#111;color:#fff;padding:10px 16px;font:700 13px/1 "Plus Jakarta Sans",sans-serif;letter-spacing:.03em;border:1px solid #DD5903;box-shadow:0 8px 24px rgba(0,0,0,.25)}' +
            '@media(max-width:575px){.test-ribbon{font-size:11px;padding:7px 10px;left:8px;bottom:8px}}' +
            '.test-ribbon:hover{background:#DD5903;color:#fff}.test-ribbon i{margin-right:6px;color:#DD5903}.test-ribbon:hover i{color:#fff}';
        document.head.appendChild(st);
    }

    window.ImaraDemo = { submit: submit, load: load, save: save, reset: reset, types: TYPES, phone: PHONE };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addBanners); else addBanners();
})();
