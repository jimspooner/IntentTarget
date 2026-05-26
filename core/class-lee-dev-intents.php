<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hardcoded Intent Categories
 *
 * IntentTarget Pro replaces the legacy dynamic taxonomy grouping with a fixed
 * five-bucket intent matrix. Discovered keyphrases are classified into one of
 * these buckets via the signal lexicon below.
 *
 * @return array<string,string> slug => human label
 */
function lee_dev_get_intent_categories_3812() {
    $categories = array(
        'transactional-intent' => 'Transactional Intent',
        'informational-intent' => 'Informational Intent',
        'engagement-intent'    => 'Engagement Intent',
        'commercial-intent'    => 'Commercial Intent',
        'specialist-intent'    => 'Specialist Intent',
    );

    return apply_filters( 'lee_dev_intent_categories_3812', $categories );
}

/**
 * Maximum number of keyphrases retained per intent category.
 */
function lee_dev_get_intent_dictionary_capacity_5083() {
    return (int) apply_filters( 'lee_dev_intent_dictionary_capacity_5083', 50 );
}

/**
 * Signal lexicon used to classify a discovered phrase into one intent bucket.
 *
 * Specialist Intent is the niche/business-specific catch-all and therefore has
 * no signals: any phrase that does not match the four upstream lexicons falls
 * through to Specialist Intent.
 *
 * Order of evaluation (priority): Transactional > Commercial > Engagement >
 * Informational > Specialist.
 *
 * @return array<string,string[]>
 */
function lee_dev_get_intent_signal_lexicon_4275() {
    $lexicon = array(
        'transactional-intent' => array(
            'buy', 'order', 'purchase', 'shop', 'basket', 'cart', 'checkout',
            'hire', 'rent', 'lease', 'book', 'booking', 'reserve', 'reservation',
            'sale', 'sales', 'deal', 'deals', 'discount', 'discounts',
            'coupon', 'voucher', 'promo', 'promotion', 'offer', 'offers',
            'price', 'prices', 'pricing', 'cost', 'quote', 'quotation',
            'subscribe', 'subscription', 'sign up', 'signup',
            'free trial', 'trial',
            'delivery', 'shipping', 'same day', 'next day',
            'payment', 'finance', 'instalment', 'installment', 'gift card',
        ),
        'commercial-intent' => array(
            'best', 'top', 'top 10', 'top ten', 'top rated',
            'compare', 'comparison', 'comparing',
            'vs', 'versus',
            'alternative', 'alternatives',
            'review', 'reviews', 'reviewed',
            'rated', 'rating', 'ratings',
            'recommend', 'recommended', 'recommendation', 'recommendations',
            'options', 'choose', 'choosing', 'which',
            'pros and cons', 'pros', 'cons',
            'leading', 'popular', 'premium', 'professional',
            'ranked', 'ranking', 'rankings',
            'award', 'award winning', 'awards',
            'certified', 'accredited', 'trusted', 'verified',
        ),
        'engagement-intent' => array(
            'contact', 'contact us', 'call us', 'email us', 'get in touch',
            'enquire', 'enquiry', 'enquiries', 'contact form',
            'customer service', 'support', 'helpline', 'helpdesk', 'help desk',
            'phone', 'message', 'message us',
            'follow', 'share', 'comment', 'comments', 'like',
            'newsletter',
            'join', 'member', 'members', 'membership',
            'community', 'forum', 'group', 'groups',
            'event', 'events', 'webinar', 'webinars', 'meetup', 'meet up',
            'workshop', 'workshops', 'conference', 'conferences',
            'register', 'registration',
            'social', 'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok',
        ),
        'informational-intent' => array(
            'how', 'how to', 'what', 'what is', 'what are', 'what does',
            'why', 'why does', 'why do',
            'when', 'when does', 'when do', 'when is',
            'where', 'where does', 'where do', 'where is',
            'who', 'who is', 'who does',
            'guide', 'guides', 'tutorial', 'tutorials',
            'learn', 'learning', 'lesson', 'lessons', 'course', 'courses',
            'tips', 'advice',
            'definition', 'meaning', 'basics', 'beginner', 'beginners',
            'intro', 'introduction', 'overview', 'explained', 'explainer',
            'faq', 'frequently asked',
            'examples', 'ideas', 'history', 'types', 'walkthrough',
            'checklist', 'cheat sheet', 'cheatsheet',
        ),
        'specialist-intent' => array(),
    );

    return apply_filters( 'lee_dev_intent_signal_lexicon_4275', $lexicon );
}

