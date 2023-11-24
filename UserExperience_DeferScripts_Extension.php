<?php
/**
 * File: UserExperience_DeferScripts_Extension.php
 *
 * Controls the defer JS feature.
 *
 * @since 2.4.2
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience DeferScripts Extension.
 *
 * @since 2.4.2
 */
class UserExperience_DeferScripts_Extension {
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
	 * User Experience DeferScripts constructor.
	 *
	 * @since 2.4.2
	 */
	public function __construct() {
		$this->config = Dispatcher::config();
	}

	/**
	 * Runs User Experience DeferScripts feature.
	 *
	 * @since 2.4.2
	 *
	 * @return void
	 */
	public function run() {
		if ( ! Util_Environment::is_w3tc_pro( $this->config ) ) {
			$this->config->set_extension_active_frontend( 'user-experience-defer-scripts', false );
			return;
		}

		Util_Bus::add_ob_callback( 'deferscripts', array( $this, 'ob_callback' ) );

		add_filter( 'w3tc_minify_js_script_tags', array( $this, 'w3tc_minify_js_script_tags' ) );
		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ) );

		add_action( 'w3tc_userexperience_page', array( $this, 'w3tc_userexperience_page' ), 11 );

		/**
		 * This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		*/
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );
	}

	/**
	 * Processes the page content buffer to defer JS.
	 *
	 * @since 2.4.2
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
		$can_process = apply_filters( 'w3tc_deferscripts_can_process', $can_process );

		// set reject reason in comment.
		if ( $can_process['enabled'] ) {
			$reject_reason = '';
		} else {
			$reject_reason = empty( $can_process['reason'] ) ? ' (not specified)' : ' (' . $can_process['reason'] . ')';
		}

		$buffer = str_replace(
			'{w3tc_deferscripts_reject_reason}',
			$reject_reason,
			$buffer
		);

		// processing.
		if ( ! $can_process['enabled'] ) {
			return $buffer;
		}

		$this->mutator = new UserExperience_DeferScripts_Mutator( $this->config );

		$buffer = $this->mutator->run( $buffer );

		// embed lazyload script.
		if ( $this->mutator->content_modified() ) {
			$buffer = apply_filters( 'w3tc_deferscripts_embed_script', $buffer );

			$is_embed_script = apply_filters( 'w3tc_deferscripts_is_embed_script', true );
			if ( $is_embed_script ) {
				$buffer = $this->embed_script( $buffer );
			}
		}

		return $buffer;
	}

	/**
	 * Checks if the request can be processed for defer JS.
	 *
	 * @since 2.4.2
	 *
	 * @param boolean $can_process flag representing if defer JS can be executed.
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
	 * Adds defer JS message to W3TC footer comment.
	 *
	 * @since 2.4.2
	 *
	 * @param array $strings array of W3TC footer comments.
	 *
	 * @return array
	 */
	public function w3tc_footer_comment( $strings ) {
		$strings[] = __( 'Defer Scripts', 'w3-total-cache' ) . '{w3tc_deferscripts_reject_reason}';
		return $strings;
	}

	/**
	 * Embeds the defer JS script in buffer.
	 *
	 * @since 2.4.2
	 *
	 * @param string $buffer page content buffer.
	 *
	 * @return string
	 */
	private function embed_script( $buffer ) {
		$config_timeout = $this->config->get_integer(
			array(
				'user-experience-defer-scripts',
				'timeout',
			)
		);

		$content = file_get_contents( W3TC_DIR . '/UserExperience_DeferScripts_Script.js' );
		$content = str_replace(
			'{config_timeout}',
			$config_timeout,
			$content
		);

		$footer_script = '<script>' . $content . '</script>';

		$buffer = preg_replace(
			'~</body(\s+[^>]*)*>~Ui',
			$footer_script . '\\0',
			$buffer,
			1
		);

		return $buffer;
	}

	/**
	 * Filters script tags that are flaged as deferred. This is used to prevent Minify from touching scripts deferred by this feature.
	 *
	 * @since 2.4.2
	 *
	 * @param array $script_tags array of script tags.
	 *
	 * @return array
	 */
	public function w3tc_minify_js_script_tags( $script_tags ) {
		if ( ! is_null( $this->mutator ) ) {
			$script_tags = $this->mutator->w3tc_minify_js_script_tags( $script_tags );
		}

		return $script_tags;
	}

	/**
	 * Renders the user experience defer JS settings page.
	 *
	 * @since 2.4.2
	 *
	 * @return void
	 */
	public function w3tc_userexperience_page() {
		if ( self::is_enabled() ) {
			include __DIR__ . '/UserExperience_DeferScripts_Page_View.php';
		}
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since 2.4.2
	 *
	 * @param mixed $descriptor Descriptor.
	 * @param mixed $key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $descriptor, $key ) {
		if ( is_array( $key ) && 'user-experience-defer-scripts.includes' === implode( '.', $key ) ) {
			$descriptor = array( 'type' => 'array' );
		}

		return $descriptor;
	}

	/**
	 * Performs actions on save.
	 *
	 * @since 2.4.2
	 *
	 * @param array $data Array of save data.
	 *
	 * @return array
	 */
	public function w3tc_save_options( $data ) {
		$new_config = $data['new_config'];
		$old_config = $data['old_config'];

		if (
			$new_config->get_array( array( 'user-experience-defer-scripts', 'timeout' ) ) !== $old_config->get_array( array( 'user-experience-defer-scripts', 'timeout' ) )
			|| $new_config->get_array( array( 'user-experience-defer-scripts', 'includes' ) ) !== $old_config->get_array( array( 'user-experience-defer-scripts', 'includes' ) )
		) {
			$minify_enabled  = $this->config->get_boolean( 'minify.enabled' );
			$pgcache_enabled = $this->config->get_boolean( 'pgcache.enabled' );
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
		return Util_Environment::is_w3tc_pro( $config ) && array_key_exists( 'user-experience-defer-scripts', $extensions_active );
	}
}

$o = new UserExperience_DeferScripts_Extension();
$o->run();
