<?php
/**
 * File: UsageStatistics_Source_DbQueriesLog.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Source_DbQueriesLog
 *
 * Database queries debug log reader - provides data from this logfile
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class UsageStatistics_Source_DbQueriesLog {
	/**
	 * The timestamp representing the start time for filtering log entries.
	 *
	 * @var int
	 */
	private $timestamp_start;

	/**
	 * The column by which the log entries should be sorted.
	 *
	 * @var string
	 */
	private $sort_column;

	/**
	 * The associative array holding query data by query string.
	 *
	 * @var array
	 */
	private $by_query = array();

	/**
	 * Flag indicating whether more log entries are needed for parsing.
	 *
	 * @var bool
	 */
	private $more_log_needed = true;

	/**
	 * Constructor for initializing the object with a start timestamp and sort column.
	 *
	 * @param int    $timestamp_start The timestamp representing the start time for filtering log entries.
	 * @param string $sort_column     The column by which the log entries should be sorted.
	 *
	 * @return void
	 */
	public function __construct( $timestamp_start, $sort_column ) {
		$this->timestamp_start = $timestamp_start;
		$this->sort_column     = $sort_column;
	}

	/**
	 * Retrieves and processes log entries, sorting them based on the specified column, and returns the top 200 entries.
	 *
	 * This method reads the log file from the end, processes the data, and filters based on the timestamp.
	 * The results are returned as an array containing various statistics such as query counts, average size, and time.
	 *
	 * @return array An array of sorted log entries containing statistics about queries such as total count, hit count,
	 *               average size, average time, total time, and reasons.
	 *
	 * @throws \Exception If the log file cannot be opened.
	 */
	public function list_entries() {
		$log_filename = Util_Debug::log_filename( 'dbcache-queries' );
		$h            = @fopen( $log_filename, 'rb' );
		if ( ! $h ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// translators: 1 Log filename.
						\__( 'Failed to open log file %1$s.', 'w3-total-cache' ),
						$log_filename
					)
				)
			);
		}

		fseek( $h, 0, SEEK_END );
		$pos           = ftell( $h );
		$unparsed_head = '';

		while ( $pos >= 0 && $this->more_log_needed ) {
			$pos -= 8192;
			if ( $pos <= 0 ) {
				$pos = 0;
			}

			fseek( $h, $pos );

			$s = fread( $h, 8192 );

			$unparsed_head = $this->parse_string( $s . $unparsed_head, $pos > 0 );
			if ( $pos <= 0 ) {
				$this->more_log_needed = false;
			}
		}

		$output = array();
		foreach ( $this->by_query as $query => $data ) {
			$output[] = array(
				'query'       => $query,
				'count_total' => $data['count_total'],
				'count_hit'   => $data['count_hit'],
				'avg_size'    => (int) ( $data['sum_size'] / $data['count_total'] ),
				'avg_time_ms' => (int) ( $data['sum_time_ms'] / $data['count_total'] ),
				'sum_time_ms' => (int) $data['sum_time_ms'],
				'reasons'     => $data['reasons'],
			);
		}

		usort(
			$output,
			function ( $a, $b ) {
				return (int) ( $b[ $this->sort_column ] ) - (int) ( $a[ $this->sort_column ] );
			}
		);

		$output = array_slice( $output, 0, 200 );

		return $output;
	}

	/**
	 * Parses a string of log data, processing individual lines and pushing relevant information to be analyzed.
	 *
	 * This method processes a chunk of log data, handling the option to skip the first line and breaking the data into
	 * individual lines for further analysis.
	 *
	 * @param string $s               The log data string to be parsed.
	 * @param bool   $skip_first_line Whether to skip the first line of the string.
	 *
	 * @return string The unparsed head of the log data, if any.
	 */
	private function parse_string( $s, $skip_first_line ) {
		$s_length      = strlen( $s );
		$unparsed_head = '';

		$n = 0;
		if ( $skip_first_line ) {
			for ( ; $n < $s_length; $n++ ) {
				$c = substr( $s, $n, 1 );
				if ( "\r" === $c || "\n" === $c ) {
					$unparsed_head = substr( $s, 0, $n + 1 );
					break;
				}
			}
		}

		$line_start = $n;
		for ( ; $n < $s_length; $n++ ) {
			$c = substr( $s, $n, 1 );
			if ( "\r" === $c || "\n" === $c ) {
				if ( $n > $line_start ) {
					$this->push_line( substr( $s, $line_start, $n - $line_start ) );
				}

				$line_start = $n + 1;
			}
		}

		return $unparsed_head;
	}

	/**
	 * Processes a single line of log data, extracting relevant information and updating the query statistics.
	 *
	 * This method parses a line of log data, updating the query statistics for the given query (e.g., count, size, time, etc.).
	 * It also checks if more log data is needed based on the timestamp and ensures that data for each query is stored appropriately.
	 *
	 * @param string $line The log line to be processed.
	 *
	 * @return void
	 */
	private function push_line( $line ) {
		$matches = str_getcsv( $line, "\t" );

		if ( ! $matches ) {
			return;
		}

		$date_string   = $matches[0];
		$query         = $matches[2];
		$time_taken_ms = isset( $matches[3] ) ? (float) $matches[3] / 1000 : 0;
		$reason        = isset( $matches[4] ) ? $matches[4] : '';
		$hit           = isset( $matches[5] ) ? $matches[5] : false;
		$size          = isset( $matches[6] ) ? $matches[6] : 0;

		$time = strtotime( $date_string );

		// dont read more if we touched entries before timeperiod of collection.
		if ( $time < $this->timestamp_start ) {
			$this->more_log_needed = false;
		}

		if ( ! isset( $this->by_query[ $query ] ) ) {
			$this->by_query[ $query ] = array(
				'count_total' => 0,
				'count_hit'   => 0,
				'sum_size'    => 0,
				'sum_time_ms' => 0,
				'reasons'     => array(),
			);
		}

		++$this->by_query[ $query ]['count_total'];
		if ( $hit ) {
			++$this->by_query[ $query ]['count_hit'];
		}
		$this->by_query[ $query ]['sum_size']    += $size;
		$this->by_query[ $query ]['sum_time_ms'] += $time_taken_ms;

		if ( ! in_array( $reason, $this->by_query[ $query ]['reasons'], true ) ) {
			$this->by_query[ $query ]['reasons'][] = $reason;
		}
	}
}
