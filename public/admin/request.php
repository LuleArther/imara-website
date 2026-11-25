<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$user = require_login('requests.view');
$id = (int) ($_GET['id'] ?? 0);
$r = get_request($id);
if (!$r) {
    http_response_code(404);
    render_admin_page('Not found', '<div class="card"><h2>Request not found</h2><p><a href="index.php">Back to requests</a></p></div>', ['active' => 'dashboard']);
    exit;
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'update');
    if ($action === 'anonymise') {
        if (!can('requests.anonymise', $user)) {
            http_response_code(403);
            exit('Not allowed');
        }
        anonymise_request($id, 'manual: ' . str_clean($_POST['why'] ?? 'patient request', 120));
        flash('ok', 'Personal details removed. The anonymous record is kept for reports.');
        header('Location: request.php?id=' . $id);
        exit;
    }
    if (!can('requests.update', $user)) {
        http_response_code(403);
        exit('Not allowed');
    }
    if ($r['anonymised_at']) {
        $error = 'This request has been anonymised and can no longer be changed.';
    } else {
        $error = update_request($r, $_POST, $user);
        if ($error === null) {
            flash('ok', 'Request ' . $r['reference'] . ' updated.');
            header('Location: request.php?id=' . $id);
            exit;
        }
    }
}

audit('request_view', 'request', $id);
$pii = request_pii($r);
$events = db()->prepare('SELECT ev.*, u.name AS user_name FROM request_events ev LEFT JOIN users u ON u.id = ev.user_id WHERE request_id = ? ORDER BY ev.id DESC');
$events->execute([$id]);
$events = $events->fetchAll();
$notifications = db()->prepare("SELECT * FROM notifications WHERE entity = 'request' AND entity_id = ? ORDER BY id");
$notifications->execute([$id]);
$notifications = $notifications->fetchAll();
$staff = db()->query("SELECT id, name, role FROM users WHERE active = 1 AND role <> 'viewer' ORDER BY name")->fetchAll();

$facilityChoices = array_values(array_unique(array_merge(
    array_filter(option_values('facilities'), fn ($f) => !in_array($f, ['Any — advise me', 'Private clinic'], true)),
    db()->query("SELECT name FROM directory WHERE kind = 'facility' ORDER BY sort, name")->fetchAll(PDO::FETCH_COLUMN)
)));

$canEdit = can('requests.update', $user) && !$r['anonymised_at'];
$phone = $pii['phone'];
$waText = rawurlencode('Hello ' . explode(' ', $pii['name'])[0] . ', this is Team Imara Gulu about your request ' . $r['reference'] . '.');
$val = fn (string $k) => $_POST[$k] ?? $r[$k] ?? '';

ob_start(); ?>
<p><a href="index.php">← All requests</a></p>

<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ((int) $r['is_urgent'] === 1 && in_array($r['status'], ['new', 'under_review'], true)): ?>
    <div class="urgent-banner" role="alert"><span style="font-size:1.6rem">⚠</span><div><strong>Urgent — the patient asked for help today.</strong> Call now. If symptoms sound severe, tell them to go to the nearest health facility immediately and do not wait for a referral.</div></div>
<?php endif; ?>
<?php if ($r['anonymised_at']): ?>
    <div class="alert alert-info">Personal details were removed on <?= e(local_time($r['anonymised_at'])) ?>. Only anonymous information remains for reporting.</div>
<?php endif; ?>