/**
 * Classify a single phrase into one of the five hardcoded intent slugs.
 *
 * Matching is whole-word (regex word boundaries) and case-insensitive. Phrases
 * that match nothing fall through to specialist-intent.
 *
 * @param string $phrase
 * @return string Intent slug.
 */
function lee_dev_classify_phrase_into_intent_8264( $phrase ) {
    $phrase = strtolower( trim( (string) $phrase ) );
    if ( $phrase === '' ) {
        return 'specialist-intent';
    }

    $priority = array(
        'transactional-intent',
        'commercial-intent',
        'engagement-intent',
        'informational-intent',
    );
    $priority = apply_filters( 'lee_dev_intent_classifier_priority_8264', $priority );

    $lexicon = lee_dev_get_intent_signal_lexicon_4275();

    foreach ( $priority as $intent_slug ) {
        if ( empty( $lexicon[$intent_slug] ) || ! is_array( $lexicon[$intent_slug] ) ) {
            continue;
        }

        foreach ( $lexicon[$intent_slug] as $signal ) {
            $signal = strtolower( trim( (string) $signal ) );
            if ( $signal === '' ) {
                continue;
            }
            $pattern = '/(^|\b)' . preg_quote( $signal, '/' ) . '(\b|$)/u';
            if ( preg_match( $pattern, $phrase ) === 1 ) {
                return $intent_slug;
            }
        }
    }

    return 'specialist-intent';
}

/**
 * Centralised stop-word and platform-noise blacklist used by every scanner.
 *
 * @return string[]
 */
function lee_dev_get_intent_scanner_blacklist_9447() {
    $default_blacklist = array_merge(
        // 1. WordPress & WooCommerce defaults
        array(
            'uncategorised', 'uncategorized', 'exclude-from-catalog',
            'exclude-from-search', 'featured', 'format-standard',
            'category', 'tag',
        ),
        // 2. English stop words
        array(
            'the', 'and', 'for', 'with', 'from', 'this', 'that', 'your',
            'will', 'have', 'are', 'was', 'were',
            'their', 'they', 'them', 'our', 'we', 'you',
            'has', 'had', 'been', 'but', 'not',
            'all', 'any', 'one', 'out', 'up', 'down', 'into', 'over',
            'after', 'about', 'which', 'there', 'then', 'than',
            'other', 'some', 'such', 'only', 'und',
        ),
        // 3. Web navigation & UI text
        array(
            'click', 'here', 'read', 'more', 'view', 'page', 'post',
            'reply', 'menu', 'home', 'results', 'submit',
            'add', 'item', 'privacy', 'policy', 'terms', 'conditions',
        ),
        // 4. Recurring noise spotted in legacy scanner audit
        array(
            'bishops', 'subdued', 'subdue', 'subside', 'subsided', 'subsiding',
            'subject', 'subjects', 'subjectof', 'subsequent', 'subsequently',
            'subordinate', 'subdivided', 'subdivide', 'subtraction', 'subtly',
            'subtle', 'substandard', 'republic', 'republicans', 'disorder',
            'sortorder', 'subsumed', 'subsuming', 'subsume', 'substations',
            'sublease', 'sweatshop',
        )
    );

    $filtered_blacklist = apply_filters( 'lee_dev_intent_scanner_blacklist_9447', $default_blacklist );
    if ( ! is_array( $filtered_blacklist ) ) {
        $filtered_blacklist = $default_blacklist;
    }

    return array_values( array_unique( array_map( 'strtolower', array_filter( array_map( 'trim', $filtered_blacklist ) ) ) ) );
}

