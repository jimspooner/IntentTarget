# IntentTarget Pro - Core Project Blueprint

## 1. Global Coding & Brand Standards
- **Plugin Identity:** IntentTarget Pro (Commercial Framework)
- **Language Formatting:** Strict PHP 8.x compatibility using strict UK English spellings ('centre', 'customise', 'categorise').
- **Modular Framework Hooks:** Expose structural changes using core WordPress action and filter hooks (`do_action` and `apply_filters`) so external add-on plugins can hook in without altering our core file.
- **Security Protocols:** Protect all form processing blocks using `check_admin_referer` and safely escape all output fields using `esc_html` and `esc_textarea`.

## 2. Structural Architecture & Database Constants
- **Master Options Key:** `cit_dynamic_keyword_dictionary` inside the `wp_options` table.
- **Tracking Badge Key:** Active evaluated content metrics must save to post metadata using `_cit_tracking_labels` as an array.
- **Cron Scheduling Key:** Store execution flags in `_cit_last_tracked_time` to balance server query frequencies.

## 3. High-Priority System Constraints
- **Low-Memory Safety Guard:** Standard blog posts (`post`) and standard layout pages (`page`) must NEVER have their raw headlines, text content, or full body copy processed to harvest keywords. They must be handled strictly via active public Taxonomies (Categories and Tags) to protect client servers from memory time-outs.
- **Overlap Deduplication Filter:** Standard words must be filtered against multi-word combinations. If a single word (e.g., 'centre') is contained within a longer phrase (e.g., 'the andersons centre'), the standalone keyword must be thrown out automatically to prevent database clutter.

## 4. Multi-Tiered Commercial Product Roadmap
- **Phase 1 (Current Core System):** Resource-safe content profiling using a background batch engine (WordPress Cron).
- **Phase 2 (Next Immediate Step):** Decouple hardcoded dictionary rows. Refactor the dashboard so the editing textareas dynamically load and label themselves based on the site's active WordPress and WooCommerce taxonomy entries.
- **Phase 3 (Add-On Expansion Packs):** Prepare filter hooks to accept a premium WooCommerce Metadata Extension and an SEO AI Side-Panel Assistant.
- **Phase 4 (Enterprise Predictive Suite):** Implement a Multi-Armed Bandit analytics engine to automatically display the most profitable advert based on the page interest scores.
