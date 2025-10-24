<?php
/**
 * File: Extension_Swarmify_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Swarmify_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Extension_Swarmify_Plugin {
	/**
	 * Reject reason
	 *
	 * @var string
	 */
	private $reject_reason = '';

	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Initializes the plugin configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs the necessary hooks and filters for the plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_config_default_values', array( $this, 'w3tc_config_default_values' ) );

		$config = Dispatcher::config();
		// remainder only when extension is frontend-active.
		if ( ! $config->is_extension_active_frontend( 'swarmify' ) ) {
			return;
		}

		if ( $this->_active() ) {
			Util_Bus::add_ob_callback( 'swarmify', array( $this, 'ob_callback' ) );
		}

		add_filter( 'w3tc_footer_comment', array( $this, 'w3tc_footer_comment' ) );
	}

	/**
	 * Sets the default configuration values for the plugin.
	 *
	 * @param array $default_values The default configuration values.
	 *
	 * @return array Modified default configuration values.
	 */
	public function w3tc_config_default_values( $default_values ) {
		$default_values['swarmify'] = array(
			'reject.logged'    => false,
			'api_key'          => '',
			'handle.htmlvideo' => true,
			'handle.jwplayer'  => true,
		);

		return $default_values;
	}

	/**
	 * Processes the output buffer and modifies video and JWPlayer tags for integration with Swarmify.
	 *
	 * phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	 *
	 * @param string $buffer The HTML content to process.
	 *
	 * @return string Modified HTML content.
	 */
	public function ob_callback( $buffer ) {
		$c       = $this->_config;
		$api_key = $c->get_string( array( 'swarmify', 'api_key' ) );
		$api_key = preg_replace( '~[^0-9a-zA-Z-]~', '', $api_key ); // make safe.

		$bootstrap_required = false;

		if ( $c->get_boolean( array( 'swarmify', 'handle.htmlvideo' ) ) ) {
			$count  = 0;
			$buffer = preg_replace( '~<video([^<>]+)>~i', '<swarmvideo\\1>', $buffer, -1, $count );

			if ( $count ) {
				$buffer             = preg_replace( '~<\\/video>~', '</swarmvideo>', $buffer );
				$bootstrap_required = true;
			}
		}

		if ( $c->get_boolean( array( 'swarmify', 'handle.jwplayer' ) ) ) {
			$count  = 0;
			$buffer = preg_replace( '~jwplayer\s*\\(([^)]+)\\)\s*\\.setup\\(~', 'swarmify.jwPlayerEmbed(\\1, ', $buffer, -1, $count );

			if ( $count ) {
				$bootstrap_required = true;
			}
		}

		// add bootstrap swarmify script if there are really any videos on page.
		if ( $bootstrap_required ) {
			$loader_script = '<script>var swarmoptions = {swarmcdnkey: "' . $api_key . '"};</script>' .
				'<script src="//assets.swarmcdn.com/cross/swarmcdn.js"></script>';

			$buffer = preg_replace( '~<head(\s+[^>]*)*>~Ui', '\\0' . $loader_script, $buffer, 1 );
		}

		return $buffer;
	}

	/**
	 * Checks whether the plugin should be active or not based on certain conditions.
	 *
	 * @return bool True if the plugin is active, false otherwise.
	 */
	public function _active() {
		$reject_reason = apply_filters( 'w3tc_swarmify_active', null );
		if ( ! empty( $reject_reason ) ) {
			$this->reject_reason = __( 'rejected by filter: ', 'w3-total-cache' ) . $reject_reason;
			return false;
		}

		/**
		 * Disable for AJAX so its not messed up
		 */
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$this->reject_reason = __( 'DOING_AJAX constant is defined', 'w3-total-cache' );
			return false;
		}

		if ( defined( 'WP_ADMIN' ) ) {
			$this->reject_reason = __( 'WP_ADMIN page', 'w3-total-cache' );
			return false;
		}

		/**
		 * Check logged users
		 */
		if ( $this->_config->get_boolean( array( 'swarmify', 'reject.logged' ) ) &&
			is_user_logged_in() ) {
			$this->reject_reason = __( 'logged in user rejected', 'w3-total-cache' );

			return false;
		}

		return true;
	}

	/**
	 * Modifies the footer comment with the plugin status.
	 *
	 * @param array $strings The footer comment strings.
	 *
	 * @return array Modified footer comment strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		$append    = ( '' !== $this->reject_reason ) ? sprintf( ' (%s)', $this->reject_reason ) : ' active';
		$strings[] = sprintf(
			// Translators: 1 status.
			__(
				'Swarmify%1$s',
				'w3-total-cache'
			),
			$append
		);

		return $strings;
	}
}

$p = new Extension_Swarmify_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_Swarmify_Plugin_Admin();
	$p->run();
}