/**
 * Normalise a raw text fragment for tokenisation and matching.
 *
 * @param string $raw
 * @return string
 */
function lee_dev_normalise_intent_text_2754( $raw ) {
    $text = is_string( $raw ) ? $raw : '';
    if ( $text === '' ) {
        return '';
    }
    $text = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', ' ', $text );
    $text = strip_shortcodes( $text );
    $text = wp_strip_all_tags( $text, true );
    $text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
    $text = strtolower( $text );
    $text = str_replace( array( '&nbsp;', "\xc2\xa0", '-', '_' ), ' ', $text );
    $text = preg_replace( '/[.,\/#!$%\^&\*;:{}=`~()?"\'’‘“”\n\r]/u', ' ', $text );
    $text = preg_replace( '/\s+/', ' ', $text );

    return trim( $text );
}

/**
 * Maximum word-count permitted for any candidate phrase produced by the
 * extractor. Phrases beyond this cap are never emitted so the dictionary
 * stays compact and matchable against page corpora. Filterable.
 *
 * @return int
 */
function lee_dev_get_max_phrase_word_count_5174() {
    return (int) apply_filters( 'lee_dev_max_phrase_word_count_5174', 3 );
}

/**
 * Extract candidate phrases from a text fragment as 1..N word n-grams,
 * where N is capped by lee_dev_get_max_phrase_word_count_5174() (default 3).
 *
 * Example output for "buy organic farm management pocketbook" (cap 3):
 *   1-grams: buy, organic, farm, management, pocketbook
 *   2-grams: buy organic, organic farm, farm management, management pocketbook
 *   3-grams: buy organic farm, organic farm management, farm management pocketbook
 *
 * @param string   $raw
 * @param string[] $blacklist
 * @return string[]
 */
function lee_dev_extract_candidate_phrases_from_text_7506( $raw, $blacklist = array() ) {
    $clean = lee_dev_normalise_intent_text_2754( $raw );
    if ( $clean === '' ) {
        return array();
    }

    if ( empty( $blacklist ) || ! is_array( $blacklist ) ) {
        $blacklist = lee_dev_get_intent_scanner_blacklist_9447();
    }

    $tokens = array_filter( array_map( 'trim', explode( ' ', $clean ) ) );
    $valid_tokens = array();
    foreach ( $tokens as $token ) {
        if ( strlen( $token ) < 3 ) {
            continue;
        }
        if ( in_array( $token, $blacklist, true ) ) {
            continue;
        }
        if ( ! preg_match( '/^[a-z][a-z0-9]*$/', $token ) ) {
            continue;
        }
        $valid_tokens[] = $token;
    }

    if ( empty( $valid_tokens ) ) {
        return array();
    }

    $max_words = max( 1, lee_dev_get_max_phrase_word_count_5174() );
    $token_count = count( $valid_tokens );
    $candidates = array();

    // Emit n-grams from 1 up to $max_words.
    for ( $n = 1; $n <= $max_words; $n++ ) {
        if ( $n > $token_count ) {
            break;
        }
        $last_start = $token_count - $n;
        for ( $i = 0; $i <= $last_start; $i++ ) {
            $candidates[] = implode( ' ', array_slice( $valid_tokens, $i, $n ) );
        }
    }

    return array_values( array_unique( $candidates ) );
}

/**
 * Resolve the taxonomy term names+slugs assigned to an asset, honouring the
 * relevant taxonomy per post type:
 *  - product   -> product_cat (+ product_tag where present)
 *  - post/page -> category, post_tag
 *
 * @param int    $post_id
 * @param string $post_type
 * @return string[] flat list of human-readable term phrases
 */