<div class="grid grid-3">
    <div>
        <div class="card">
            <div class="card-head">
                <h2><?= e($r['reference']) ?> &nbsp;<span class="badge b-<?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span> <?php if ((int) $r['is_urgent'] === 1): ?><span class="badge b-urgent">Urgent</span><?php endif; ?></h2>
                <span class="muted">Received <?= e(local_time($r['created_at'])) ?></span>
            </div>
            <dl class="kv">
                <dt>Patient</dt><dd><strong><?= e($pii['name'] ?: '—') ?></strong></dd>
                <dt>Age / gender</dt><dd><?= (int) $r['age'] ?> years · <?= e($r['gender']) ?><?= (int) $r['is_minor'] === 1 ? ' · <strong>child</strong>' : '' ?></dd>
                <dt>Phone / WhatsApp</dt><dd><?= $phone ? e(format_phone($phone)) : '—' ?></dd>
                <dt>Location</dt><dd><?= e($r['location']) ?><?= $r['location_other'] ? ' — ' . e($r['location_other']) : '' ?> <span class="muted">· <?= e(location_group($r['location'])) ?></span></dd>
                <dt>Insurance / scheme</dt><dd><?= e($r['insurance']) ?></dd>
                <?php if ($pii['guardian']): ?>
                    <dt>Parent / guardian</dt><dd><?= e($pii['guardian']['name'] ?? '') ?> (<?= e($pii['guardian']['relationship'] ?? '') ?>) · <?= e(format_phone((string) ($pii['guardian']['phone'] ?? ''))) ?><br><span class="muted">Guardian consent given: <?= (int) $r['guardian_consent'] === 1 ? 'Yes' : 'No' ?></span></dd>
                <?php endif; ?>
            </dl>
            <?php if ($phone && !$r['anonymised_at']): ?>
            <div class="contact-actions">
                <a class="btn btn-dark btn-sm" href="tel:+<?= e($phone) ?>">Call patient</a>
                <a class="btn btn-wa btn-sm" href="https://wa.me/<?= e($phone) ?>?text=<?= e($waText) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <?php if ($pii['guardian'] && !empty($pii['guardian']['phone'])): ?><a class="btn btn-ghost btn-sm" href="tel:+<?= e($pii['guardian']['phone']) ?>">Call guardian</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>What they need</h3>
            <dl class="kv">
                <dt>Service</dt><dd><strong><?= e($r['service']) ?></strong><?= $r['service_other'] ? ' — ' . e($r['service_other']) : '' ?></dd>
                <dt>How soon</dt><dd><?= e($r['urgency']) ?></dd>
                <dt>Had it for</dt><dd><?= e($r['duration']) ?></dd>
                <dt>Concern</dt><dd><div class="concern"><?= e($pii['concern'] ?: '—') ?></div></dd>
                <dt>Prefers</dt><dd><?= e($r['assistance']) ?></dd>
                <dt>Preferred facility</dt><dd><?= e($r['preferred_facility']) ?></dd>
                <dt>Sought care before</dt><dd><?= e($r['previous_care'] ?: 'Not answered') ?><?= $pii['previous_details'] ? '<div class="concern" style="margin-top:6px">' . e($pii['previous_details']) . '</div>' : '' ?></dd>
                <dt>Heard about Imara</dt><dd><?= e($r['heard']) ?></dd>
                <dt>Consent</dt><dd>Care coordination: <strong><?= (int) $r['consent_care'] === 1 ? 'Yes' : 'No' ?></strong> · WhatsApp health updates: <strong><?= (int) $r['consent_whatsapp'] === 1 ? 'Yes' : 'No' ?></strong><br><span class="muted">Given <?= e(local_time($r['consent_at'])) ?>, privacy notice <?= e($r['privacy_version']) ?></span></dd>
            </dl>
            <p class="pii-warning">Imara does not diagnose or prescribe. A qualified health professional reviews every referral decision. Do not copy health details into ordinary WhatsApp or email.</p>
        </div>

        <div class="card">
            <h3>History</h3>
            <ul class="timeline">
                <?php foreach ($events as $ev): $note = decrypt_field($ev['note_enc']); ?>
                    <li>
                        <div class="when"><?= e(local_time($ev['created_at'])) ?> · <?= e($ev['user_name'] ?? 'Website') ?></div>
                        <div class="what">
                            <?php if ($ev['type'] === 'status'): ?>Status: <?= e(status_label((string) $ev['from_status'])) ?> → <strong><?= e(status_label((string) $ev['to_status'])) ?></strong>
                            <?php elseif ($ev['type'] === 'created'): ?>Request received
                            <?php elseif ($ev['type'] === 'note'): ?>Note added
                            <?php else: ?>Referral details updated<?php endif; ?>
                        </div>
                        <?php if ($note !== ''): ?><div class="note"><?= e($note) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div>
        <?php if ($canEdit): ?>
        <form class="card" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <h3>Update referral</h3>
            <div class="field">
                <label class="f" for="status">Status</label>
                <select id="status" name="status" data-conditional>
                    <?php foreach (REQUEST_STATUSES as $k => $label): ?>
                        <option value="<?= e($k) ?>" data-help="<?= e(STATUS_HELP[$k]) ?>" <?= $val('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="status-help" id="status-help"></p>
            </div>
            <div class="field">
                <label class="f" for="assigned_to">Assigned to</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">— Nobody yet —</option>
                    <?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (string) $val('assigned_to') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> (<?= e(ROLES[$s['role']]) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="f" for="recommended_facility">Facility recommended by Imara</label>
                <input list="facility-list" id="recommended_facility" name="recommended_facility" value="<?= e($val('recommended_facility')) ?>" placeholder="Patient preferred: <?= e($r['preferred_facility']) ?>">
                <datalist id="facility-list"><?php foreach ($facilityChoices as $f): ?><option value="<?= e($f) ?>"><?php endforeach; ?></datalist>
            </div>
            <div data-show-for="referred linked follow_up closed">
                <div class="field">
                    <label class="f" for="receiving_institution">Receiving institution</label>
                    <input list="facility-list" id="receiving_institution" name="receiving_institution" value="<?= e($val('receiving_institution')) ?>">
                </div>
                <div class="row-fields">
                    <div class="field">
                        <label class="f" for="referral_date">Referral date</label>
                        <input type="date" id="referral_date" name="referral_date" value="<?= e($val('referral_date')) ?>">
                    </div>
                    <div class="field">
                        <label class="f" for="follow_up_date">Follow-up date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date" value="<?= e($val('follow_up_date')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="f" for="reviewed_by">Referral approved by (health professional)</label>
                    <input type="text" id="reviewed_by" name="reviewed_by" value="<?= e($val('reviewed_by') ?: ($user['role'] === 'clinician' ? $user['name'] : '')) ?>" placeholder="Name of the qualified health professional">
                    <p class="help">Required before marking as Referred.</p>
                </div>
            </div>
            <div class="field" data-show-for="referred linked follow_up closed">
                <label class="f" for="accessed_care">Did the patient actually get care?</label>
                <select id="accessed_care" name="accessed_care">
                    <?php foreach (ACCESSED_CARE as $k => $label): ?><option value="<?= e($k) ?>" <?= $val('accessed_care') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field" data-show-for="linked follow_up closed">
                <label class="f" for="outcome">Follow-up outcome</label>
                <textarea id="outcome" name="outcome" placeholder="What happened after the referral?"><?= e($_POST['outcome'] ?? $pii['outcome']) ?></textarea>
            </div>
            <div class="field" data-show-for="closed">
                <label class="f" for="closed_reason">Reason for closing</label>
                <select id="closed_reason" name="closed_reason">
                    <?php foreach (['', 'Completed — patient received care', 'Patient could not be reached', 'Patient no longer needs help', 'Referred elsewhere / outside our area', 'Duplicate request', 'Not a request for care', 'Other'] as $reason): ?>
                        <option value="<?= e($reason) ?>" <?= $val('closed_reason') === $reason ? 'selected' : '' ?>><?= $reason === '' ? '— Choose —' : e($reason) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="f" for="note">Add a note</label>
                <textarea id="note" name="note" placeholder="e.g. Called patient, booked at Lacor OPD for Monday"></textarea>
                <p class="help">Notes are encrypted and only visible to staff.</p>
            </div>
            <button type="submit" class="btn" style="width:100%">Save changes</button>
        </form>
        <?php else: ?>
        <div class="card"><h3>Referral</h3>
            <dl class="kv">
                <dt>Recommended</dt><dd><?= e($r['recommended_facility'] ?: '—') ?></dd>
                <dt>Receiving</dt><dd><?= e($r['receiving_institution'] ?: '—') ?></dd>
                <dt>Referral date</dt><dd><?= e($r['referral_date'] ?: '—') ?></dd>
                <dt>Approved by</dt><dd><?= e($r['reviewed_by'] ?: '—') ?></dd>
                <dt>Got care?</dt><dd><?= e(ACCESSED_CARE[$r['accessed_care']] ?? '—') ?></dd>
                <dt>Closed reason</dt><dd><?= e($r['closed_reason'] ?: '—') ?></dd>
            </dl>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Alerts sent</h3>
            <?php if (!$notifications): ?><p class="muted">None recorded.</p><?php endif; ?>
            <?php foreach ($notifications as $n): ?>
                <p style="margin:0 0 8px;font-size:.88rem"><span class="badge b-<?= e($n['status']) ?>"><?= e($n['status']) ?></span> <?= e(ucfirst($n['channel'])) ?> → <?= e($n['recipient']) ?><br><span class="muted"><?= e($n['subject']) ?><?= $n['error'] ? ' — ' . e($n['error']) : '' ?></span></p>
            <?php endforeach; ?>
        </div>

        <?php if (can('requests.anonymise', $user) && !$r['anonymised_at']): ?>
        <form class="card" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="anonymise">
            <h3>Delete personal details</h3>
            <p class="muted" style="font-size:.88rem">Use when the patient asks for their data to be deleted, or under the retention policy. Anonymous numbers stay in reports. This cannot be undone.</p>
            <div class="field"><label class="f" for="why">Reason</label><input type="text" id="why" name="why" value="Patient asked for deletion"></div>
            <button class="btn btn-danger" type="submit" data-confirm="Permanently remove this patient's personal details?">Anonymise request</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php
render_admin_page($r['reference'], (string) ob_get_clean(), ['active' => 'dashboard']);
