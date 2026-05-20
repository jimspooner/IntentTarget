=== IntentTarget Pro ===
Contributors: Lee Dev
Tags: woocommerce, marketing, personalisation, audience profiling, privacy
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.0

== Description ==

IntentTarget Pro provides a white-labelled digital intelligence layer for WordPress and WooCommerce websites. It tracks authorised user engagement across active taxonomy sectors, allows users to manually set preferences via their WooCommerce account dashboard, and uses a propensity algorithm to display intent-based adverts. It includes robust controls to support UK GDPR and profiling regulations.

== Key Features ==

* **Propensity Scoring:** Automatically ranks user interest across active WordPress and WooCommerce taxonomy groups.
* **Dynamic Adverts:** Displays adverts matching the highest scoring intent category.
* **Upsell Mode:** Detects if a user has already purchased a specific product and automatically switches the active layout to an "Alternative" (Alt) call-to-action.
* **Management Suite:** Administrators can manage titles, descriptions, linked product IDs, destination URLs, scanner settings, styling controls, and access controls from the IntentTarget Pro dashboard.
* **Privacy Toggle:** Users can explicitly opt out of profiling directly from their account preferences page to view static default advertisements.
* **Digital Intelligence Panel:** Displays real-time propensity metrics and search histories on every WordPress user profile page for administrator review.

== File Structure ==

* `intenttarget-pro.php`: Loads the modular plugin framework, shortcodes, WooCommerce account integration, and activation routines.
* `core/`: Handles access control, cron scheduling, hook registration, parser logic, and propensity scoring.
* `admin/`: Handles the dashboard, management suite views, and sidebar components.
* `telemetry/`: Handles advertising display, AJAX callbacks, licence checks, and telemetry routines.
* `master-server-hub/`: Provides the standalone licence verification hub.

== Administration ==

To manage active frontend advertising content:
1. Navigate to **Interest Tracker** in your WordPress sidebar.
2. Open the **Dynamic Adverts** tab.
3. Set the **WooCommerce Product ID** used by the core logic to evaluate user purchase histories.
4. Update the **Primary Advert** and **Alternative Ad Line** messaging configurations.
5. Save your modifications.

== Propensity Logic & Privacy Flows ==

Scores are evaluated dynamically based on the following triggers:
* **Manual Input:** +50 points per category hit.
* **Page Views:** +5 points per visit to mapped taxonomy sectors.
* **Search Queries:** +10 points for relevant keyword matches.
* **Seasonal Boost:** Selected promotional categories can receive seasonal weighting where configured.
* **Opt-Out Bypassing:** If a user flags the "Show generic announcements instead of tailored recommendations" preference, all processing calculations stop immediately. The server purges historical tracking records from the user metadata table and falls back to rendering the default advert layout.

== Changelog ==

= 1.0.0 =
* Initial IntentTarget Pro commercial framework release.
* Added modular core, admin, telemetry, advertising, and master hub components.
* Added resource-safe background tracking architecture and white-labelled dashboard controls.