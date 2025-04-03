<?php
/**
 * File: Util_UsageStatistics.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_UsageStatistics
 */
class Util_UsageStatistics {
	/**
	 * Converts a byte value into a human-readable size format (KB, MB, or GB).
	 *
	 * This method formats a numeric byte value into a string representing the size in
	 * kilobytes (KB), megabytes (MB), or gigabytes (GB), based on the value's magnitude.
	 * If the input value is null, it returns 'n/a'.
	 *
	 * @param int|float|null $v The byte value to convert.
	 *
	 * @return string The formatted size (e.g., '1.2 GB', '512.0 MB', or '256.0 KB'),
	 *                or 'n/a' if the value is null.
	 */
	public static function bytes_to_size( $v ) {
		if ( is_null( $v ) ) {
			return 'n/a';
		}

		if ( $v > 500000000 ) {
			return sprintf( '%.1f GB', $v / 1024 /*KB*/ / 1024 /*MB*/ / 1024/*GB*/ );
		}

		if ( $v > 500000 ) {
			return sprintf( '%.1f MB', $v / 1024 /*KB*/ / 1024 /*MB*/ );
		} else {
			return sprintf( '%.1f KB', $v / 1024 /*KB*/ );
		}
	}

	/**
	 * Converts a nested byte value from an array into a human-readable size format (KB, MB, or GB).
	 *
	 * This method retrieves a nested value from an array using the `v` method, then converts it
	 * into a human-readable size format using the `bytes_to_size` method. If the value is null,
	 * it returns 'n/a'.
	 *
	 * @param array           $a  The input array to search for the byte value.
	 * @param string|int      $p1 The first key to search for.
	 * @param string|int|null $p2 The second key to search for (optional).
	 * @param string|int|null $p3 The third key to search for (optional).
	 *
	 * @return string The formatted size (e.g., '1.2 GB', '512.0 MB', or '256.0 KB'),
	 *                or 'n/a' if the value is null or not found.
	 */
	public static function bytes_to_size2( $a, $p1, $p2 = null, $p3 = null ) {
		$v = self::v( $a, $p1, $p2, $p3 );
		if ( is_null( $v ) ) {
			return 'n/a';
		}

		return self::bytes_to_size( $v );
	}

	/**
	 * Calculates the percentage of two values and formats it as a string with a '%' symbol.
	 *
	 * If the denominator is zero, the result is '0 %'. If the numerator exceeds the denominator,
	 * the result is capped at '100 %'. Otherwise, the percentage is calculated and formatted
	 * as an integer.
	 *
	 * @param float|int $v1 The numerator value.
	 * @param float|int $v2 The denominator value.
	 *
	 * @return string The calculated percentage as a string (e.g., '75 %'), or '0 %' if $v2 is 0.
	 */
	public static function percent( $v1, $v2 ) {
		if ( 0 === $v2 ) {
			return '0 %';
		} elseif ( $v1 > $v2 ) {
			return '100 %';
		} else {
			return sprintf( '%d', $v1 / $v2 * 100 ) . ' %';
		}
	}

	/**
	 * Calculates the percentage of two properties from an array and formats it as a string with a '%' symbol.
	 *
	 * If either property is missing or the denominator is zero, a fallback value is returned.
	 *
	 * @param array      $a {
	 *     The input array containing the properties.
	 *
	 *     @type string|int $property1 The key for the numerator value.
	 *     @type string|int $property2 The key for the denominator value.
	 * }
	 * @param string|int $property1 The key for the numerator value.
	 * @param string|int $property2 The key for the denominator value.
	 *
	 * @return string The calculated percentage as a string (e.g., '75 %'), 'n/a' if properties
	 *                are missing, or '0 %' if $a[$property2] is 0.
	 */
	public static function percent2( $a, $property1, $property2 ) {
		if ( ! isset( $a[ $property1 ] ) || ! isset( $a[ $property2 ] ) ) {
			return 'n/a';
		} elseif ( empty( $a[ $property2 ] ) ) {
			return '0 %';
		} else {
			return sprintf( '%d', $a[ $property1 ] / $a[ $property2 ] * 100 ) . ' %';
		}
	}

	/**
	 * Sums up the values of a specified property from an array of arrays.
	 *
	 * This method iterates through the array, retrieving the value of the specified property
	 * using the `v3` method, and sums all non-empty values.
	 *
	 * @param array      $history  An array of arrays to sum values from.
	 * @param string|int $property The key of the property to sum.
	 *
	 * @return int|float The total sum of the specified property.
	 */
	public static function sum( $history, $property ) {
		$v = 0;
		foreach ( $history as $i ) {
			$item_value = self::v3( $i, $property );
			if ( ! empty( $item_value ) ) {
				$v += $item_value;
			}
		}
		return $v;
	}

