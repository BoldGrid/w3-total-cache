<?php
/**
 * File: Util_Url.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class Util_Url
 *
 * SSRF guard: resolves a URL's host and rejects any address that
 * targets a private / loopback / link-local / reserved range.
 *
 * Every outbound `wp_remote_*` call where the URL is admin-influenced
 * (license API override, AlwaysCached queue, recursive sitemap
 * fetcher, varnish purge, forums-api tab id, etc.) MUST run through
 * {@see self::is_public_host()} before issuing the request. Bare
 * `wp_remote_get( $admin_url )` with no host check lets an attacker
 * with config-write (or any chained primitive that lands a config
 * key) target internal services: AWS instance metadata at
 * `169.254.169.254`, a Redis at `127.0.0.1:6379`, an RFC1918
 * neighbour, the WordPress front-end (`http://0.0.0.0/`), etc.
 *
 * **Residual risk: DNS rebinding.** `gethostbynamel()` resolves once
 * at check time; a malicious resolver can return a public IP for the
 * check and a private IP for the subsequent fetch. Closing that gap
 * requires `CURLOPT_RESOLVE` (pinning the IP at the cURL layer) or a
 * single end-to-end resolve. WordPress's `wp_remote_*` doesn't expose
 * `CURLOPT_RESOLVE`, so the documented mitigation is operator-level
 * (e.g. an egress firewall / outbound-policy that blocks RFC1918
 * destinations from the application tier). This class documents the
 * gap rather than hiding it.
 *
 * @since X.X.X
 */
class Util_Url {

	/**
	 * Returns true when the URL parses, has an http/https scheme, and
	 * every IP its hostname resolves to is a public-range address.
	 *
	 * False is returned for any of:
	 *
	 *  - non-string / empty input
	 *  - malformed URL (no scheme, no host)
	 *  - non-http/https scheme (`file://`, `gopher://`, `ftp://`, etc.)
	 *  - hostname that fails to resolve
	 *  - any resolved IP that is loopback, RFC1918, link-local,
	 *    reserved, or an IPv4-mapped IPv6 address pointing at the
	 *    above (e.g. `::ffff:127.0.0.1`)
	 *  - the host parsed as a bare IP literal that hits any of the
	 *    above checks directly (so `http://127.0.0.1/`, `http://[::1]/`,
	 *    and `http://10.0.0.5/` are refused without a DNS lookup)
	 *
	 * @since X.X.X
	 *
	 * @param string $url Candidate URL.
	 *
	 * @return bool
	 */
	public static function is_public_host( $url ) {
		if ( ! \is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( ! self::is_valid_http_scheme( $url ) ) {
			return false;
		}

		$host = \wp_parse_url( $url, PHP_URL_HOST );
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}

		// Strip the surrounding brackets from an IPv6 literal so the
		// validator can recognise the address shape.
		if ( '[' === \substr( $host, 0, 1 ) && ']' === \substr( $host, -1 ) ) {
			$host = \substr( $host, 1, -1 );
		}

		// If the host is already a literal IP, validate it directly —
		// no DNS lookup needed, and the wp_remote_* layer would have
		// used the literal anyway.
		if ( false !== \filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_public_ip( $host );
		}

		// Otherwise resolve. `gethostbynamel()` returns the full v4
		// resolution set; we accept the URL only when every result is
		// public. (One CNAME -> mixed result set is exotic but possible.)
		$ips = @\gethostbynamel( $host );
		if ( false === $ips || empty( $ips ) ) {
			return false;
		}

		foreach ( $ips as $ip ) {
			if ( ! \is_string( $ip ) || ! self::is_public_ip( $ip ) ) {
				return false;
			}
		}

		// Bonus: try the v6 resolution if available, fold any private
		// addresses there into a rejection too. `dns_get_record` is
		// optional but PHP-builtin; ignore failures silently.
		if ( \function_exists( 'dns_get_record' ) ) {
			$aaaa = @\dns_get_record( $host, DNS_AAAA );
			if ( \is_array( $aaaa ) ) {
				foreach ( $aaaa as $rec ) {
					if ( isset( $rec['ipv6'] ) && ! self::is_public_ip( $rec['ipv6'] ) ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Returns true for an http or https URL; refuses every other
	 * scheme. `gopher`, `file`, `php`, `data`, etc. are all SSRF
	 * primitives in their own right and have no place in a `wp_remote_*`
	 * call.
	 *
	 * @since X.X.X
	 *
	 * @param string $url Candidate URL.
	 *
	 * @return bool
	 */
	public static function is_valid_http_scheme( $url ) {
		if ( ! \is_string( $url ) || '' === $url ) {
			return false;
		}
		$scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
		return 'http' === $scheme || 'https' === $scheme;
	}

	/**
	 * Returns true when the given IP literal is a public-range address.
	 *
	 * Wraps `filter_var` with `FILTER_FLAG_NO_PRIV_RANGE` (rejects
	 * 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, fc00::/7) and
	 * `FILTER_FLAG_NO_RES_RANGE` (rejects 0.0.0.0/8, 127.0.0.0/8,
	 * 169.254.0.0/16 / link-local, 224.0.0.0/4 multicast, 240.0.0.0/4
	 * reserved-future, ::1, fe80::/10, fc00::/7, ff00::/8).
	 *
	 * Adds an explicit IPv4-mapped-IPv6 check (`::ffff:127.0.0.1`) —
	 * PHP's filter sometimes lets these through depending on version,
	 * and unwrapping the embedded v4 lets us re-run the v4 check.
	 *
	 * @since X.X.X
	 *
	 * @param string $ip IP literal (v4 or v6).
	 *
	 * @return bool
	 */
	public static function is_public_ip( $ip ) {
		if ( ! \is_string( $ip ) || '' === $ip ) {
			return false;
		}

		// IPv4-mapped IPv6: ::ffff:127.0.0.1 — pull out the embedded v4
		// and re-run the check (otherwise FILTER_VALIDATE_IP would treat
		// the whole thing as a "public v6 address" with no privacy
		// markers, even though it routes to a v4 loopback).
		if ( false !== \stripos( $ip, '::ffff:' ) ) {
			$tail = \substr( $ip, \stripos( $ip, '::ffff:' ) + 7 );
			if ( false !== \filter_var( $tail, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				return self::is_public_ip( $tail );
			}
		}

		return false !== \filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
