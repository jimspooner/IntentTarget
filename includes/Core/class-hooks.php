<?php
/**
 * IntentTarget Core — Frontend Hooks, Styles & Search Capture
 *
 * @package IntentTarget\Core
 */

namespace IntentTarget\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hooks
 */
class Hooks {

	/**
	 * Register all frontend and database hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_custom_styles' ) );
		add_action( 'wp_head', array( __CLASS__, 'inject_customiser_css_overrides' ), 100 );
		add_action( 'template_redirect', array( __CLASS__, 'intercept_search_requests' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'boost_interests_on_purchase' ), 10, 1 );
		}
	}

	/**
	 * Set up the search feedback database table.
	 *
	 * @return void
	 */
	public static function setup_search_insights_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'itp_search_feedback';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			search_query varchar(255) NOT NULL,
			found_result varchar(50) NOT NULL,
			feedback_notes text NOT NULL,
			submitted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Enqueue custom styles when the preferences shortcode or account page is present.
	 *
	 * @return void
	 */
	public static function enqueue_custom_styles(): void {
		if ( ! Access::is_ready() ) {
			return;
		}

		$should_load = false;
		global $post;

		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'itp_user_preferences' ) || has_shortcode( $post->post_content, 'itp_preferences_dashboard' ) ) ) {
			$should_load = true;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$should_load = true;
		}

		if ( $should_load ) {
			wp_enqueue_style(
				'itp-custom-interests',
				plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'custom-interests.css',
				array(),
				'2.0.0'
			);
		}
	}

	/**
	 * Inject dynamic palette CSS variables into the frontend head.
	 *
	 * @return void
	 */
	public static function inject_customiser_css_overrides(): void {
		$colors = get_option( 'itp_design_settings', array() );

		$c_heading     = esc_attr( $colors['heading'] ?? '#074e85' );
		$c_recommended = esc_attr( $colors['recommended'] ?? '#e1ad01' );
		$c_button      = esc_attr( $colors['button'] ?? '#074e85' );
		$c_btn_text    = esc_attr( $colors['button_text'] ?? '#ffffff' );
		?>
		<style id="itp-design-customiser-overrides">
			#intent-slidein h4,
			.itp-dashboard-offer h3,
			.uk-text-spot1 { color: <?php echo $c_heading; ?> !important; }
			#intent-slidein .uk-label,
			.itp-dashboard-offer .uk-label,
			.itp-preferences-wrap .uk-label { background-color: <?php echo $c_recommended; ?> !important; color: #ffffff !important; }
			.uk-button-spot1,
			#intent-slidein .uk-button-spot1,
			.itp-dashboard-offer .uk-button-spot1,
			.itp-preferences-wrap .uk-button-spot1 { background-color: <?php echo $c_button; ?> !important; color: <?php echo $c_btn_text; ?> !important; border: none !important; }
			.uk-button-spot1:hover { opacity: 0.9; color: <?php echo $c_btn_text; ?> !important; }
		</style>
		<?php
	}

	/**
	 * Boost user interest scores on WooCommerce purchase completion.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function boost_interests_on_purchase( int $order_id ): void {
		$order   = wc_get_order( $order_id );
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$user_data = get_userdata( $user_id );
		if ( ! $user_data || ! in_array( $user_data->user_email, array( 'jim@finedesign.co.uk' ), true ) ) {
			return;
		}

		$interest_map = get_user_meta( $user_id, 'user_interest_map', true ) ?: array();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$terms      = get_the_terms( $product_id, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$slug = $term->slug;
					if ( isset( $interest_map[ $slug ] ) && is_array( $interest_map[ $slug ] ) ) {
						$interest_map[ $slug ]['score']     += 10;
						$interest_map[ $slug ]['last_seen']  = time();
					} else {
						$interest_map[ $slug ] = array( 'score' => 10, 'last_seen' => time() );
					}
				}
			}
		}

		$expiry_limit = strtotime( '-6 months' );
		foreach ( $interest_map as $key => $data ) {
			if ( isset( $data['last_seen'] ) && $data['last_seen'] < $expiry_limit ) {
				unset( $interest_map[ $key ] );
			}
		}

		uasort( $interest_map, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );
		update_user_meta( $user_id, 'user_interest_map', array_slice( $interest_map, 0, 15, true ) );
	}

	/**
	 * Capture search requests and record them in the feedback table.
	 *
	 * @return void
	 */
	public static function intercept_search_requests(): void {
		if ( ! Access::is_ready() ) {
			return;
		}

		global $wp_query, $wpdb;

		if ( is_search() || isset( $wp_query->query_vars['s'] ) || isset( $_GET['s'] ) ) {
			$search_query = get_search_query();
			if ( empty( $search_query ) ) {
				$search_query = isset( $wp_query->query_vars['s'] ) ? $wp_query->query_vars['s'] : sanitize_text_field( $_GET['s'] );
			}

			if ( ! empty( $search_query ) ) {
				$user_id = get_current_user_id();
				$term    = strtolower( sanitize_text_field( $search_query ) );
				$now     = time();

				if ( $user_id > 0 ) {
					$expiry_limit = strtotime( '-6 months' );
					$user_history = get_user_meta( $user_id, 'user_search_history', true ) ?: array();
					if ( ! is_array( $user_history ) ) {
						$user_history = array();
					}
					if ( isset( $user_history[ $term ] ) ) {
						$user_history[ $term ]['count']++;
						$user_history[ $term ]['last_searched'] = $now;
					} else {
						$user_history[ $term ] = array( 'count' => 1, 'last_searched' => $now );
					}
					foreach ( $user_history as $query => $data ) {
						if ( isset( $data['last_searched'] ) && $data['last_searched'] < $expiry_limit ) {
							unset( $user_history[ $query ] );
						}
					}
					uasort( $user_history, function ( $a, $b ) {
						return $b['count'] <=> $a['count'];
					} );
					update_user_meta( $user_id, 'user_search_history', array_slice( $user_history, 0, 20, true ) );
				}

				$table_name  = $wpdb->prefix . 'itp_search_feedback';
				$time_buffer = date( 'Y-m-d H:i:s', strtotime( '-5 seconds' ) );

				$duplicate_check = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND search_query = %s AND submitted_at >= %s",
					$user_id,
					$search_query,
					$time_buffer
				) );

				if ( (int) $duplicate_check === 0 ) {
					$inserted = $wpdb->insert(
						$table_name,
						array(
							'user_id'        => $user_id,
							'search_query'   => $search_query,
							'found_result'   => 'implicit',
							'feedback_notes' => '',
							'submitted_at'   => current_time( 'mysql' ),
						),
						array( '%d', '%s', '%s', '%s', '%s' )
					);
					if ( false === $inserted ) {
						error_log( 'Search Capture DB Error: ' . $wpdb->last_error );
					}
				}
			}
		}
	}
}
