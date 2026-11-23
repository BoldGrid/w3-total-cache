<?php
/**
 * File: UserExperience_Preload_Requests_Extension.php
 *
 * Controls the Preload Requests feature.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
/**
 * UserExperience Preload Requests Extension.
 *
 * @since 2.6.0
 */
class UserExperience_Preload_Requests_Extension {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $w3tc_config;

	/**
	 * User Experience DNS Prefetc constructor.
	 *
	 * @since 2.6.0
	 */
	public function __construct() {
		$this->w3tc_config = Dispatcher::config();
	}

	/**
	 * Runs User Experience DNS Prefetc feature.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function run() {
		if ( ! Util_Environment::is_w3tc_pro( $this->w3tc_config ) ) {
			$this->w3tc_config->set_extension_active_frontend( 'user-experience-preload-requests', false );
			return;
		}

		// Applies logic to display page cache flush notice if Preload Requests settings are altered and saved.
		add_filter( 'w3tc_save_options', array( $this, 'w3tc_save_options' ) );

		// Renders the Preload Reqeusts settings metabox on the User Expereince advanced setting page.
		add_action( 'w3tc_userexperience_page', array( $this, 'w3tc_userexperience_page' ), 20 );

		// This filter is documented in Generic_AdminActions_Default.php under the read_request method.
		add_filter( 'w3tc_config_key_descriptor', array( $this, 'w3tc_config_key_descriptor' ), 10, 2 );

		// Applies dns-prefetch, preconnect, and preload headers.
		add_action( 'wp_head', array( $this, 'w3tc_preload_requests_headers' ) );
		add_action( 'admin_head', array( $this, 'w3tc_preload_requests_headers' ) );
	}

	/**
	 * Renders the user experience Preload Requests settings page.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_userexperience_page() {
		if ( self::is_enabled() ) {
			include __DIR__ . '/UserExperience_Preload_Requests_Page_View.php';
		}
	}

	/**
	 * Specify config key typing for fields that need it.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $w3tc_descriptor Descriptor.
	 * @param mixed $w3tc_key Compound key array.
	 *
	 * @return array
	 */
	public function w3tc_config_key_descriptor( $w3tc_descriptor, $w3tc_key ) {
		if (
			is_array( $w3tc_key ) &&
			in_array(
				implode( '.', $w3tc_key ),
				array(
					'user-experience-preload-requests.dns-prefetch',
					'user-experience-preload-requests.preconnect',
					'user-experience-preload-requests.preload-css',
					'user-experience-preload-requests.preload-js',
					'user-experience-preload-requests.preload-fonts',
					'user-experience-preload-requests.preload-images',
					'user-experience-preload-requests.preload-videos',
					'user-experience-preload-requests.preload-audio',
					'user-experience-preload-requests.preload-documents',
				),
				true
			)
		) {
			$w3tc_descriptor = array( 'type' => 'array' );
		}

		return $w3tc_descriptor;
	}

	/**
	 * Performs actions on save.
	 *
	 * @since 2.6.0
	 *
	 * @param array $w3tc_data Array of save data.
	 *
	 * @return array
	 */
	public function w3tc_save_options( $w3tc_data ) {
		$new_config = $w3tc_data['new_config'];
		$old_config = $w3tc_data['old_config'];

		$new_includes = $new_config->get_array( array( 'user-experience-preload-requests', 'includes' ) );
		$old_includes = $old_config->get_array( array( 'user-experience-preload-requests', 'includes' ) );

		if ( $new_includes !== $old_includes && $this->w3tc_config->get_boolean( 'pgcache.enabled' ) ) {
			$state = Dispatcher::config_state();
			$state->set( 'common.show_note.flush_posts_needed', true );
			$state->save();
		}

		return $w3tc_data;
	}

	/**
	 * Applies the Preload Requests headers for wp_head and admin_head.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_preload_requests_headers() {
		// Preconnect hints should be printed first so they take priority. If not supported then dns-prefetch will be the fallback.
		$preconnect = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preconnect' ) );
		foreach ( $preconnect as $w3tc_url ) {
			echo '<link rel="preconnect" href="' . esc_url( $w3tc_url ) . '" crossorigin>';
		}

		$dns_prefetch = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'dns-prefetch' ) );
		foreach ( $dns_prefetch as $w3tc_url ) {
			echo '<link rel="dns-prefetch" href="' . esc_url( $w3tc_url ) . '">';
		}

		$preload_css = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-css' ) );
		foreach ( $preload_css as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="style">';
		}

		$preload_js = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-js' ) );
		foreach ( $preload_js as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="script">';
		}

		$preload_fonts = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-fonts' ) );
		foreach ( $preload_fonts as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="font" type="font/woff2">';
		}

		$preload_images = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-images' ) );
		foreach ( $preload_images as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="image">';
		}

		$preload_videos = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-videos' ) );
		foreach ( $preload_videos as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="video">';
		}

		$preload_audio = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-audio' ) );
		foreach ( $preload_audio as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="audio">';
		}

		$preload_documents = $this->w3tc_config->get_array( array( 'user-experience-preload-requests', 'preload-documents' ) );
		foreach ( $preload_documents as $w3tc_url ) {
			echo '<link rel="preload" href="' . esc_url( $w3tc_url ) . '" as="document">';
		}
	}

	/**
	 * Gets the enabled status of the extension.
	 *
	 * @since 2.5.1
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$w3tc_config       = Dispatcher::config();
		$extensions_active = $w3tc_config->get_array( 'extensions.active' );
		return Util_Environment::is_w3tc_pro( $w3tc_config ) && array_key_exists( 'user-experience-preload-requests', $extensions_active );
	}
}

$w3tc_o = new UserExperience_Preload_Requests_Extension();
$w3tc_o->run();
