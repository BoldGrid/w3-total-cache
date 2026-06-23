<?php
/**
 * File: ccjs2.php
 *
 * Options page: Minify - Closure Compiler JS
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_compilation_levels = array(
	'WHITESPACE_ONLY'        => __( 'Whitespace only', 'w3-total-cache' ),
	'SIMPLE_OPTIMIZATIONS'   => __( 'Simple optimizations', 'w3-total-cache' ),
	'ADVANCED_OPTIMIZATIONS' => __( 'Advanced optimizations', 'w3-total-cache' ),
);

$w3tc_c                 = $this->_config;
$w3tc_compilation_level = $w3tc_c->get_string( 'minify.ccjs.options.compilation_level' );

?>
<tr>
	<th>
		<label for="minify__ccjs__path__java">
			<?php Util_Ui::e_config_label( 'minify.ccjs.path.java' ); ?>
		</label>
	</th>
	<td>
		<input id="minify__ccjs__path__java" class="js_enabled" type="text"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?>
			name="minify__ccjs__path__java"
			value="<?php echo esc_attr( $w3tc_c->get_string( 'minify.ccjs.path.java' ) ); ?>"
			size="60" />
	</td>
</tr>
<tr>
	<th>
		<label for="minify__ccjs__path__jar">
			<?php Util_Ui::e_config_label( 'minify.ccjs.path.jar' ); ?>
		</label>
	</th>
	<td>
		<input id="minify__ccjs__path__jar" class="js_enabled" type="text"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?>
			name="minify__ccjs__path__jar"
			value="<?php echo esc_attr( $w3tc_c->get_string( 'minify.ccjs.path.jar' ) ); ?>"
			size="60" />
	</td>
</tr>
<tr>
	<th>&nbsp;</th>
	<td>
		<input class="minifier_test js_enabled button {type: 'ccjs', nonce: '<?php echo esc_attr( Util_Nonce::create_admin( 'w3tc_test_minifier' ) ); ?>'}"
			type="button"
			value="<?php esc_attr_e( 'Test Closure Compiler', 'w3-total-cache' ); ?>" />
		<span class="minifier_test_status w3tc-status w3tc-process"></span>
	</td>
</tr>
<tr>
	<th>
		<label for="minify_ccjs_options_compilation_level">
			<?php Util_Ui::e_config_label( 'minify.ccjs.options.compilation_level' ); ?>
		</label>
	</th>
	<td>
		<select id="minify_ccjs_options_compilation_level" class="js_enabled"
			name="minify__ccjs__options__compilation_level"
			<?php Util_Ui::sealing_disabled( 'minify.' ); ?>>
			<?php foreach ( $w3tc_compilation_levels as $w3tc_compilation_level_key => $w3tc_compilation_level_name ) : ?>
				<option value="<?php echo esc_attr( $w3tc_compilation_level_key ); ?>"
					<?php selected( $w3tc_compilation_level, $w3tc_compilation_level_key ); ?>>
					<?php echo esc_html( $w3tc_compilation_level_name ); ?>
				</option>
			<?php endforeach ?>
		</select>
	</td>
</tr>
