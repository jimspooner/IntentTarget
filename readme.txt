=== CIT Interests & Propensity Advertising ===
Contributors: The Andersons Centre
Tags: woocommerce, marketing, personalization, agriculture, privacy
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.3.0

== Description ==

This plugin provides a "Digital Intelligence" layer for The Andersons Centre website. It tracks user engagement across sectors, allows users to manually set preferences via their WooCommerce account dashboard, and uses a propensity algorithm to display intent-based adverts. It includes robust controls to comply with UK GDPR and profiling regulations.

== Key Features ==

* **Propensity Scoring:** Automatically ranks user interest across five distinct business groups: Sales, News, Seminars, Brand (About), and Environment.
* **Dynamic Adverts:** Displays adverts matching the highest scoring intent category.
* **Upsell Mode:** Detects if a user has already purchased a specific product and automatically switches the active layout to an "Alternative" (Alt) call-to-action.
* **ACF Integration:** Administrators can easily manage titles, descriptions, linked product IDs, and destination URLs via a settings hub placed directly under the WooCommerce Marketing tab.
* **Privacy Toggle:** Users can explicitly opt out of profiling directly from their account preferences page to view static default advertisements.
* **Digital Intelligence Panel:** Displays real-time propensity metrics and search histories on every WordPress user profile page for administrator review.

== File Structure ==

* `cit-interests.php`: Handles core logic, processing form updates, propensity scoring equations, and shortcode rendering.
* `acf-interests.php`: Handles programmatic registration of custom ACF options panels and dashboard layout fields.

== Administration ==

To manage active frontend advertising content:
1. Navigate to **WooCommerce > Interest Adverts** in your WordPress sidebar.
2. Expand the accordion tab for the desired sector (e.g., Environment & Sustainability).
3. Set the **Product ID** (used by the core logic to evaluate user purchase histories).
4. Update the **Main** (Pre-purchase) and **Alt** (Post-purchase) messaging configurations.
5. Save your modifications.

== Propensity Logic & Privacy Flows ==

Scores are evaluated dynamically based on the following triggers:
* **Manual Input:** +50 points per category hit.
* **Page Views:** +5 points per visit to mapped taxonomy sectors.
* **Search Queries:** +10 points for relevant keyword matches.
* **Seasonal Boost:** Seminars automatically gain a 1.5x multiplier from January to April.
* **Opt-Out Bypassing:** If a user flags the "Show generic announcements instead of tailored recommendations" preference, all processing calculations stop immediately. The server purges historical tracking records from the user metadata table and falls back to rendering the core Sales layout (John Nix Pocketbook).

== Changelog ==

= 1.3.0 =
* Integrated a native UK GDPR "Right to Object" opt-out toggle within the frontend preferences layout.
* Added custom data retention cleanup procedures that clear database tables upon opt-out triggers.
* Resolved a fatal function error present during fallback evaluation conditions.

= 1.2.0 =
* Added "Environment & Sustainability" sector across all processing routines and admin panel views.
* Migrated configuration arrays to an ACF Options Page positioned under the WooCommerce Marketing section.
* Refactored workspace structure to separate local field registrations into `acf-interests.php`.
* Added standard UIkit accordion layout controls and a "Select All" feature to frontend options.

= 1.1.0 =
* Initial framework release configuring tracker logic for Sales, News, Seminars, and Brand categories.