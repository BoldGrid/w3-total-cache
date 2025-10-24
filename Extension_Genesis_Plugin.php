<?php
/**
 * File: Extension_Genesis_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_Genesis_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Extension_Genesis_Plugin {
	/**
	 * Request URI
	 *
	 * @var string
	 */
	private $_request_uri = '';

	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Constructs the Extension_Genesis_Plugin instance and initializes configurations.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Executes the main logic to initialize hooks and actions for the Extension_Genesis_Plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_config_default_values', array( $this, 'w3tc_config_default_values' ) );

		add_action( 'w3tc_register_fragment_groups', array( $this, 'register_groups' ) );

		$this->_config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
			if ( ! is_admin() ) {
				/**
				 * Register the caching of content to specific hooks
				 */
				foreach ( array( 'genesis_header', 'genesis_footer', 'genesis_sidebar', 'genesis_loop', 'wp_head', 'wp_footer', 'genesis_comments', 'genesis_pings' ) as $hook ) {
					add_action( $hook, array( $this, 'cache_genesis_start' ), -999999999 );
					add_action( $hook, array( $this, 'cache_genesis_end' ), 999999999 );
				}
				foreach ( array( 'genesis_do_subnav', 'genesis_do_nav' ) as $filter ) {
					add_filter( $filter, array( $this, 'cache_genesis_filter_start' ), -999999999 );
					add_filter( $filter, array( $this, 'cache_genesis_filter_end' ), 999999999 );
				}
			}

			/**
			 * Since posts pages etc are cached individually need to be able to flush just those and not all fragment
			 */
			add_action( 'clean_post_cache', array( $this, 'flush_post_fragment' ) );
			add_action( 'clean_post_cache', array( $this, 'flush_terms_fragment' ), 0, 0 );

			$this->_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		}
	}

	/**
	 * Adds default configuration values for the Genesis theme.
	 *
	 * @param array $default_values Existing default configuration values.
	 *
	 * @return array The modified configuration values.
	 */
	public function w3tc_config_default_values( $default_values ) {
		$default_values['genesis.theme'] = array(
			'wp_head'                        => '0',
			'genesis_header'                 => '1',
			'genesis_do_nav'                 => '0',
			'genesis_do_subnav'              => '0',
			'loop_front_page'                => '1',
			'loop_terms'                     => '1',
			'flush_terms'                    => '1',
			'loop_single'                    => '1',
			'loop_single_excluded'           => '',
			'loop_single_genesis_comments'   => '0',
			'loop_single_genesis_pings'      => '0',
			'sidebar'                        => '0',
			'sidebar_excluded'               => '',
			'genesis_footer'                 => '1',
			'wp_footer'                      => '0',
			'reject_logged_roles'            => '1',
			'reject_logged_roles_on_actions' => array(
				'genesis_loop',
				'wp_head',
				'wp_footer',
			),
			'reject_roles'                   => array( 'administrator' ),
		);

		return $default_values;
	}

	/**
	 * Starts fragment caching for Genesis-specific hooks.
	 *
	 * @return void
	 */
	public function cache_genesis_start() {
		$hook = current_filter();
		$keys = $this->_get_id_group( $hook );
		if ( is_null( $keys ) ) {
			return;
		}

		list( $id, $group ) = $keys;
		w3tc_fragmentcache_start( $id, $group, $hook );
	}

	/**
	 * Ends fragment caching for Genesis-specific hooks.
	 *
	 * @return void
	 */
	public function cache_genesis_end() {
		$keys = $this->_get_id_group( current_filter() );
		if ( is_null( $keys ) ) {
			return;
		}

		list( $id, $group ) = $keys;
		w3tc_fragmentcache_end( $id, $group, $this->_config->get_boolean( array( 'fragmentcache', 'debug' ) ) );
	}

	/**
	 * Starts fragment caching for Genesis navigation-related filters.
	 *
	 * @param mixed $data The data to be filtered.
	 *
	 * @return mixed The modified data after applying the filter.
	 */
	public function cache_genesis_filter_start( $data ) {
		$hook = current_filter();
		$keys = $this->_get_id_group( $hook, strpos( $data, 'current' ) !== false );
		if ( is_null( $keys ) ) {
			return $data;
		}

		list( $id, $group ) = $keys;
		return w3tc_fragmentcache_filter_start( $id, $group, $hook, $data );
	}

	/**
	 * Ends fragment caching for Genesis navigation-related filters.
	 *
	 * @param mixed $data The data to be filtered.
	 *
	 * @return mixed The modified data after applying the filter.
	 */
	public function cache_genesis_filter_end( $data ) {
		$keys = $this->_get_id_group( current_filter(), strpos( $data, 'current' ) !== false );
		if ( is_null( $keys ) ) {
			return $data;
		}

		list( $id, $group ) = $keys;
		return w3tc_fragmentcache_filter_end( $id, $group, $data );
	}

	/**
	 * Generates the Genesis fragment group identifier.
	 *
	 * @param string $subgroup The subgroup to append to the fragment group.
	 * @param bool   $state    Whether the group is for logged-in users.
	 *
	 * @return string The Genesis fragment group identifier.
	 */
	private function _genesis_group( $subgroup, $state = false ) {
		$postfix = '';
		if ( $state && is_user_logged_in() ) {
			$postfix = 'logged_in_';
		}

		return ( $subgroup ? "genesis_fragment_{$subgroup}_" : 'genesis_fragment_' ) . $postfix;
	}

	/**
	 * Retrieves the ID and group for fragment caching based on the current hook.
	 *
	 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
	 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found
	 * phpcs:disable Generic.CodeAnalysis.AssignmentInCondition
	 *
	 * @param string $hook         The current hook being processed.
	 * @param bool   $current_menu Whether the current menu is relevant to caching.
	 *
	 * @return array|null An array containing the ID and group or null if not applicable.
	 */
	private function _get_id_group( $hook, $current_menu = false ) {
		if ( $this->cannot_cache_current_hook() ) {
			return null;
		}

		switch ( true ) {
			case $keys = $this->generate_sidebar_keys():
				list( $group, $genesis_id ) = $keys;
				break;
			case $keys = $this->generate_genesis_loop_keys():
				list( $group, $genesis_id ) = $keys;
				break;
			case $keys = $this->generate_genesis_comments_pings_keys():
				list( $group, $genesis_id ) = $keys;
				break;
			case $keys = $this->generate_genesis_navigation_keys( $current_menu ):
				list( $group, $genesis_id ) = $keys;
				break;
			default:
				$group      = $hook;
				$genesis_id = $this->get_page_slug();
				if ( is_paged() ) {
					$genesis_id .= $this->get_paged_page_key();
				}
				break;
		}

		if ( $this->_cache_group( $group ) && ! $this->_exclude_page( $group ) ) {
			return array( $genesis_id, $this->_genesis_group( $group, true ) );
		}

		return null;
	}

	/**
	 * Checks if a group should be cached based on the plugin's configuration.
	 *
	 * @param string $group The group name to check.
	 *
	 * @return bool True if the group should be cached, false otherwise.
	 */
	private function _cache_group( $group ) {
		return $this->_config->get_string( array( 'genesis.theme', $group ) );
	}

	/**
	 * Checks if a page should be excluded from caching based on the plugin's configuration.
	 *
	 * @param string $group The group name to check.
	 *
	 * @return bool True if the page should be excluded, false otherwise.
	 */
	private function _exclude_page( $group ) {
		$reject_uri = $this->_config->get_array( array( 'genesis.theme', "{$group}_excluded" ) );

		if ( is_null( $reject_uri ) || ! is_array( $reject_uri ) || empty( $reject_uri ) ) {
			return false;
		}

		$auto_reject_uri = array(
			'wp-login',
			'wp-register',
		);

		foreach ( $auto_reject_uri as $uri ) {
			if ( strstr( $this->_request_uri, $uri ) !== false ) {
				return true;
			}
		}

		$reject_uri = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $reject_uri );

		foreach ( $reject_uri as $expr ) {
			$expr = trim( $expr );
			$expr = str_replace( '~', '\~', $expr );
			if ( '' !== $expr && preg_match( '~' . $expr . '~i', $this->_request_uri ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registers groups and their corresponding flush actions for fragment caching.
	 *
	 * @return void
	 */
	public function register_groups() {
		// blog specific group and an array of actions that will trigger a flush of the group.
		$groups = array(
			$this->_genesis_group( '' )                => array(
				'clean_post_cache',
				'update_option_sidebars_widgets',
				'wp_update_nav_menu_item',
			),
			$this->_genesis_group( 'sidebar' )         => array(
				'update_option_sidebars_widgets',
			),
			$this->_genesis_group( 'loop_single' )     => array(
				'no_action',
			),
			$this->_genesis_group( 'loop_front_page' ) => array(
				'clean_post_cache',
			),
			$this->_genesis_group( 'loop_terms' )      => array(
				'no_action',
			),
		);

		foreach ( $groups as $group => $actions ) {
			w3tc_register_fragment_group( $group, $actions, 3600 );
		}
	}

	/**
	 * Flushes fragment cache for a specific post.
	 *
	 * @param int $post_ID The ID of the post whose fragment cache needs to be flushed.
	 *
	 * @return void
	 */
	public function flush_post_fragment( $post_ID ) {
		$page_slug = $this->get_page_slug( $post_ID );
		$urls      = Util_PageUrls::get_post_urls( $post_ID );
		$hooks     = array( 'genesis_loop', 'genesis_comments', 'genesis_pings' );
		foreach ( $hooks as $hook ) {
			$genesis_id = $page_slug;
			$genesis_id = "{$hook}_{$genesis_id}";

			w3tc_fragmentcache_flush_fragment( $genesis_id, $this->_genesis_group( 'loop_single_logged_in' ) );
			w3tc_fragmentcache_flush_fragment( $genesis_id, $this->_genesis_group( 'loop_single' ) );

			$count = count( $urls );
			for ( $page = 0; $page <= $count; $page++ ) {
				$genesis_id  = $page_slug;
				$genesis_id .= $this->get_paged_page_key( $page );
				$genesis_id  = "{$hook}_{$genesis_id}";

				w3tc_fragmentcache_flush_fragment( $genesis_id, $this->_genesis_group( 'loop_single_logged_in' ) );
				w3tc_fragmentcache_flush_fragment( $genesis_id, $this->_genesis_group( 'loop_single' ) );
			}
		}
	}

	/**
	 * Flushes fragment cache for terms if enabled in the configuration.
	 *
	 * @return void
	 */
	public function flush_terms_fragment() {
		if ( $this->_config->get_boolean( array( 'genesis.theme', 'flush_terms' ) ) ) {
			w3tc_fragmentcache_flush_group( 'loop_terms' );
		}
	}

	/**
	 * Determines whether the current hook can be cached.
	 *
	 * @return bool True if the current hook cannot be cached, false otherwise.
	 */
	private function cannot_cache_current_hook() {
		if ( is_user_logged_in() && $this->_config->get_boolean( array( 'genesis.theme', 'reject_logged_roles' ) ) ) {
			$roles = $this->_config->get_array( array( 'genesis.theme', 'reject_roles' ) );
			if ( $roles ) {
				$hooks = $this->_config->get_array( array( 'genesis.theme', 'reject_logged_roles_on_actions' ) );
				$hook  = current_filter();
				foreach ( $roles as $role ) {
					if ( $hooks && current_user_can( $role ) && in_array( $hook, $hooks, true ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Generates keys for fragment caching related to Genesis loops.
	 *
	 * @return array|null An array containing group and Genesis ID or null if not applicable.
	 */
	private function generate_genesis_loop_keys() {
		$hook = current_filter();
		if ( 'genesis_loop' !== $hook ) {
			return null;
		}

		if ( is_front_page() ) {
			$group = 'loop_front_page';
		} elseif ( is_single() ) {
			$group = 'loop_single';
		} else {
			$group = 'loop_terms';
		}

		$genesis_id = $this->get_page_slug();
		if ( is_paged() ) {
			$genesis_id .= $this->get_paged_page_key();
		}

		$genesis_id = "{$hook}_{$genesis_id}";

		return array( $group, $genesis_id );
	}

	/**
	 * Generates keys for fragment caching related to Genesis sidebars.
	 *
	 * @return array|null An array containing group and Genesis ID or null if not applicable.
	 */
	private function generate_sidebar_keys() {
		$hook = current_filter();
		if ( true !== strpos( $hook, 'sidebar' ) ) {
			return null;
		}

		$genesis_id = $hook;
		$group      = 'sidebar';
		return array( $group, $genesis_id );
	}

	/**
	 * Generates keys for fragment caching related to Genesis comments and pings.
	 *
	 * @return array|null An array containing group and Genesis ID or null if not applicable.
	 */
	private function generate_genesis_comments_pings_keys() {
		$hook = current_filter();
		if ( 'genesis_comments' !== $hook ) {
			return null;
		}

		$group = 'loop_single';

		$genesis_id = $this->get_page_slug();
		if ( is_paged() ) {
			$genesis_id .= $this->get_paged_page_key();
		}

		$genesis_id = "{$hook}_{$genesis_id}";

		return array( $group, $genesis_id );
	}

	/**
	 * Generates keys for fragment caching related to Genesis navigation menus.
	 *
	 * @param bool $current_menu Whether to use the current menu for generating keys.
	 *
	 * @return array|null An array containing group and Genesis ID or null if not applicable.
	 */
	private function generate_genesis_navigation_keys( $current_menu ) {
		$hook = current_filter();
		if ( ! ( strpos( $hook, '_nav' ) && $current_menu ) ) {
			return null;
		}

		$genesis_id = $this->get_page_slug();
		if ( is_paged() ) {
			$genesis_id .= $this->get_paged_page_key();
		}

		return array( $hook, $genesis_id );
	}

	/**
	 * Retrieves the slug for the current page or a specific post.
	 *
	 * @param int|null $post_ID Optional. The ID of the post to retrieve the slug for.
	 *
	 * @return string The generated slug for the page or post.
	 */
	private function get_page_slug( $post_ID = null ) {
		if ( $post_ID ) {
			$purl = get_permalink( $post_ID );
			return str_replace( '/', '-', trim( str_replace( home_url(), '', $purl ), '/' ) );
		}

		if ( is_front_page() ) {
			return 'front_page';
		}

		return isset( $_SERVER['REQUEST_URI'] ) ? str_replace( '/', '-', trim( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/' ) ) : '';
	}

	/**
	 * Retrieves the key for paged pages based on the given page number.
	 *
	 * @param int|null $page Optional. The page number. If null, the global query's paged variable is used.
	 *
	 * @return string The key for the paged page.
	 */
	private function get_paged_page_key( $page = null ) {
		if ( is_null( $page ) ) {
			global $wp_query;
			return '_' . $wp_query->query_vars['paged'] . '_';
		}

		return '_' . $page . '_';
	}
}

$p = new Extension_Genesis_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_Genesis_Plugin_Admin();
	$p->run();
}
