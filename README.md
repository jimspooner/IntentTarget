# IntentTarget Pro

A white-labelled digital intelligence layer for WordPress and WooCommerce. IntentTarget Pro tracks authorised user engagement across active taxonomy sectors, allows visitors to manually set interests via their account dashboard, and uses a propensity algorithm to surface intent-based advertisements. The suite is built with privacy-first defaults and includes full controls to support UK GDPR and profiling regulations.

## Overview

IntentTarget Pro operates as a two-part suite:

- **IntentTarget Core** — The free foundation that handles content scanning, background cron processing, propensity scoring, advertising surfaces, user preferences, and licence management.
- **IntentTarget Pro Add-On** — A separately licenced extension that unlocks advert styling controls, role-based access control, ROI attribution tracking, and an AI-powered FAQ generator powered by the native WordPress 7.0 AI Client.

Both plugins share a single Master Hub licence server. The Core plugin requires an `ITP-` prefixed licence; the Pro add-on requires an `ITPP-` prefixed licence.

---

## What It Does

### 1. Audience Interest Tracking
Every time an authorised user views a post, page, or product, the plugin records the taxonomy sectors they engage with. These signals are stored against the user profile and aggregated into a running propensity score.

### 2. Propensity Scoring
The engine ranks user interest across five fixed intent buckets:

- **Transactional Intent** — buy, order, purchase, checkout, quote, etc.
- **Commercial Intent** — best, compare, review, recommend, top rated, etc.
- **Engagement Intent** — contact, enquire, support, newsletter, etc.
- **Informational Intent** — how to, guide, tutorial, what is, etc.
- **Specialist Intent** — niche / business-specific catch-all

Points are awarded for:
- Manual preference selection (+50)
- Page views in mapped taxonomy sectors (+5)
- Relevant on-site search queries (+10)
- Seasonal promotional weighting (where configured)

### 3. Dynamic Intent-Based Adverts
When a user reaches a threshold score in a given category, the plugin surfaces a tailored advert. Supported surfaces include:

- **Slide-in popup** — Appears after 1.5 seconds on non-search pages.
- **Top alert banner** — Static alert bar driven by the active priority advert.
- **WooCommerce "My Interests" tab** — A dedicated account page where users see their highest-scoring category and a personalised recommendation.
- **My Account dashboard promo tiles** — Two-tile layout rendered inside the WooCommerce dashboard.

### 4. Search Feedback
On search result pages, a small feedback form asks users whether they found what they were looking for. Submissions are sent via AJAX and stored for administrator review.

### 5. User Preferences (Privacy Toggle)
Users can explicitly opt out of profiling from their account preferences page. When opted out, all historical tracking records are purged and the engine falls back to generic default advertisements.

### 6. Digital Intelligence Panel
Administrators can view real-time propensity metrics, search histories, and assigned taxonomy sectors on every WordPress user profile page.

### 7. Background Cron Batch Engine
Content scanning and keyword dictionary updates run inside a resource-safe hourly cron job (`lee_dev_cron_batch_scan_event_9201`). The engine processes a maximum of 50 assets per cycle to safeguard host server RAM.

---

## Pro Add-On Features

The Pro add-on is loaded as a separate top-level plugin (`IntentTarget-Pro/`) and gates every feature behind a valid `ITPP-` licence.

### Advert Styling Controls
A dedicated dashboard tab lets administrators customise the colour palette used across all frontend surfaces:

- Heading & offer title colour
- "Recommended" badge background
- Action button background and label text colour

The tab also scans the active WordPress theme and suggests detected palette colours for one-click copying.

### Role-Based Access Control
Restrict interest tracking to specific WordPress user roles. When Pro is inactive, every logged-in user is tracked. When Pro is active, only roles explicitly selected by the administrator are enrolled.

### ROI Attribution & Analytics
Captures clicks on Pro-tracked adverts and stores them in a dedicated database table (`wp_itp_pro_advert_attributions`). Downstream conversions (form submissions, WooCommerce purchases, link visits) can be attributed back to the originating advert within a 14-day window.

### AI FAQ Generator (WordPress 7.0)
Powered by the native WordPress 7.0 AI Client and Abilities API:

- An admin metabox appears on every post, page, and public custom post type.
- Clicking **Generate FAQs** scans the page title, content, ACF fields, and public custom meta.
- The scan is sent to the configured AI provider via `wp_ai_client_prompt()`.
- The returned FAQ pairs are stored in post meta (`_itp_pro_ai_faqs`).
- FAQs are injected at the bottom of `the_content` (priority 999) with styling pulled from `itp_design_settings`.
- FAQPage Schema JSON-LD is automatically output in `<head>` for SEO.
- The feature is also registered as a WordPress 7.0 Ability (`intenttarget-pro/generate-page-faqs`) so it can be triggered from the Command Palette.

**Cost transparency:** The metabox displays a clear cost notice before generation. The JavaScript layer requires an explicit `window.confirm()` step before any AI call is made. After generation, the editor sees the exact model used and token consumption.

---

## How It Works

