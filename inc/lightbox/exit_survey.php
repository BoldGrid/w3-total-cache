<?php
/**
 * File: exit_survey.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

?>
<div id="w3tc-exit-survey-modal">
	<div class="w3tc-modal-content">
		<!-- Survey Form -->
		<form id="w3tc-exit-survey-form">
			<h2><?php esc_html_e( 'Why are you leaving W3 Total Cache?', 'w3-total-cache' ); ?></h2>
			<p><?php esc_html_e( 'Please select the primary reason:', 'w3-total-cache' ); ?></p>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="performance_issues">
					<?php esc_html_e( 'I experienced performance issues (e.g., slow website speed)', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="complicated_setup">
					<?php esc_html_e( 'The plugin was too complicated to set up or use', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="conflicts">
					<?php esc_html_e( 'Conflicts with other plugins or my theme', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="technical_errors">
					<?php esc_html_e( 'The plugin caused technical errors on my website (e.g., 500 errors, broken pages)', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="no_improvement">
					<?php esc_html_e( 'I didn’t see an improvement in website speed or performance', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="switched_plugin">
					<?php esc_html_e( 'I switched to another caching plugin', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="no_longer_needed">
					<?php esc_html_e( 'I no longer need a caching plugin', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="hosting_managed_caching">
					<?php esc_html_e( 'I’m using a hosting provider that manages caching for me', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="premium_features">
					<?php esc_html_e( 'I was unaware of the premium features or didn’t find them worth it', 'w3-total-cache' ); ?>
				</label>
			</div>

			<!-- Other Option -->
			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="other">
					<?php esc_html_e( 'Other (please specify):', 'w3-total-cache' ); ?>
				</label>
				<input type="text" id="w3tc_exit_survey_uninstall_reason_other" name="other" placeholder="<?php esc_attr_e( 'Please specify...', 'w3-total-cache' ); ?>" style="width: 100%; margin-top: 5px;" />
			</div>

			<h2><?php esc_html_e( 'Remove all plugin data?', 'w3-total-cache' ); ?></h2>

			<div class="w3tc-exit-survey-remove-data">
				<label>
					<input type="radio" name="remove" value="yes">
					<?php esc_html_e( 'Yes', 'w3-total-cache' ); ?>
				</label>
			</div>

			<div class="w3tc-exit-survey-remove-data">
				<label>
					<input type="radio" name="remove" value="no" checked="checked">
					<?php esc_html_e( 'No', 'w3-total-cache' ); ?>
				</label>
			</div>

			<!-- Submit and Cancel Buttons -->
			<div class="w3tc-exit-survey-actions" style="margin-top: 15px;">
				<button type="submit" id="w3tc-exit-survey-submit" class="button button-primary" disabled><?php esc_html_e( 'Submit & Deactivate', 'w3-total-cache' ); ?></button>
				<button type="button" id="w3tc-exit-survey-skip" class="button button-secondary"><?php esc_html_e( 'Skip & Deactivate', 'w3-total-cache' ); ?></button>
			</div>
		</form>
	</div>
</div>
