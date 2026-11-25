# WhatsApp alerts — setup and message templates

Imara uses the **official WhatsApp Business Platform (Meta Cloud API)**. No unofficial scripts or "WhatsApp Web" bots are used, because these break the WhatsApp terms and can get the number banned.

Until this is set up, the site still works: staff get **email** alerts, and the dashboard's **Notifications** page shows each WhatsApp alert as "skipped".

## 1. What you need

1. A Meta Business account for Imara Health Initiative AFRIKA (business.facebook.com).
2. A phone number for the WhatsApp Business Platform. It **cannot** be a number already used in the normal WhatsApp or WhatsApp Business app, so a separate SIM is usually easiest.
3. In Meta for Developers, create an app → add **WhatsApp** → note the **Phone number ID**, then create a **permanent access token** (System User token with the `whatsapp_business_messaging` permission).

## 2. Templates to submit for approval

In WhatsApp Manager → Message templates → Create template. Category: **Utility**. Language: **English**.

### Template 1 — staff alert. Name: `imara_new_request`

```
NEW PATIENT REQUEST — IMARA HEALTH BRIDGE GULU
A new healthcare assistance request has been received.
Reference: {{1}}  Patient: {{2}}  Service requested: {{3}}
Urgency: {{4}}  Location: {{5}}
Please log in to the Imara dashboard to review and follow up.
Imara Health Initiative AFRIKA — Strong Communities. Strong Health. Strong Afrika.
```

Sample values for Meta's review: `IMARA-0001`, `Akello Grace`, `General Doctor`, `This week`, `Pece`.

### Template 2 — patient confirmation. Name: `imara_request_received`

```
Apwoyo matek! Thank you, {{1}}. Your healthcare assistance request has been received.
Your reference number is {{2}}.
An Imara team member will contact you within 24 hours during working days to guide you through the next steps.
Need urgent medical attention? Do not wait for Imara to respond. Visit the nearest appropriate health facility immediately or seek emergency assistance.
Strong Communities. Strong Health. Strong Afrika. — Team Imara Gulu
```

Sample values: `Grace`, `IMARA-0001`.

> These alerts never contain the patient's health concern, only what staff need in order to act. Keep it that way.

## 3. Switch it on

Edit `app/config.local.php` on the server:

```php
'whatsapp' => [
    'token' => 'EAAG...permanent token...',
    'phone_number_id' => '1234567890',
    'admin_numbers' => ['256777106608'],          // who receives staff alerts (no + sign)
    'template_new_request' => 'imara_new_request',
    'template_patient_confirm' => 'imara_request_received',
],
```

Submit a test request on the website. In the dashboard, open **Notifications**: the WhatsApp line should say **sent**. If it says **failed**, the reason from Meta is shown next to it.

## 4. Costs

Meta charges per conversation for business-started messages. Utility conversations in Uganda are inexpensive, but check current WhatsApp pricing for Uganda, and keep a payment method on the Meta Business account.
