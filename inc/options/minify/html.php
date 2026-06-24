<?php
/**
 * File: html.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<?php $this->checkbox( 'minify.html.strip.crlf', false, 'html_' ); ?> <?php Util_Ui::e_config_label( 'minify.html.strip.crlf' ); ?></label><br />
