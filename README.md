# wp-elementor-pipedrive

WordPress plugin that captures Elementor Pro form submissions and creates Person, Organization, and Deal in Pipedrive CRM. Optionally syncs contacts to Brevo (email marketing) and/or fires a custom webhook. All integrations are configured per-form via an admin mapping UI.

---

## Development Rule

> **Every modification to any file in this plugin must include a version bump** in both places inside `wp-elementor-pipedrive.php`:
> - Header comment: `Version: X.Y.Z`
> - Constant: `define( 'EPD_VERSION', 'X.Y.Z' );`

---

## Requirements

- WordPress 5.0+
- Elementor Pro (with Forms widget)
- PHP 7.4+
- Pipedrive account (required)
- Brevo account (optional)

---

## File Structure

```
wp-elementor-pipedrive/
├── wp-elementor-pipedrive.php          # Main entry — constants, hooks, plugin init
├── includes/
│   ├── class-activator.php             # DB table creation and schema migrations
│   ├── class-admin.php                 # Admin pages, form handlers, AJAX endpoints
│   ├── class-elementor-handler.php     # Submission orchestrator (Pipedrive → Brevo → Webhook)
│   ├── class-attribution.php            # Validates and normalizes browser attribution data
│   ├── class-field-mapper.php          # Maps Elementor fields to Pipedrive entities
│   ├── class-pipedrive-api.php         # Pipedrive REST API wrapper (v1 + v2)
│   └── class-brevo-api.php             # Brevo REST API wrapper (v3)
├── admin/
│   ├── css/admin.css                   # Admin panel styles
│   ├── js/admin.js                     # Admin interactions (dropdowns, AJAX, retries)
│   └── partials/
│       ├── settings-page.php           # Global API keys (Pipedrive + Brevo)
│       ├── mapping-page.php            # List all form mappings
│       ├── mapping-edit.php            # Create/edit a mapping (most complex page)
│       ├── submissions-page.php        # Last 100 submissions with retry buttons
│       └── logs-page.php               # Last 200 log entries
├── public/
│   └── js/
│       ├── epd-attribution-classifier.js # Classifies UTMs, click IDs, referrers, and direct traffic
│       ├── epd-utm.js                  # Persists first/last touch and injects attribution into forms
│       └── epd-form-validation.js      # Phone mask + email domain blocking (client-side)
├── languages/                          # i18n files (text domain: elementor-pipedrive)
└── tests/
    └── attribution-classifier.test.js  # Classifier unit tests
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `wp_epd_mappings` | One row per form mapping — stores pipeline/stage, field maps for Pipedrive/Brevo/Webhook, and validation rules |
| `wp_epd_submissions` | One row per form submission — stores all field values (JSON) and status/result for each integration (Pipedrive, Brevo, Webhook) |
| `wp_epd_logs` | Simple timestamped log messages for debugging API calls and plugin events |

Tables are created on plugin activation via `EPD_Activator::activate()`. Schema migrations run automatically on `plugins_loaded` by comparing `epd_version` option against `EPD_VERSION`.

---

## Key Classes

| Class | File | Responsibility |
|-------|------|---------------|
| `EPD_Activator` | `includes/class-activator.php` | Creates/upgrades DB tables; handles backward-compatible migrations |
| `EPD_Admin` | `includes/class-admin.php` | Registers admin menu, handles form POSTs and 9 AJAX endpoints |
| `EPD_Elementor_Handler` | `includes/class-elementor-handler.php` | Hooks into `elementor_pro/forms/new_record`, runs the full integration pipeline |
| `EPD_Attribution` | `includes/class-attribution.php` | Validates the attribution carrier and exposes backward-compatible UTM fields |
| `EPD_Field_Mapper` | `includes/class-field-mapper.php` | Transforms form fields into `{person, organization, deal}` payloads for Pipedrive |
| `EPD_Pipedrive_API` | `includes/class-pipedrive-api.php` | HTTP wrapper for Pipedrive (v1 for reads, v2 for creates) |
| `EPD_Brevo_API` | `includes/class-brevo-api.php` | HTTP wrapper for Brevo API v3 — contact upsert with `updateEnabled: true` |

---

## Submission Flow

When an Elementor Pro form is submitted:

1. `elementor_pro/forms/new_record` fires → `EPD_Elementor_Handler::handle_form_submit()`
2. Look up active mapping for the `form_id` in `wp_epd_mappings`
3. Normalize form fields; validate the attribution carrier and fall back to the legacy `epd_utm` format
4. Create a `wp_epd_submissions` row with status `pending`
5. **Pipedrive** (if API token + domain configured):
   - Map fields via `EPD_Field_Mapper::map()`
   - `POST /organizations` → get `org_id`
   - `POST /persons` → get `person_id`
   - `POST /deals` linking both → get `deal_id`
   - Update submission with IDs and status
6. **Brevo** (if enabled in mapping):
   - Map fields to Brevo attributes
   - Format phone numbers → `+55...` international format
   - `POST /contacts` (upsert)
   - Update submission with contact ID and status
7. **Webhook** (if `webhook_url` set in mapping):
   - Build the JSON payload without changing the existing contract
   - Send explicit or inferred values in the existing `form.utm` object
   - Apply field renaming from `webhook_field_map`
   - `POST` to URL (15s timeout, 3 redirects max)
   - Update submission with HTTP status code
8. Final submission row saved with all statuses (`success` / `error` / `skipped`)

Failed submissions can be retried individually from the Submissions admin page.

---

## Integrations

### Pipedrive
- **API:** v1 for reading fields/pipelines/stages, v2 for creating entities
- **Auth:** HTTP header `x-api-token`
- **Options:** `epd_api_token`, `epd_company_domain` (e.g. `company.pipedrive.com`)
- **Entity order:** Organization → Person → Deal (each links to the previous)
- **Custom fields:** Identified by 40-char hex key, sent inside `custom_fields` object
- **Native fields:** `name`, `emails[]`, `phones[]` at root level

### Brevo
- **API:** v3 (`https://api.brevo.com/v3`)
- **Auth:** HTTP header `api-key`
- **Option:** `epd_brevo_api_key`
- **Behavior:** Upserts contact (`updateEnabled: true`); HTTP 201 = created, 204 = updated
- **Phone:** Auto-converts 10–11 digit numbers to `+55...` for `SMS`/`LANDLINE_NUMBER` attributes
- **Scope:** Enabled/disabled per-form mapping, with optional Brevo list ID

