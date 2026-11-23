<?php
/**
 * File: Util_DebugPurgeLog_Reader.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_DebugPurgeLog_Reader
 *
 * Reads purge log from the end to top to get last records
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Util_DebugPurgeLog_Reader {
	/**
	 * Lines
	 *
	 * @var array
	 */
	private $lines = array();

	/**
	 * Current Time
	 *
	 * @var array
	 */
	private $current_item = array();

	/**
	 * Constructor
	 *
	 * @return void
	 */
	private function __construct() {
	}

	/**
	 * Read log setup
	 *
	 * @param string $w3tc_module Module.
	 *
	 * @return array
	 */
	public static function read( $w3tc_module ) {
		$w3tc_o = new Util_DebugPurgeLog_Reader();
		return $w3tc_o->_read( $w3tc_module );
	}

	/**
	 * Read log
	 *
	 * @param string $w3tc_module Module.
	 *
	 * @throws \Exception Exception.
	 *
	 * @return array
	 */
	private function _read( $w3tc_module ) {
		$log_filename = Util_Debug::log_filename( $w3tc_module . '-purge' );
		if ( ! file_exists( $log_filename ) ) {
			return array();
		}

		$h = @fopen( $log_filename, 'rb' );
		if ( ! $h ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Log filename.
						\__( 'Failed to open log file %1$s.', 'w3-total-cache' ),
						$log_filename
					)
				)
			);
		}

		fseek( $h, 0, SEEK_END );
		$pos           = ftell( $h );
		$unparsed_head = '';

		$more_log_needed = true;

		while ( $pos >= 0 ) {
			$to_read = 26;
			$pos    -= $to_read;

			if ( $pos <= 0 ) {
				$to_read = $to_read + $pos;
				$pos     = 0;
			}

			fseek( $h, $pos );

			$s = fread( $h, $to_read );

			$unparsed_head = $this->parse_string( $s . $unparsed_head );
			if ( count( $this->lines ) > 100 ) {
				break;
			}

			if ( $pos <= 0 ) {
				$this->push_line( $unparsed_head );
				break;
			}
		}

		return $this->lines;
	}

	/**
	 * Parse string
	 *
	 * @param string $s String.
	 *
	 * @return string
	 */
	private function parse_string( $s ) {
		$first_unparsed = strlen( $s );
		$pos            = $first_unparsed;

		for ( ; $pos >= 0; $pos-- ) {
			$w3tc_c = substr( $s, $pos, 1 );
			if ( "\r" === $w3tc_c || "\n" === $w3tc_c ) {
				$this->push_line( substr( $s, $pos + 1, $first_unparsed - $pos - 1 ) );
				$first_unparsed = $pos;
			}
		}

		return substr( $s, 0, $first_unparsed );
	}

	/**
	 * Push line
	 *
	 * @param string $w3tc_line Line.
	 *
	 * @return string
	 */
	private function push_line( $w3tc_line ) {
		if ( empty( $w3tc_line ) ) {
			return;
		}

		if ( "\t" === substr( $w3tc_line, 0, 1 ) ) {
			array_unshift( $this->current_item, $w3tc_line );
			return;
		}

		// split secondary lines to urls and backtrace.
		$postfix   = array();
		$backtrace = array();
		$username  = '';
		foreach ( $this->current_item as $w3tc_item ) {
			$w3tc_item = trim( $w3tc_item );
			if ( preg_match( '~^(#[^ ]+) ([^:]+): (.*)~', $w3tc_item, $m ) ) {
				$backtrace[] = array(
					'number'   => $m[1],
					'filename' => $m[2],
					'function' => $m[3],
				);
			} elseif ( preg_match( '~^username:(.*)~', $w3tc_item, $m ) ) {
				$username = $m[1];
			} else {
				$postfix[] = $w3tc_item;
			}
		}

		$m = null;
		if ( preg_match( '~\\[([^\\]]+)\\] (.*)~', $w3tc_line, $m ) ) {
			$this->lines[] = array(
				'date'      => $m[1],
				'message'   => $m[2],
				'username'  => $username,
				'postfix'   => $postfix,
				'backtrace' => $backtrace,
			);
		}

		$this->current_item = array();
	}
}
