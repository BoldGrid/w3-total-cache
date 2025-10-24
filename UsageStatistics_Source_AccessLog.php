<?php
/**
 * File: UsageStatistics_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Source_AccessLog
 *
 * Access log reader - provides statistics data from http server access log
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class UsageStatistics_Source_AccessLog {
	/**
	 * Regular expression for parsing access log lines.
	 *
	 * @var string
	 */
	private $line_regexp;

	/**
	 * The last processed log line during the statistics collection.
	 *
	 * @var string
	 */
	private $max_line = '';

	/**
	 * The first processed log line during the statistics collection.
	 *
	 * @var string
	 */
	private $min_line = '';

	/**
	 * The maximum timestamp encountered during statistics collection.
	 *
	 * @var int
	 */
	private $max_time = 0;

	/**
	 * The minimum timestamp encountered during statistics collection.
	 *
	 * @var int
	 */
	private $min_time;

	/**
	 * Timestamp up to which the access log has been processed already.
	 * Used to avoid re-processing already counted log entries.
	 *
	 * @var int
	 */
	private $max_already_counted_timestamp;

	/**
	 * Timestamp up to which log entries have been processed in the current cycle.
	 *
	 * @var int|null
	 */
	private $max_now_counted_timestamp = null;

	/**
	 * A flag indicating if more log data needs to be read from the access log file.
	 *
	 * @var bool
	 */
	private $more_log_needed = true;

	/**
	 * The array where aggregated usage statistics are stored.
	 *
	 * @var array
	 */
	private $history;

	/**
	 * The current position in the history array for processing log entries.
	 *
	 * @var int
	 */
	private $history_current_pos;

	/**
	 * The current item in the history array representing a specific log entry's aggregated statistics.
	 *
	 * @var array
	 */
	private $history_current_item;

	/**
	 * The timestamp marking the start of the current time period for the log entry in history.
	 *
	 * @var int
	 */
	private $history_current_timestamp_start;

	/**
	 * The timestamp marking the end of the current time period for the log entry in history.
	 *
	 * @var int
	 */
	private $history_current_timestamp_end;

	/**
	 * Processes the usage statistics from a history of access logs and adds relevant summary data.
	 *
	 * This method sums up dynamic and static request data and calculates average timings for each,
	 * then integrates the statistics into a given summary array. It's essential for summarizing access log
	 * statistics for the period represented by the history.
	 *
	 * @param array $summary The existing summary data, which will be updated with new information.
	 * @param array $history The historical access log data to summarize.
	 *
	 * @return array The updated summary array with added statistics.
	 */
	public static function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		$dynamic_requests_total     = Util_UsageStatistics::sum( $history, array( 'access_log', 'dynamic_count' ) );
		$dynamic_timetaken_ms_total = Util_UsageStatistics::sum( $history, array( 'access_log', 'dynamic_timetaken_ms' ) );
		$static_requests_total      = Util_UsageStatistics::sum( $history, array( 'access_log', 'static_count' ) );
		$static_timetaken_ms_total  = Util_UsageStatistics::sum( $history, array( 'access_log', 'static_timetaken_ms' ) );

		$summary['access_log'] = array(
			'dynamic_requests_total_v'    => $dynamic_requests_total,
			'dynamic_requests_total'      => Util_UsageStatistics::integer( $dynamic_requests_total ),
			'dynamic_requests_per_second' => Util_UsageStatistics::value_per_period_seconds( $dynamic_requests_total, $summary ),
			'dynamic_requests_timing'     => Util_UsageStatistics::integer_divideby( $dynamic_timetaken_ms_total, $dynamic_requests_total ),
			'static_requests_total'       => Util_UsageStatistics::integer( $static_requests_total ),
			'static_requests_per_second'  => Util_UsageStatistics::value_per_period_seconds( $static_requests_total, $summary ),
			'static_requests_timing'      => Util_UsageStatistics::integer_divideby( $static_timetaken_ms_total, $static_requests_total ),
		);

		return $summary;
	}

	/**
	 * Initializes the access log handler with the provided data.
	 *
	 * This constructor sets up the necessary properties based on the provided data such as the log format,
	 * webserver type, and log filename. It also generates a regular expression for parsing the log entries based
	 * on the webserver type (Nginx or Apache).
	 *
	 * @param array $data Data array containing the log format, webserver type, and log filename.
	 */
	public function __construct( $data ) {
		$format                   = $data['format'];
		$webserver                = $data['webserver'];
		$this->accesslog_filename = str_replace( '://', '/', $data['filename'] );

		if ( 'nginx' === $webserver ) {
			$line_regexp = $this->logformat_to_regexp_nginx( $format );
		} else {
			$line_regexp = $this->logformat_to_regexp_apache( $format );
		}

		$this->line_regexp = apply_filters( 'w3tc_ustats_access_log_format_regexp', $line_regexp );
	}

	/**
	 * Processes the history of access logs and sets the relevant usage statistics.
	 *
	 * This method loads the access log file, reads from it to collect usage statistics, and updates the history
	 * array with data. It ensures that only the relevant logs (those not already processed) are parsed. If the
	 * log file cannot be opened, it logs an error and returns the history unchanged.
	 *
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
	 *
	 * @param array $history The existing history data that will be updated with parsed log statistics.
	 *
	 * @return array The updated history data.
	 */
	public function w3tc_usage_statistics_history_set( $history ) {
		$this->max_already_counted_timestamp = (int) get_site_option( 'w3tc_stats_history_access_log' );
		if ( isset( $history[0]['timestamp_start'] ) && $history[0]['timestamp_start'] > $this->max_already_counted_timestamp ) {
			$this->max_already_counted_timestamp = $history[0]['timestamp_start'] - 1;
		}

		$this->history  = $history;
		$this->min_time = time();
		$this->setup_history_item( count( $history ) - 1 );

		$h = @fopen( $this->accesslog_filename, 'rb' );
		if ( ! $h ) {
			error_log( 'Failed to open access log for usage statisics collection' );
			return $history;
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

		if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
			Util_Debug::log(
				'time',
				'period ' .
					gmdate( DATE_ATOM, $this->max_already_counted_timestamp ) . ' - ' .
					gmdate( DATE_ATOM, $this->max_now_counted_timestamp ) . "\n" .
					'min line: ' . $this->min_line . "\n" .
					'max line: ' . $this->max_line
			);
		}

		if ( ! is_null( $this->max_now_counted_timestamp ) ) {
			update_site_option( 'w3tc_stats_history_access_log', $this->max_now_counted_timestamp );
		}

		return $this->history;
	}

	/**
	 * Initializes a specific history item in the given position.
	 *
	 * This method sets up the access log item for the specific position within the history, initializing it with
	 * default values if not already set. It's essential for tracking the access log statistics across multiple
	 * history items.
	 *
	 * @param int $pos The position in the history array to set up.
	 */
	private function setup_history_item( $pos ) {
		$this->history_current_pos = $pos;

		if ( ! isset( $this->history[ $pos ]['access_log'] ) ) {
			$this->history[ $pos ]['access_log'] = array(
				'dynamic_count'        => 0,
				'dynamic_timetaken_ms' => 0,
				'static_count'         => 0,
				'static_timetaken_ms'  => 0,
			);
		}

		$this->history_current_item            = &$this->history[ $pos ]['access_log'];
		$this->history_current_timestamp_start = $this->history[ $pos ]['timestamp_start'];
		$this->history_current_timestamp_end   = $this->history[ $pos ]['timestamp_end'];
	}

	/**
	 * Parses the given log string, processing its lines and collecting statistics.
	 *
	 * This method reads the log string, skips the first line if required, and processes each line to collect
	 * data for usage statistics. It also handles the boundary checks for the collection period and logs
	 * debugging information if needed.
	 *
	 * @param string $s               The log string to parse.
	 * @param bool   $skip_first_line Whether to skip the first line when parsing.
	 *
	 * @return string Any unparsed head of the string.
	 */
	private function parse_string( $s, $skip_first_line ) {
		$s_length      = strlen( $s );
		$unparsed_head = '';
		$lines         = array();

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

		$line_start         = $n;
		$line_elements      = array();
		$line_element_start = $n;

		for ( ; $n < $s_length; $n++ ) {
			$c = substr( $s, $n, 1 );
			if ( "\r" === $c || "\n" === $c ) {
				if ( $n > $line_start ) {
					$lines[] = substr( $s, $line_start, $n - $line_start );
				}

				$line_start = $n + 1;
			}
		}

		// last line comes first, boundary checks logic based on that.
		for ( $n = count( $lines ) - 1; $n >= 0; $n-- ) {
			$this->push_line( $lines[ $n ] );
		}

		return $unparsed_head;
	}

	/**
	 * Pushes a single line of log data into the history statistics.
	 *
	 * This method processes a single line from the access log, extracting relevant data (such as request time)
	 * and categorizing the request as either dynamic or static. It updates the statistics for the current history item.
	 *
	 * @param string $line The line from the access log to process.
	 */
	private function push_line( $line ) {
		$e = array();
		preg_match( $this->line_regexp, $line, $e );

		$e = apply_filters( 'w3tc_ustats_access_log_line_elements', $e, $line );
		if ( ! isset( $e['request_line'] ) || ! isset( $e['date'] ) ) {
			if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
				Util_Debug::log( 'time', "line $line cant be parsed using regexp $this->line_regexp, request_line or date elements missing" );
			}

			return;
		}

		$date_string = $e['date'];
		$time        = strtotime( $date_string );

		// dont read more if we touched entries before timeperiod of collection.
		if ( $time <= $this->max_already_counted_timestamp ) {
			$this->more_log_needed = false;
			return;
		}

		if ( $time > $this->history_current_timestamp_end ) {
			return;
		}

		while ( $time < $this->history_current_timestamp_start ) {
			if ( $this->history_current_pos <= 0 ) {
				$this->more_log_needed = false;
				return;
			}

			$this->setup_history_item( $this->history_current_pos - 1 );
		}

		if ( is_null( $this->max_now_counted_timestamp ) ) {
			$this->max_now_counted_timestamp = $time;
		}

		if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
			if ( $time < $this->min_time ) {
				$this->min_line = $line;
				$this->min_time = $time;
			}

			if ( $time > $this->max_time ) {
				$this->max_line = $line;
				$this->max_time = $time;
			}
		}

		$http_request_line       = $e['request_line'];
		$http_request_line_items = explode( ' ', $http_request_line );
		$uri                     = $http_request_line_items[1];

		$time_ms = 0;
		if ( isset( $e['time_taken_microsecs'] ) ) {
			$time_ms = (int) ( $e['time_taken_microsecs'] / 1000 );
		} elseif ( isset( $e['time_taken_ms'] ) ) {
			$time_ms = (int) $e['time_taken_ms'];
		}

		$m = null;
		preg_match( '~\\.([a-zA-Z0-9]+)(\?.+)?$~', $uri, $m );
		if ( $m && 'php' !== $m[1] ) {
			++$this->history_current_item['static_count'];
			$this->history_current_item['static_timetaken_ms'] += $time_ms;
		} else {
			++$this->history_current_item['dynamic_count'];
			$this->history_current_item['dynamic_timetaken_ms'] += $time_ms;
		}
	}

	/**
	 * Converts an Apache log format into a regular expression.
	 *
	 * This method translates a given Apache log format into a regular expression that can be used to parse
	 * Apache logs. It handles various Apache log format variables and removes unnecessary modifiers.
	 *
	 * default: %h %l %u %t \"%r\" %>s %b
	 * common : %h %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"
	 *
	 * @param string $format The Apache log format string.
	 *
	 * @return string The regular expression to parse Apache logs.
	 */
	public function logformat_to_regexp_apache( $format ) {
		// remove modifiers like %>s, %!400,501{User-agent}i.
		$format = preg_replace( '~%[<>!0-9]([a-zA-Z{])~', '%$1', $format );

		// remove modifiers %{User-agent}^ti, %{User-agent}^to.
		$format = preg_replace( '~%({[^}]+})(^ti|^to)~', '%$1z', $format );

		// take all quoted vars.
		$format = preg_replace_callback(
			'~\\\"(%[a-zA-Z%]|%{[^}]+}[a-zA-Z])\\\"~',
			array( $this, 'logformat_to_regexp_apache_element_quoted' ),
			$format
		);

		// take all remaining vars.
		$format = preg_replace_callback(
			'~(%[a-zA-Z%]|%{[^}]+}[a-zA-Z])~',
			array( $this, 'logformat_to_regexp_apache_element_naked' ),
			$format
		);

		return '~' . $format . '~';
	}

	/**
	 * Converts a quoted Apache log format element into a regular expression.
	 *
	 * This method handles the conversion of quoted log elements (e.g., request lines) from Apache log format
	 * to the corresponding regular expression pattern.
	 *
	 * @param array $matched_value The matched portion of the log format.
	 *
	 * @return string The regular expression for the quoted log element.
	 */
	public function logformat_to_regexp_apache_element_quoted( $matched_value ) {
		$v = $matched_value[1];

		if ( '%r' === $v ) {
			return '\"(?<request_line>[^"]+)\"';
		}

		// default behavior, expected value doesnt contain spaces.
		return '\"([^"]+)\"';
	}

	/**
	 * Converts a non-quoted Apache log format element into a regular expression.
	 *
	 * This method handles the conversion of unquoted log elements (e.g., timestamps or status codes) from
	 * Apache log format to the corresponding regular expression pattern.
	 *
	 * @param array $matched_value The matched portion of the log format.
	 *
	 * @return string The regular expression for the non-quoted log element.
	 */
	public function logformat_to_regexp_apache_element_naked( $matched_value ) {
		$v = $matched_value[1];

		if ( '%t' === $v ) {
			return '\[(?<date>[^\]]+)\]';
		} elseif ( '%D' === $v ) {
			return '(?<time_taken_microsecs>[0-9]+)';
		}

		// default behavior, expected value doesnt contain spaces.
		return '([^ ]+)';
	}

	/**
	 * Converts an Nginx log format into a regular expression.
	 *
	 * This method translates a given Nginx log format into a regular expression that can be used to parse
	 * Nginx logs. It handles various Nginx log format variables and escapes any quotes or special characters.
	 *
	 * default: $remote_addr - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent"
	 * w3tc:    $remote_addr - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent" $request_time
	 *
	 * @param string $format The Nginx log format string.
	 *
	 * @return string The regular expression to parse Nginx logs.
	 */
	public function logformat_to_regexp_nginx( $format ) {
		// escape quotes.
		$format = preg_replace_callback(
			'~([\"\[\]])~',
			array( $this, 'logformat_to_regexp_nginx_quote' ),
			$format
		);

		// take all quoted vars.
		$format = preg_replace_callback(
			'~\\\"(\$[a-zA-Z0-9_]+)\\\"~',
			array( $this, 'logformat_to_regexp_nginx_element_quoted' ),
			$format
		);

		// take all remaining vars.
		$format = preg_replace_callback(
			'~(\$[a-zA-Z0-9_]+)~',
			array( $this, 'logformat_to_regexp_nginx_element_naked' ),
			$format
		);

		return '~' . $format . '~';
	}

	/**
	 * Escapes quotes in the Nginx log format.
	 *
	 * This method ensures that any quotes in the Nginx log format are properly escaped for use in a regular
	 * expression.
	 *
	 * @param array $matched_value The matched quote from the Nginx log format.
	 *
	 * @return string The escaped quote.
	 */
	public function logformat_to_regexp_nginx_quote( $matched_value ) {
		return '\\' . $matched_value[1];
	}

	/**
	 * Converts a quoted Nginx log format element into a regular expression.
	 *
	 * This method handles the conversion of quoted log elements (e.g., request lines) from Nginx log format
	 * to the corresponding regular expression pattern.
	 *
	 * @param array $matched_value The matched portion of the log format.
	 *
	 * @return string The regular expression for the quoted log element.
	 */
	public function logformat_to_regexp_nginx_element_quoted( $matched_value ) {
		$v = $matched_value[1];

		if ( '$request' === $v ) {
			return '\"(?<request_line>[^"]+)\"';
		}

		// default behavior, expected value doesnt contain spaces.
		return '\"([^"]+)\"';
	}

	/**
	 * Converts a non-quoted Nginx log format element into a regular expression.
	 *
	 * This method handles the conversion of unquoted log elements (e.g., timestamps or request times) from
	 * Nginx log format to the corresponding regular expression pattern.
	 *
	 * @param array $matched_value The matched portion of the log format.
	 *
	 * @return string The regular expression for the non-quoted log element.
	 */
	public function logformat_to_regexp_nginx_element_naked( $matched_value ) {
		$v = $matched_value[1];

		if ( '$time_local' === $v ) {
			return '(?<date>[^\]]+)';
		} elseif ( '$request_time' === $v ) {
			return '(?<time_taken_ms>[0-9.]+)';
		}

		// default behavior, expected value doesnt contain spaces.
		return '([^ ]+)';
	}
}