### Webhook
- **Method:** POST, `Content-Type: application/json`
- **Timeout:** 15 seconds, max 3 redirects
- **Scope:** Configured per-form mapping
- **Field renaming:** `webhook_field_map` lets you rename keys before sending
- **Compatibility contract:** `form.utm` always keeps `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, and `utm_content`
- Explicit UTMs take priority; missing values are inferred from click IDs or the external referrer
- Internal `epd_utm*` and `epd_attribution_*` fields are stripped from the regular `form` fields

---

## Frontend Scripts

### `epd-utm.js`
- Classifies explicit UTMs, Google/Bing/Meta/TikTok click IDs, organic search, social, referral, and direct traffic
- Saves first touch and last non-direct touch for 90 days without renewing the original attribution window on every navigation
- Migrates the legacy `epd_utm` cookie and keeps it synchronized for backward compatibility
- Injects the validated attribution carrier and legacy UTM hidden inputs into all `form.elementor-form` elements
- Uses `MutationObserver` to handle forms that load dynamically (popups, tabs)

The existing `epd_utm_*` fields remain available to Pipedrive and Brevo mappings. Additional first/last touch, click ID, landing URL, and conversion URL fields are optional mapping sources.

Run the classifier tests with:

```bash
node --test tests/attribution-classifier.test.js
```

### `epd-form-validation.js`
- **Phone:** Applies mask `(11) 99999-8888` to `telefone`/`celular` fields; blocks submit if < 10 digits
- **Email:** Checks domain against blocklists (domains, suffixes, keywords); shows inline error
- Config injected from PHP via `wp_localize_script()` using `epdValidation.forms[form_id]`

---

## WordPress Options

| Option | Value |
|--------|-------|
| `epd_api_token` | Pipedrive personal API token |
| `epd_company_domain` | Pipedrive company domain (e.g. `company.pipedrive.com`) |
| `epd_brevo_api_key` | Brevo API key |
| `epd_version` | Stored plugin version — used to trigger DB migrations on upgrade |

---

## Configuration

### Step 1 — Global Settings
Go to **Elementor Pipedrive → Settings** and enter:
- Pipedrive company domain and API token
- Brevo API key (optional)

Use the "Test Connection" buttons to verify credentials.

### Step 2 — Form Mapping
Go to **Elementor Pipedrive → Mappings → Add New**:
1. Select the Elementor form
2. Select the Pipedrive pipeline and stage
3. Map form fields to Pipedrive entities (Person / Organization / Deal)
4. Set deal title template — supports `{field_id}` placeholders
5. (Optional) Enable phone/email validation rules
6. (Optional) Add webhook URL and field renaming
7. (Optional) Enable Brevo, set list ID, map fields to Brevo attributes

---

## Versioning

Every code change must bump two values in [wp-elementor-pipedrive.php](wp-elementor-pipedrive.php):

```php
 * Version: X.Y.Z                    ← plugin header
define( 'EPD_VERSION', 'X.Y.Z' );   ← constant
```

The `epd_version` option in the DB is compared against `EPD_VERSION` on every page load to trigger DB migrations automatically.

---

## Logs & Debugging

- **Admin Logs page:** last 200 entries from `wp_epd_logs`
- **PHP error log:** all events also written with `[EPD]` prefix via `error_log()`
- **Submissions page:** shows integration status per submission; failed ones have retry buttons
