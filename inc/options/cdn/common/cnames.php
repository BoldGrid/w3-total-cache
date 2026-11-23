<?php
/**
 * File: cnames.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
defined( 'W3TC' ) || die();

?>
<ol id="cdn_cnames" style="margin: 0">
<?php
if ( ! count( $w3tc_cnames ) ) {
	$w3tc_cnames = array( '' );
}

$w3tc_count = count( $w3tc_cnames );
if ( isset( $w3tc_cnames['http_default'] ) ) {
	--$w3tc_count;
}
if ( isset( $w3tc_cnames['https_default'] ) ) {
	--$w3tc_count;
}

$w3tc_real_index = 0;
foreach ( $w3tc_cnames as $w3tc_index => $w3tc_cname ) :
	if ( 'http_default' === $w3tc_index || 'https_default' === $w3tc_index ) {
		continue;
	}

	$w3tc_label = '';

	if ( $w3tc_count > 1 ) :
		switch ( $w3tc_real_index ) :
			case 0:
				$w3tc_label = __( '(reserved for CSS)', 'w3-total-cache' );
				break;

			case 1:
				$w3tc_label = __( '(reserved for JS in <head>)', 'w3-total-cache' );
				break;

			case 2:
				$w3tc_label = __( '(reserved for JS after <body>)', 'w3-total-cache' );
				break;

			case 3:
				$w3tc_label = __( '(reserved for JS before </body>)', 'w3-total-cache' );
				break;

			default:
				$w3tc_label = '';
				break;
		endswitch;
	endif;
	?>
	<li>
		<input type="text" name="cdn_cnames[]" id="cdn_cnames_<?php echo esc_attr( $w3tc_real_index ); ?>"
			<?php Util_Ui::sealing_disabled( 'cdn.' ); ?> value="<?php echo esc_attr( $w3tc_cname ); ?>" size="60" />
		<input class="button cdn_cname_delete" type="button"
			<?php Util_Ui::sealing_disabled( 'cdn.' ); ?> value="<?php esc_attr_e( 'Delete', 'w3-total-cache' ); ?>"<?php echo ! $w3tc_index ? ' style="display: none;"' : ''; ?> />
		<span><?php echo esc_html( $w3tc_label ); ?></span>
	</li>
	<?php
	++$w3tc_real_index;
	endforeach;
?>
</ol>
<input id="cdn_cname_add" class="button" type="button" value="<?php esc_attr_e( 'Add CNAME', 'w3-total-cache' ); ?>"
	<?php Util_Ui::sealing_disabled( 'cdn.' ); ?> />
