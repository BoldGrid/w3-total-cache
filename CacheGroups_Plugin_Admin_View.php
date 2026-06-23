<?php
/**
 * File: CacheGroups_Plugin_Admin_View.php
 *
 * @since 2.1.0
 *
 * @package W3TC
 *
 * @uses $useragent_groups
 * @uses $useragent_themes
 * @uses $referrer_groups
 * @uses $referrer_themes
 * @uses $cookie_groups
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>

<form id="cachegroups_form" action="admin.php?page=<?php echo esc_attr( $this->_page ); ?>" method="post">
	<?php Util_UI::print_control_bar( 'cachegroups_form_control' ); ?>

	<!-- User Agenet Groups -->

	<script type="text/javascript">/*<![CDATA[*/
	var mobile_themes = {};
	<?php foreach ( $useragent_themes as $w3tc_theme_key => $w3tc_theme_name ) : ?>
	mobile_themes['<?php echo esc_attr( addslashes( $w3tc_theme_key ) ); ?>'] = '<?php echo esc_html( addslashes( $w3tc_theme_name ) ); ?>';
	<?php endforeach; ?>
	/*]]>*/</script>

	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Manage User Agent Groups', 'w3-total-cache' ), '', 'manage-uag' ); ?>
		<p>
			<input id="mobile_add" type="button" class="button"
				<?php disabled( $useragent_groups['disabled'] ); ?>
				value="<?php esc_html_e( 'Create a group', 'w3-total-cache' ); ?>" />
			<?php esc_html_e( 'of user agents by specifying names in the user agents field. Assign a set of user agents to use a specific theme, redirect them to another domain or if an existing mobile plugin is active, create user agent groups to ensure that a unique cache is created for each user agent group. Drag and drop groups into order (if needed) to determine their priority (top -&gt; down).', 'w3-total-cache' ); ?>
		</p>

		<ul id="mobile_groups">
			<?php
			$w3tc_index = 0;

			foreach ( $useragent_groups['value'] as $w3tc_group => $w3tc_group_config ) :
				++$w3tc_index;
				?>
			<li id="mobile_group_<?php echo esc_attr( $w3tc_group ); ?>">
				<table class="form-table">
					<tr>
						<th>
							<?php esc_html_e( 'Group name:', 'w3-total-cache' ); ?>
						</th>
						<td>
							<span class="mobile_group_number"><?php echo esc_attr( $w3tc_index ); ?>.</span> <span class="mobile_group"><?php echo esc_html( $w3tc_group ); // phpcs:ignore ?></span>
							<input type="button" class="button mobile_delete"
								value="<?php esc_html_e( 'Delete group', 'w3-total-cache' ); ?>"
								<?php disabled( $useragent_groups['disabled'] ); ?> />
						</td>
					</tr>
					<tr>
						<th>
							<label for="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_enabled"><?php esc_html_e( 'Enabled:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="mobile_groups[<?php echo esc_attr( $w3tc_group ); ?>][enabled]" value="0" />
							<input id="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_enabled"
								class="mobile_group_enabled" type="checkbox"
								name="mobile_groups[<?php echo esc_attr( $w3tc_group ); ?>][enabled]"
								<?php disabled( $useragent_groups['disabled'] ); ?> value="1"
								<?php checked( $w3tc_group_config['enabled'], true ); ?> />
						</td>
					</tr>
					<tr>
						<th>
							<label for="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_theme"><?php esc_html_e( 'Theme:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<select id="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_theme"
								name="mobile_groups[<?php echo esc_attr( $w3tc_group ); ?>][theme]"
								<?php disabled( $useragent_groups['disabled'] ); ?> >
								<option value=""><?php esc_html_e( '-- Pass-through --', 'w3-total-cache' ); ?></option>
								<?php foreach ( $useragent_themes as $w3tc_theme_key => $w3tc_theme_name ) : ?>
								<option value="<?php echo esc_attr( $w3tc_theme_key ); ?>"<?php selected( $w3tc_theme_key, $w3tc_group_config['theme'] ); ?>><?php echo esc_html( $w3tc_theme_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Assign this group of user agents to a specific theme. Selecting "Pass-through" allows any plugin(s) (e.g. mobile plugins) to properly handle requests for these user agents. If the "redirect users to" field is not empty, this setting is ignored.', 'w3-total-cache' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_redirect"><?php esc_html_e( 'Redirect users to:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<input id="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_redirect"
								type="text" name="mobile_groups[<?php echo esc_attr( $w3tc_group ); ?>][redirect]"
								value="<?php echo esc_attr( $w3tc_group_config['redirect'] ); ?>"
								<?php disabled( $useragent_groups['disabled'] ); ?>
								size="60" />
							<p class="description"><?php esc_html_e( 'A 302 redirect is used to send this group of users to another hostname (domain); recommended if a 3rd party service provides a mobile version of your site.', 'w3-total-cache' ); ?></p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_agents"><?php esc_html_e( 'User agents:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<textarea id="mobile_groups_<?php echo esc_attr( $w3tc_group ); ?>_agents"
								name="mobile_groups[<?php echo esc_attr( $w3tc_group ); ?>][agents]"
								rows="10" cols="50" <?php disabled( $useragent_groups['disabled'] ); ?>><?php echo esc_textarea( implode( "\r\n", (array) $w3tc_group_config['agents'] ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Specify the user agents for this group. Remember to escape special characters like spaces, dots or dashes with a backslash. Regular expressions are also supported.', 'w3-total-cache' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</li>
			<?php endforeach; ?>
		</ul>
		<div id="mobile_groups_empty" style="display: none;"><?php esc_html_e( 'No groups added. All user agents receive the same page and minify cache results.', 'w3-total-cache' ); ?></div>

		<?php
		Util_Ui::postbox_footer();

		Util_Ui::postbox_header(
			__( 'Note(s):', 'w3-total-cache' ),
			'',
			'notes'
		);
		?>

		<table class="form-table">
			<tr>
				<th colspan="2">
					<ul>
						<?php
						/**
						 * `description` reaches us through the public
						 * `w3tc_ui_config_item_mobile.rgroups` filter, so any
						 * plugin can mutate it before we render. The
						 * legitimate content is two translated strings each
						 * wrapped in `<li>`; clamp to that exact shape with
						 * wp_kses so any other tag becomes inert text
						 * .
						 */
						echo wp_kses(
							$useragent_groups['description'],
							array( 'li' => array() )
						);
						?>
					</ul>
				</th>
			</tr>
		</table>
		<?php Util_Ui::postbox_footer(); ?>
	</div>

<!-- Referrer Groups -->

	<script type="text/javascript">/*<![CDATA[*/
		var referrer_themes = {};
		<?php foreach ( $referrer_themes as $w3tc_theme_key => $w3tc_theme_name ) : ?>
		referrer_themes['<?php echo esc_attr( $w3tc_theme_key ); ?>'] = '<?php echo esc_html( $w3tc_theme_name ); ?>';
		<?php endforeach; ?>
	/*]]>*/</script>

	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Manage Referrer Groups', 'w3-total-cache' ), '', 'manage-rg' ); ?>
		<p>
			<input id="referrer_add" type="button" class="button" value="<?php esc_html_e( 'Create a group', 'w3-total-cache' ); ?>" /> <?php esc_html_e( 'of referrers by specifying names in the referrers field. Assign a set of referrers to use a specific theme, redirect them to another domain, create referrer groups to ensure that a unique cache is created for each referrer group. Drag and drop groups into order (if needed) to determine their priority (top -&gt; down).', 'w3-total-cache' ); ?>
		</p>

		<ul id="referrer_groups">
			<?php
			$w3tc_index = 0;

			foreach ( $referrer_groups as $w3tc_group => $w3tc_group_config ) :
				++$w3tc_index;
				?>
			<li id="referrer_group_<?php echo esc_attr( $w3tc_group ); ?>">
				<table class="form-table">
					<tr>
						<th>
							<?php esc_html_e( 'Group name:', 'w3-total-cache' ); ?>
						</th>
						<td>
							<span class="referrer_group_number"><?php echo esc_attr( $w3tc_index ); ?>.</span> <span class="referrer_group"><?php echo esc_html( $w3tc_group ); ?></span> <input type="button" class="button referrer_delete" value="<?php esc_html_e( 'Delete group', 'w3-total-cache' ); ?>" />
						</td>
					</tr>
					<tr>
						<th>
							<label for="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_enabled"><?php esc_html_e( 'Enabled:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="referrer_groups[<?php echo esc_attr( $w3tc_group ); ?>][enabled]" value="0" />
							<input id="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_enabled"
								class="referrer_group_enabled" type="checkbox"
								name="referrer_groups[<?php echo esc_attr( $w3tc_group ); ?>][enabled]"
								value="1"<?php checked( $w3tc_group_config['enabled'], true ); ?> />
						</td>
					</tr>
					<tr>
						<th>
							<label for="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_theme"><?php esc_html_e( 'Theme:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<select id="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_theme" name="referrer_groups[<?php echo esc_attr( $w3tc_group ); ?>][theme]">
								<option value=""><?php esc_html_e( '-- Pass-through --', 'w3-total-cache' ); ?></option>
								<?php foreach ( $referrer_themes as $w3tc_theme_key => $w3tc_theme_name ) : ?>
								<option value="<?php echo esc_attr( $w3tc_theme_key ); ?>"<?php selected( $w3tc_theme_key, $w3tc_group_config['theme'] ); ?>><?php echo esc_html( $w3tc_theme_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Assign this group of referrers to a specific theme. Selecting "Pass-through" allows any plugin(s) (e.g. referrer plugins) to properly handle requests for these referrers. If the "redirect users to" field is not empty, this setting is ignored.', 'w3-total-cache' ); ?></p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_redirect"><?php esc_html_e( 'Redirect users to:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<input id="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_redirect" type="text" name="referrer_groups[<?php echo esc_attr( $w3tc_group ); ?>][redirect]" value="<?php echo esc_attr( $w3tc_group_config['redirect'] ); ?>" size="60" />
							<p class="description"><?php esc_html_e( 'A 302 redirect is used to send this group of referrers to another hostname (domain).', 'w3-total-cache' ); ?></p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_referrers"><?php esc_html_e( 'Referrers:', 'w3-total-cache' ); ?></label>
						</th>
						<td>
							<textarea id="referrer_groups_<?php echo esc_attr( $w3tc_group ); ?>_referrers" name="referrer_groups[<?php echo esc_attr( $w3tc_group ); ?>][referrers]" rows="10" cols="50"><?php echo esc_textarea( implode( "\r\n", (array) $w3tc_group_config['referrers'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Specify the referrers for this group. Remember to escape special characters like spaces, dots or dashes with a backslash. Regular expressions are also supported.', 'w3-total-cache' ); ?></p>
						</td>
					</tr>
				</table>
			</li>
			<?php endforeach; ?>
		</ul>
		<div id="referrer_groups_empty" style="display: none;"><?php esc_html_e( 'No groups added. All referrers receive the same page and minify cache results.', 'w3-total-cache' ); ?></div>

		<?php Util_Ui::postbox_footer(); ?>
	</div>

<!-- Cookie Groups -->

	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Manage Cookie Groups', 'w3-total-cache' ), '', 'manage-cg' ); ?>
		<p>
			<input id="w3tc_cookiegroup_add" type="button" class="button"
				<?php disabled( $cookie_groups['disabled'] ); ?>
				value="<?php esc_html_e( 'Create a group', 'w3-total-cache' ); ?>" />
			<?php esc_html_e( 'of Cookies by specifying names in the Cookies field. Assign a set of Cookies to ensure that a unique cache is created for each Cookie group. Drag and drop groups into order (if needed) to determine their priority (top -&gt; down).', 'w3-total-cache' ); ?>
		</p>

		<ul id="cookiegroups" class="w3tc_cachegroups">
			<?php
			$w3tc_index = 0;
			foreach ( $cookie_groups['value'] as $w3tc_group => $w3tc_group_config ) :
				++$w3tc_index;
				?>
			<li id="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>">
				<table class="form-table">
					<tr>
						<th>
							<?php esc_html_e( 'Group name:', 'w3-total-cache' ); ?>
						</th>
						<td>
							<span class="cookiegroup_number"><?php echo esc_attr( $w3tc_index ); ?>.</span>
							<span class="cookiegroup_name"><?php echo htmlspecialchars( $w3tc_group ); // phpcs:ignore ?></span>
							<input type="button" class="button w3tc_cookiegroup_delete"
								value="<?php esc_html_e( 'Delete group', 'w3-total-cache' ); ?>"
								<?php disabled( $cookie_groups['disabled'] ); ?> />
						</td>
					</tr>
					<tr>
						<th>
							<label for="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>_enabled">
								<?php esc_html_e( 'Enabled:', 'w3-total-cache' ); ?>
							</label>
						</th>
						<td>
							<input id="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>_enabled"
								class="cookiegroup_enabled" type="checkbox"
								name="cookiegroups[<?php echo esc_attr( $w3tc_group ); ?>][enabled]"
								<?php disabled( $cookie_groups['disabled'] ); ?> value="1"
								<?php checked( $w3tc_group_config['enabled'], true ); ?> />
						</td>
					</tr>
					<tr>
						<th>
							<label for="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>_cache">
								<?php esc_html_e( 'Cache:', 'w3-total-cache' ); ?>
							</label>
						</th>
						<td>
							<input id="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>_cache"
								type="checkbox"
								name="cookiegroups[<?php echo esc_attr( $w3tc_group ); ?>][cache]"
								<?php disabled( $cookie_groups['disabled'] ); ?> value="1"
								<?php checked( $w3tc_group_config['cache'], true ); ?> /> <?php esc_html_e( 'Enable', 'w3-total-cache' ); ?>
							<p class="description"><?php esc_html_e( 'Controls whether web pages can be cached or not when cookies from this group are detected.', 'w3-total-cache' ); ?></p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>_cookies">
								<?php esc_html_e( 'Cookies:', 'w3-total-cache' ); ?>
							</label>
						</th>
						<td>
							<textarea id="cookiegroup_<?php echo esc_attr( $w3tc_group ); ?>_cookies"
								name="cookiegroups[<?php echo esc_attr( $w3tc_group ); ?>][cookies]"
								rows="10" cols="50" <?php disabled( $cookie_groups['disabled'] ); ?>><?php echo esc_textarea( implode( "\r\n", (array) $w3tc_group_config['cookies'] ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Specify the cookies for this group. Values like \'cookie\', \'cookie=value\', and cookie[a-z]+=value[a-z]+ are supported. Remember to escape special characters like spaces, dots or dashes with a backslash. Regular expressions are also supported.', 'w3-total-cache' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</li>
			<?php endforeach; ?>
		</ul>
		<div id="cookiegroups_empty" style="display: none;"><?php esc_html_e( 'No groups added. All Cookies receive the same page and minify cache results.', 'w3-total-cache' ); ?></div>

		<?php
		Util_Ui::postbox_footer();

		Util_Ui::postbox_header(
			__( 'Note(s):', 'w3-total-cache' ),
			'',
			'notes'
		);
		?>
		<table class="form-table">
			<tr>
				<th colspan="2">
					<ul>
						<li>
							<?php esc_html_e( 'Content is cached for each group separately.', 'w3-total-cache' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Per the above, make sure that visitors are notified about the cookie as per any regulations in your market.', 'w3-total-cache' ); ?>
						</li>
					</ul>
				</th>
			</tr>
		</table>
		<?php Util_Ui::postbox_footer(); ?>
	</div>

</form>