function lee_dev_collect_asset_taxonomy_phrases_5912( $post_id, $post_type ) {
    $phrases = array();

    if ( $post_type === 'product' && class_exists( 'WooCommerce' ) ) {
        $target_taxonomies = array( 'product_cat', 'product_tag' );
    } elseif ( $post_type === 'post' ) {
        $target_taxonomies = array( 'category', 'post_tag' );
    } else {
        // Pages do not need taxonomies under the 3-pronged rule
        return $phrases;
    }

    $terms = wp_get_object_terms( (int) $post_id, $target_taxonomies );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return $phrases;
    }

    foreach ( $terms as $term ) {
        if ( ! is_object( $term ) ) {
            continue;
        }
        if ( ! empty( $term->name ) ) {
            $phrases[] = (string) $term->name;
        }
        if ( ! empty( $term->slug ) ) {
            $phrases[] = str_replace( array( '-', '_' ), ' ', (string) $term->slug );
        }
    }

    return $phrases;
}

/**
 * Build the public meta corpus for a page asset (or any post type the user
 * elects to apply page-style scanning to).
 *
 * Only public meta keys (those that do NOT start with an underscore) are
 * harvested, which intentionally captures ACF field values while excluding
 * private/system meta and avoids the heavy serialised payloads such as Yoast
 * primary-term cache or Elementor data caches.
 *
 * @param int $post_id
 * @return string[]
 */
function lee_dev_collect_asset_public_meta_phrases_8137( $post_id ) {
    $phrases = array();
    $all_meta = get_post_meta( (int) $post_id );
    if ( empty( $all_meta ) || ! is_array( $all_meta ) ) {
        return $phrases;
    }

    foreach ( $all_meta as $meta_key => $meta_values ) {
        if ( strpos( (string) $meta_key, '_' ) === 0 ) {
            continue;
        }
        if ( ! is_array( $meta_values ) ) {
            continue;
        }
        foreach ( $meta_values as $value ) {
            if ( is_object( $value ) ) {
                continue;
            }
            $maybe_unserialised = maybe_unserialize( $value );
            if ( is_array( $maybe_unserialised ) ) {
                array_walk_recursive( $maybe_unserialised, function( $leaf ) use ( &$phrases ) {
                    if ( is_string( $leaf ) || is_numeric( $leaf ) ) {
                        $phrases[] = (string) $leaf;
                    }
                } );
            } elseif ( is_string( $maybe_unserialised ) || is_numeric( $maybe_unserialised ) ) {
                $phrases[] = (string) $maybe_unserialised;
            }
        }
    }

    return $phrases;
}

/**
 * Build the normalised search corpus for a post under the 3-pronged rule.
 *
 *  - product : title + assigned product taxonomies + content + excerpt + short description
 *  - post    : title + assigned categories/tags
 *  - page    : title + content + public meta (incl. ACF fields)
 *
 * @param WP_Post|null $post
 * @return string lowercase, normalised, space-collapsed corpus
 */
function lee_dev_build_asset_search_corpus_4216( $post ) {
    if ( ! $post || ! is_object( $post ) || empty( $post->ID ) ) {
        return '';
    }

    $post_type = ! empty( $post->post_type ) ? (string) $post->post_type : 'post';
    $fragments = array();

    if ( $post_type === 'product' && class_exists( 'WooCommerce' ) ) {
        $fragments[] = (string) $post->post_title;
        $fragments[] = (string) $post->post_content;
        $fragments[] = (string) $post->post_excerpt;
        if ( function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $post->ID );
            if ( $product ) {
                $fragments[] = (string) $product->get_short_description();
            }
        }
        $fragments = array_merge( $fragments, lee_dev_collect_asset_taxonomy_phrases_5912( $post->ID, 'product' ) );
    } elseif ( $post_type === 'post' ) {
        $fragments[] = (string) $post->post_title;
        $fragments = array_merge( $fragments, lee_dev_collect_asset_taxonomy_phrases_5912( $post->ID, 'post' ) );
    } elseif ( $post_type === 'page' ) {
        $fragments[] = (string) $post->post_title;
        $fragments[] = (string) $post->post_content;
        $fragments = array_merge( $fragments, lee_dev_collect_asset_public_meta_phrases_8137( $post->ID ) );
    } else {
        // Unknown CPTs: be conservative and use title + taxonomy only
        $fragments[] = (string) $post->post_title;
    }

    $combined = implode( ' ', array_filter( array_map( 'strval', $fragments ) ) );
    $corpus = lee_dev_normalise_intent_text_2754( $combined );

    return apply_filters( 'lee_dev_asset_search_corpus_4216', $corpus, $post );
}

