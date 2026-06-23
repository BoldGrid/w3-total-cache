<?php
/**
 * HTML mime types.
 *
 * @package W3TC
 */

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die;
}

return array(
	'html|htm' => 'text/html',
	'rtf|rtx'  => 'text/richtext',
	'txt'      => 'text/plain',
	'xsd'      => 'text/xsd',
	'xsl'      => 'text/xsl',
	'xml'      => 'text/xml',
);
