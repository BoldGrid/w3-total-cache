<?php
/**
 * File: UsageStatistics_Source_DbQueriesLog.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Source_PageCacheLog
 *
 * PageCache debug log reader - provides data from this logfile
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class UsageStatistics_Source_PageCacheLog {
	/**
	 * Timestamp indicating the start time for log processing.
	 *
	 * @var int
	 */
	private $timestamp_start;

	/**
	 * Current process status used to filter logs.
	 *
	 * @var string
	 */
	private $process_status;

	/**
	 * Column used for sorting the final log entries output.
	 *
	 * @var string
	 */
	private $sort_column;

	/**
	 * Holds URI data parsed from the log file, including count, sum size, sum time, and reasons.
	 *
	 * @var array
	 */
	private $by_uri = array();

	/**
	 * Flag indicating whether more log entries are required to be processed.
	 *
	 * @var bool
	 */
	private $more_log_needed = true;

	/**
	 * Constructor to initialize the class attributes.
	 *
	 * @param int    $timestamp_start The start timestamp for the log processing.
	 * @param string $process_status  The process status to filter the logs by.
	 * @param string $sort_column     The column to sort the final results by.
	 *
	 * @return void
	 */
	public function __construct( $timestamp_start, $process_status, $sort_column ) {
		$this->timestamp_start = $timestamp_start;
		$this->process_status  = $process_status;
		$this->sort_column     = $sort_column;
	}

	/**
	 * Lists all the parsed log entries, sorted by the specified column.
	 *
	 * This method reads the log file, processes it in reverse order, and accumulates the relevant data
	 * about each URI, such as the count of occurrences, average size, average time taken,
	 * and unique reasons for the status. It returns a sorted list of the data based on the selected column.
	 *
	 * @return array List of log entries, each containing URI, count, average size,
	 *               average time in milliseconds, total time, and reasons.
	 *
	 * @throws \Exception If the log file cannot be opened.
	 */
	public function list_entries() {
		$log_filename = Util_Debug::log_filename( 'pagecache' );
		$h            = @fopen( $log_filename, 'rb' );
		if ( ! $h ) {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Log filename.
						\__( 'Failed to open pagecache log file %1$s.', 'w3-total-cache' ),
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
		foreach ( $this->by_uri as $uri => $data ) {
			$output[] = array(
				'uri'         => $uri,
				'count'       => $data['count'],
				'avg_size'    => (int) ( $data['sum_size'] / $data['count'] ),
				'avg_time_ms' => (int) ( $data['sum_time_ms'] / $data['count'] ),
				'sum_time_ms' => $data['sum_time_ms'],
				'reasons'     => $data['reasons'],
			);
		}

		usort(
			$output,
			function ( $a, $b ) {
				return (int) ( $b[ $this->sort_column ] ) - (int) ( $a[ $this->sort_column ] );
			}
		);

		return $output;
	}

	/**
	 * Parses the provided string and processes each line of log data.
	 *
	 * This method processes the log data in chunks and parses each line for relevant information.
	 * It will skip the first line if required and handle the raw log string by calling `push_line` for each parsed line.
	 *
	 * @param string $s               The string to be parsed.
	 * @param bool   $skip_first_line Flag to indicate whether the first line should be skipped.
	 *
	 * @return string Unparsed head of the log data that remains after parsing.
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
	 * Processes and stores the data from a single log line.
	 *
	 * This method matches the log line against a regular expression and extracts details such as the URI,
	 * time taken, size, process status, and the reason. If the log line meets the filter criteria (e.g.,
	 * matching the process status and timestamp), the relevant information is added to the `$by_uri` attribute.
	 *
	 * @param string $line A single line of log data to be processed.
	 *
	 * @return void
	 */
	private function push_line( $line ) {
		$matches = null;
		preg_match(
			'/\[([^>\]]+)\] \[([^>\]]+)\] \[([^>\]]+)\] finished in (\d+) size (\d+) with process status ([^ ]+) reason (.*)/',
			$line,
			$matches
		);

		if ( ! $matches ) {
			return;
		}

		$date_string   = $matches[1];
		$uri           = $matches[2];
		$time_taken_ms = $matches[4];
		$size          = $matches[5];
		$status        = $matches[6];
		$reason        = $matches[7];
		$time          = strtotime( $date_string );

		// dont read more if we touched entries before timeperiod of collection.
		if ( $time < $this->timestamp_start ) {
			$this->more_log_needed = false;
		}

		if ( $status !== $this->process_status ) {
			return;
		}

		if ( ! isset( $this->by_uri[ $uri ] ) ) {
			$this->by_uri[ $uri ] = array(
				'count'       => 0,
				'sum_size'    => 0,
				'sum_time_ms' => 0,
				'reasons'     => array(),
			);
		}

		++$this->by_uri[ $uri ]['count'];
		$this->by_uri[ $uri ]['sum_size']    += $size;
		$this->by_uri[ $uri ]['sum_time_ms'] += $time_taken_ms;

		if ( ! in_array( $reason, $this->by_uri[ $uri ]['reasons'], true ) ) {
			$this->by_uri[ $uri ]['reasons'][] = $reason;
		}
	}
}