/**
 * Canonical mapping from each hardcoded intent slug to one or more Site
 * Purpose keys (as defined in lee_dev_get_valid_site_intents_6048()).
 *
 * Drives the user-aware advert routing layer in
 * lee_dev_resolve_user_intent_priority_4762(). Filterable so installations
 * can override the canonical mapping.
 *
 *   transactional-intent  -> driving_sales
 *   commercial-intent     -> driving_sales
 *   informational-intent  -> educating_audiences
 *   engagement-intent     -> generating_leads, customer_support
 *   specialist-intent     -> (no automatic mapping; fall through to admin)
 *
 * @return array<string,string[]>
 */
function lee_dev_get_intent_to_site_purpose_map_5614() {
    $map = array(
        'transactional-intent' => array( 'driving_sales' ),
        'commercial-intent'    => array( 'driving_sales' ),
        'informational-intent' => array( 'educating_audiences' ),
        'engagement-intent'    => array( 'generating_leads', 'customer_support' ),
        'specialist-intent'    => array(),
    );

    return apply_filters( 'lee_dev_intent_to_site_purpose_map_5614', $map );
}

/**
 * Resolve the Site Purpose waterfall for a specific user using their
 * intent-category propensity scores.
 *
 * Behaviour:
 *  - Anonymous users (user_id <= 0)            -> admin-saved priority unchanged.
 *  - Opted-out users (itp_disable_tracking)    -> admin-saved priority unchanged.
 *  - Users with no positive intent signal      -> admin-saved priority unchanged.
 *  - Otherwise the highest-scoring intent's mapped Site Purpose is bumped to
 *    the top, then the next highest, and so on. Any Site Purposes not
 *    expressed by the user's intent profile are appended in their admin-saved
 *    order so the waterfall is always exhaustive.
 *
 * @param int $user_id
 * @return string[] ordered Site Purpose keys
 */
function lee_dev_resolve_user_intent_priority_4762( $user_id ) {
    $admin_priority = function_exists( 'lee_dev_get_global_priority_order_7136' )
        ? lee_dev_get_global_priority_order_7136()
        : array();

    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return $admin_priority;
    }

    if ( get_user_meta( $user_id, 'itp_disable_tracking', true ) ) {
        return $admin_priority;
    }

    if ( ! function_exists( 'lee_dev_calculate_group_propensity_1289' ) ) {
        return $admin_priority;
    }

    $intent_slugs = array_keys( lee_dev_get_intent_categories_3812() );
    $scored = array();
    foreach ( $intent_slugs as $intent_slug ) {
        $score = (int) lee_dev_calculate_group_propensity_1289( $intent_slug, $user_id );
        if ( $score > 0 ) {
            $scored[$intent_slug] = $score;
        }
    }

    if ( empty( $scored ) ) {
        return $admin_priority;
    }

    arsort( $scored, SORT_NUMERIC );

    $map = lee_dev_get_intent_to_site_purpose_map_5614();
    $waterfall = array();

    foreach ( array_keys( $scored ) as $intent_slug ) {
        if ( empty( $map[$intent_slug] ) || ! is_array( $map[$intent_slug] ) ) {
            continue;
        }
        foreach ( $map[$intent_slug] as $purpose ) {
            if ( ! in_array( $purpose, $waterfall, true ) ) {
                $waterfall[] = (string) $purpose;
            }
        }
    }

    foreach ( $admin_priority as $purpose ) {
        if ( ! in_array( $purpose, $waterfall, true ) ) {
            $waterfall[] = (string) $purpose;
        }
    }

    return apply_filters( 'lee_dev_user_intent_priority_4762', $waterfall, $user_id, $scored, $admin_priority );
}

