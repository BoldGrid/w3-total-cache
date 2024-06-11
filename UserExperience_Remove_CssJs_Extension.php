<?php
/**
 * File: UserExperience_Remove_CssJs_Extension.php
 *
 * Controls the Remove CSS/JS feature.
 *
 * @since 2.7.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience Remove Css/Js Extension.
 *
 * @since 2.7.0
 */
class UserExperience_Remove_CssJs_Extension {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $config;

	/**
	 * Mutator.
	 *
	 * @var object
	 */
	private $mutator;

	/**
	 * User Experience Remove Css/Js constructor.
	 *
	 * @since 2.7.0
	 */
	public function __construct() {
		$this->config = Dispatcher::config();
	}

	/**
	 * Runs User Experience Remove Css/Js feature.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_userexperience_page', array( $this, 'w3tc_userexperience_page' ), 12 );

		/**
		 * This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		*/
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );

		if ( ! Util_Environment::is_w3tc_pro( $this->config ) ) {
			$this->config->set_extension_active_frontend( 'user-experience-remove-cssjs', false );
			return;
		}

		Util_Bus::add_ob_callback( 'removecssjs', array( $this, 'ob_callback' ) );

		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ), 10, 2 );

		add_action( 'w3tc_userexperience_page', array( $this, 'w3tc_userexperience_page' ), 12 );

		/**
		 * This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		*/
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );
	}

	/**
	 * Processes the page content buffer to remove target CSS/JS.
	 *
	 * @since 2.7.0
	 *
	 * @param string $buffer page content buffer.
	 *
	 * @return string
	 */
	public function ob_callback( $buffer ) {
		if ( '' === $buffer || ! \W3TC\Util_Content::is_html_xml( $buffer ) ) {
			return $buffer;
		}

		$can_process = array(
			'enabled' => true,
			'buffer'  => $buffer,
			'reason'  => null,
		);

		$can_process = $this->can_process( $can_process );
		$can_process = apply_filters( 'w3tc_remove_cssjs_can_process', $can_process );

		// set reject reason in comment.
		if ( $can_process['enabled'] ) {
			$reject_reason = '';
		} else {
			$reject_reason = empty( $can_process['reason'] ) ? ' (not specified)' : ' (' . $can_process['reason'] . ')';
		}

		$buffer = str_replace(
			'{w3tc_remove_cssjs_reject_reason}',
			$reject_reason,
			$buffer
		);

		// processing.
		if ( ! $can_process['enabled'] ) {
			return $buffer;
		}

		$this->mutator = new UserExperience_Remove_CssJs_Mutator( $this->config );

		$buffer = $this->mutator->run( $buffer );

		return $buffer;
	}

	/**
	 * Checks if the request can be processed for remove CSS/JS.
	 *
	 * @since 2.7.0
	 *
	 * @param boolean $can_process flag representing if remove CSS/JS can be executed.
	 *
	 * @return boolean
	 */
	private function can_process( $can_process ) {
		if ( defined( 'WP_ADMIN' ) ) {
			$can_process['enabled'] = false;
			$can_process['reason']  = 'WP_ADMIN';

			return $can_process;
		}

		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			$can_process['enabled'] = false;
			$can_process['reason']  = 'SHORTINIT';

			return $can_process;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$can_process['enabled'] = false;
			$can_process['reason']  = 'feed';

			return $can_process;
		}

		return $can_process;
	}

	/**
	 * Adds remove CSS/JS message to W3TC footer comment.
	 *
	 * @since 2.7.0
	 *
	 * @param array $strings array of W3TC footer comments.
	 *
	 * @return array
	 */
	public function w3tc_footer_comment( $strings ) {
		$strings[] = __( 'Remove CSS/JS', 'w3-total-cache' ) . '{w3tc_remove_cssjs_reject_reason}';
		return $strings;
	}

	/**
	 * Renders the user experience remove CSS/JS settings page.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function w3tc_userexperience_page() {
		include __DIR__ . '/UserExperience_Remove_CssJs_Page_View.php';
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since 2.7.0
	 *
	 * @param mixed $descriptor Descriptor.
	 * @param mixed $key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $descriptor, $key ) {
		if ( is_array( $key ) && 'user-experience-remove-cssjs.includes' === implode( '.', $key ) ) {
			$descriptor = array( 'type' => 'array' );
		}

		return $descriptor;
	}

	/**
	 * Performs actions on save.
	 *
	 * @since 2.7.0
	 *
	 * @param array $data Array of save data.
	 * @param array $page String page value.
	 *
	 * @return array
	 */
	public function w3tc_save_options( $data, $page ) {
		if ( 'w3tc_userexperience' === $page ) {
			$new_config =& $data['new_config'];
			$old_config =& $data['old_config'];

			$old_cssjs_includes = $old_config->get_array( array( 'user-experience-remove-cssjs', 'includes' ) );
			$old_cssjs_singles  = $old_config->get_array( 'user-experience-remove-cssjs-singles' );
			$new_cssjs_includes = $new_config->get_array( array( 'user-experience-remove-cssjs', 'includes' ) );
			$new_cssjs_singles  = $new_config->get_array( 'user-experience-remove-cssjs-singles' );

			if ( ! ( $new_cssjs_singles === $old_cssjs_singles ) ) {
				$raw_cssjs_singles = $new_config->get_array( 'user-experience-remove-cssjs-singles' );

				$new_cssjs_singles = array();
				foreach ( $raw_cssjs_singles as $single_id => $single_config ) {
					if ( ! empty( $single_config['url_pattern'] ) && ! empty( $single_config['action'] ) && is_string( $single_config['includes'] ) ) {
						$new_cssjs_singles[ $single_id ]['url_pattern'] = filter_var( $single_config['url_pattern'], FILTER_SANITIZE_URL );
						$new_cssjs_singles[ $single_id ]['action']      = $single_config['action'];
						$new_cssjs_singles[ $single_id ]['includes']    = Util_Environment::textarea_to_array( $single_config['includes'] );
					}
				}

				$new_config->set( 'user-experience-remove-cssjs-singles', $new_cssjs_singles );
			}

			if ( $new_cssjs_includes !== $old_cssjs_includes || $new_cssjs_singles !== $old_cssjs_singles ) {
				$minify_enabled  = $new_config->get_boolean( 'minify.enabled' );
				$pgcache_enabled = $new_config->get_boolean( 'pgcache.enabled' );
				if ( $minify_enabled || $pgcache_enabled ) {
					$state = Dispatcher::config_state();
					if ( $minify_enabled ) {
						$state->set( 'minify.show_note.need_flush', true );
					}
					if ( $pgcache_enabled ) {
						$state->set( 'common.show_note.flush_posts_needed', true );
					}
					$state->save();
				}
			}
		}

		return $data;
	}

	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since 2.5.1
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$config            = Dispatcher::config();
		$extensions_active = $config->get_array( 'extensions.active' );
		return Util_Environment::is_w3tc_pro( $config ) && array_key_exists( 'user-experience-remove-cssjs', $extensions_active );
	}
}

$o = new UserExperience_Remove_CssJs_Extension();
$o->run();
