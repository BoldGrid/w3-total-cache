<?php
/**
 * File: csstidy2.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_csstidy_templates = array(
	'highest_compression' => __( 'Highest (no readability, smallest size)', 'w3-total-cache' ),
	'high_compression'    => __( 'High (moderate readability, smaller size)', 'w3-total-cache' ),
	'default'             => __( 'Standard (balance between readability and size)', 'w3-total-cache' ),
	'low_compression'     => __( 'Low (higher readability)', 'w3-total-cache' ),
);

$w3tc_optimise_shorthands_values = array(
	0 => __( 'Don\'t optimise', 'w3-total-cache' ),
	1 => __( 'Safe optimisations', 'w3-total-cache' ),
	2 => __( 'Level II optimisations', 'w3-total-cache' ),
	3 => __( 'All optimisations', 'w3-total-cache' ),
);

$w3tc_case_properties_values = array(
	0 => __( 'None', 'w3-total-cache' ),
	1 => __( 'Lowercase', 'w3-total-cache' ),
	2 => __( 'Uppercase', 'w3-total-cache' ),
);

$w3tc_merge_selectors_values = array(
	0 => __( 'Do not change anything', 'w3-total-cache' ),
	1 => __( 'Only seperate selectors (split at ,)', 'w3-total-cache' ),
	2 => __( 'Merge selectors with the same properties (fast)', 'w3-total-cache' ),
);

$w3tc_csstidy_template    = $this->_config->get_string( 'minify.csstidy.options.template' );
$w3tc_optimise_shorthands = $this->_config->get_integer( 'minify.csstidy.options.optimise_shorthands' );
$w3tc_case_properties     = $this->_config->get_integer( 'minify.csstidy.options.case_properties' );
$w3tc_merge_selectors     = $this->_config->get_integer( 'minify.csstidy.options.merge_selectors' );
?>
<tr>
	<th><label for="minify_csstidy_options_template"><?php Util_Ui::e_config_label( 'minify.csstidy.options.template' ); ?></label></th>
	<td>
		<select id="minify_csstidy_options_template" class="css_enabled" name="minify__csstidy__options__template"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?>>
			<?php foreach ( $w3tc_csstidy_templates as $w3tc_csstidy_template_key => $w3tc_csstidy_template_name ) : ?>
				<option value="<?php echo esc_attr( $w3tc_csstidy_template_key ); ?>"  <?php selected( $w3tc_csstidy_template, $w3tc_csstidy_template_key ); ?>>
					<?php echo esc_html( $w3tc_csstidy_template_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
<tr>
	<th><label for="minify_csstidy_options_optimise_shorthands"><?php Util_Ui::e_config_label( 'minify.csstidy.options.optimise_shorthands' ); ?></label></th>
	<td>
		<select id="minify_csstidy_options_optimise_shorthands" class="css_enabled"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?> name="minify__csstidy__options__optimise_shorthands">
			<?php foreach ( $w3tc_optimise_shorthands_values as $w3tc_optimise_shorthands_key => $w3tc_optimise_shorthands_name ) : ?>
				<option value="<?php echo esc_attr( $w3tc_optimise_shorthands_key ); ?>" <?php selected( $w3tc_optimise_shorthands, $w3tc_optimise_shorthands_key ); ?>>
					<?php echo esc_html( $w3tc_optimise_shorthands_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
<tr>
	<th><label for="minify_csstidy_options_case_properties"><?php Util_Ui::e_config_label( 'minify.csstidy.options.case_properties' ); ?></label></th>
	<td>
		<select id="minify_csstidy_options_case_properties" class="css_enabled"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?> name="minify__csstidy__options__case_properties">
			<?php foreach ( $w3tc_case_properties_values as $w3tc_case_properties_key => $w3tc_case_properties_name ) : ?>
			<option value="<?php echo esc_attr( $w3tc_case_properties_key ); ?>"  <?php selected( $w3tc_case_properties, $w3tc_case_properties_key ); ?>>
				<?php echo esc_html( $w3tc_case_properties_name ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
<tr>
	<th><label for="minify_csstidy_options_merge_selectors"><?php Util_Ui::e_config_label( 'minify.csstidy.options.merge_selectors' ); ?></label></th>
	<td>
		<select id="minify_csstidy_options_merge_selectors" class="css_enabled"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?> name="minify__csstidy__options__merge_selectors">
			<?php foreach ( $w3tc_merge_selectors_values as $w3tc_merge_selectors_key => $w3tc_merge_selectors_name ) : ?>
				<option value="<?php echo esc_attr( $w3tc_merge_selectors_key ); ?>" <?php selected( $w3tc_merge_selectors, $w3tc_merge_selectors_key ); ?>>
					<?php echo esc_html( $w3tc_merge_selectors_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
