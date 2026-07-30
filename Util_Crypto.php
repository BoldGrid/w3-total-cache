<?php
/**
 * File: Util_Crypto.php
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

namespace W3TC;

/**
 * Class Util_Crypto
 *
 * At-rest encryption envelope for credential-typed config values.
 *
 * W3TC's master.php has historically stored CDN keys, API tokens, FTP
 * passwords, SFTP private keys, and similar credentials as plain JSON
 * strings. A read of `wp-content/w3tc-config/master.php` — via any
 * file-disclosure primitive, a backup grab, or `support-handler` style
 * exfil — yielded every secret in the clear.
 *
 * This class wraps each secret in an envelope:
 *
 *     enc:v1:<base64( IV || HMAC || CIPHERTEXT )>
 *
 * - Cipher: AES-256-CBC (OpenSSL).
 * - Key: derived from `wp_salt('secure_auth')` via HMAC-SHA256 over the
 *   constant string `'w3tc:envelope'` (binding the key to W3TC so a
 *   future re-use of the same salt for another purpose can't accidentally
 *   produce the same key).
 * - IV: 16 random bytes per call (CBC needs a fresh IV per encrypt).
 * - HMAC: 32 bytes of HMAC-SHA256 over `IV || CIPHERTEXT` keyed with the
 *   same envelope key. Verified with `hash_equals()` before decrypt.
 *
 * Decrypt is a no-op on any value that doesn't start with `enc:v1:`, so
 * legacy plaintext values still read transparently. The next time the
 * config is saved, every secret-flagged key gets re-wrapped — an existing
 * install upgrades itself without an explicit migration step.
 *
 * Tamper detection is in-band: a flipped ciphertext byte fails the HMAC
 * check and `envelope_decrypt()` returns false. Callers treat that as
 * "credential needs re-entry" rather than silently feeding a corrupted
 * value into a CDN client.
 *
 * Salt rotation: if an operator rotates `secure_auth` (or `auth`) salts
 * in wp-config.php, every previously-encrypted secret will fail to
 * decrypt. That's intentional and matches WordPress's own behaviour for
 * cookie-signed sessions — the salt is the master secret. Operators
 * rotating salts must re-enter credentials. The release notes call this
 * out alongside the existing salt-rotation guidance.
 *
 * @since 2.10.0
 */
class Util_Crypto {

	/**
	 * Envelope-format prefix. Anything not starting with this string is
	 * treated as legacy plaintext and passed through unchanged.
	 *
	 * @var string
	 */
	const ENVELOPE_PREFIX = 'enc:v1:';

	/**
	 * Returns true if OpenSSL is available and AES-256-CBC is supported.
	 *
	 * On the (very rare) host without OpenSSL or without AES-256-CBC,
	 * the envelope cannot be produced. Callers fall back to writing the
	 * plaintext value — no functional regression vs. the pre-fix
	 * behaviour — and a one-time admin notice surfaces the missing
	 * dependency.
	 *
	 * @since 2.10.0
	 *
	 * @return bool
	 */
	public static function is_available() {
		if ( ! \function_exists( 'openssl_encrypt' ) ) {
			return false;
		}

		if ( ! \function_exists( 'openssl_get_cipher_methods' ) ) {
			return false;
		}

		$methods = @\openssl_get_cipher_methods( true );

		return \is_array( $methods ) && \in_array( 'aes-256-cbc', \array_map( 'strtolower', $methods ), true );
	}

	/**
	 * Derives a per-purpose envelope key.
	 *
	 * Encrypt-then-MAC best practice is to use independent keys for the
	 * cipher and the authenticator: re-using the same key across two
	 * primitives is widely flagged as a design smell even when (as here,
	 * with AES-256-CBC + HMAC-SHA256) it has no known break.  We derive
	 * two 32-byte keys from the same salt by HMAC-ing different context
	 * strings — a poor-man's HKDF-Expand that's enough to keep the
	 * cipher key and the MAC key cryptographically independent.
	 *
	 * Both keys are tied to `wp_salt('secure_auth')` so the keying
	 * material rotates whenever the operator rotates that salt. The
	 * salt itself is resolved by {@see self::key_salt()}, which keeps the
	 * derived key identical whether or not `wp_salt()` has loaded yet.
	 *
	 * @since 2.10.0
	 *
	 * @param string $purpose `'enc'` for the AES-256-CBC key, `'mac'`
	 *                        for the HMAC-SHA256 key.
	 *
	 * @return string Raw 32-byte HMAC-SHA256 output.
	 */
	private static function derive_key( $purpose = 'enc' ) {
		return \hash_hmac( 'sha256', 'w3tc:envelope:' . $purpose, self::key_salt(), true );
	}

