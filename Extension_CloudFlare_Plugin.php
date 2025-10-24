<?php
/**
 * File: Extension_CloudFlare_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * W3 ObjectCache plugin
 */
define( 'W3TC_MARKER_BEGIN_CLOUDFLARE', '# BEGIN W3TC CloudFlare' );
define( 'W3TC_MARKER_END_CLOUDFLARE', '# END W3TC CloudFlare' );

/**
 * Class: Extension_CloudFlare_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_CloudFlare_Plugin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * Flush operation requested flag
	 *
	 * @var bool
	 */
	private $flush_operation_requested = false;

	/**
	 * Constructs the Extension_CloudFlare_Plugin class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Initializes the plugin and hooks into WordPress actions and filters.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_config_default_values', array( $this, 'w3tc_config_default_values' ) );

		add_action( 'wp_set_comment_status', array( $this, 'set_comment_status' ), 1, 2 );

		// priority is important, see do_action call of that action.
		add_action( 'w3tc_flush_all', array( $this, 'w3tc_flush_all' ), 3000, 1 );

		add_filter( 'w3tc_flush_execute_delayed_operations', array( $this, 'w3tc_flush_execute_delayed_operations' ), 3000, 1 );

		$this->fix_remote_addr();

		// if page caching is enabled on CF - attach to post modifications and flush it.
		if ( $this->_config->get_boolean( array( 'cloudflare', 'pagecache' ) ) ) {
			Util_AttachToActions::flush_posts_on_actions();

			add_action( 'w3tc_flush_post', array( $this, 'w3tc_flush_xxx' ), 3000 );
			add_action( 'w3tc_flushable_posts', '__return_true', 3000 );
			add_action( 'w3tc_flush_posts', array( $this, 'w3tc_flush_xxx' ), 3000 );
		}
	}

	/**
	 * Modifies default configuration values for Cloudflare.
	 *
	 * @param array $default_values {
	 *     Default configuration values.
	 *
	 *     @type array $cloudflare {
	 *         Cloudflare specific settings.
	 *
	 *         @type int $widget_interval Interval for the widget in seconds. Default is 30.
	 *         @type int $widget_cache_mins Cache duration for the widget in minutes. Default is 5.
	 *         @type int $timelimit.api_request Time limit for API requests in seconds. Default is 180.
	 *     }
	 * }
	 *
	 * @return array Modified configuration values.
	 */
	public function w3tc_config_default_values( $default_values ) {
		$default_values['cloudflare'] = array(
			'widget_interval'       => 30,
			'widget_cache_mins'     => 5,
			'timelimit.api_request' => 180,
		);

		return $default_values;
	}

	/**
	 * Flushes all caches for Cloudflare, unless specified otherwise.
	 *
	 * @param array $extras {
	 *     Optional extra parameters for flushing.
	 *
	 *     @type string $cloudflare If set to 'skip', skips Cloudflare cache flushing.
	 * }
	 *
	 * @return void
	 */
	public function w3tc_flush_all( $extras ) {
		if (
			is_array( $extras ) &&
			isset( $extras['cloudflare'] ) &&
			'skip' === $extras['cloudflare']
		) {
			return;
		}

		$this->flush_operation_requested = true;
	}

	/**
	 * Marks the flush operation as requested for Cloudflare.
	 *
	 * @return void
	 */
	public function w3tc_flush_xxx() {
		$this->flush_operation_requested = true;
	}

	/**
	 * Executes delayed flush operations for Cloudflare.
	 *
	 * @param array $actions_made {
	 *     Array of actions performed.
	 *
	 *     @type string $module The module that performed the action.
	 *     @type string $error  (Optional) Error message if an error occurred.
	 * }
	 *
	 * @return array Modified actions array after execution.
	 */
	public function w3tc_flush_execute_delayed_operations( $actions_made ) {
		if ( $this->flush_operation_requested ) {
			$c   = Dispatcher::config();
			$api = new Extension_CloudFlare_Api(
				array(
					'email'                 => $c->get_string( array( 'cloudflare', 'email' ) ),
					'key'                   => $c->get_string( array( 'cloudflare', 'key' ) ),
					'zone_id'               => $c->get_string( array( 'cloudflare', 'zone_id' ) ),
					'timelimit_api_request' => $c->get_integer(
						array( 'cloudflare', 'timelimit.api_request' )
					),
				)
			);

			$action_made = array(
				'module' => 'cloudflare',
			);

			try {
				$api->purge();
			} catch ( \Exception $ex ) {
				$action_made['error'] =
					'Cloudflare cache: ' . $ex->getMessage();
			}

			$this->flush_operation_requested = false;

			$actions_made[] = $action_made;
		}

		return $actions_made;
	}

	/**
	 * Sends an external spam event to Cloudflare when a comment is marked as spam.
	 *
	 * @param int    $id     The ID of the comment.
	 * @param string $status The new status of the comment.
	 *
	 * @return void
	 *
	 * @throws \Exception If an error occurs during the API call.
	 */
	public function set_comment_status( $id, $status ) {
		if ( 'spam' === $status ) {
			$comment = get_comment( $id );
			$value   = array(
				'a'   => $comment->comment_author,
				'am'  => $comment->comment_author_email,
				'ip'  => $comment->comment_author_IP,
				'con' => substr( $comment->comment_content, 0, 100 ),
			);

			$c   = Dispatcher::config();
			$api = new Extension_CloudFlare_Api(
				array(
					'email'                 => $c->get_string( array( 'cloudflare', 'email' ) ),
					'key'                   => $c->get_string( array( 'cloudflare', 'key' ) ),
					'zone_id'               => $c->get_string( array( 'cloudflare', 'zone_id' ) ),
					'timelimit_api_request' => $c->get_integer(
						array( 'cloudflare', 'timelimit.api_request' )
					),
				)
			);

			try {
				$api->external_event( 'WP_SPAM', wp_json_encode( $value ) );
			} catch ( \Exception $ex ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}
	}

	/**
	 * Adds Cloudflare-related items to the WordPress admin menu bar.
	 *
	 * @param array $menu_items {
	 *     Existing menu items.
	 *
	 *     @type string      $id     The menu item ID.
	 *     @type string      $title  The menu item title.
	 *     @type string      $href   The URL for the menu item link.
	 *     @type string|null $parent The ID of the parent menu item, if applicable.
	 * }
	 *
	 * @return array Modified menu items with Cloudflare entries.
	 */
	public function menu_bar( $menu_items ) {
		$menu_items = array_merge(
			$menu_items,
			array(
				array(
					'id'    => 'cloudflare',
					'title' => __( 'Cloudflare', 'w3-total-cache' ),
					'href'  => 'https://www.cloudflare.com',
				),
				array(
					'id'     => 'cloudflare-my-websites',
					'parent' => 'cloudflare',
					'title'  => __( 'My Websites', 'w3-total-cache' ),
					'href'   => 'https://www.cloudflare.com/my-websites.html',
				),
				array(
					'id'     => 'cloudflare-analytics',
					'parent' => 'cloudflare',
					'title'  => __( 'Analytics', 'w3-total-cache' ),
					'href'   => 'https://www.cloudflare.com/analytics.html',
				),
				array(
					'id'     => 'cloudflare-account',
					'parent' => 'cloudflare',
					'title'  => __( 'Account', 'w3-total-cache' ),
					'href'   => 'https://www.cloudflare.com/my-account.html',
				),
			)
		);

		return $menu_items;
	}

	/**
	 * Fixes the remote IP address when accessed through Cloudflare.
	 *
	 * @return void
	 */
	private function fix_remote_addr() {
		$remote_addr           = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$http_cf_connecting_ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';

		if ( empty( $http_cf_connecting_ip ) ) {
			return;
		}

		if ( strpos( $remote_addr, ':' ) === false ) {
			$ip4_ranges = $this->_config->get_array(
				array(
					'cloudflare',
					'ips.ip4',
				)
			);
			foreach ( $ip4_ranges as $range ) {
				if ( $this->ipv4_in_range( $remote_addr, $range ) ) {
					$_SERVER['REMOTE_ADDR'] = $http_cf_connecting_ip;
					break;
				}
			}
		} elseif ( ! empty( $remote_addr ) ) {
			$ip6_ranges = $this->_config->get_array(
				array(
					'cloudflare',
					'ips.ip6',
				)
			);
			$ip6        = $this->get_ipv6_full( $remote_addr );
			foreach ( $ip6_ranges as $range ) {
				if ( $this->ipv6_in_range( $ip6, $range ) ) {
					$_SERVER['REMOTE_ADDR'] = $http_cf_connecting_ip;
					break;
				}
			}
		}
	}

	/**
	 * Checks if an IPv4 address is within a given range.
	 *
	 * Network ranges can be specified as:
	 * 1. Wildcard format:     1.2.3.*
	 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
	 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
	 *
	 * Copyright 2008: Paul Gregg <pgregg@pgregg.com>
	 * 10 January 2008
	 * Version: 1.2
	 *
	 * Source website: http://www.pgregg.com/projects/php/ip_in_range/
	 * Version 1.2
	 *
	 * This software is Donationware - if you feel you have benefited from
	 * the use of this tool then please consider a donation. The value of
	 * which is entirely left up to your discretion.
	 * http://www.pgregg.com/donate/
	 *
	 * Please do not remove this header, or source attibution from this file.
	 * Modified by James Greene <james@cloudflare.com> to include IPV6 support
	 * (original version only supported IPV4).
	 * 21 May 2012
	 *
	 * @param string $ip    The IPv4 address to check.
	 * @param string $range The range to check against.
	 *
	 * @return bool True if the IP is within the range, false otherwise.
	 */
	private function ipv4_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) !== false ) {
			// $range is in IP/NETMASK format
			list( $range, $netmask ) = explode( '/', $range, 2 );
			if ( strpos( $netmask, '.' ) !== false ) {
				// $netmask is a 255.255.0.0 format
				$netmask     = str_replace( '*', '0', $netmask );
				$netmask_dec = ip2long( $netmask );

				return ( ip2long( $ip ) & $netmask_dec ) === ( ip2long( $range ) & $netmask_dec );
			} else {
				// $netmask is a CIDR size block fix the range argument.
				$x     = explode( '.', $range );
				$count = count( $x );  // Assign count to a variable.
				while ( $count < 4 ) {
					$x[]   = '0';
					$count = count( $x );  // Update the count after adding a '0'.
				}

				list( $a, $b, $c, $d ) = $x;
				$range                 = sprintf(
					'%u.%u.%u.%u',
					empty( $a ) ? '0' : $a,
					empty( $b ) ? '0' : $b,
					empty( $c ) ? '0' : $c,
					empty( $d ) ? '0' : $d
				);
				$range_dec             = ip2long( $range );
				$ip_dec                = ip2long( $ip );

				// Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
				// $netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0')); phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar.

				// Strategy 2 - Use math to create it.
				$wildcard_dec = pow( 2, ( 32 - $netmask ) ) - 1;
				$netmask_dec  = ~ $wildcard_dec;

				return ( $ip_dec & $netmask_dec ) === ( $range_dec & $netmask_dec );
			}
		} else {
			// range might be 255.255.*.* or 1.2.3.0-1.2.3.255.
			if ( false !== strpos( $range, '*' ) ) { // a.b.*.* format.
				// Just convert to A-B format by setting * to 0 for A and 255 for B.
				$lower = str_replace( '*', '0', $range );
				$upper = str_replace( '*', '255', $range );
				$range = "$lower-$upper";
			}

			if ( false !== strpos( $range, '-' ) ) { // A-B format.
				list( $lower, $upper ) = explode( '-', $range, 2 );
				$lower_dec             = (float) sprintf( '%u', ip2long( $lower ) );
				$upper_dec             = (float) sprintf( '%u', ip2long( $upper ) );
				$ip_dec                = (float) sprintf( '%u', ip2long( $ip ) );
				return ( $ip_dec >= $lower_dec ) && ( $ip_dec <= $upper_dec );
			}

			return false;
		}
	}

	/**
	 * Converts an IPv6 address to its numerical representation.
	 *
	 * @param string $ip The IPv6 address to convert.
	 *
	 * @return string The numerical representation of the IPv6 address.
	 */
	private function ip2long6( $ip ) {
		if ( substr_count( $ip, '::' ) ) {
			$ip = str_replace( '::', str_repeat( ':0000', 8 - substr_count( $ip, ':' ) ) . ':', $ip );
		}

		$ip   = explode( ':', $ip );
		$r_ip = '';
		foreach ( $ip as $v ) {
			$r_ip .= str_pad( base_convert( $v, 16, 2 ), 16, 0, STR_PAD_LEFT );
		}

		return base_convert( $r_ip, 2, 10 );
	}

	/**
	 * Returns the full representation of a compressed IPv6 address.
	 *
	 * @param string $ip The compressed IPv6 address.
	 *
	 * @return string The full representation of the IPv6 address.
	 */
	private function get_ipv6_full( $ip ) {
		$pieces = explode( '/', $ip, 2 );
		if ( count( $pieces ) < 2 ) {
			return 0;
		}

		$left_piece = $pieces[0];

		// Extract out the main IP pieces.
		$ip_pieces = explode( '::', $left_piece, 2 );
		if ( count( $ip_pieces ) < 2 ) {
			return 0;
		}

		$main_ip_piece = $ip_pieces[0];
		$last_ip_piece = $ip_pieces[1];

		// Pad out the shorthand entries.
		$main_ip_pieces = explode( ':', $main_ip_piece );
		foreach ( $main_ip_pieces as $key => $val ) {
			$main_ip_pieces[ $key ] = str_pad( $main_ip_pieces[ $key ], 4, '0', STR_PAD_LEFT );
		}

		// Check to see if the last IP block (part after ::) is set.
		$size = count( $main_ip_pieces );
		if ( '' !== trim( $last_ip_piece ) ) {
			$last_piece = str_pad( $last_ip_piece, 4, '0', STR_PAD_LEFT );

			// Build the full form of the IPV6 address considering the last IP block set.
			for ( $i = $size; $i < 7; $i++ ) {
				$main_ip_pieces[ $i ] = '0000';
			}
			$main_ip_pieces[7] = $last_piece;
		} else {
			// Build the full form of the IPV6 address.
			for ( $i = $size; $i < 8; $i++ ) {
				$main_ip_pieces[ $i ] = '0000';
			}
		}

		// Rebuild the final long form IPV6 address.
		$final_ip = implode( ':', $main_ip_pieces );

		return $this->ip2long6( $final_ip );
	}

	/**
	 * Checks if an IPv6 address is within a given range.
	 *
	 * @param string $ip      The IPv6 address to check.
	 * @param string $range_ip The range to check against.
	 *
	 * @return bool True if the IP is within the range, false otherwise.
	 */
	private function ipv6_in_range( $ip, $range_ip ) {
		$pieces     = explode( '/', $range_ip, 2 );
		$left_piece = $pieces[0];

		// Extract out the main IP pieces.
		$ip_pieces     = explode( '::', $left_piece, 2 );
		$main_ip_piece = $ip_pieces[0];
		$last_ip_piece = $ip_pieces[1];

		// Pad out the shorthand entries.
		$main_ip_pieces = explode( ':', $main_ip_piece );
		foreach ( $main_ip_pieces as $key => $val ) {
			$main_ip_pieces[ $key ] = str_pad( $main_ip_pieces[ $key ], 4, '0', STR_PAD_LEFT );
		}

		// Create the first and last pieces that will denote the IPV6 range.
		$first = $main_ip_pieces;
		$last  = $main_ip_pieces;

		// Check to see if the last IP block (part after ::) is set.
		$last_piece = '';
		$size       = count( $main_ip_pieces );
		if ( ! empty( trim( $last_ip_piece ) ) ) {
			$last_piece = str_pad( $last_ip_piece, 4, '0', STR_PAD_LEFT );

			// Build the full form of the IPV6 address considering the last IP block set.
			for ( $i = $size; $i < 7; $i++ ) {
				$first[ $i ] = '0000';
				$last[ $i ]  = 'ffff';
			}

			$main_ip_pieces[7] = $last_piece;
		} else {
			// Build the full form of the IPV6 address.
			for ( $i = $size; $i < 8; $i++ ) {
				$first[ $i ] = '0000';
				$last[ $i ]  = 'ffff';
			}
		}

		// Rebuild the final long form IPV6 address.
		$first    = $this->ip2long6( implode( ':', $first ) );
		$last     = $this->ip2long6( implode( ':', $last ) );
		$in_range = ( $ip >= $first && $ip <= $last );

		return $in_range;
	}
}

$p = new Extension_CloudFlare_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_CloudFlare_Plugin_Admin();
	$p->run();
}
