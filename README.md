# Imara Health Initiative AFRIKA — website & Imara Health Bridge Gulu

Website for **Imara Health Initiative AFRIKA** (Gulu City, Northern Uganda), built on the **Dinenos** HTML template's design (same header, hero slider, ornaments, marquee, counters, dark story slider, CTA band and footer). Its centrepiece is **Imara Health Bridge Gulu**, a trackable patient-to-care navigation system.

*Strong Communities. Strong Health. Strong Afrika.*

## Live preview

**https://lulearther.github.io/imara-website/**

This is a static preview hosted on GitHub Pages. GitHub Pages cannot run PHP, so the preview runs in **test mode**:

- Forms are saved only in the visitor's own browser, and every form says clearly that nothing is sent to Imara.
- A **test dashboard** at https://lulearther.github.io/imara-website/demo-admin/ shows those requests with the same screens and referral rules as the real dashboard. Test login: `test@imaraafrika.org` / `imara-test` (shown on the sign-in page).
- Each device sees only its own test data plus 4 sample requests. "Reset test data" in the sidebar starts again.

The real, shared system runs on PHP hosting (see [docs/HANDOVER.md](docs/HANDOVER.md)). Test-mode files live in `preview/` and are added only to the preview. The preview is hidden from search engines.

To update the preview after changing the site: `python tools/publish_preview.py`

## Quick start (Windows)

Double-click **`run-dev.bat`**, then open:

- Website: http://127.0.0.1:8090
- Staff dashboard: http://127.0.0.1:8090/admin/

## What's included

**Public pages** (`public/*.html`, generated from `src/`)

| Page | File |
| --- | --- |
| Home | `index.html` |
| About / Who we are, vision, mission, 3 pillars, founder | `about.html` |
| **Get Care**, the 3-step patient request form | `get-care.html` |
| Find a Specialist | `specialists.html` |
| Find a Hospital | `hospitals.html` |
| Request a Medical Camp | `request-camp.html` |
| Volunteer Registration | `volunteer.html` |
| Partnership Portal (5 audiences, each with its own call to action) | `partner.html` |
| Donate / Support Our Mission | `donate.html` |
| Health Resources & Imara Voices Podcast | `resources.html` |
| Impact Dashboard (verified figures plus live system numbers) | `impact.html` |
| Contact Imara | `contact.html` |
| Privacy Policy | `privacy.html` |

**Backend** (PHP 8 + SQLite, runs on ordinary shared hosting)

- Unique references **IMARA-0001…**, validation, duplicate-submission protection, spam protection
- Encrypted storage of names, phone numbers and health notes; search by reference, name or phone
- Email alert to hello@imaraafrika.org and **official WhatsApp Business Platform** alerts, with no health details in either
- Patient confirmation on screen (and on WhatsApp once connected), including the emergency notice
- Urgent requests flagged red and pinned to the top of the dashboard for immediate human review
- Staff dashboard with role-based access: the 7 request statuses, notes, referral date, receiving institution, facility recommended by Imara, approving health professional, follow-up outcome and whether the patient actually got care
- Audit log, weekly/monthly reports (print or CSV), forms inbox, directory manager, backups, data-retention clean-up

**Docs:** [Staff guide](docs/ADMIN-GUIDE.md) · [Deployment & operations](docs/HANDOVER.md) · [WhatsApp templates](docs/WHATSAPP-TEMPLATES.md)

## Brief improvements implemented

- Gulu City divisions are listed separately from other districts; the list is easy to extend (`config/form-options.json`)
- Parent/guardian contact and consent are required when the patient is under 18
- Choosing "Urgent" shows an emergency warning and routes the request to a human immediately
- Staff can record a facility recommended by Imara, separate from the patient's preference
- The concern box tells patients not to enter unnecessary medical details
- Details about previous treatment are optional
- The Uganda-specific insurance wording is kept
- The consent box links to the full privacy policy; the two consents (care coordination and WhatsApp updates) are separate
- Donation options are shown as "being verified", and no payment is presented as working until it has been confirmed

## Still to confirm with Clifton

- [ ] Logo, brand colours and fonts. A temporary wordmark is used, in the template's orange.
- [ ] Verified MTN, Airtel and PayPal details for the donate page
- [ ] Who receives WhatsApp alerts and who covers the 24-hour response, plus the Meta WhatsApp Business account
- [ ] Hosting and domain (imaraafrika.org), and the hello@imaraafrika.org mailbox
- [ ] Partner hospitals and specialists to list (only with their agreement)
- [ ] The full list of districts and divisions
- [ ] Permission to publish the photos of identifiable patients
- [ ] PDPO registration before collecting real patient data
- [ ] Social media links (none are shown until real accounts are confirmed)
