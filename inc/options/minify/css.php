<?php
/**
 * File: css.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

?>

<?php $this->checkbox( 'minify.css.strip.comments', false, 'css_' ); ?> <?php Util_Ui::e_config_label( 'minify.css.strip.comments' ); ?></label><br />

<?php $this->checkbox( 'minify.css.strip.crlf', false, 'css_' ); ?> <?php Util_Ui::e_config_label( 'minify.css.strip.crlf' ); ?></label><br />

<?php do_action( 'w3tc_settings_minify_css_pro' ); ?>

<br />
