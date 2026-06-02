<?php
namespace IntentTarget\Pro;
if ( ! defined( 'ABSPATH' ) ) exit;
class Styling {
	public static function apply_design_overrides( array $default_colors ): array {
		$saved = get_option( 'itp_design_settings', array() );
		return wp_parse_args( $saved, $default_colors );
	}
	public static function detect_theme_palette(): array {
		$colors = array();
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			$settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
			if ( isset( $settings['color']['palette']['theme'] ) ) {
				foreach ( $settings['color']['palette']['theme'] as $color ) {
					if ( ! empty( $color['color'] ) ) $colors[] = $color['color'];
				}
			}
		}
		if ( empty( $colors ) && current_theme_supports( 'editor-color-palette' ) ) {
			$palette = get_theme_support( 'editor-color-palette' );
			if ( ! empty( $palette[0] ) ) {
				foreach ( $palette[0] as $color ) {
					if ( ! empty( $color['color'] ) ) $colors[] = $color['color'];
				}
			}
		}
		if ( empty( $colors ) ) {
			$bg = get_background_color(); if ( $bg ) $colors[] = '#' . ltrim( $bg, '#' );
			$header = get_header_textcolor(); if ( $header && $header !== 'blank' ) $colors[] = '#' . ltrim( $header, '#' );
		}
		$clean = array(); foreach ( $colors as $c ) { if ( is_string( $c ) && ! in_array( $c, $clean, true ) ) $clean[] = $c; }
		return array_slice( $clean, 0, 8 );
	}
	public static function auto_populate_theme_colours(): void {
		$current = get_option( 'itp_design_settings', array() );
		if ( empty( $current ) ) {
			$palette = self::detect_theme_palette();
			update_option( 'itp_design_settings', array(
				'heading'     => $palette[0] ?? '#1d2327',
				'recommended' => $palette[1] ?? '#e1ad01',
				'button'      => $palette[2] ?? '#2271b1',
				'button_text' => $palette[3] ?? '#ffffff',
			) );
		}
	}
	public static function render_design_form(): void {
		if ( isset( $_POST['itp_save_design'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'itp_save_design_action', 'itp_save_design_nonce' );
			$design = isset( $_POST['itp_design_settings'] ) ? array_map( 'sanitize_text_field', $_POST['itp_design_settings'] ) : array();
			update_option( 'itp_design_settings', $design );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Visual interface colours saved successfully.', 'intenttarget-pro' ) . '</p></div>';
		}
		$colors = get_option( 'itp_design_settings', array() );
		$c_heading     = esc_attr( $colors['heading'] ?? '#1d2327' );
		$c_recommended = esc_attr( $colors['recommended'] ?? '#e1ad01' );
		$c_button      = esc_attr( $colors['button'] ?? '#2271b1' );
		$c_button_text = esc_attr( $colors['button_text'] ?? '#ffffff' );
		$detected_palette = self::detect_theme_palette();
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'itp_save_design_action', 'itp_save_design_nonce' ); ?>
			<div style="background:#fff; padding:25px; border:1px solid #ccd0d4; border-radius:4px; margin-top: 15px; max-width: 800px;">
				<h3 style="margin-top: 0;">Interface Presentation Colour Profiles</h3>
				<p class="description">Control the dynamic palette rendered across pop-out boxes, top message alert bars, and client account recommendations.</p>
				<?php if ( ! empty( $detected_palette ) ) : ?>
				<div style="margin-top: 25px; padding: 15px; background: #f6f7f7; border-left: 4px solid #72aee6; border-radius: 3px;">
					<h4 style="margin: 0 0 10px 0; font-size: 13px;">Detected Theme Palette</h4>
					<p class="description" style="margin-bottom: 10px; font-size: 12px;">We scanned your active theme. Click a swatch below to easily copy its hex code, then paste it into your desired setting.</p>
					<div style="display: flex; gap: 8px; flex-wrap: wrap;">
						<?php foreach ( $detected_palette as $hex ) : ?>
							<button type="button" class="itp-swatch-btn" data-hex="<?php echo esc_attr( $hex ); ?>" title="<?php echo esc_attr( $hex ); ?>" style="width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.2); background-color: <?php echo esc_attr( $hex ); ?>; cursor: pointer; transition: transform 0.1s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
				<table class="form-table" style="margin-top: 25px;">
					<tr><th style="width: 250px;"><label>Heading & Offer Titles Colour</label></th><td><input type="color" id="itp_c_heading" name="itp_design_settings[heading]" value="<?php echo $c_heading; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" /><input type="text" id="itp_t_heading" value="<?php echo $c_heading; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" /><p class="description" style="margin-top:5px;">Applies to recommend block titles and question prompts.</p></td></tr>
					<tr><th><label>"Recommended" Badge Background</label></th><td><input type="color" id="itp_c_recommended" name="itp_design_settings[recommended]" value="<?php echo $c_recommended; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" /><input type="text" id="itp_t_recommended" value="<?php echo $c_recommended; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" /><p class="description" style="margin-top:5px;">Applies to the 'Recommended' chip backgrounds and search feedback labels.</p></td></tr>
					<tr><th><label>Action Button Background</label></th><td><input type="color" id="itp_c_button" name="itp_design_settings[button]" value="<?php echo $c_button; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" /><input type="text" id="itp_t_button" value="<?php echo $c_button; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" /><p class="description" style="margin-top:5px;">Applies to interaction links inside recommendations and forms.</p></td></tr>
					<tr><th><label>Action Button Label Text Colour</label></th><td><input type="color" id="itp_c_button_text" name="itp_design_settings[button_text]" value="<?php echo $c_button_text; ?>" style="vertical-align: middle; width:50px; height:30px; padding:0; cursor:pointer;" /><input type="text" id="itp_t_button_text" value="<?php echo $c_button_text; ?>" class="small-text itp-hex-input" readonly style="vertical-align: middle; background:#eee; text-align:center; margin-left:5px;" /></td></tr>
				</table>
				<p class="submit" style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;"><input type="submit" name="itp_save_design" class="button button-primary button-large" value="Save Interface Styles" /></p>
			</div>
		</form>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				document.querySelectorAll('input[type="color"]').forEach(input => {
					input.addEventListener('input', function() { this.nextElementSibling.value = this.value; });
				});
				document.querySelectorAll('.itp-swatch-btn').forEach(swatch => {
					swatch.addEventListener('click', function() {
						navigator.clipboard.writeText(this.getAttribute('data-hex')).then(() => {
							const t = this.style.transform; this.style.transform = 'scale(0.8)'; setTimeout(() => this.style.transform = t, 150);
						});
					});
				});
			});
		</script>
		<?php
	}
}