/**
 * Sanitise a single manual phrase received from the sidebar editor.
 *
 *  - Lowercased, trimmed, punctuation stripped via the corpus normaliser.
 *  - Truncated to the active n-gram word cap (default 3 words).
 *  - Empty result is allowed and signals "reject this input".
 *
 * @param string $raw
 * @return string
 */
function lee_dev_sanitise_manual_label_phrase_6749( $raw ) {
    $clean = function_exists( 'lee_dev_normalise_intent_text_2754' )
        ? lee_dev_normalise_intent_text_2754( $raw )
        : strtolower( trim( (string) $raw ) );

    if ( $clean === '' ) {
        return '';
    }

    $words = array_values( array_filter( array_map( 'trim', explode( ' ', $clean ) ) ) );
    if ( empty( $words ) ) {
        return '';
    }

    $max_words = function_exists( 'lee_dev_get_max_phrase_word_count_5174' )
        ? lee_dev_get_max_phrase_word_count_5174()
        : 3;
    $max_words = max( 1, (int) $max_words );

    $words = array_slice( $words, 0, $max_words );
    return implode( ' ', $words );
}

/**
 * Read the manual override sidecar for a post. Returns a normalised structure
 * with two flat lists keyed 'added' (force-include) and 'removed' (force-exclude).
 *
 * @param int $post_id
 * @return array{added:string[], removed:string[]}
 */
function lee_dev_get_manual_label_overrides_4861( $post_id ) {
    $raw = get_post_meta( (int) $post_id, '_itp_manual_label_overrides', true );
    if ( ! is_array( $raw ) ) {
        $raw = array();
    }

    $normalise = function( $list ) {
        if ( ! is_array( $list ) ) {
            return array();
        }
        $out = array();
        foreach ( $list as $entry ) {
            $clean = lee_dev_sanitise_manual_label_phrase_6749( $entry );
            if ( $clean !== '' ) {
                $out[] = $clean;
            }
        }
        return array_values( array_unique( $out ) );
    };

    return array(
        'added'   => isset( $raw['added'] )   ? $normalise( $raw['added'] )   : array(),
        'removed' => isset( $raw['removed'] ) ? $normalise( $raw['removed'] ) : array(),
    );
}

/**
 * Persist manual overrides for a post. Pass the normalised structure produced
 * by lee_dev_get_manual_label_overrides_4861() (possibly mutated).
 *
 * @param int   $post_id
 * @param array $overrides
 * @return void
 */
function lee_dev_save_manual_label_overrides_4861( $post_id, $overrides ) {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 ) {
        return;
    }

    $payload = array(
        'added'   => array(),
        'removed' => array(),
    );

    if ( is_array( $overrides ) ) {
        if ( isset( $overrides['added'] ) && is_array( $overrides['added'] ) ) {
            foreach ( $overrides['added'] as $entry ) {
                $clean = lee_dev_sanitise_manual_label_phrase_6749( $entry );
                if ( $clean !== '' ) {
                    $payload['added'][] = $clean;
                }
            }
            $payload['added'] = array_values( array_unique( $payload['added'] ) );
        }

        if ( isset( $overrides['removed'] ) && is_array( $overrides['removed'] ) ) {
            foreach ( $overrides['removed'] as $entry ) {
                $clean = lee_dev_sanitise_manual_label_phrase_6749( $entry );
                if ( $clean !== '' ) {
                    $payload['removed'][] = $clean;
                }
            }
            $payload['removed'] = array_values( array_unique( $payload['removed'] ) );
        }
    }

    if ( empty( $payload['added'] ) && empty( $payload['removed'] ) ) {
        delete_post_meta( $post_id, '_itp_manual_label_overrides' );
        return;
    }

    update_post_meta( $post_id, '_itp_manual_label_overrides', $payload );
}

