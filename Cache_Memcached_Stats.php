<?php
/**
 * File: Cache_Memcached_Stats.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Memcached_Stats
 *
 * Download extended statistics since module cant do it by itself
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Cache_Memcached_Stats {
	/**
	 * Constructor to initialize the Memcached stats handler.
	 *
	 * @param string $host The hostname or IP address of the Memcached server.
	 * @param int    $port The port number of the Memcached server.
	 *
	 * @return void
	 */
	public function __construct( $host, $port ) {
		$this->host = $host;
		$this->port = $port;
	}

	/**
	 * Sends a command to the Memcached server and retrieves the response.
	 *
	 * @param string $command The command to send to the Memcached server.
	 *
	 * @return array|null An array of response lines from the server, or null on failure.
	 */
	public function request( $command ) {
		$handle = @fsockopen( $this->host, $this->port );
		if ( ! $handle ) {
			return null;
		}

		fwrite( $handle, $command . "\r\n" );

		$response = array();
		while ( ( ! feof( $handle ) ) ) {
			$line       = fgets( $handle );
			$response[] = $line;

			if ( $this->end( $line, $command ) ) {
				break;
			}
		}

		@fclose( $handle );

		return $response;
	}

	/**
	 * Determines if the server response indicates the end of a command's execution.
	 *
	 * @param string $buffer  The current line of the server's response.
	 * @param string $command The command that was sent to the server.
	 *
	 * @return bool True if the response indicates the end of the command, false otherwise.
	 */
	private function end( $buffer, $command ) {
		// incr or decr also return integer.
		if ( ( preg_match( '/^(incr|decr)/', $command ) ) ) {
			if ( preg_match( '/^(END|ERROR|SERVER_ERROR|CLIENT_ERROR|NOT_FOUND|[0-9]*)/', $buffer ) ) {
				return true;
			}
		} elseif ( preg_match( '/^(END|DELETED|OK|ERROR|SERVER_ERROR|CLIENT_ERROR|NOT_FOUND|STORED|RESET|TOUCHED)/', $buffer ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Parses the response lines into an array of data.
	 *
	 * @param array $lines An array of response lines from the Memcached server.
	 *
	 * @return array A parsed array where each line is split into words.
	 */
	public function parse( $lines ) {
		$return = array();

		foreach ( $lines as $line ) {
			$data     = explode( ' ', $line );
			$return[] = $data;
		}

		return $return;
	}

	/**
	 * Retrieves the IDs of all active slabs on the Memcached server.
	 *
	 * A slab is a logical unit of storage in Memcached.
	 *
	 * @return array|null An array of slab IDs, or null on failure.
	 */
	public function slabs() {
		$result = $this->request( 'stats slabs' );
		if ( is_null( $result ) ) {
			return null;
		}

		$result = $this->parse( $result );
		$slabs  = array();

		foreach ( $result as $line_words ) {
			if ( count( $line_words ) < 2 ) {
				continue;
			}

			$key = explode( ':', $line_words[1] );
			if ( (int) $key[0] > 0 ) {
				$slabs[ $key[0] ] = '*';
			}
		}

		return array_keys( $slabs );
	}

	/**
	 * Retrieves a cachedump for a specific slab ID.
	 *
	 * A cachedump returns the cached items for the specified slab.
	 *
	 * @param int $slab_id The ID of the slab to retrieve the cachedump for.
	 *
	 * @return array|null An array of cachedump data, or null on failure.
	 */
	public function cachedump( $slab_id ) {
		$result = $this->request( 'stats cachedump ' . $slab_id . ' 0' );
		if ( is_null( $result ) ) {
			return null;
		}

		// return pure data to limit memory usage.
		return $result;
	}
}