	/**
	 * Calculates the average of values of a specified property from an array of arrays.
	 *
	 * This method iterates through the array, retrieves the values of the specified property
	 * using the `v3` method, and calculates the average of all non-empty values.
	 *
	 * @param array      $history  An array of arrays to calculate the average from.
	 * @param string|int $property The key of the property to average.
	 *
	 * @return float The average value, or 0 if there are no valid entries.
	 */
	public static function avg( $history, $property ) {
		$v     = 0;
		$count = 0;
		foreach ( $history as $i ) {
			$item_value = self::v3( $i, $property );
			if ( ! empty( $item_value ) ) {
				$v += $item_value;
				++$count;
			}
		}
		return ( $count <= 0 ? 0 : $v / $count );
	}

	/**
	 * Sums up all positive values of properties whose names start with a specific prefix.
	 *
	 * This method iterates through an array of arrays and aggregates values of keys that start
	 * with the given prefix. Only positive values are included in the sum.
	 *
	 * @param array  $output          The array to store the aggregated sums by property name.
	 * @param array  $history         An array of arrays to sum values from.
	 * @param string $property_prefix The prefix of the property names to match.
	 *
	 * @return void
	 */
	public static function sum_by_prefix_positive( &$output, $history, $property_prefix ) {
		$property_prefix_len = strlen( $property_prefix );

		foreach ( $history as $i ) {
			foreach ( $i as $key => $value ) {
				if ( substr( $key, 0, $property_prefix_len ) === $property_prefix && $value > 0 ) {
					if ( ! isset( $output[ $key ] ) ) {
						$output[ $key ] = 0;
					}

					$output[ $key ] += $value;
				}
			}
		}
	}

	/**
	 * Formats a timestamp into a human-readable date and time.
	 *
	 * This method uses PHP's `date()` function to format the provided timestamp in
	 * the 'm/d/Y H:i' format.
	 *
	 * @param int $timestamp The Unix timestamp to format.
	 *
	 * @return string The formatted date and time string.
	 */
	public static function time_mins( $timestamp ) {
		return gmdate( 'm/d/Y H:i', $timestamp );
	}

	/**
	 * Formats an integer value with thousand separators.
	 *
	 * This method uses `number_format()` to add separators to large integers for readability.
	 *
	 * @param int|float $v The value to format.
	 *
	 * @return string The formatted number as a string.
	 */
	public static function integer( $v ) {
		return number_format( $v );
	}

	/**
	 * Divides a value by a specified divisor and formats the result as an integer with thousand separators.
	 *
	 * If the divisor is zero, the method returns 'n/a'.
	 *
	 * @param float|int $v         The value to divide.
	 * @param float|int $divide_by The divisor.
	 *
	 * @return string The formatted result, or 'n/a' if the divisor is zero.
	 */
	public static function integer_divideby( $v, $divide_by ) {
		if ( empty( $divide_by ) ) {
			return 'n/a';
		}

		return self::integer( $v / $divide_by );
	}

	/**
	 * Retrieves a nested value from an array and formats it as an integer with thousand separators.
	 *
	 * This method uses the `v` method to retrieve a nested value from an array and formats it
	 * using `number_format()`. If the value is null, it returns 'n/a'.
	 *
	 * @param array           $a  The input array to search.
	 * @param string|int      $p1 The first key to search for.
	 * @param string|int|null $p2 The second key to search for (optional).
	 * @param string|int|null $p3 The third key to search for (optional).
	 *
	 * @return string The formatted value, or 'n/a' if the value is null.
	 */
	public static function integer2( $a, $p1, $p2 = null, $p3 = null ) {
		$v = self::v( $a, $p1, $p2, $p3 );
		if ( is_null( $v ) ) {
			return 'n/a';
		} else {
			return number_format( $v );
		}
	}

	/**
	 * Retrieves a nested value from a multidimensional array using up to three keys.
	 *
	 * This method safely navigates an array by checking the existence of keys at each level.
	 * If a key does not exist or the path is incomplete, the method returns `null`.
	 *
	 * @param array           $a   The input array to search.
	 * @param string|int      $p1 The first key, required to locate the initial value.
	 * @param string|int|null $p2 Optional. The second key to access a nested value. Default is null.
	 * @param string|int|null $p3 Optional. The third key to access a deeper nested value. Default is null.
	 *
	 * @return mixed|null The value located at the specified key path, or null if the path is invalid or incomplete.
	 */
	public static function v( $a, $p1, $p2 = null, $p3 = null ) {
		if ( ! isset( $a[ $p1 ] ) ) {
			return null;
		}

		$v = $a[ $p1 ];
		if ( is_null( $p2 ) ) {
			return $v;
		}

		if ( ! isset( $v[ $p2 ] ) ) {
			return null;
		}

		$v = $v[ $p2 ];
		if ( is_null( $p3 ) ) {
			return $v;
		}

		if ( ! isset( $v[ $p3 ] ) ) {
			return null;
		}

		return $v[ $p3 ];
	}

