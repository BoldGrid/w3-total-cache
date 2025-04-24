<?php
/**
 * File: ConfigState.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ConfigState
 *
 * Provides state information - state can be changed by plugin during lifetime,
 * while configuration is static
 *
 * master keys:
 *   common.install - time() of plugin installation
 *   common.support_us_invitations - number of invitations to support us shown
 *   common.next_support_us_invitation - time() of next support us invitation
 *   common.hide_note_wp_content_permissions
 *   common.hide_note_no_zlib
 *   common.hide_note_zlib_output_compression
 *   common.show_note.nginx_restart_required
 *   common.hide_note_php_version_56
 *   license.status
 *   license.next_check - time of next check
 *   license.terms - accepted/declined/''
 *   license.community_terms - accepted/declined/'' (master)
 *   minify.error.file
 *   minify.error.last
 *   minify.error.notification.last
 *   minify.show_note_minify_error
 *   minify.hide_minify_help
 *   extension.cloudflare.next_ips_check
 *   extension.cloudflare.ips.ip4
 *   extension.cloudflare.ips.ip6
 *
 * blog-level keys:
 *   newrelic.hide_note_pageload_slow
 *   minify.show_note.need_flush
 *   minify.show_note.need_flush.timestamp - when the note was set
 *   cdn.hide_note_no_curl
 *   cdn.google_drive.access_token
 *   cdn.rackspace_cf.access_state
 *   cdn.rackspace_cdn.access_state
 *   cdn.show_note_theme_changed
 *   cdn.show_note_wp_upgraded
 *   cdn.show_note_cdn_upload
 *   cdn.show_note_cdn_reupload
 *   common.hide_note_no_permalink_rules
 *   common.show_note.plugins_updated
 *   common.show_note.plugins_updated.timestamp - when the note was set
 *   common.show_note.flush_statics_needed
 *   common.show_note.flush_statics_needed.timestamp
 *   common.show_note.flush_posts_needed
 *   common.show_note.flush_posts_needed.timestamp - when the note was set
 *   objectcache.show_note.flush_needed
 *   objectcache.show_note.flush_needed.timestamp - when the note was set
 *   extension.<extension_id>.hide_note_suggest_activation
 *   track.bunnycdn_signup
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class ConfigState {
	/**
	 * Data
	 *
	 * @var array
	 */
	private $_data;

	/**
	 * Is master flag
	 *
	 * @var bool
	 */
	private $_is_master;

	/**
	 * Initializes the configuration state.
	 *
	 * @param bool $is_master Whether this is the master configuration state.
	 *
	 * @return void
	 */
	public function __construct( $is_master ) {
		$this->_is_master = $is_master;

		if ( $is_master ) {
			$data_raw = get_site_option( 'w3tc_state' );
		} else {
			$data_raw = get_option( 'w3tc_state' );
		}

		$this->_data = @json_decode( $data_raw, true );
		if ( ! is_array( $this->_data ) ) {
			$this->_data = array();
			$this->apply_defaults();
			$this->save();
		}
	}

	/**
	 * Retrieves a value from the configuration state by key.
	 *
	 * @param string $key           The key to retrieve.
	 * @param mixed  $default_value The default value to return if the key is not set.
	 *
	 * @return mixed The value associated with the key, or the default value.
	 */
	public function get( $key, $default_value ) {
		if ( ! isset( $this->_data[ $key ] ) ) {
			return $default_value;
		}

		return $this->_data[ $key ];
	}

	/**
	 * Retrieves a string value from the configuration state.
	 *
	 * @param string $key           The key to retrieve.
	 * @param string $default_value The default string to return if the key is not set. Default is an empty string.
	 * @param bool   $trim          Whether to trim the returned string. Default is true.
	 *
	 * @return string The string value associated with the key, or the default string.
	 */
	public function get_string( $key, $default_value = '', $trim = true ) {
		$value = (string) $this->get( $key, $default_value );

		return $trim ? trim( $value ) : $value;
	}

	/**
	 * Retrieves an integer value from the configuration state.
	 *
	 * @param string $key           The key to retrieve.
	 * @param int    $default_value The default integer to return if the key is not set. Default is 0.
	 *
	 * @return int The integer value associated with the key, or the default integer.
	 */
	public function get_integer( $key, $default_value = 0 ) {
		return (int) $this->get( $key, $default_value );
	}

	/**
	 * Retrieves a boolean value from the configuration state.
	 *
	 * @param string $key           The key to retrieve.
	 * @param bool   $default_value The default boolean to return if the key is not set. Default is false.
	 *
	 * @return bool The boolean value associated with the key, or the default boolean.
	 */
	public function get_boolean( $key, $default_value = false ) {
		$v = $this->get( $key, $default_value );
		if ( 'false' === $v || empty( $v ) ) {
			$v = false;
		}

		return (bool) $v;
	}

	/**
	 * Retrieves an array value from the configuration state.
	 *
	 * @param string $key           The key to retrieve.
	 * @param array  $default_value The default array to return if the key is not set. Default is an empty array.
	 *
	 * @return array The array value associated with the key, or the default array.
	 */
	public function get_array( $key, $default_value = array() ) {
		return (array) $this->get( $key, $default_value );
	}

	/**
	 * Sets a value in the configuration state.
	 *
	 * @param string $key   The key to set.
	 * @param mixed  $value The value to associate with the key.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->_data[ $key ] = $value;
	}

	/**
	 * Resets the configuration state to its default values.
	 *
	 * @return void
	 */
	public function reset() {
		$this->_data = array();
		$this->apply_defaults();
	}

	/**
	 * Saves the current configuration state.
	 *
	 * @return void
	 */
	public function save() {
		if ( $this->_is_master ) {
			update_site_option( 'w3tc_state', wp_json_encode( $this->_data ) );
		} else {
			update_option( 'w3tc_state', wp_json_encode( $this->_data ) );
		}
	}

	/**
	 * Applies default values to the configuration state.
	 *
	 * @return void
	 */
	private function apply_defaults() {
		$this->set( 'common.install', time() );
		$this->set( 'common.install_version', W3TC_VERSION );
	}
}