### Content Scanning Pipeline
1. On activation, a hourly cron event is scheduled.
2. Each cron run queries up to 50 published posts/pages/products that have never been scanned or have been modified since their last scan.
3. For each post, the scanner normalises the content body (strips shortcodes, tags, and entities) and extracts keyphrases.
4. Keyphrases are classified into one of the five intent buckets using the signal lexicon.
5. Results are stored in `_itp_tracking_labels` post meta and the global `itp_dynamic_keyword_dictionary` option.

### Propensity Calculation
1. On every page load, the tracker checks the current post's taxonomy sectors.
2. If the user is authorised and not opted out, the matching sectors are added to their profile.
3. The propensity engine recalculates scores and identifies the dominant intent category.
4. The dominant category drives which advert is displayed on the next eligible surface.

### Frontend Hydration (Cache-Safe)
All per-visitor surfaces (popup, dashboard tiles, preferences nonce, ROI click tracker) are rendered as empty shells in the cached HTML. A single cache-safe JavaScript runtime (`itp-frontend-ui.js`) bootstraps itself from a static REST endpoint (`intenttarget/v1/bootstrap`) and hydrates each surface with the visitor-specific payload at runtime. This ensures full-page caching plugins (e.g. WP Rocket, LiteSpeed Cache) never cache personalised content.

### Licence Flow
1. The customer purchases a licence from the Master Hub.
2. The administrator enters the licence code on the **Interest Tracker -> Pro Licence** tab.
3. The plugin sends an authenticated activation request to the Master Hub REST API (`intenttarget-hub/v1/activate`). The request includes both a custom HTTP header (`x_intenttarget_client_auth`) and a body fallback (`client_auth`) to survive proxy and CDN header stripping on live hosts.
4. On success, the local status is set to `authorised`, the Hub records the activation date, and Pro features are unlocked. The licence expires 1 year from activation.
5. A daily background job re-verifies the licence against the Hub. If revoked, expired, or deactivated, the local status reverts to `unauthorised`.
6. **Same-domain reactivation:** If a licence is deactivated (via the plugin UI, uninstall, or Hub admin), it can be reactivated on the exact same domain without purchasing a new key. The Hub verifies the requesting domain matches the previously mapped domain.
7. **Deactivation feedback:** When deactivating from the plugin UI, a modal optionally captures the user's reason and sends it to the Hub for product-improvement analytics.

---

## File Structure

```
IntentTarget/
├── intenttarget.php                    # Core plugin bootstrap, shortcodes, WC integration
├── readme.txt                          # WordPress.org readme
├── README.md                           # This file
├── custom-interests.css                # Frontend interest-selection styles
├── admin/
│   ├── class-lee-dev-menu.php          # Dashboard menu registration and tab routing
│   ├── class-lee-dev-feedback.php      # Deactivation feedback modal (Core + Pro UI)
│   ├── view-dashboard.php              # Main dashboard view (adverts, analytics, settings)
│   └── view-sidebar-box.php            # Post editor sidebar box for per-post controls
├── assets/
│   ├── js/
│   │   ├── itp-frontend-ui.js          # Cache-safe hydration runtime
│   │   └── itp-pro-ai-faq-admin.js     # Pro AI FAQ metabox controller
│   └── css/
│       └── itp-pro-ai-faq-admin.css    # Pro AI FAQ metabox styling
├── core/
│   ├── class-lee-dev-access.php        # Access control, readiness checks, cache-purge helper
│   ├── class-lee-dev-cron.php          # Background batch scan engine
│   ├── class-lee-dev-hooks.php         # Frontend hook registration
│   ├── class-lee-dev-intents.php       # Intent categories, lexicon, and classification
│   ├── class-lee-dev-parser.php        # Content normalisation and keyphrase extraction
│   ├── class-lee-dev-propensity.php    # Propensity scoring algorithm
│   └── class-lee-dev-transient.php     # Temporary data helpers
├── telemetry/
│   ├── class-lee-dev-advertising.php   # Advert rendering, popup, banner, dashboard tiles
│   ├── class-lee-dev-ajax.php          # Public AJAX handlers (engagement, search feedback)
│   └── class-lee-dev-tracker.php       # Core licence activation, deactivation, daily verification
├── telemetry-master/
│   └── (telemetry master stubs)
├── master-server-hub/
│   ├── intenttarget-master-hub.php     # Standalone Master Hub licence server (REST API, admin UI, db schema)
│   └── class-intenttarget-cron.php     # Daily cron for licence expiration checks
└── IntentTarget-Pro/                 # Pro add-on source (mirrored to top-level plugin)
    ├── intenttarget-pro.php          # Pro bootstrap, licence activation, styling tab
    └── includes/
        ├── class-lee-dev-pro-roi-tracking.php    # ROI attribution table and click tracking
        ├── class-lee-dev-pro-access-control.php  # Role-based access control tab
        └── class-lee-dev-pro-ai-faq.php          # AI FAQ generator (WP 7.0 AI Client)

IntentTarget-Pro/                     # Top-level Pro plugin (loaded by WordPress)
├── intenttarget-pro.php
└── includes/
    ├── class-lee-dev-pro-roi-tracking.php
    ├── class-lee-dev-pro-access-control.php
    └── class-lee-dev-pro-ai-faq.php
```

