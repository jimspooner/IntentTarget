<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Extract active categories and product categories dynamically
 */
function lee_dev_get_active_categories_5921() {
    $categories = array();

    // Fetch active WordPress categories
    $wp_terms = get_terms( array(
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ) );
    if ( ! is_wp_error( $wp_terms ) && ! empty( $wp_terms ) ) {
        foreach ( $wp_terms as $term ) {
            $categories[$term->slug] = $term->name . ' (Category)';
        }
    }

    // Fetch active WooCommerce product categories
    if ( taxonomy_exists( 'product_cat' ) ) {
        $wc_terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );
        if ( ! is_wp_error( $wc_terms ) && ! empty( $wc_terms ) ) {
            foreach ( $wc_terms as $term ) {
                $categories[$term->slug] = $term->name . ' (Product Category)';
            }
        }
    }

    return apply_filters( 'lee_dev_active_categories_5921', $categories );
}

/**
 * Master Text Sweeper Engine (Smart Hierarchical Substring Pass)
 */
function lee_dev_execute_combined_content_scan_1289($post_id, $post) {
    if ( ! $post ) return;
    
    do_action( 'lee_dev_before_post_scan_1289', $post_id, $post );

    $current_type = ! empty($post->post_type) ? $post->post_type : 'post';
    $dictionary = get_option('cit_dynamic_keyword_dictionary', []);
    if ( empty($dictionary) ) return;

    $target_phrases = [];
    foreach ($dictionary as $slugs_array) {
        if ( is_array($slugs_array) ) {
            foreach ( $slugs_array as $slug ) {
                $clean_slug = strtolower(trim($slug));
                if ( ! empty($clean_slug) ) {
                    $target_phrases[] = $clean_slug;
                }
            }
        }
    }
    
    usort($target_phrases, function($a, $b) {
        return strlen($b) - strlen($a);
    });

    $master_string = '';

    // CRITICAL LOW-MEMORY SAFETY GUARD: Standard blog posts & pages NEVER scan title, content, or full body copy.
    // They are evaluated strictly via active public Taxonomy terms.
    $scannable_post_types = apply_filters( 'lee_dev_scannable_post_types_1289', array( 'post', 'page', 'product' ) );
    
    if ( in_array( $current_type, $scannable_post_types, true ) ) {
        if ( $current_type === 'post' || $current_type === 'page' ) {
            $taxonomy_words = [];
            $taxonomies = get_object_taxonomies( $post );
            if ( ! empty( $taxonomies ) ) {
                foreach ( $taxonomies as $taxonomy ) {
                    $terms = wp_get_object_terms( $post_id, $taxonomy );
                    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                        foreach ( $terms as $term ) {
                            $taxonomy_words[] = strtolower( $term->name );
                            $taxonomy_words[] = strtolower( $term->slug );
                            $taxonomy_words[] = str_replace( array( '-', '_' ), ' ', strtolower( $term->slug ) );
                        }
                    }
                }
            }
            $master_string = implode(' ', $taxonomy_words);
        } else {
            // Other post types (e.g. product) are allowed to scan full body fields
            $raw_text_pool = [];
            $raw_text_pool[] = $post->post_title;
            $raw_text_pool[] = $post->post_content;
            $raw_text_pool[] = $post->post_excerpt;

            if ( $current_type === 'product' && class_exists('WooCommerce') ) {
                if ( function_exists('wc_get_product') ) {
                    $product = wc_get_product($post_id);
                    if ( $product ) {
                        $raw_text_pool[] = $product->get_short_description();
                    }
                }
            }

            $all_meta_fields = get_post_meta($post_id);
            if ( ! empty($all_meta_fields) && is_array($all_meta_fields) ) {
                foreach ( $all_meta_fields as $meta_key => $meta_values ) {
                    if ( strpos($meta_key, '_') === 0 ) continue; 
                    if ( is_array($meta_values) ) {
                        foreach ( $meta_values as $value ) {
                            if ( is_object($value) ) continue;
                            if ( is_array($value) ) {
                                $raw_text_pool[] = implode(' ', array_filter(array_map('strval', $value)));
                            } elseif ( is_string($value) || is_numeric($value) ) {
                                $raw_text_pool[] = (string) $value;
                            }
                        }
                    }
                }
            }

            $master_string = implode(' ', $raw_text_pool);
        }
    }

    $master_string = strip_tags(strip_shortcodes($master_string));
    $master_string = strtolower($master_string);
    $master_string = str_replace(['&nbsp;', "\xc2\xa0", '-', '_'], ' ', $master_string);
    $master_string = preg_replace('/[.,\/#!$%\^&\*;:{}=`~()?"’‘“”\n\r]/', ' ', $master_string);
    $master_string = preg_replace('/\s+/', ' ', $master_string);

    $matched_labels = [];

    foreach ( $target_phrases as $phrase ) {
        if ( empty($phrase) ) continue;

        if ( strpos($master_string, $phrase) !== false ) {
            $already_covered = false;
            if ( strpos($phrase, ' ') === false ) { 
                foreach ( $matched_labels as $matched_phrase ) {
                    if ( strpos($matched_phrase, $phrase) !== false ) {
                        $already_covered = true;
                        break;
                    }
                }
            }

            if ( ! $already_covered ) {
                $matched_labels[] = $phrase;
            }
        }
    }

    $matched_labels = array_unique(array_filter($matched_labels));
    $matched_labels = apply_filters( 'lee_dev_matched_labels_1289', $matched_labels, $post_id, $post );

    if ( ! empty($matched_labels) ) {
        update_post_meta($post_id, '_cit_tracking_labels', array_values($matched_labels));
    } else {
        delete_post_meta($post_id, '_cit_tracking_labels');
    }

    do_action( 'lee_dev_after_post_scan_1289', $post_id, $matched_labels );
}
