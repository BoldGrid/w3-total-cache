<?php
/**
 * File: Util_Url.php
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

namespace W3TC;

/**
 * Class Util_Url
 *
 * Outbound-URL guard: resolves a URL's host and rejects any address
 * that targets a private / loopback / link-local / reserved range.
 *
 * Every outbound `wp_remote_*` call where the URL is admin-influenced
 * (license API override, AlwaysCached queue, recursive sitemap
 * fetcher, forums-api tab id, etc.) MUST run through
 * {@see self::is_public_host()} before issuing the request. Varnish
 * PURGE uses {@see self::is_safe_varnish_purge_target()} instead,
 * because legitimate destinations are often RFC1918 or loopback on
 * an HTTP port. Without these checks, a `wp_remote_get( $admin_url )`
 * whose `$admin_url` carries an internal-range value would target
 * internal services: AWS instance metadata at `169.254.169.254`, a
 * Redis at `127.0.0.1:6379`, an RFC1918 neighbour, the WordPress
 * front-end (`http://0.0.0.0/`), etc.
 *
 * **Residual risk: DNS rebinding.** `gethostbynamel()` resolves once
 * at check time; a hostile resolver can return a public IP for the
 * check and a private IP for the subsequent fetch. Closing that gap
 * requires `CURLOPT_RESOLVE` (pinning the IP at the cURL layer) or a
 * single end-to-end resolve. WordPress's `wp_remote_*` doesn't expose
 * `CURLOPT_RESOLVE`, so the documented operator-level posture is an
 * egress firewall / outbound policy that blocks RFC1918 destinations
 * from the application tier. This class documents the gap rather
 * than hiding it.
 *
 * @since 2.10.0
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
	 * @since 2.10.0
	 *
	 * @param string $w3tc_url Candidate URL.
	 *
	 * @return bool
	 */
	public static function is_public_host( $w3tc_url ) {
		if ( ! \is_string( $w3tc_url ) || '' === $w3tc_url ) {
			return false;
		}

		if ( ! self::is_valid_http_scheme( $w3tc_url ) ) {
			return false;
		}

		$host = \wp_parse_url( $w3tc_url, PHP_URL_HOST );
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}

		/**
		 * Strip the surrounding brackets from an IPv6 literal so the
		 * validator can recognise the address shape.
		 */
		if ( '[' === \substr( $host, 0, 1 ) && ']' === \substr( $host, -1 ) ) {
			$host = \substr( $host, 1, -1 );
		}

		/**
		 * If the host is already a literal IP, validate it directly —
		 * no DNS lookup needed, and the wp_remote_* layer would have
		 * used the literal anyway.
		 */
		if ( false !== \filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_public_ip( $host );
		}

		/**
		 * Resolve A records (v4). `gethostbynamel()` returns the full v4
		 * resolution set; we accept the URL only when every result is
		 * public. (One CNAME -> mixed result set is exotic but possible.)
		 * An empty / failed result here is NOT fatal on its own — an
		 * IPv6-only host has no A records but legitimately resolves via
		 * AAAA. We treat A-failure as "consult AAAA" rather than refuse.
		 */
		$ipv4_set = @\gethostbynamel( $host );
		if ( false === $ipv4_set ) {
			$ipv4_set = array();
		}

		foreach ( $ipv4_set as $ip ) {
			if ( ! \is_string( $ip ) || ! self::is_public_ip( $ip ) ) {
				return false;
			}
		}

		/**
		 * Resolve AAAA records (v6). `dns_get_record()` is PHP-builtin
		 * but disabled on some hardened hosts, so its absence isn't an
		 * error — we just lose the v6 leg of the check.
		 */
		$ipv6_set = array();
		if ( \function_exists( 'dns_get_record' ) ) {
			$aaaa = @\dns_get_record( $host, DNS_AAAA );
			if ( \is_array( $aaaa ) ) {
				foreach ( $aaaa as $rec ) {
					if ( isset( $rec['ipv6'] ) && \is_string( $rec['ipv6'] ) ) {
						$ipv6_set[] = $rec['ipv6'];
					}
				}
			}
		}

		foreach ( $ipv6_set as $ip ) {
			if ( ! self::is_public_ip( $ip ) ) {
				return false;
			}
		}

		/**
		 * Refuse hosts that have NO public address at all — i.e. neither
		 * a v4 resolution nor a v6 resolution. This covers both the
		 * genuinely-unresolvable case and the "dns_get_record disabled
		 * and gethostbynamel returned nothing" edge.
		 */
		if ( empty( $ipv4_set ) && empty( $ipv6_set ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns true when the URL has an http/https scheme and its host
	 * matches this installation's own home or site host.
	 *
	 * The comparison is a case-insensitive host-string match — no DNS
	 * lookup. Requests the plugin issues to its own front-end (e.g.
	 * priming this site's own minify cache files) must keep working on
	 * installs whose hostname resolves to an internal address
	 * (intranet, staging, split-horizon DNS), where a resolution-based
	 * check like {@see self::is_public_host()} would refuse them.
	 *
	 * @since 2.10.1
	 *
	 * @param string $w3tc_url Candidate URL.
	 *
	 * @return bool
	 */
	public static function is_self_host_url( $w3tc_url ) {
		if ( ! self::is_valid_http_scheme( $w3tc_url ) ) {
			return false;
		}

		$host = \wp_parse_url( $w3tc_url, PHP_URL_HOST );
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}

		$self_hosts = array(
			\wp_parse_url( \home_url(), PHP_URL_HOST ),
			\wp_parse_url( \site_url(), PHP_URL_HOST ),
		);

		foreach ( $self_hosts as $self_host ) {
			if ( \is_string( $self_host ) && '' !== $self_host && 0 === \strcasecmp( $host, $self_host ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns true for an http or https URL; refuses every other
	 * scheme. `gopher`, `file`, `php`, `data`, etc. each open
	 * out-of-band fetch paths in their own right and have no place
	 * in a `wp_remote_*` call.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_url Candidate URL.
	 *
	 * @return bool
	 */
	public static function is_valid_http_scheme( $w3tc_url ) {
		if ( ! \is_string( $w3tc_url ) || '' === $w3tc_url ) {
			return false;
		}
		$scheme = \wp_parse_url( $w3tc_url, PHP_URL_SCHEME );
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
	 * @since 2.10.0
	 *
	 * @param string $ip IP literal (v4 or v6).
	 *
	 * @return bool
	 */
	public static function is_public_ip( $ip ) {
		if ( ! \is_string( $ip ) || '' === $ip ) {
			return false;
		}

		/**
		 * IPv4-mapped IPv6: ::ffff:127.0.0.1 — pull out the embedded v4
		 * and re-run the check (otherwise FILTER_VALIDATE_IP would treat
		 * the whole thing as a "public v6 address" with no privacy
		 * markers, even though it routes to a v4 loopback).
		 */
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

	/**
	 * Returns true for an IP that is safe as a target for outbound
	 * internal-services traffic — i.e. RFC1918 / private-range hosts
	 * are accepted (a Varnish or memcached server on `10.x` or
	 * `192.168.x` is a normal deployment) BUT loopback, link-local
	 * (incl. AWS instance-metadata `169.254.169.254`), broadcast, and
	 * reserved-future ranges are still refused.
	 *
	 * Uses `FILTER_FLAG_NO_RES_RANGE` only — without `NO_PRIV_RANGE`.
	 * Per the PHP docs, `NO_RES_RANGE` blocks `0.0.0.0/8`,
	 * `127.0.0.0/8`, `169.254.0.0/16`, `240.0.0.0/4`, `::1/128`,
	 * `::/128`, `::ffff:0:0/96`, and `fe80::/10` — exactly the
	 * dangerous set for SSRF — while leaving RFC1918 ranges valid.
	 *
	 * Use this (not {@see self::is_public_ip()}) at sinks like
	 * Varnish purge where the legitimate destination is intentionally
	 * a private-network host but a forged config value pointing at
	 * cloud metadata must be refused. Loopback is intentionally
	 * refused here; Varnish callers that need Cloudways-style
	 * `127.0.0.1:8080` should use {@see self::is_safe_varnish_purge_target()}
	 * which re-allows loopback only on HTTP/Varnish ports.
	 *
	 * @since 2.10.0
	 *
	 * @param string $ip IP literal (v4 or v6).
	 *
	 * @return bool
	 */
	public static function is_safe_internal_ip( $ip ) {
		if ( ! \is_string( $ip ) || '' === $ip ) {
			return false;
		}

		// IPv4-mapped IPv6 — pull out and re-check the embedded v4.
		if ( false !== \stripos( $ip, '::ffff:' ) ) {
			$tail = \substr( $ip, \stripos( $ip, '::ffff:' ) + 7 );
			if ( false !== \filter_var( $tail, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				return self::is_safe_internal_ip( $tail );
			}
		}

		return false !== \filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Returns true when `$host` is a hostname or IP literal that
	 * resolves only to safe outbound destinations — i.e. {@see
	 * self::is_safe_internal_ip()} returns true for every IP the host
	 * resolves to.
	 *
	 * Mirrors {@see self::is_public_host()}'s resolution shape (literal-
	 * IP fast path, gethostbynamel for A records, dns_get_record for
	 * AAAA, refuse when neither resolves) but with the looser
	 * RFC1918-allowing IP policy.
	 *
	 * The caller is responsible for any scheme / port / path validation
	 * separately — this method takes a hostname or literal, not a URL.
	 *
	 * @since 2.10.0
	 *
	 * @param string $host Hostname or IP literal (no scheme, no port).
	 *
	 * @return bool
	 */
	public static function host_resolves_safe_internal( $host ) {
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}

		// Strip surrounding brackets from an IPv6 literal.
		if ( '[' === \substr( $host, 0, 1 ) && ']' === \substr( $host, -1 ) ) {
			$host = \substr( $host, 1, -1 );
		}

		// Literal IP — validate directly.
		if ( false !== \filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_safe_internal_ip( $host );
		}

		$ipv4_set = @\gethostbynamel( $host );
		if ( false === $ipv4_set ) {
			$ipv4_set = array();
		}
		foreach ( $ipv4_set as $ip ) {
			if ( ! \is_string( $ip ) || ! self::is_safe_internal_ip( $ip ) ) {
				return false;
			}
		}

		$ipv6_set = array();
		if ( \function_exists( 'dns_get_record' ) ) {
			$aaaa = @\dns_get_record( $host, DNS_AAAA );
			if ( \is_array( $aaaa ) ) {
				foreach ( $aaaa as $rec ) {
					if ( isset( $rec['ipv6'] ) && \is_string( $rec['ipv6'] ) ) {
						$ipv6_set[] = $rec['ipv6'];
					}
				}
			}
		}
		foreach ( $ipv6_set as $ip ) {
			if ( ! self::is_safe_internal_ip( $ip ) ) {
				return false;
			}
		}

		// Refuse hosts with no resolved address at all.
		return ! empty( $ipv4_set ) || ! empty( $ipv6_set );
	}

	/**
	 * Returns true when `$ip` is a loopback address (IPv4 `127.0.0.0/8`
	 * or IPv6 `::1`, including IPv4-mapped `::ffff:127.x.x.x`).
	 *
	 * @since 2.10.3
	 *
	 * @param string $ip IP literal (v4 or v6).
	 *
	 * @return bool
	 */
	public static function is_loopback_ip( $ip ) {
		if ( ! \is_string( $ip ) || '' === $ip ) {
			return false;
		}

		if ( false !== \stripos( $ip, '::ffff:' ) ) {
			$tail = \substr( $ip, \stripos( $ip, '::ffff:' ) + 7 );
			if ( false !== \filter_var( $tail, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				return self::is_loopback_ip( $tail );
			}
		}

		if ( false !== \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return 0 === \strpos( $ip, '127.' );
		}

		if ( false !== \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = @\inet_pton( $ip );
			$loop   = @\inet_pton( '::1' );
			return false !== $packed && false !== $loop && $packed === $loop;
		}

		return false;
	}

	/**
	 * Returns true when `$host` is a hostname or IP literal that
	 * resolves only to loopback addresses (and resolves to at least
	 * one address). Used to recognize Cloudways / sidecar Varnish
	 * targets such as `127.0.0.1` and `localhost`.
	 *
	 * @since 2.10.3
	 *
	 * @param string $host Hostname or IP literal (no scheme, no port).
	 *
	 * @return bool
	 */
	public static function host_resolves_loopback_only( $host ) {
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}

		if ( '[' === \substr( $host, 0, 1 ) && ']' === \substr( $host, -1 ) ) {
			$host = \substr( $host, 1, -1 );
		}

		if ( false !== \filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_loopback_ip( $host );
		}

		$ipv4_set = @\gethostbynamel( $host );
		if ( false === $ipv4_set ) {
			$ipv4_set = array();
		}
		foreach ( $ipv4_set as $ip ) {
			if ( ! \is_string( $ip ) || ! self::is_loopback_ip( $ip ) ) {
				return false;
			}
		}

		$ipv6_set = array();
		if ( \function_exists( 'dns_get_record' ) ) {
			$aaaa = @\dns_get_record( $host, DNS_AAAA );
			if ( \is_array( $aaaa ) ) {
				foreach ( $aaaa as $rec ) {
					if ( isset( $rec['ipv6'] ) && \is_string( $rec['ipv6'] ) ) {
						$ipv6_set[] = $rec['ipv6'];
					}
				}
			}
		}
		foreach ( $ipv6_set as $ip ) {
			if ( ! self::is_loopback_ip( $ip ) ) {
				return false;
			}
		}

		return ! empty( $ipv4_set ) || ! empty( $ipv6_set );
	}

	/**
	 * Returns true when `$port` is a common HTTP / Varnish listen port
	 * safe to target on loopback for PURGE (not Redis, memcached, MySQL).
	 *
	 * Filterable via `w3tc_varnish_http_ports` for unusual deployments.
	 *
	 * @since 2.10.3
	 *
	 * @param int $port Destination port.
	 *
	 * @return bool
	 */
	public static function is_varnish_http_port( $port ) {
		$port  = (int) $port;
		$ports = array( 80, 443, 8080, 6081, 6082 );
		/**
		 * Filters the HTTP/Varnish ports allowed for loopback PURGE.
		 *
		 * @since 2.10.3
		 *
		 * @param int[] $ports Allowed destination ports.
		 */
		$ports = \apply_filters( 'w3tc_varnish_http_ports', $ports );
		if ( ! \is_array( $ports ) ) {
			return false;
		}
		$ports = \array_map( 'intval', $ports );
		return \in_array( $port, $ports, true );
	}

	/**
	 * Returns true when `$host`:`$port` is a safe Varnish PURGE target.
	 *
	 * Accepts the same destinations as {@see self::host_resolves_safe_internal()}
	 * (RFC1918 + public; still refuses link-local / metadata), AND also
	 * accepts loopback (`127.0.0.1`, `localhost`, `::1`) when the port is
	 * a common HTTP/Varnish port. That restores Cloudways and other
	 * sidecar-Varnish setups without re-enabling the rt9-127 abuse case
	 * of pointing PURGE at `127.0.0.1:6379` (Redis), `:11211`
	 * (memcached), `:3306` (MySQL), etc.
	 *
	 * @since 2.10.3
	 *
	 * @param string     $host Hostname or IP literal (no scheme, no port).
	 * @param int|string $port Destination port.
	 *
	 * @return bool
	 */
	public static function is_safe_varnish_purge_target( $host, $port ) {
		if ( self::host_resolves_safe_internal( $host ) ) {
			return true;
		}

		return self::host_resolves_loopback_only( $host )
			&& self::is_varnish_http_port( $port );
	}

	/**
	 * Returns true when the URL parses, has an https scheme, every
	 * resolved IP is public (per {@see self::is_public_host()}), AND
	 * the host ends with one of the entries in `$allowed_suffixes`
	 * (matched case-insensitively against the FQDN's tail).
	 *
	 * The suffix list MUST be authoritative-host patterns such as
	 * `.rackspacecloud.com` — leading dot included so a literal
	 * `rackspacecloud.com` (without subdomain) is NOT incorrectly
	 * matched by a `rackspacecloud.com` suffix on an attacker-owned
	 * `foorackspacecloud.com.evil.example`.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_url               Candidate URL.
	 * @param array  $allowed_suffixes  Host suffixes such as
	 *                                   `array( '.rackspacecloud.com',
	 *                                   '.rackcdn.com' )`.
	 *
	 * @return bool
	 */
	public static function is_https_public_host_with_suffix( $w3tc_url, array $allowed_suffixes ) {
		if ( ! \is_string( $w3tc_url ) || '' === $w3tc_url ) {
			return false;
		}
		if ( 'https' !== \wp_parse_url( $w3tc_url, PHP_URL_SCHEME ) ) {
			return false;
		}
		if ( ! self::is_public_host( $w3tc_url ) ) {
			return false;
		}
		$host = \wp_parse_url( $w3tc_url, PHP_URL_HOST );
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}
		$host_lc = \strtolower( $host );
		foreach ( $allowed_suffixes as $suffix ) {
			if ( ! \is_string( $suffix ) || '' === $suffix ) {
				continue;
			}
			$suffix_lc = \strtolower( $suffix );
			$slen      = \strlen( $suffix_lc );
			$hlen      = \strlen( $host_lc );
			if ( $hlen >= $slen && \substr( $host_lc, -$slen ) === $suffix_lc ) {
				return true;
			}
		}
		return false;
	}
}
