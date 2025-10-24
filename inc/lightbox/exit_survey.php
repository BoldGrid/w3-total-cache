<?php
/**
 * File: exit_survey.php
 *
 * @since 2.8.3
 *
 * @package W3TC
 */

?>
<div id="w3tc-exit-survey-modal">
	<div class="w3tc-modal-content">
		<!-- Survey Form -->
		<form id="w3tc-exit-survey-form">
			<h2><?php esc_html_e( 'We\'re sorry to see you go!', 'w3-total-cache' ); ?></h2>
			<p><?php esc_html_e( 'Before you deactivate W3 Total Cache, could you take a moment to let us know why? Your feedback is incredibly valuable and helps us make W3 Total Cache better for everyone.', 'w3-total-cache' ); ?></p>

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
					<input type="radio" name="reason" value="no_improvement">
					<?php esc_html_e( 'I didn’t see an improvement in website speed or performance', 'w3-total-cache' ); ?>
				</label>
			</div>

			<!-- Other Option -->
			<div class="w3tc-exit-survey-option">
				<label>
					<input type="radio" name="reason" value="other">
					<?php esc_html_e( 'Other', 'w3-total-cache' ); ?>
				</label>
			</div>

			<input type="text" id="w3tc_exit_survey_uninstall_reason_more_info" class="hidden" name="other" placeholder="<?php esc_attr_e( 'Please provide details...', 'w3-total-cache' ); ?>" />

			<h2><?php esc_html_e( 'May we contact you?', 'w3-total-cache' ); ?></h2>
			<p><?php esc_html_e( 'We\'re serious about making W3 Total Cache better, and sometimes that means reaching out to users like you to get a few more details about your experience. Would you be open to us contacting you for further feedback? If so, please enter your email address below.', 'w3-total-cache' ); ?></p>

			<div class="w3tc-exit-survey-email">
				<input id="email" type="email" name="email" placeholder="<?php esc_attr_e( 'Email address...', 'w3-total-cache' ); ?>" disabled/>
			</div>

			<h2><?php esc_html_e( 'Remove all plugin data?', 'w3-total-cache' ); ?></h2>
			<p><?php esc_html_e( 'Selecting "Yes" will permanently delete all W3 Total Cache settings, cached data, and other plugin-related information from your site. This action cannot be undone.', 'w3-total-cache' ); ?></p>

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
			<div class="w3tc-exit-survey-actions">
				<button type="submit" id="w3tc-exit-survey-submit" class="button button-primary" disabled><?php esc_html_e( 'Submit & Deactivate', 'w3-total-cache' ); ?></button>
				<a href="#" id="w3tc-exit-survey-skip"><?php esc_html_e( 'Skip & Deactivate', 'w3-total-cache' ); ?></a>
			</div>
		</form>
	</div>
</div>