	/**
	 * Retrieves a deeply nested value from a multidimensional array using an array of keys.
	 *
	 * This method safely navigates a nested array by following the sequence of keys provided.
	 * If any key in the sequence is not set or the path is invalid, the method returns `null`.
	 *
	 * @param array            $a The input array to search.
	 * @param string|int|array $p A single key or an array of keys representing the path to the desired value
	 *                            If a single key is provided, it will be converted to an array.
	 *
	 * @return mixed|null The value at the specified key path, or null if the path is invalid or incomplete.
	 */
	public static function v3( $a, $p ) {
		if ( ! is_array( $p ) ) {
			$p = array( $p );
		}

		$actual = &$a;
		$count  = count( $p );
		for ( $i = 0; $i < $count; $i++ ) {
			$property = $p[ $i ];

			if ( ! isset( $actual[ $property ] ) ) {
				return null;
			}

			$actual = &$actual[ $property ];
		}

		return $actual;
	}

	/**
	 * Calculates a value per second based on two properties of an array.
	 *
	 * This method checks the presence of two properties in an array and calculates the ratio
	 * of the first property to the second, scaled by 100. If the properties are missing or
	 * the second property is zero, a fallback value is returned.
	 *
	 * @param array      $a The input array containing the values to calculate.
	 * @param string|int $property1 The key for the numerator value.
	 * @param string|int $property2 The key for the denominator value.
	 *
	 * @return string The calculated value as a percentage (formatted to 1 decimal place), '0'
	 *                if the denominator is zero, or 'n/a' if either key is missing.
	 */
	public static function value_per_second( $a, $property1, $property2 ) {
		if ( ! isset( $a[ $property1 ] ) || ! isset( $a[ $property2 ] ) ) {
			return 'n/a';
		} elseif ( empty( $a[ $property2 ] ) ) {
			return '0';
		} else {
			return sprintf( '%.1f', $a[ $property1 ] / $a[ $property2 ] * 100 );
		}
	}

	/**
	 * Calculates the average value per second over a specified period.
	 *
	 * This method divides a total value by the number of seconds in a period,
	 * which is retrieved from the provided summary array.
	 *
	 * @param float $total The total value to divide.
	 * @param array $summary {
	 *     The summary array containing the period details.
	 *
	 *     @type array $period {
	 *         Details about the period.
	 *
	 *         @type int $seconds The number of seconds in the period.
	 *     }
	 * }
	 *
	 * @return string The calculated value per second (formatted to 1 decimal place), or 'n/a'
	 *                if the period's seconds value is missing or empty.
	 */
	public static function value_per_period_seconds( $total, $summary ) {
		if ( empty( $summary['period']['seconds'] ) ) {
			return 'n/a';
		}

		$period_seconds = $summary['period']['seconds'];

		return sprintf( '%.1f', $total / $period_seconds );
	}

	/**
	 * Retrieves or initializes a size-related transient for cache summary calculations.
	 *
	 * This method checks for the existence of a transient storing cache size details. If the
	 * transient is invalid or missing, it initializes the transient with placeholder values
	 * and marks it for counting. It also ensures that the transient corresponds to the current
	 * period defined in the summary.
	 *
	 * @param string $transient The name of the transient to retrieve or initialize.
	 * @param array  $summary {
	 *     Summary array containing the current period's timestamp details.
	 *
	 *     @type array $period {
	 *         Information about the summary period.
	 *
	 *         @type int $timestamp_end The end timestamp of the current period.
	 *     }
	 * }
	 *
	 * @return array {
	 *     An array containing the transient value and a flag indicating if counting is required.
	 *
	 *     @type array $0 The transient value (existing or initialized).
	 *     @type bool  $1 Whether counting is required (`true` for initialization).
	 * }
	 */
	public static function get_or_init_size_transient( $transient, $summary ) {
		$should_count = false;

		$v = get_transient( $transient );
		if ( is_array( $v ) && isset( $v['timestamp_end'] ) && $v['timestamp_end'] === $summary['period']['timestamp_end'] ) {
			return array( $v, false );
		}

		// limit number of processing counting it at the same time.
		$v = array(
			'timestamp_end' => $summary['period']['timestamp_end'],
			'size_used'     => '...counting',
			'items'         => '...counting',
		);

		set_transient( $transient, $v, 120 );

		return array( $v, true );
	}
}
