<?php
/**
 * File: settings.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

/**
 * Extension settings template variables.
 *
 * @var string $active_tab
 * @var string $w3tc_extension
 * @var array $w3tc_meta
 */

do_action( "w3tc_extension_page_{$w3tc_extension}" );