---

## Installation

1. Upload the `IntentTarget` folder to `/wp-content/plugins/`.
2. Upload the `IntentTarget-Pro` folder to `/wp-content/plugins/`.
3. Activate **IntentTarget Core** from the WordPress Plugins screen.
4. Activate **IntentTarget Pro** from the WordPress Plugins screen.
5. Navigate to **Interest Tracker** in the admin sidebar and enter your licence codes.

### Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- WooCommerce (optional — adds sales intent, My Interests tab, and dashboard surfaces)
- WordPress 7.0 or higher (required for Pro AI FAQ Generator)

---

## Licence Model

| Component | Prefix | Feature Gate |
|-----------|--------|--------------|
| Core | `ITP-` | `lee_dev_has_authorised_licence_7365()` |
| Pro Add-On | `ITPP-` | `lee_dev_is_addon_active_3812('pro')` |

When a Pro licence is inactive, Pro UI tabs remain visible but are disabled with an up-sell message. All Pro functional code remains dormant.

---

## Privacy & Compliance

- **No tracking for guests.** Only logged-in users with authorised roles are profiled.
- **Opt-out by design.** Users can disable profiling from their account page; all historical data is purged immediately.
- **No external AI API keys are stored.** The AI FAQ generator delegates to the WordPress 7.0 Connectors API; the site administrator authenticates their own provider.
- **Telemetry is rate-limited.** Anonymous usage pings are sent no more than once per week.

---

## Changelog

### 1.1.0
- **Master Hub Licensing Overhaul**
  - Added `expires_at` column to `wp_intenttarget_licenses`; licences expire 1 year from activation.
  - Added daily cron (`itp_hub_daily_expiration_check`) to automatically deactivate expired licences.
  - Added `feedback_reason` and `feedback_text` columns for deactivation analytics.
  - Added `/release` endpoint for remote deactivation/uninstall.
  - Added `/feedback` endpoint to capture user deactivation reasons.
  - **Same-domain reactivation:** Deactivated licences can be reactivated on the exact same domain without purchasing a new key. The Hub matches the requesting domain against the previously mapped domain.
  - **Dual-auth mechanism:** The secure activation endpoint (`intenttarget-hub/v1/activate`) accepts authentication via both a custom HTTP header (`x_intenttarget_client_auth`) and a POST body fallback (`client_auth`), ensuring requests survive proxy and CDN header stripping on live hosts.
  - Improved error messages: the Hub now returns specific rejection reasons (domain mismatch, wrong prefix, expired, etc.) instead of a generic failure.

- **Client Plugin Improvements**
  - Core (`telemetry/class-lee-dev-tracker.php`) and Pro (`IntentTarget-Pro/intenttarget-pro.php`) activation requests now send the auth token in both header and body.
  - Both client plugins capture and display the **actual** error message returned by the Master Hub (passed via `err_msg` query arg) instead of showing a misleading generic "invalid prefix" message for every failure.
  - Pro UI now shows domain-specific, Hub-generated error text in the admin notice on activation failure.
  - Core and Pro daily verification jobs now correctly ping the Hub to detect remote deactivation or expiration.

- **Cache Safety Fixes**
  - `LEE_DEV_FRONTEND_UI_VERSION` now auto-bumps using `filemtime()` on the JS file — no more stale browser caches after JS updates.
  - `LEE_DEV_PRO_AI_FAQ_VERSION` now also auto-bumps via `filemtime()`.
  - Added automatic page cache purging on every licence status change. Supports WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache, Hummingbird, SG Optimiser, Cloudflare Super Page Cache, WP Engine, and Kinsta.
  - This ensures that when a licence is activated or deactivated, full-page caches are invalidated so the frontend features (popup, dashboard, preferences) appear or disappear immediately.

- **Deactivation Feedback UI**
  - New `admin/class-lee-dev-feedback.php` renders a modal when the user deactivates a licence from the plugin UI.
  - Modal offers pre-set reasons ("Switching to another plugin", "Not working as expected", "Feature missing", "Temporary deactivation", "Other") plus an optional free-text field.
  - Feedback is sent to the Hub and stored against the licence record.
  - The UI also allows skipping feedback while still releasing the licence remotely.

- **Bug Fixes**
  - Fixed activation routing bug where `activation_email` parameter was not recognised, causing requests to hit the wrong handler.
  - Fixed Core plugin not sending `plugin_slug` in activation requests.
  - Fixed Pro plugin `uninstall.php` and Core `uninstall.php` to ping the `/release` endpoint before deleting data, so licences are properly deactivated on the Hub rather than deleted.

### 1.0.0
- Initial commercial framework release.
- Modular core, admin, telemetry, advertising, and master hub components.
- Resource-safe background tracking architecture.
- Cache-safe frontend hydration system.
- Pro add-on with advert styling, role-based access control, and ROI attribution.
- AI FAQ Generator integrating WordPress 7.0 AI Client, Abilities API, and Connectors API.
