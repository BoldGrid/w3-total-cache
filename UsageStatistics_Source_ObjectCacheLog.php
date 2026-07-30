<?php
/**
 * File: UsageStatistics_Source_DbQueriesLog.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Source_ObjectCacheLog
 *
 * Database queries debug log reader - provides data from this logfile
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class UsageStatistics_Source_ObjectCacheLog {
	/**
	 * The timestamp marking the start time for log entries to process.
	 *
	 * @var int
	 */
	private $timestamp_start;

	/**
	 * The column used to sort the output of log entries.
	 *
	 * @var string
	 */
	private $sort_column;

	/**
	 * Stores the log data grouped by category for analysis.
	 *
	 * @var array
	 */
	private $by_group = array();

	/**
	 * Flag to determine if more log data needs to be read.
	 *
	 * @var bool
	 */
	private $more_log_needed = true;

	/**
	 * Constructor to initialize the object cache log processing.
	 *
	 * @param int    $timestamp_start The timestamp marking the start of the log entry range.
	 * @param string $sort_column     The column name to sort the log entries.
	 *
	 * @return void
	 */
	public function __construct( $timestamp_start, $sort_column ) {
		$this->timestamp_start = $timestamp_start;
		$this->sort_column     = $sort_column;
	}

	/**
	 * Lists the entries from the log file, processing and grouping them.
	 * The entries are filtered, parsed, and then sorted according to the provided column.
	 *
	 * @return array The processed log entries, sorted and grouped as per the configuration.
	 *
	 * @throws \Exception If the log file cannot be opened.
	 */
	public function list_entries() {
		$log_filename = Util_Debug::log_filename( 'objectcache-calls' );
		$h            = @fopen( $log_filename, 'rb' );
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

		$w3tc_output = array();
		foreach ( $this->by_group as $w3tc_group => $w3tc_data ) {
			$w3tc_output[] = array(
				'group'           => $w3tc_group,
				'count_total'     => $w3tc_data['count_total'],
				'count_get_total' => $w3tc_data['count_get_total'],
				'count_get_hit'   => $w3tc_data['count_get_hit'],
				'count_set'       => $w3tc_data['count_set'],
				'sum_size'        => $w3tc_data['sum_size'],
				'avg_size'        => $w3tc_data['count_total'] ? (int) ( $w3tc_data['sum_size'] / $w3tc_data['count_total'] ) : 0,
				'sum_time_ms'     => (int) $w3tc_data['sum_time_ms'],
			);
		}

		usort(
			$w3tc_output,
			function ( $w3tc_a, $b ) {
				return (int) ( $b[ $this->sort_column ] ) - (int) ( $w3tc_a[ $this->sort_column ] );
			}
		);

		$w3tc_output = array_slice( $w3tc_output, 0, 200 );

		return $w3tc_output;
	}

	/**
	 * Parses the string data from the log file and pushes individual lines to the processing function.
	 *
	 * @param string $s               The raw string to parse.
	 * @param bool   $skip_first_line Flag to indicate if the first line should be skipped.
	 *
	 * @return string The unparsed portion of the string, if any.
	 */
	private function parse_string( $s, $skip_first_line ) {
		$s_length      = strlen( $s );
		$unparsed_head = '';

		$n = 0;
		if ( $skip_first_line ) {
			for ( ; $n < $s_length; $n++ ) {
				$w3tc_c = substr( $s, $n, 1 );
				if ( "\r" === $w3tc_c || "\n" === $w3tc_c ) {
					$unparsed_head = substr( $s, 0, $n + 1 );
					break;
				}
			}
		}

		$line_start = $n;
		for ( ; $n < $s_length; $n++ ) {
			$w3tc_c = substr( $s, $n, 1 );
			if ( "\r" === $w3tc_c || "\n" === $w3tc_c ) {
				if ( $n > $line_start ) {
					$this->push_line( substr( $s, $line_start, $n - $line_start ) );
				}

				$line_start = $n + 1;
			}
		}

		return $unparsed_head;
	}

	/**
	 * Processes a single line from the log file, extracting relevant details and updating group statistics.
	 *
	 * @param string $w3tc_line The log line to process.
	 *
	 * @return void
	 */
	private function push_line( $w3tc_line ) {
		$matches = str_getcsv( $w3tc_line, "\t" );

		if ( ! $matches ) {
			return;
		}

		$date_string   = $matches[0];
		$op            = $matches[1];
		$w3tc_group    = $matches[2];
		$id            = $matches[3];
		$reason        = $matches[4];
		$size          = (int) $matches[5];
		$time_taken_ms = isset( $matches[6] ) ? (float) $matches[6] / 1000 : 0;

		$time = strtotime( $date_string );

		// dont read more if we touched entries before timeperiod of collection.
		if ( $time < $this->timestamp_start ) {
			$this->more_log_needed = false;
		}

		if ( 'not tried cache' === $reason || 'not set' === substr( $reason, 0, 7 ) ) {
			return; // it's not cache-related activity.
		}

		if ( ! isset( $this->by_group[ $w3tc_group ] ) ) {
			$this->by_group[ $w3tc_group ] = array(
				'count_total'     => 0,
				'count_get_total' => 0,
				'count_get_hit'   => 0,
				'count_set'       => 0,
				'sum_size'        => 0,
				'sum_time_ms'     => 0,
			);
		}

		if ( 'get' === $op ) {
			++$this->by_group[ $w3tc_group ]['count_total'];
			++$this->by_group[ $w3tc_group ]['count_get_total'];
			if ( 'from persistent cache' === $reason ) {
				++$this->by_group[ $w3tc_group ]['count_get_hit'];
			}
		} elseif ( 'set' === $op ) {
			++$this->by_group[ $w3tc_group ]['count_total'];
			++$this->by_group[ $w3tc_group ]['count_set'];
		}

		$this->by_group[ $w3tc_group ]['sum_size']    += $size;
		$this->by_group[ $w3tc_group ]['sum_time_ms'] += $time_taken_ms;
	}
}
