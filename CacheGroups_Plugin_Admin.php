<?php
/**
 * File: CacheGroups_Plugin_Admin.php
 *
 * @since 2.1.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: CacheGroups_Plugin_Admin
 *
 * @since 2.1.0
 */
class CacheGroups_Plugin_Admin extends Base_Page_Settings {
	/**
	 * Current page.
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_cachegroups'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Cache groups settings view.
	 *
	 * @since 2.1.0
	 */
	public function view() {
		$w3tc_c = Dispatcher::config();

		// Header.
		require W3TC_INC_DIR . '/options/common/header.php';

		// User agent groups.
		$useragent_groups = array(
			'value'       => $w3tc_c->get_array( 'mobile.rgroups' ),
			'disabled'    => $w3tc_c->is_sealed( 'mobile.rgroups' ),
			'description' =>
				'<li>' .
				__(
					'Enabling even a single user agent group will set a cookie called "w3tc_referrer." It is used to ensure a consistent user experience across page views. Make sure any reverse proxy servers etc. respect this cookie for proper operation.',
					'w3-total-cache'
				) .
				'</li>' .
				'<li>' .
				__(
					'Per the above, make sure that visitors are notified about the cookie as per any regulations in your market.',
					'w3-total-cache'
				) .
				'</li>',
		);

		$useragent_groups = apply_filters( 'w3tc_ui_config_item_mobile.rgroups', $useragent_groups ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		$w3_mobile        = Dispatcher::component( 'Mobile_UserAgent' );
		$useragent_themes = $w3_mobile->get_themes();

		// Referrer groups.
		$referrer_groups = $this->_config->get_array( 'referrer.rgroups' );
		$w3_referrer     = Dispatcher::component( 'Mobile_Referrer' );
		$referrer_themes = $w3_referrer->get_themes();

		// Cookie groups.
		$cookie_groups = array(
			'value'    => $w3tc_c->get_array( 'pgcache.cookiegroups.groups' ),
			'disabled' => $w3tc_c->is_sealed( 'pgcache.cookiegroups.groups' ),
		);
		$cookie_groups = apply_filters( 'w3tc_ui_config_item_pgcache.cookiegroups.groups', $cookie_groups ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// Load view.
		require W3TC_DIR . '/CacheGroups_Plugin_Admin_View.php';
	}

	/**
	 * Save settings.
	 *
	 * @since 2.1.0
	 *
	 * @static
	 *
	 * @param array $w3tc_config Config.
	 */
	public static function w3tc_config_ui_save_w3tc_cachegroups( $w3tc_config ) {
		// * User agent groups.
		$useragent_groups     = Util_Request::get_array( 'mobile_groups' );
		$mobile_groups        = array();
		$cached_mobile_groups = array();

		foreach ( $useragent_groups as $w3tc_group => $w3tc_group_config ) {
			$w3tc_group = strtolower( $w3tc_group );
			$w3tc_group = preg_replace( '~[^0-9a-z_]+~', '_', $w3tc_group );
			$w3tc_group = trim( $w3tc_group, '_' );

			if ( $w3tc_group ) {
				$theme        = isset( $w3tc_group_config['theme'] ) ? trim( $w3tc_group_config['theme'] ) : 'default';
				$w3tc_enabled = isset( $w3tc_group_config['enabled'] ) ? (bool) $w3tc_group_config['enabled'] : true;
				$redirect     = isset( $w3tc_group_config['redirect'] ) ? trim( $w3tc_group_config['redirect'] ) : '';

				/**
				 * Strip WP's `magic-quotes`-style slashes on the raw
				 * $_POST agents textarea BEFORE the value reaches the
				 * `w3tc_mobile_groups` filter. Plugins hooking that
				 * filter pass back ordinary regex strings (e.g.
				 * `google\.com`) — running `wp_unslash` later inside
				 * `clean_values()` would strip that legitimate
				 * backslash and change the match semantics.
				 */
				$agents = isset( $w3tc_group_config['agents'] )
					? Util_Environment::textarea_to_array( wp_unslash( (string) $w3tc_group_config['agents'] ) )
					: array();

				$mobile_groups[ $w3tc_group ] = array(
					'theme'    => $theme,
					'enabled'  => $w3tc_enabled,
					'redirect' => $redirect,
					'agents'   => $agents,
				);

				$cached_mobile_groups[ $w3tc_group ] = $agents;
			}
		}

		// Allow plugins modify WPSC mobile groups.
		$cached_mobile_groups = apply_filters( 'w3tc_cached_mobile_groups', $cached_mobile_groups );

		// Merge existent and delete removed groups.
		foreach ( $mobile_groups as $w3tc_group => $w3tc_group_config ) {
			if ( isset( $cached_mobile_groups[ $w3tc_group ] ) ) {
				$mobile_groups[ $w3tc_group ]['agents'] = (array) $cached_mobile_groups[ $w3tc_group ];
			} else {
				unset( $mobile_groups[ $w3tc_group ] );
			}
		}

		// Add new groups.
		foreach ( $cached_mobile_groups as $w3tc_group => $agents ) {
			if ( ! isset( $mobile_groups[ $w3tc_group ] ) ) {
				$mobile_groups[ $w3tc_group ] = array(
					'theme'    => '',
					'enabled'  => true,
					'redirect' => '',
					'agents'   => $agents,
				);
			}
		}

		// Allow plugins modify W3TC mobile groups.
		$mobile_groups = apply_filters( 'w3tc_mobile_groups', $mobile_groups );

		// Sanitize mobile groups.
		foreach ( $mobile_groups as $w3tc_group => $w3tc_group_config ) {
			$mobile_groups[ $w3tc_group ] = array_merge(
				array(
					'theme'    => '',
					'enabled'  => true,
					'redirect' => '',
					'agents'   => array(),
				),
				$w3tc_group_config
			);

			$mobile_groups[ $w3tc_group ]['agents'] = self::clean_values( $mobile_groups[ $w3tc_group ]['agents'] );

			sort( $mobile_groups[ $w3tc_group ]['agents'] );
		}

		$enable_mobile = false;

		foreach ( $mobile_groups as $w3tc_group_config ) {
			if ( $w3tc_group_config['enabled'] ) {
				$enable_mobile = true;
				break;
			}
		}

		$w3tc_config->set( 'mobile.enabled', $enable_mobile );
		$w3tc_config->set( 'mobile.rgroups', $mobile_groups );

		// * Referrer groups.
		$ref_groups = Util_Request::get_array( 'referrer_groups' );

		$referrer_groups = array();

		foreach ( $ref_groups as $w3tc_group => $w3tc_group_config ) {
			$w3tc_group = strtolower( $w3tc_group );
			$w3tc_group = preg_replace( '~[^0-9a-z_]+~', '_', $w3tc_group );
			$w3tc_group = trim( $w3tc_group, '_' );

			if ( $w3tc_group ) {
				$theme        = isset( $w3tc_group_config['theme'] ) ? trim( $w3tc_group_config['theme'] ) : 'default';
				$w3tc_enabled = isset( $w3tc_group_config['enabled'] ) ? (bool) $w3tc_group_config['enabled'] : true;
				$redirect     = isset( $w3tc_group_config['redirect'] ) ? trim( $w3tc_group_config['redirect'] ) : '';

				/**
				 * Same pre-filter unslash as the mobile branch above.
				 * Filter callers (`w3tc_referrer_groups`) pass ordinary
				 * regex strings; `clean_values()` must NOT unslash them
				 * again or `google\.com` becomes `google.com`.
				 */
				$referrers = isset( $w3tc_group_config['referrers'] )
					? Util_Environment::textarea_to_array( wp_unslash( (string) $w3tc_group_config['referrers'] ) )
					: array();

				$referrer_groups[ $w3tc_group ] = array(
					'theme'     => $theme,
					'enabled'   => $w3tc_enabled,
					'redirect'  => $redirect,
					'referrers' => $referrers,
				);
			}
		}

		// Allow plugins modify W3TC referrer groups.
		$referrer_groups = apply_filters( 'w3tc_referrer_groups', $referrer_groups );

		// Sanitize mobile groups.
		foreach ( $referrer_groups as $w3tc_group => $w3tc_group_config ) {
			$referrer_groups[ $w3tc_group ] = array_merge(
				array(
					'theme'     => '',
					'enabled'   => true,
					'redirect'  => '',
					'referrers' => array(),
				),
				$w3tc_group_config
			);

			$referrer_groups[ $w3tc_group ]['referrers'] = self::clean_values( $referrer_groups[ $w3tc_group ]['referrers'] );

			sort( $referrer_groups[ $w3tc_group ]['referrers'] );
		}

		$enable_referrer = false;

		foreach ( $referrer_groups as $w3tc_group_config ) {
			if ( $w3tc_group_config['enabled'] ) {
				$enable_referrer = true;
				break;
			}
		}

		$w3tc_config->set( 'referrer.enabled', $enable_referrer );
		$w3tc_config->set( 'referrer.rgroups', $referrer_groups );

		// * Cookie groups.
		$cookiegroups  = array();
		$cookie_groups = Util_Request::get_array( 'cookiegroups' );

		foreach ( $cookie_groups as $w3tc_group => $w3tc_group_config ) {
			$w3tc_group = strtolower( $w3tc_group );
			$w3tc_group = preg_replace( '~[^0-9a-z_]+~', '_', $w3tc_group );
			$w3tc_group = trim( $w3tc_group, '_' );

			if ( $w3tc_group ) {
				$w3tc_enabled = isset( $w3tc_group_config['enabled'] ) ? (bool) $w3tc_group_config['enabled'] : false;
				$cache        = isset( $w3tc_group_config['cache'] ) ? (bool) $w3tc_group_config['cache'] : false;
				$cookies      = isset( $w3tc_group_config['cookies'] ) ? Util_Environment::textarea_to_array( $w3tc_group_config['cookies'] ) : array();

				$cookiegroups[ $w3tc_group ] = array(
					'enabled' => $w3tc_enabled,
					'cache'   => $cache,
					'cookies' => $cookies,
				);
			}
		}

		// Allow plugins modify W3TC cookie groups.
		$cookiegroups = apply_filters( 'w3tc_pgcache_cookiegroups', $cookiegroups );

		$w3tc_enabled = false;

		foreach ( $cookiegroups as $w3tc_group_config ) {
			if ( $w3tc_group_config['enabled'] ) {
				$w3tc_enabled = true;
				break;
			}
		}

		$w3tc_config->set( 'pgcache.cookiegroups.enabled', $w3tc_enabled );
		$w3tc_config->set( 'pgcache.cookiegroups.groups', $cookiegroups );
	}

	/**
	 * Clean entries.
	 *
	 * Callers are responsible for stripping WP magic-quotes-style
	 * slashes BEFORE handing values to this helper. That keeps
	 * legitimate regex backslashes (`google\.com`, `foo\ bar`)
	 * intact when the value originates from a `w3tc_mobile_groups`
	 * / `w3tc_referrer_groups` filter callback rather than from a
	 * raw $_POST textarea. The request-side write paths in
	 * `w3tc_config_ui_save_w3tc_cachegroups` do the `wp_unslash`
	 * at the textarea-parse step, before the filter runs.
	 *
	 * `sanitize_text_field` strips tags + control characters at
	 * the storage boundary so a stored value (whether from raw
	 * request or filter-provided) cannot carry an HTML tag or
	 * embedded NUL into the persisted User-Agent / referrer
	 * match string. Every render path already escapes on output,
	 * but the strip on the way in is defence-in-depth and the
	 * persisted store stays free of tag-shaped bytes.
	 *
	 * @static
	 *
	 * @param array $values Values (already wp_unslash'd by caller).
	 */
	public static function clean_values( $values ) {
		return array_unique(
			array_map(
				function ( $w3tc_value ) {
					$w3tc_value = sanitize_text_field( (string) $w3tc_value );

					return preg_replace( '/(?<!\\\\)' . wp_spaces_regexp() . '/', '\ ', strtolower( $w3tc_value ) );
				},
				$values
			)
		);
	}
}
