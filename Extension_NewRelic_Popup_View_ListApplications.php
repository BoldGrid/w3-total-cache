<?php
/**
 * File: Extension_NewRelic_Popup_View_ListApplications.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<form style="padding: 20px" class="w3tcnr_form">
	<?php
	Util_Ui::hidden( 'w3tc-rackspace-api-key', 'api_key', $details['api_key'] );
	?>

	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Select Application', 'w3-total-cache' ) ); ?>
		<?php
		$w3tc_has_apm     = ! empty( $details['apm_applications'] );
		$w3tc_has_browser = ! empty( $details['browser_applications'] );
		$w3tc_has_apps    = $w3tc_has_apm || $w3tc_has_browser;

		$w3tc_selected_type = $details['monitoring_type'];

		// If nothing is selected yet (fresh setup), default to the only available type.
		if ( empty( $w3tc_selected_type ) ) {
			if ( $w3tc_has_apm && ! $w3tc_has_browser ) {
				$w3tc_selected_type = 'apm';
			} elseif ( $w3tc_has_browser && ! $w3tc_has_apm ) {
				$w3tc_selected_type = 'browser';
			}
		}

		// If a type is selected but no specific app is saved yet, default to the first available option.
		$w3tc_apm_application_name = $details['apm.application_name'];
		if ( 'apm' === $w3tc_selected_type && empty( $w3tc_apm_application_name ) && $w3tc_has_apm ) {
			$w3tc_apm_application_name = (string) reset( $details['apm_applications'] );
		}

		$w3tc_browser_application_id = $details['browser.application_id'];
		if ( 'browser' === $w3tc_selected_type && empty( $w3tc_browser_application_id ) && $w3tc_has_browser ) {
			$w3tc_first = reset( $details['browser_applications'] );
			if ( is_array( $w3tc_first ) && isset( $w3tc_first['id'] ) ) {
				$w3tc_browser_application_id = (string) $w3tc_first['id'];
			}
		}

		$w3tc_apm_selected = ( 'apm' === $w3tc_selected_type && ! empty( $w3tc_apm_application_name ) );
		$w3tc_br_selected  = ( 'browser' === $w3tc_selected_type && ! empty( $w3tc_browser_application_id ) );
		$w3tc_can_apply    = $w3tc_has_apps && ( $w3tc_apm_selected || $w3tc_br_selected );
		?>
		<table class="form-table">
			<?php if ( ! empty( $details['apm_applications'] ) ) : ?>
			<tr>
				<td>
					<label>
						<input name="monitoring_type" type="radio" value="apm"
							<?php checked( $w3tc_selected_type, 'apm' ); ?> />
						APM application (uses NewRelic PHP module)
					</label><br />
					<select name="apm_application_name" class="w3tcnr_apm">
						<?php
						foreach ( $details['apm_applications'] as $w3tc_a ) {
							echo '<option ';
							selected( $w3tc_a, $w3tc_apm_application_name );
							echo '>' . esc_html( $w3tc_a ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<td>
					<label>
						<input name="monitoring_type" type="radio" value="apm" disabled="disabled" />
						APM application (uses NewRelic PHP module)
					</label><br />
					<em><?php esc_html_e( 'No APM applications found. Ensure the PHP agent is reporting.', 'w3-total-cache' ); ?></em>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ( ! empty( $details['browser_applications'] ) ) : ?>
			<tr>
				<td>
					<label>
						<input name="monitoring_type" type="radio" value="browser"
							<?php checked( $w3tc_selected_type, 'browser' ); ?>
							<?php disabled( $details['browser_disabled'] ); ?> />
						Standalone Browser
						<?php
						if ( $details['browser_disabled'] ) {
							echo ' (W3TC Pro Only)';
						}
						?>
					</label><br />
					<select name="browser_application_id" class="w3tcnr_browser">
						<?php
						foreach ( $details['browser_applications'] as $w3tc_a ) {
							echo '<option value="' . esc_attr( $w3tc_a['id'] ) . '" ';
							selected( $w3tc_a['id'], $w3tc_browser_application_id );
							echo '>' . esc_html( $w3tc_a['name'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<td>
					<label>
						<input name="monitoring_type" type="radio" value="browser" disabled="disabled" />
						Standalone Browser
						<?php
						if ( $details['browser_disabled'] ) {
							echo ' (W3TC Pro Only)';
						}
						?>
					</label><br />
					<em><?php esc_html_e( 'No Browser applications found. Create a browser app in New Relic first.', 'w3-total-cache' ); ?></em>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<p class="submit">
			<input type="button"
				class="w3tcnr_apply_configuration w3tc-button-save button-primary"
				<?php disabled( ! $w3tc_can_apply ); ?>
				value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
