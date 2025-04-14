<?php
/**
 * File: Extensions_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extensions_Page
 *
 * phpcs:disable Generic.Commenting.DocComment.LongNotCapital
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extensions_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_extensions';

	/**
	 * Active tab
	 *
	 * @var string
	 */
	protected $_active_tab;

	/**
	 * Config settings
	 *
	 * @var array
	 */
	protected $_config_settings = array();

	/**
	 * Renders the content for extensions page.
	 *
	 * Retrieves and processes extension-related data based on the current request parameters, and includes the appropriate view template.
	 *
	 * @return void
	 */
	public function render_content() {
		$extension_status     = 'all';
		$extension_status_val = Util_Request::get_string( 'extension_status' );
		if ( ! empty( $extension_status_val ) ) {
			if ( in_array( $extension_status_val, array( 'all', 'active', 'inactive', 'core' ), true ) ) {
				$extension_status = $extension_status_val;
			}
		}

		$extension_val = Util_Request::get_string( 'extension' );
		$extension     = ( ! empty( $extension_val ) ? esc_attr( $extension_val ) : '' );

		$action_val = Util_Request::get_string( 'action' );
		$view       = ( ! empty( $action_val ) && 'view' === $action_val );

		$extensions_active = Extensions_Util::get_active_extensions( $this->_config );
		foreach ( $extensions_active as $key => $ext ) {
			if ( isset( $ext['public'] ) && false === $ext['public'] ) {
				unset( $extensions_active[ $key ] );
			}
		}

		if ( $extension && $view && ! empty( $extensions_active[ $extension ] ) ) {
			$all_settings = $this->_config->get_array( 'extensions.settings' );
			$meta         = $extensions_active[ $extension ];
			$sub_view     = 'settings';
		} else {
			$extensions_all = Extensions_Util::get_extensions( $this->_config );
			foreach ( $extensions_all as $key => $ext ) {
				if ( isset( $ext['public'] ) && false === $ext['public'] ) {
					unset( $extensions_all[ $key ] );
				}
			}

			$extensions_inactive = Extensions_Util::get_inactive_extensions( $this->_config );
			foreach ( $extensions_inactive as $key => $ext ) {
				if ( isset( $ext['public'] ) && false === $ext['public'] ) {
					unset( $extensions_inactive[ $key ] );
				}
			}

			$var            = "extensions_{$extension_status}";
			$extensions     = $$var;
			$extension_keys = array_keys( $extensions );
			sort( $extension_keys );

			$sub_view = 'list';
			$page     = 1;
		}

		include W3TC_INC_OPTIONS_DIR . '/extensions.php';
	}

	/**
	 * Returns the default metadata for an extension.
	 *
	 * Merges the provided metadata with a set of default values.
	 *
	 * @param array $meta {
	 *     Array of extension metadata to be merged with defaults.
	 *
	 *     @type string  $name          Extension name.
	 *     @type string  $author        Author name.
	 *     @type string  $description   Extension description.
	 *     @type string  $author_uri    Author URL.
	 *     @type string  $extension_uri Extension URL.
	 *     @type string  $extension_id  Unique extension ID.
	 *     @type string  $version       Extension version.
	 *     @type bool    $enabled       Whether the extension is enabled. Default true.
	 *     @type array   $requirements  List of extension requirements.
	 *     @type bool    $core          Whether the extension is a core feature. Default false.
	 *     @type bool    $public        Whether the extension is publicly available. Default true.
	 *     @type string  $path          File path to the extension.
	 * }
	 *
	 * @return array Merged array of default and provided extension metadata.
	 */
	public function default_meta( $meta ) {
		$default = array(
			'name'          => '',
			'author'        => '',
			'description'   => '',
			'author_uri'    => '',
			'extension_uri' => '',
			'extension_id'  => '',
			'version'       => '',
			'enabled'       => true,
			'requirements'  => array(),
			'core'          => false,
			'public'        => true,
			'path'          => '',
		);
		return array_merge( $default, $meta );
	}
}