	/**
	 * Resolves the salt the envelope key is derived from.
	 *
	 * The salt must be identical at encrypt time (admin context, `wp_salt()`
	 * available) and at decrypt time — including the early-bootstrap window
	 * where `advanced-cache.php` and the object-cache drop-ins read stored
	 * values before `wp-includes/pluggable.php` defines `wp_salt()`. If the two
	 * differ, the derived key differs and the stored value cannot be recovered.
	 *
	 * Resolution order:
	 *
	 * 1. `wp_salt('secure_auth')` when available — authoritative; honours a
	 *    `salt` filter and DB-stored salts.
	 * 2. Otherwise reproduce what `wp_salt('secure_auth')` returns from the
	 *    wp-config.php constants `SECURE_AUTH_KEY . SECURE_AUTH_SALT`. For the
	 *    common case (all eight keys/salts defined in wp-config.php, no `salt`
	 *    filter) this is byte-identical to (1), so a value stored in admin can
	 *    be read back during the pre-pluggable bootstrap.
	 * 3. Last-resort fallback for standalone CLI helpers / unit tests with no
	 *    salts defined at all. Values keyed here cannot be read back in any
	 *    other context, but nothing in a real install relies on it.
	 *
	 * Installs relying on auto-generated (DB-stored) salts or a `salt` filter —
	 * where (2) cannot reproduce (1) — cannot resolve stored values
	 * pre-pluggable; the caller leaves the envelope intact rather than
	 * discarding it (see {@see \W3TC\Config::_maybe_lazy_decrypt()}).
	 *
	 * @since 2.10.0
	 *
	 * @return string Salt string fed to {@see self::derive_key()}.
	 */
	private static function key_salt() {
		if ( \function_exists( 'wp_salt' ) ) {
			return (string) \wp_salt( 'secure_auth' );
		}

		if ( self::salt_constants_available() ) {
			return (string) SECURE_AUTH_KEY . (string) SECURE_AUTH_SALT;
		}

		return ( defined( 'SECURE_AUTH_KEY' ) ? (string) SECURE_AUTH_KEY : '' )
			. '|' . ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '' );
	}

	/**
	 * True when the wp-config.php salt constants needed to reproduce
	 * `wp_salt('secure_auth')` without `wp_salt()` are defined and non-empty.
	 *
	 * Used both by {@see self::key_salt()} (to pick the early-bootstrap branch)
	 * and by {@see \W3TC\Config::_maybe_lazy_decrypt()} (to decide whether a
	 * pre-pluggable decrypt can be trusted to use the right key).
	 *
	 * @since 2.10.0
	 *
	 * @return bool
	 */
	public static function salt_constants_available() {
		return defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY
			&& defined( 'SECURE_AUTH_SALT' ) && SECURE_AUTH_SALT;
	}

	/**
	 * Encrypts a plaintext value into the envelope format.
	 *
	 * Returns the envelope string on success, or the raw plaintext on
	 * failure (so the call site can write *something* and the existing
	 * fallback behaviour persists).
	 *
	 * @since 2.10.0
	 *
	 * @param string $plaintext Raw secret value.
	 *
	 * @return string `enc:v1:...` envelope on success, raw plaintext on failure.
	 */
	public static function envelope_encrypt( $plaintext ) {
		if ( ! \is_string( $plaintext ) || '' === $plaintext ) {
			return $plaintext;
		}

		// If the value is already enveloped, don't double-wrap it.
		if ( self::is_envelope( $plaintext ) ) {
			return $plaintext;
		}

		if ( ! self::is_available() ) {
			return $plaintext;
		}

		$enc_key = self::derive_key( 'enc' );
		$mac_key = self::derive_key( 'mac' );
		$iv      = \random_bytes( 16 );
		$ct      = @\openssl_encrypt( $plaintext, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ct ) {
			return $plaintext;
		}

		$tag = \hash_hmac( 'sha256', $iv . $ct, $mac_key, true );

		return self::ENVELOPE_PREFIX . \base64_encode( $iv . $tag . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypts an envelope back to plaintext.
	 *
	 * Non-envelope inputs (legacy plaintext) pass through unchanged so
	 * pre-fix installs read correctly until their next save migrates
	 * them. Tampered envelopes (bad HMAC, malformed base64, wrong
	 * length) return `false` — callers should treat that as "credential
	 * needs re-entry" rather than feeding a partial value downstream.
	 *
	 * @since 2.10.0
	 *
	 * @param string $envelope Either an `enc:v1:...` envelope or a legacy plaintext string.
	 *
	 * @return string|false Plaintext on success, the original string for
	 *                      non-envelope input, or `false` on tamper.
	 */
	public static function envelope_decrypt( $envelope ) {
		if ( ! \is_string( $envelope ) || '' === $envelope ) {
			return $envelope;
		}

		if ( ! self::is_envelope( $envelope ) ) {
			return $envelope;
		}

		if ( ! self::is_available() ) {
			return false;
		}

		$body = \base64_decode( \substr( $envelope, \strlen( self::ENVELOPE_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $body || \strlen( $body ) < 48 ) {
			return false;
		}

		$iv  = \substr( $body, 0, 16 );
		$tag = \substr( $body, 16, 32 );
		$ct  = \substr( $body, 48 );

		$enc_key  = self::derive_key( 'enc' );
		$mac_key  = self::derive_key( 'mac' );
		$expected = \hash_hmac( 'sha256', $iv . $ct, $mac_key, true );
		if ( ! \hash_equals( $expected, $tag ) ) {
			return false;
		}

		$pt = @\openssl_decrypt( $ct, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $pt ) {
			return false;
		}

		return $pt;
	}

	/**
	 * Quick check: does this string look like an envelope?
	 *
	 * Used by `Config::set()` to avoid double-wrapping on save and by
	 * `Config::get_string()` to skip the decrypt path on legacy values.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_value Candidate value.
	 *
	 * @return bool
	 */
	public static function is_envelope( $w3tc_value ) {
		return \is_string( $w3tc_value ) && 0 === \strpos( $w3tc_value, self::ENVELOPE_PREFIX );
	}
}