/**
 * Merge the scanner output with the manual override sidecar:
 *  - Anything in `removed` is force-excluded from the final list.
 *  - Anything in `added` is force-included in the final list.
 *
 * Used by both the per-post save scanner and the hourly cron batch so manual
 * curations survive any future scan.
 *
 * @param string[] $scanner_labels
 * @param int      $post_id
 * @return string[]
 */
function lee_dev_apply_manual_label_overrides_2937( $scanner_labels, $post_id ) {
    $normalised = array();
    if ( is_array( $scanner_labels ) ) {
        foreach ( $scanner_labels as $label ) {
            $clean = strtolower( trim( (string) $label ) );
            if ( $clean !== '' ) {
                $normalised[] = $clean;
            }
        }
    }
    $normalised = array_values( array_unique( $normalised ) );

    $overrides = lee_dev_get_manual_label_overrides_4861( $post_id );

    if ( ! empty( $overrides['removed'] ) ) {
        $normalised = array_values( array_diff( $normalised, $overrides['removed'] ) );
    }

    if ( ! empty( $overrides['added'] ) ) {
        foreach ( $overrides['added'] as $manual_phrase ) {
            if ( $manual_phrase !== '' && ! in_array( $manual_phrase, $normalised, true ) ) {
                $normalised[] = $manual_phrase;
            }
        }
    }

    return array_values( array_unique( $normalised ) );
}

/**
 * Distribute a flat list of candidate phrases into the 5 hardcoded intent
 * buckets, capped at the dictionary capacity per bucket.
 *
 * @param string[] $candidates
 * @return array<string,string[]> intent_slug => phrases
 */
function lee_dev_bucket_candidates_into_intents_6024( $candidates ) {
    $buckets = array();
    foreach ( array_keys( lee_dev_get_intent_categories_3812() ) as $intent_slug ) {
        $buckets[$intent_slug] = array();
    }

    if ( ! is_array( $candidates ) || empty( $candidates ) ) {
        return $buckets;
    }

    $cap = lee_dev_get_intent_dictionary_capacity_5083();
    $seen = array();

    foreach ( $candidates as $phrase ) {
        $phrase = strtolower( trim( (string) $phrase ) );
        if ( $phrase === '' || isset( $seen[$phrase] ) ) {
            continue;
        }
        $seen[$phrase] = true;

        $intent_slug = lee_dev_classify_phrase_into_intent_8264( $phrase );
        if ( ! isset( $buckets[$intent_slug] ) ) {
            $intent_slug = 'specialist-intent';
        }
        if ( count( $buckets[$intent_slug] ) >= $cap ) {
            continue;
        }
        $buckets[$intent_slug][] = $phrase;
    }

    // Final sub-string deduplication per bucket (drop short tokens already
    // covered by a longer phrase already in the same bucket).
    foreach ( $buckets as $intent_slug => $phrases ) {
        $filtered = array();
        foreach ( $phrases as $phrase ) {
            $covered = false;
            if ( strpos( $phrase, ' ' ) === false ) {
                foreach ( $phrases as $comparison ) {
                    if ( $phrase !== $comparison && strpos( $comparison, $phrase ) !== false ) {
                        $covered = true;
                        break;
                    }
                }
            }
            if ( ! $covered ) {
                $filtered[] = $phrase;
            }
        }
        $buckets[$intent_slug] = array_values( array_slice( $filtered, 0, $cap ) );
    }

    return $buckets;
}
