<?php
/**
 * File: Extension_NewRelic_Popup_View_ListApplications.php
 *
 * @package W3TC
 */

namespace W3TC;

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
		$has_apm     = ! empty( $details['apm_applications'] );
		$has_browser = ! empty( $details['browser_applications'] );
		$has_apps    = $has_apm || $has_browser;

		$selected_type = $details['monitoring_type'];

		// If nothing is selected yet (fresh setup), default to the only available type.
		if ( empty( $selected_type ) ) {
			if ( $has_apm && ! $has_browser ) {
				$selected_type = 'apm';
			} elseif ( $has_browser && ! $has_apm ) {
				$selected_type = 'browser';
			}
		}

		// If a type is selected but no specific app is saved yet, default to the first available option.
		$apm_application_name = $details['apm.application_name'];
		if ( 'apm' === $selected_type && empty( $apm_application_name ) && $has_apm ) {
			$apm_application_name = (string) reset( $details['apm_applications'] );
		}

		$browser_application_id = $details['browser.application_id'];
		if ( 'browser' === $selected_type && empty( $browser_application_id ) && $has_browser ) {
			$first = reset( $details['browser_applications'] );
			if ( is_array( $first ) && isset( $first['id'] ) ) {
				$browser_application_id = (string) $first['id'];
			}
		}

		$apm_selected = ( 'apm' === $selected_type && ! empty( $apm_application_name ) );
		$br_selected  = ( 'browser' === $selected_type && ! empty( $browser_application_id ) );
		$can_apply    = $has_apps && ( $apm_selected || $br_selected );
		?>
		<table class="form-table">
			<?php if ( ! empty( $details['apm_applications'] ) ) : ?>
			<tr>
				<td>
					<label>
						<input name="monitoring_type" type="radio" value="apm"
							<?php checked( $selected_type, 'apm' ); ?> />
						APM application (uses NewRelic PHP module)
					</label><br />
					<select name="apm_application_name" class="w3tcnr_apm">
						<?php
						foreach ( $details['apm_applications'] as $a ) {
							echo '<option ';
							selected( $a, $apm_application_name );
							echo '>' . esc_html( $a ) . '</option>';
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
							<?php checked( $selected_type, 'browser' ); ?>
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
						foreach ( $details['browser_applications'] as $a ) {
							echo '<option value="' . esc_attr( $a['id'] ) . '" ';
							selected( $a['id'], $browser_application_id );
							echo '>' . esc_html( $a['name'] ) . '</option>';
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
				<?php disabled( ! $can_apply ); ?>
				value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
