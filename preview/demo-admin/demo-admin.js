/* Imara Health Bridge — TEST DASHBOARD for the GitHub Pages preview.
   Same screens and referral rules as the real PHP dashboard (public/admin/),
   but the data lives only in this browser (see assets/js/demo-store.js). */
(function () {
    'use strict';

    var D = window.ImaraDemo;
    var app = document.getElementById('app');
    var LOGIN = { email: 'test@imaraafrika.org', password: 'imara-test' };
    var USER = 'Test Admin';
    var STAFF = ['Test Admin', 'Test Coordinator', 'Dr. Test Reviewer'];

    var STATUSES = [
        ['new', 'New request', 'Received, not yet reviewed'],
        ['under_review', 'Under review', 'Team assessing it'],
        ['contacted', 'Contacted', 'Patient reached'],
        ['referred', 'Referred', 'Connected to a healthcare provider'],
        ['linked', 'Linked to care', 'Patient confirmed they got the service'],
        ['follow_up', 'Follow-up required', 'More contact needed'],
        ['closed', 'Closed', 'Completed, or closed with a recorded reason']
    ];
    var LABEL = {}; var HELP = {};
    STATUSES.forEach(function (s) { LABEL[s[0]] = s[1]; HELP[s[0]] = s[2]; });
    var ACCESSED = { unknown: 'Not yet known', yes: 'Yes — patient received care', no: 'No — patient did not receive care' };
    var CLOSE_REASONS = ['Completed — patient received care', 'Patient could not be reached', 'Patient no longer needs help', 'Referred elsewhere / outside our area', 'Duplicate request', 'Not a request for care', 'Other'];
    var FACILITIES = ['Gulu Regional Referral Hospital', "St. Mary's Hospital Lacor", 'Gulu Independent Hospital'];
    var SUB_STATUS = { new: 'New', in_progress: 'In progress', done: 'Done', archived: 'Archived' };

    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function when(iso, short) {
        if (!iso) return '—';
        var d = new Date(iso);
        return d.toLocaleString('en-GB', short ? { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' } : { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    function phone(p) { p = String(p || ''); return p.length === 12 && p.indexOf('256') === 0 ? '+256 ' + p.slice(3, 6) + ' ' + p.slice(6, 9) + ' ' + p.slice(9) : (p ? '+' + p : ''); }
    function badge(s) { return '<span class="badge b-' + esc(s) + '">' + esc(LABEL[s] || SUB_STATUS[s] || s) + '</span>'; }
    function signedIn() { return sessionStorage.getItem('imara-test-user') === '1'; }
    function audit(action, ref) { var s = D.load(); s.audit.unshift({ at: new Date().toISOString(), by: USER, action: action, ref: ref || '' }); s.audit = s.audit.slice(0, 300); D.save(s); }

    /* ---------- page shell (same markup as app/views/admin-layout.php) ---------- */
    function shell(title, active, content) {
        var nav = [['#/requests', 'requests', 'Requests'], ['#/forms', 'forms', 'Forms inbox'], ['#/reports', 'reports', 'Reports'], ['#/audit', 'audit', 'Audit log']];
        return '<aside class="sidebar" id="sidebar"><a href="#/requests" class="brand"><span class="brand-word">IMARA</span><span class="brand-tag">Health Bridge &middot; Gulu</span></a><nav>' +
            nav.map(function (n) { return '<a href="' + n[0] + '" class="' + (active === n[1] ? 'active' : '') + '">' + n[2] + '</a>'; }).join('') +
            '</nav><div class="sidebar-foot"><a href="../index.html">&larr; Back to the website</a><button type="button" id="reset-data">Reset test data</button></div></aside>' +
            '<div class="main"><header class="topbar"><button class="menu-toggle" id="menu-toggle" aria-label="Menu">&#9776;</button><h1>' + esc(title) + '</h1>' +
            '<div class="who"><span class="who-name">' + USER + ' <small>Administrator (test)</small></span><button type="button" class="btn btn-ghost btn-sm" id="sign-out">Sign out</button></div></header>' +
            '<div class="test-strip"><strong>Test dashboard.</strong> This preview runs in your browser only: it shows requests made on <em>this device</em> plus sample data, and nothing reaches Imara. The real dashboard runs on the Imara server.</div>' +
            '<main class="content">' + flashHtml() + content + '</main><footer class="admin-foot">Imara Health Initiative AFRIKA &middot; Strong Communities. Strong Health. Strong Afrika. &middot; Test mode</footer></div>';
    }
    var flash = null;
    function flashHtml() { var f = flash; flash = null; return f ? '<div class="alert alert-' + f[0] + '" role="status">' + esc(f[1]) + '</div>' : ''; }

    /* ---------- sign in ---------- */
    function loginView(error) {
        document.body.className = 'auth-page';
        app.innerHTML = '<main class="auth-wrap"><div class="auth-card"><div class="brand"><span class="brand-word">IMARA</span><span class="brand-tag">Health Bridge &middot; Gulu</span></div>' +
            '<form id="login" autocomplete="off"><h1>Staff sign in</h1><p class="lead">Test dashboard on the preview site</p>' +
            '<div class="creds"><strong>Test login</strong><br>Email: <code>' + LOGIN.email + '</code><br>Password: <code>' + LOGIN.password + '</code></div>' +
            (error ? '<div class="alert alert-error" role="alert">' + esc(error) + '</div>' : '') +
            '<div class="field"><label class="f" for="email">Email</label><input type="email" id="email" required></div>' +
            '<div class="field"><label class="f" for="password">Password</label><input type="password" id="password" required></div>' +
            '<button type="submit" class="btn" style="width:100%">Sign in</button></form></div>' +
            '<p class="auth-note">This test dashboard only shows data saved in this browser. Real patient data lives on the Imara server and needs a real staff account.</p></main>';
        document.getElementById('login').addEventListener('submit', function (e) {
            e.preventDefault();
            var em = document.getElementById('email').value.trim().toLowerCase();
            var pw = document.getElementById('password').value;
            if (em === LOGIN.email && pw === LOGIN.password) { sessionStorage.setItem('imara-test-user', '1'); audit('login'); location.hash = '#/requests'; route(); }
            else loginView('Email or password is not correct.');
        });
    }

    /* ---------- requests list ---------- */
    var filters = { q: '', status: '', urgent: false };
    function matches(r) {
        if (filters.status === 'open' && (r.status === 'linked' || r.status === 'closed')) return false;
        if (filters.status && filters.status !== 'open' && r.status !== filters.status) return false;
        if (filters.urgent && !r.is_urgent) return false;
        var q = filters.q.trim().toLowerCase();
        if (!q) return true;
        if (/^(imara-?)?\d{1,6}$/.test(q)) return r.reference === 'IMARA-' + ('000' + q.replace(/\D/g, '')).slice(-4);
        var digits = q.replace(/\D/g, '').replace(/^0+/, '');
        if (digits.length >= 7) return String(r.phone).slice(-9) === digits.slice(-9);
        return String(r.name).toLowerCase().indexOf(q) !== -1;
    }
    function requestsView() {
        var s = D.load();
        var all = s.requests.slice().sort(function (a, b) {
            var ua = a.is_urgent && (a.status === 'new' || a.status === 'under_review') ? 1 : 0;
            var ub = b.is_urgent && (b.status === 'new' || b.status === 'under_review') ? 1 : 0;
            return ub - ua || b.id - a.id;
        });
        var rows = all.filter(matches);
        var count = function (f) { return all.filter(f).length; };
        var urgentOpen = all.filter(function (r) { return r.is_urgent && (r.status === 'new' || r.status === 'under_review'); });
        var tab = function (k, label, n) { return '<a href="#" data-status="' + k + '" class="' + (filters.status === k ? 'on' : '') + '">' + label + '<span class="n">' + n + '</span></a>'; };
        var html = '<p style="margin:-6px 0 18px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:.8rem;color:#DD5903">Imara Health Bridge — Gulu</p>';
        if (urgentOpen.length) {
            html += '<div class="urgent-banner" role="alert"><span style="font-size:1.6rem">&#9888;</span><div><strong>' + urgentOpen.length + ' urgent request' + (urgentOpen.length > 1 ? 's' : '') + ' waiting for a human.</strong> Call these patients first. If symptoms are severe, tell them to go to the nearest health facility now.<ul>' +
                urgentOpen.map(function (r) { return '<li><a href="#/request/' + r.id + '"><strong>' + esc(r.reference) + '</strong></a> — received ' + esc(when(r.created_at)) + '</li>'; }).join('') + '</ul></div></div>';
        }
        html += '<div class="stat-cards">' +
            '<div class="stat primary"><div class="label">Total patients reached</div><div class="value">' + all.length + '</div><div class="sub">All requests received online</div></div>' +
            '<a class="stat" href="#" data-status="new"><div class="label">New requests</div><div class="value">' + count(function (r) { return r.status === 'new'; }) + '</div><div class="sub">Not yet reviewed</div></a>' +
            '<a class="stat" href="#" data-status="linked"><div class="label">Successfully linked</div><div class="value">' + count(function (r) { return r.status === 'linked' || (r.status === 'closed' && r.accessed_care === 'yes'); }) + '</div><div class="sub">Patient confirmed they got care</div></a>' +
            '<a class="stat" href="#" data-status="open"><div class="label">Pending requests</div><div class="value">' + count(function (r) { return r.status !== 'linked' && r.status !== 'closed'; }) + '</div><div class="sub">Still being handled</div></a></div>';
        html += '<div class="card"><div class="card-head"><h2>Request tracking</h2></div>' +
            '<form class="toolbar" id="search"><div class="field grow"><label class="f" for="q">Search by reference, name or phone</label><input type="search" id="q" value="' + esc(filters.q) + '" placeholder="e.g. IMARA-0003, Akello, 0772000001"></div>' +
            '<label class="check" style="margin-bottom:10px"><input type="checkbox" id="urgent" ' + (filters.urgent ? 'checked' : '') + '> Urgent only</label><button class="btn" type="submit">Search</button>' +
            (filters.q || filters.status || filters.urgent ? '<a class="btn btn-ghost" href="#" id="clear">Clear</a>' : '') + '</form>' +
            '<div class="tabs">' + tab('', 'All', all.length) + tab('open', 'Open', count(function (r) { return r.status !== 'linked' && r.status !== 'closed'; })) +
            STATUSES.map(function (st) { return tab(st[0], st[1], count(function (r) { return r.status === st[0]; })); }).join('') + '</div>' +
            '<div class="table-wrap"><table class="data"><thead><tr><th>Reference</th><th>Received</th><th>Patient</th><th>Phone</th><th>Service</th><th>Location</th><th>Urgency</th><th>Status</th><th>Assigned</th></tr></thead><tbody>' +
            (rows.length ? rows.map(function (r) {
                return '<tr class="' + (r.is_urgent && (r.status === 'new' || r.status === 'under_review') ? 'is-urgent' : '') + '"><td><a class="ref" href="#/request/' + r.id + '">' + esc(r.reference) + '</a></td>' +
                    '<td class="muted">' + esc(when(r.created_at, true)) + '</td><td>' + esc(r.name) + '<div class="muted">' + r.age + ' yrs' + (r.is_minor ? ' · child' : '') + '</div></td>' +
                    '<td>' + esc(phone(r.phone)) + '</td><td>' + esc(r.service === 'Other' && r.service_other ? 'Other: ' + r.service_other : r.service) + '</td>' +
                    '<td>' + esc(r.location === 'Other' && r.location_other ? r.location_other : r.location) + '</td>' +
                    '<td>' + (r.is_urgent ? '<span class="badge b-urgent">Urgent</span>' : '<span class="muted">' + esc(r.urgency) + '</span>') + '</td>' +
                    '<td>' + badge(r.status) + '</td><td class="muted">' + esc(r.assigned_to || '—') + '</td></tr>';
            }).join('') : '<tr><td colspan="9" class="empty">No requests found.</td></tr>') + '</tbody></table></div></div>';
        render('Patient requests', 'requests', html);
        app.querySelectorAll('[data-status]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); filters.status = a.getAttribute('data-status'); requestsView(); }); });
        document.getElementById('search').addEventListener('submit', function (e) { e.preventDefault(); filters.q = document.getElementById('q').value; filters.urgent = document.getElementById('urgent').checked; if (filters.q) audit('requests_search'); requestsView(); });
        document.getElementById('urgent').addEventListener('change', function () { filters.urgent = this.checked; requestsView(); });
        var clear = document.getElementById('clear');
        if (clear) clear.addEventListener('click', function (e) { e.preventDefault(); filters = { q: '', status: '', urgent: false }; requestsView(); });
    }

    /* ---------- one request ---------- */
    function requestView(id) {
        var s = D.load();
        var r = s.requests.filter(function (x) { return x.id === id; })[0];
        if (!r) { render('Not found', 'requests', '<div class="card"><h2>Request not found</h2><p><a href="#/requests">Back to requests</a></p></div>'); return; }
        audit('request_view', r.reference);
        var opt = function (list, val) { return list.map(function (o) { var v = Array.isArray(o) ? o[0] : o; var l = Array.isArray(o) ? o[1] : o; return '<option value="' + esc(v) + '"' + (v === val ? ' selected' : '') + '>' + esc(l) + '</option>'; }).join(''); };
        var wa = 'https://wa.me/' + r.phone + '?text=' + encodeURIComponent('Hello ' + String(r.name).split(' ')[0] + ', this is Team Imara Gulu about your request ' + r.reference + '.');
        var g = r.guardian;
        var html = '<p><a href="#/requests">&larr; All requests</a></p>';
        if (r.is_urgent && (r.status === 'new' || r.status === 'under_review')) html += '<div class="urgent-banner" role="alert"><span style="font-size:1.6rem">&#9888;</span><div><strong>Urgent — the patient asked for help today.</strong> Call now. If symptoms sound severe, tell them to go to the nearest health facility immediately and do not wait for a referral.</div></div>';
        html += '<div class="grid grid-3"><div>' +
            '<div class="card"><div class="card-head"><h2>' + esc(r.reference) + ' &nbsp;' + badge(r.status) + (r.is_urgent ? ' <span class="badge b-urgent">Urgent</span>' : '') + '</h2><span class="muted">Received ' + esc(when(r.created_at)) + '</span></div><dl class="kv">' +
            '<dt>Patient</dt><dd><strong>' + esc(r.name) + '</strong></dd>' +
            '<dt>Age / gender</dt><dd>' + r.age + ' years · ' + esc(r.gender) + (r.is_minor ? ' · <strong>child</strong>' : '') + '</dd>' +
            '<dt>Phone / WhatsApp</dt><dd>' + esc(phone(r.phone)) + '</dd>' +
            '<dt>Location</dt><dd>' + esc(r.location) + (r.location_other ? ' — ' + esc(r.location_other) : '') + '</dd>' +
            '<dt>Insurance / scheme</dt><dd>' + esc(r.insurance) + '</dd>' +
            (g ? '<dt>Parent / guardian</dt><dd>' + esc(g.name) + ' (' + esc(g.relationship) + ') · ' + esc(phone(g.phone)) + '<br><span class="muted">Guardian consent given: Yes</span></dd>' : '') +
            '</dl><div class="contact-actions"><a class="btn btn-dark btn-sm" href="tel:+' + esc(r.phone) + '">Call patient</a><a class="btn btn-wa btn-sm" href="' + esc(wa) + '" target="_blank" rel="noopener">WhatsApp</a>' +
            (g && g.phone ? '<a class="btn btn-ghost btn-sm" href="tel:+' + esc(g.phone) + '">Call guardian</a>' : '') + '</div></div>' +
            '<div class="card"><h3>What they need</h3><dl class="kv">' +
            '<dt>Service</dt><dd><strong>' + esc(r.service) + '</strong>' + (r.service_other ? ' — ' + esc(r.service_other) : '') + '</dd>' +
            '<dt>How soon</dt><dd>' + esc(r.urgency) + '</dd><dt>Had it for</dt><dd>' + esc(r.duration) + '</dd>' +
            '<dt>Concern</dt><dd><div class="concern">' + esc(r.concern || '—') + '</div></dd>' +
            '<dt>Prefers</dt><dd>' + esc(r.assistance) + '</dd><dt>Preferred facility</dt><dd>' + esc(r.preferred_facility) + '</dd>' +
            '<dt>Sought care before</dt><dd>' + esc(r.previous_care || 'Not answered') + (r.previous_details ? '<div class="concern" style="margin-top:6px">' + esc(r.previous_details) + '</div>' : '') + '</dd>' +
            '<dt>Heard about Imara</dt><dd>' + esc(r.heard) + '</dd>' +
            '<dt>Consent</dt><dd>Care coordination: <strong>' + (r.consent_care ? 'Yes' : 'No') + '</strong> · WhatsApp health updates: <strong>' + (r.consent_whatsapp ? 'Yes' : 'No') + '</strong></dd></dl>' +
            '<p class="pii-warning">Imara does not diagnose or prescribe. A qualified health professional reviews every referral decision.</p></div>' +
            '<div class="card"><h3>History</h3><ul class="timeline">' + r.events.slice().reverse().map(function (ev) {
                var what = ev.type === 'status' ? 'Status: ' + esc(LABEL[ev.from] || ev.from) + ' &rarr; <strong>' + esc(LABEL[ev.to] || ev.to) + '</strong>' : ev.type === 'created' ? 'Request received' : ev.type === 'note' ? 'Note added' : 'Referral details updated';
                return '<li><div class="when">' + esc(when(ev.at)) + ' · ' + esc(ev.by) + '</div><div class="what">' + what + '</div>' + (ev.note ? '<div class="note">' + esc(ev.note) + '</div>' : '') + '</li>';
            }).join('') + '</ul></div></div>' +
            '<div><form class="card" id="update"><h3>Update referral</h3>' +
            '<div class="field"><label class="f" for="status">Status</label><select id="status" name="status">' + opt(STATUSES.map(function (st) { return [st[0], st[1]]; }), r.status) + '</select><p class="status-help" id="status-help"></p></div>' +
            '<div class="field"><label class="f" for="assigned_to">Assigned to</label><select id="assigned_to" name="assigned_to"><option value="">— Nobody yet —</option>' + opt(STAFF, r.assigned_to) + '</select></div>' +
            '<div class="field"><label class="f" for="recommended_facility">Facility recommended by Imara</label><input list="fac" id="recommended_facility" name="recommended_facility" value="' + esc(r.recommended_facility) + '" placeholder="Patient preferred: ' + esc(r.preferred_facility) + '"><datalist id="fac">' + FACILITIES.map(function (f) { return '<option value="' + esc(f) + '">'; }).join('') + '</datalist></div>' +
            '<div data-show-for="referred linked follow_up closed"><div class="field"><label class="f" for="receiving_institution">Receiving institution</label><input list="fac" id="receiving_institution" name="receiving_institution" value="' + esc(r.receiving_institution) + '"></div>' +
            '<div class="row-fields"><div class="field"><label class="f" for="referral_date">Referral date</label><input type="date" id="referral_date" name="referral_date" value="' + esc(r.referral_date) + '"></div>' +
            '<div class="field"><label class="f" for="follow_up_date">Follow-up date</label><input type="date" id="follow_up_date" name="follow_up_date" value="' + esc(r.follow_up_date) + '"></div></div>' +
            '<div class="field"><label class="f" for="reviewed_by">Referral approved by (health professional)</label><input type="text" id="reviewed_by" name="reviewed_by" value="' + esc(r.reviewed_by) + '" placeholder="Name of the qualified health professional"><p class="help">Required before marking as Referred.</p></div></div>' +
            '<div class="field" data-show-for="referred linked follow_up closed"><label class="f" for="accessed_care">Did the patient actually get care?</label><select id="accessed_care" name="accessed_care">' + opt(Object.keys(ACCESSED).map(function (k) { return [k, ACCESSED[k]]; }), r.accessed_care) + '</select></div>' +
            '<div class="field" data-show-for="linked follow_up closed"><label class="f" for="outcome">Follow-up outcome</label><textarea id="outcome" name="outcome">' + esc(r.outcome) + '</textarea></div>' +
            '<div class="field" data-show-for="closed"><label class="f" for="closed_reason">Reason for closing</label><select id="closed_reason" name="closed_reason"><option value="">— Choose —</option>' + opt(CLOSE_REASONS, r.closed_reason) + '</select></div>' +
            '<div class="field"><label class="f" for="note">Add a note</label><textarea id="note" name="note" placeholder="e.g. Called patient, booked at Lacor OPD for Monday"></textarea></div>' +
            '<button type="submit" class="btn" style="width:100%">Save changes</button></form>' +
            '<div class="card"><h3>Alerts</h3><p class="muted" style="font-size:.88rem">On the real system, every new request emails hello@imaraafrika.org and (once connected) sends a WhatsApp Business alert. The test dashboard does not send anything.</p></div></div></div>';
        render(r.reference, 'requests', html);

        var form = document.getElementById('update');
        var status = document.getElementById('status');
        var sync = function () {
            form.querySelectorAll('[data-show-for]').forEach(function (el) { el.hidden = el.getAttribute('data-show-for').split(' ').indexOf(status.value) === -1; });
            document.getElementById('status-help').textContent = HELP[status.value] || '';
        };
        status.addEventListener('change', sync); sync();
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var v = function (n) { return (form.elements[n] ? form.elements[n].value : '').trim(); };
            var to = v('status'), err = null;
            var acc = v('accessed_care') || 'unknown';
            var refDate = v('referral_date');
            if (to === 'referred' && (!v('receiving_institution') || !v('reviewed_by'))) err = 'To mark as Referred, record the receiving institution and the qualified health professional who approved the referral.';
            if (to === 'closed' && !v('closed_reason')) err = 'Please record why this request is being closed.';
            if (err) { flash = ['error', err]; requestView(id); return; }
            if (to === 'referred' && !refDate) refDate = new Date().toISOString().slice(0, 10);
            if (to === 'linked') acc = 'yes';
            var st = D.load(); var rr = st.requests.filter(function (x) { return x.id === id; })[0];
            var from = rr.status, t = new Date().toISOString();
            rr.status = to; rr.assigned_to = v('assigned_to'); rr.recommended_facility = v('recommended_facility');
            rr.receiving_institution = v('receiving_institution'); rr.referral_date = refDate; rr.follow_up_date = v('follow_up_date');
            rr.reviewed_by = v('reviewed_by'); rr.accessed_care = acc; rr.outcome = v('outcome'); rr.closed_reason = v('closed_reason');
            if (!rr.first_contact_at && ['contacted', 'referred', 'linked', 'follow_up'].indexOf(to) !== -1) rr.first_contact_at = t;
            if (to === 'linked' && !rr.linked_at) rr.linked_at = t;
            rr.closed_at = to === 'closed' ? (rr.closed_at || t) : null;
            var note = v('note');
            rr.events.push(from !== to ? { at: t, by: USER, type: 'status', from: from, to: to, note: note } : { at: t, by: USER, type: note ? 'note' : 'update', note: note });
            D.save(st);
            audit('request_update', rr.reference + (from !== to ? ' (' + from + ' → ' + to + ')' : ''));
            flash = ['ok', 'Request ' + rr.reference + ' updated.'];
            requestView(id);
        });
    }

    /* ---------- forms inbox ---------- */
    function formsView() {
        var s = D.load();
        var rows = s.submissions.slice().reverse();
        var html = '<div class="card"><div class="card-head"><h2>Forms inbox</h2><span class="muted">Volunteers, partners, medical camp requests, messages and donation pledges</span></div><div class="table-wrap"><table class="data"><thead><tr><th>Reference</th><th>Received</th><th>Type</th><th>From</th><th>Summary</th><th>Status</th></tr></thead><tbody>' +
            (rows.length ? rows.map(function (x) {
                return '<tr><td><a class="ref" href="#/form/' + x.id + '">' + esc(x.reference) + '</a></td><td class="muted">' + esc(when(x.created_at, true)) + '</td><td>' + esc(x.label) + '</td><td>' + esc(x.name) + '</td><td>' + esc(x.summary) + '</td><td>' + badge(x.status) + '</td></tr>';
            }).join('') : '<tr><td colspan="6" class="empty">Nothing here yet. Try the Volunteer, Partner, Request a Camp, Contact or Donate forms on the website.</td></tr>') + '</tbody></table></div></div>';
        render('Forms inbox', 'forms', html);
    }
    function formView(id) {
        var s = D.load();
        var x = s.submissions.filter(function (y) { return y.id === id; })[0];
        if (!x) { render('Not found', 'forms', '<div class="card"><h2>Not found</h2><p><a href="#/forms">Back</a></p></div>'); return; }
        var p = x.payload, skip = { client_token: 1, phone_code: 1, consent: 1, amount_custom: 1 };
        var rows = Object.keys(p).filter(function (k) { return !skip[k] && p[k] !== '' && p[k] != null; }).map(function (k) {
            var val = Array.isArray(p[k]) ? p[k].join(', ') : p[k];
            if (k === 'amount') val = 'UGX ' + Number(val).toLocaleString('en-US');
            return '<dt>' + esc(k.replace(/_/g, ' ')) + '</dt><dd style="white-space:pre-wrap">' + esc(val) + '</dd>';
        }).join('');
        var html = '<p><a href="#/forms">&larr; Forms inbox</a></p><div class="grid grid-3"><div class="card"><div class="card-head"><h2>' + esc(x.reference) + ' ' + badge(x.status) + '</h2><span class="muted">' + esc(when(x.created_at)) + '</span></div><dl class="kv"><dt>Form</dt><dd>' + esc(x.label) + '</dd>' + rows + '</dl>' +
            (x.type === 'donation' ? '<p class="pii-warning">Pledge only — no money has been received through the website.</p>' : '') + '</div>' +
            '<form class="card" id="sub"><h3>Follow up</h3><div class="field"><label class="f" for="sstatus">Status</label><select id="sstatus">' + Object.keys(SUB_STATUS).map(function (k) { return '<option value="' + k + '"' + (k === x.status ? ' selected' : '') + '>' + SUB_STATUS[k] + '</option>'; }).join('') + '</select></div>' +
            '<div class="field"><label class="f" for="snotes">Internal notes</label><textarea id="snotes" rows="8">' + esc(x.notes) + '</textarea></div><button class="btn" type="submit" style="width:100%">Save</button></form></div>';
        render(x.reference, 'forms', html);
        document.getElementById('sub').addEventListener('submit', function (e) {
            e.preventDefault();
            var st = D.load(); var y = st.submissions.filter(function (z) { return z.id === id; })[0];
            y.status = document.getElementById('sstatus').value; y.notes = document.getElementById('snotes').value; D.save(st);
            audit('submission_update', y.reference);
            flash = ['ok', y.reference + ' saved.']; formView(id);
        });
    }

    /* ---------- reports ---------- */
    function bars(list) {
        var counts = {};
        list.forEach(function (v) { v = v || 'Not answered'; counts[v] = (counts[v] || 0) + 1; });
        var keys = Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; });
        if (!keys.length) return '<p class="muted">No data yet.</p>';
        var max = counts[keys[0]];
        return '<ul class="bars">' + keys.map(function (k) { return '<li><span>' + esc(k) + '</span><span class="bar"><span style="width:' + Math.round(100 * counts[k] / max) + '%"></span></span><span class="n">' + counts[k] + '</span></li>'; }).join('') + '</ul>';
    }
    function reportsView() {
        var s = D.load(), r = s.requests;
        var linked = r.filter(function (x) { return x.status === 'linked' || x.accessed_care === 'yes'; }).length;
        var referred = r.filter(function (x) { return x.referral_date; }).length;
        var hours = r.filter(function (x) { return x.first_contact_at; }).map(function (x) { return (new Date(x.first_contact_at) - new Date(x.created_at)) / 3600000; }).sort(function (a, b) { return a - b; });
        var median = hours.length ? hours[Math.floor(hours.length / 2)].toFixed(1) + 'h' : '—';
        var html = '<div class="card"><h2>Impact report · All time (test data)</h2><p class="muted">Anonymous figures from this browser\'s test data.</p><div class="kpis">' +
            '<div class="kpi"><div class="v">' + r.length + '</div><div class="l">Requests received</div></div>' +
            '<div class="kpi"><div class="v">' + referred + '</div><div class="l">Referred</div></div>' +
            '<div class="kpi"><div class="v">' + linked + '</div><div class="l">Linked to care</div></div>' +
            '<div class="kpi"><div class="v">' + r.filter(function (x) { return x.is_urgent; }).length + '</div><div class="l">Marked urgent</div></div>' +
            '<div class="kpi"><div class="v">' + median + '</div><div class="l">Median time to first contact</div></div></div>' +
            '<div class="grid grid-2"><div><h3>Status</h3>' + bars(r.map(function (x) { return LABEL[x.status]; })) + '</div><div><h3>Service requested</h3>' + bars(r.map(function (x) { return x.service; })) + '</div>' +
            '<div><h3>Division / district</h3>' + bars(r.map(function (x) { return x.location; })) + '</div><div><h3>How they heard about Imara</h3>' + bars(r.map(function (x) { return x.heard; })) + '</div></div></div>' +
            '<div class="card"><h3>Other forms</h3>' + bars(s.submissions.map(function (x) { return x.label; })) + '</div>';
        render('Reports', 'reports', html);
    }

    function auditView() {
        var a = D.load().audit;
        render('Audit log', 'audit', '<div class="card"><div class="card-head"><h2>Audit log</h2><span class="muted">Who viewed or changed what (test data)</span></div><div class="table-wrap"><table class="data"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th></tr></thead><tbody>' +
            (a.length ? a.map(function (x) { return '<tr><td class="muted">' + esc(when(x.at)) + '</td><td>' + esc(x.by) + '</td><td>' + esc(x.action.replace(/_/g, ' ')) + '</td><td>' + esc(x.ref) + '</td></tr>'; }).join('') : '<tr><td colspan="4" class="empty">Nothing yet.</td></tr>') + '</tbody></table></div></div>');
    }

    /* ---------- routing ---------- */
    function render(title, active, content) {
        document.body.className = 'has-sidebar';
        document.title = title + ' — Imara test dashboard';
        app.innerHTML = shell(title, active, content);
        window.scrollTo(0, 0);
        document.getElementById('sign-out').addEventListener('click', function () { audit('logout'); sessionStorage.removeItem('imara-test-user'); location.hash = ''; loginView(); });
        document.getElementById('reset-data').addEventListener('click', function () {
            if (confirm('Delete all test requests and forms on this device and start again with the sample data?')) { D.reset(); flash = ['ok', 'Test data reset.']; location.hash = '#/requests'; route(); }
        });
        var t = document.getElementById('menu-toggle'), sb = document.getElementById('sidebar');
        t.addEventListener('click', function () { sb.classList.toggle('open'); });
    }
    function route() {
        if (!D) { app.innerHTML = '<p style="padding:40px">Test mode is not available.</p>'; return; }
        if (!signedIn()) { loginView(); return; }
        var h = location.hash || '#/requests', m;
        if ((m = h.match(/^#\/request\/(\d+)/))) requestView(Number(m[1]));
        else if ((m = h.match(/^#\/form\/(\d+)/))) formView(Number(m[1]));
        else if (h === '#/forms') formsView();
        else if (h === '#/reports') reportsView();
        else if (h === '#/audit') auditView();
        else requestsView();
    }
    window.addEventListener('hashchange', route);
    route();
})();
