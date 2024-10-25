<?php
/**
 * File: UserExperience_PartyTown_Extension.php
 *
 * Controls the PartyTown feature.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience PartyTown Extension.
 *
 * @since X.X.X
 */
class UserExperience_PartyTown_Extension {
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
	 * User Experience PartyTown constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		$this->config = Dispatcher::config();
	}

	/**
	 * Runs User Experience PartyTown feature.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_userexperience_page', array( $this, 'w3tc_userexperience_page' ), 14 );

		/**
		 * This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		*/
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );

		if ( ! Util_Environment::is_w3tc_pro( $this->config ) ) {
			$this->config->set_extension_active_frontend( 'user-experience-partytown', false );
			return;
		}

		Util_Bus::add_ob_callback( 'partytown', array( $this, 'ob_callback' ) );

		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ), 11, 2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'w3tc_enqueue_partytown' ) );
	}

	/**
	 * Enqueue main PartyTown script.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_enqueue_partytown() {
		$party_path = substr( plugins_url( '/lib/PartyTown', W3TC_LIB_DIR ), strlen( site_url() ) );
		$init_path  = substr( plugins_url( '/pub/js', W3TC_LIB_DIR ), strlen( site_url() ) );

		wp_enqueue_script( 'partytown', $party_path . '/lib/debug/partytown.js', array(), W3TC_VERSION, false );

		if ( $this->config->get_boolean( array( 'user-experience-partytown', 'preload' ) ) ) {
			wp_script_add_data( 'partytown', 'preload', 'true' );
		}

		wp_register_script( 'partytown-init', $init_path . '/partytown-init.js', array( 'partytown' ), W3TC_VERSION, true );
		wp_localize_script(
			'partytown-init',
			'partytownConfig',
			array(
				'lib'               => $party_path . '/lib/',
				'debug'             => $this->config->get_boolean( array( 'user-experience-partytown', 'debug' ) ) ?? false,
				'timeout'           => $this->config->get_integer( array( 'user-experience-partytown', 'timeout' ) ) ?? 2000,
				'workerConcurrency' => $this->config->get_integer( array( 'user-experience-partytown', 'workers' ) ) ?? 5,
			)
		);
		wp_enqueue_script( 'partytown-init' );
	}

	/**
	 * Processes the page content buffer to modify target CSS/JS.
	 *
	 * @since X.X.X
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
		$can_process = apply_filters( 'w3tc_partytown_can_process', $can_process );

		// set reject reason in comment.
		if ( $can_process['enabled'] ) {
			$reject_reason = '';
		} else {
			$reject_reason = empty( $can_process['reason'] ) ? ' (not specified)' : ' (' . $can_process['reason'] . ')';
		}

		$buffer = str_replace(
			'{w3tc_partytown_reject_reason}',
			$reject_reason,
			$buffer
		);

		// processing.
		if ( ! $can_process['enabled'] ) {
			return $buffer;
		}

		$this->mutator = new UserExperience_PartyTown_Mutator( $this->config );

		$buffer = $this->mutator->run( $buffer );

		return $buffer;
	}

	/**
	 * Checks if the request can be processed for PartyTown.
	 *
	 * @since X.X.X
	 *
	 * @param boolean $can_process flag representing if PartyTown can be executed.
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
	 * Adds PartyTown message to W3TC footer comment.
	 *
	 * @since X.X.X
	 *
	 * @param array $strings array of W3TC footer comments.
	 *
	 * @return array
	 */
	public function w3tc_footer_comment( $strings ) {
		$strings[] = __( 'PartyTown', 'w3-total-cache' ) . '{w3tc_partytown_reject_reason}';
		return $strings;
	}

	/**
	 * Renders the user experience PartyTown settings page.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_userexperience_page() {
		include __DIR__ . '/UserExperience_PartyTown_Page_View.php';
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $descriptor Descriptor.
	 * @param mixed $key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $descriptor, $key ) {
		if ( is_array( $key ) && 'user-experience-partytown.includes' === implode( '.', $key ) ) {
			$descriptor = array( 'type' => 'array' );
		}

		return $descriptor;
	}

	/**
	 * Performs actions on save.
	 *
	 * @since X.X.X
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

			$old_partytown_includes = $old_config->get_array( array( 'user-experience-partytown', 'includes' ) );
			$new_partytown_includes = $new_config->get_array( array( 'user-experience-partytown', 'includes' ) );

			if ( $new_partytown_includes !== $old_partytown_includes ) {
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
		return Util_Environment::is_w3tc_pro( $config ) && array_key_exists( 'user-experience-partytown', $extensions_active );
	}
}

$o = new UserExperience_PartyTown_Extension();
$o->run();
