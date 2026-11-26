<?php
/**
 * File: class-w3tc-sql-literal-scrub-test.php
 *
 * Quoted and numeric SQL literals are replaced before a statement is
 * logged or rendered. Parameter masking via Util_Debug::redact() is unchanged.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Util_Debug;

/**
 * Class: W3tc_Sql_Literal_Scrub_Test
 *
 * @since 2.11.1
 */
class W3tc_Sql_Literal_Scrub_Test extends WP_UnitTestCase {

	/**
	 * An email and a display name in quoted literals are removed.
	 * SQL keywords, the table name, and column names remain.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_drops_email_and_display_name() {
		$email = 'ada@example.com';
		$name  = 'Ada Lovelace';
		$sql   = "SELECT user_email, display_name FROM wp_users WHERE user_email = '" . $email . "' AND display_name = '" . $name . "' AND ID = 42";

		$out = Util_Debug::scrub_sql_literals( $sql );

		$this->assertStringNotContainsString( $email, $out );
		$this->assertStringNotContainsString( $name, $out );
		$this->assertStringContainsString( 'SELECT', $out );
		$this->assertStringContainsString( 'FROM', $out );
		$this->assertStringContainsString( 'WHERE', $out );
		$this->assertStringContainsString( 'AND', $out );
		$this->assertStringContainsString( 'wp_users', $out );
		$this->assertStringContainsString( 'user_email', $out );
		$this->assertStringContainsString( 'display_name', $out );
		$this->assertSame(
			'SELECT user_email, display_name FROM wp_users WHERE user_email = ? AND display_name = ? AND ID = ?',
			$out
		);
	}

	/**
	 * Digits inside identifiers stay. Standalone numbers, hex literals,
	 * and escaped or doubled quotes do not.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_keeps_statement_shape() {
		$identifier = 'SELECT ID FROM `wp_2_posts` WHERE ID = 15 LIMIT 10';
		$this->assertSame(
			'SELECT ID FROM `wp_2_posts` WHERE ID = ? LIMIT ?',
			Util_Debug::scrub_sql_literals( $identifier )
		);

		$escaped = "SELECT display_name FROM wp_users WHERE display_name = 'Ada\\'s Lovelace'";
		$this->assertSame(
			'SELECT display_name FROM wp_users WHERE display_name = ?',
			Util_Debug::scrub_sql_literals( $escaped )
		);
		$this->assertStringNotContainsString( 'Ada', Util_Debug::scrub_sql_literals( $escaped ) );

		$doubled = "SELECT post_title FROM wp_posts WHERE post_title = 'Ada''s Lovelace'";
		$this->assertSame(
			'SELECT post_title FROM wp_posts WHERE post_title = ?',
			Util_Debug::scrub_sql_literals( $doubled )
		);

		$hex = 'SELECT guid FROM wp_posts WHERE guid = 0x616461';
		$this->assertSame(
			'SELECT guid FROM wp_posts WHERE guid = ?',
			Util_Debug::scrub_sql_literals( $hex )
		);
		$this->assertStringNotContainsString( '616461', Util_Debug::scrub_sql_literals( $hex ) );

		$double = 'SELECT display_name FROM wp_users WHERE display_name = "Ada Lovelace"';
		$this->assertSame(
			'SELECT display_name FROM wp_users WHERE display_name = ?',
			Util_Debug::scrub_sql_literals( $double )
		);
		$this->assertStringNotContainsString( 'Ada Lovelace', Util_Debug::scrub_sql_literals( $double ) );

		$decimal = 'SELECT meta_value FROM wp_usermeta WHERE umeta_id = -3 AND meta_value = 1.5';
		$this->assertSame(
			'SELECT meta_value FROM wp_usermeta WHERE umeta_id = -? AND meta_value = ?',
			Util_Debug::scrub_sql_literals( $decimal )
		);
	}

	/**
	 * Util_Debug::redact() still masks sensitive query parameters and
	 * leaves unrelated parameters alone.
	 *
	 * @since 2.11.1
	 */
	public function test_redact_still_masks_sensitive_query_params() {
		$out = Util_Debug::redact( 'GET /foo?monkey=banana&api_key=AKIA-XYZ&compass=north' );

		$this->assertSame( 'GET /foo?monkey=banana&api_key=REDACTED&compass=north', $out );
		$this->assertStringNotContainsString( 'AKIA-XYZ', $out );
	}

	/**
	 * Scrubbing a statement and then applying Util_Debug::redact()
	 * does not put the literal values back.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_then_redact_keeps_literals_out() {
		$sql = "SELECT * FROM wp_users WHERE user_email = 'ada@example.com' AND display_name = 'Ada Lovelace'";
		$out = Util_Debug::redact( Util_Debug::scrub_sql_literals( $sql ) );

		$this->assertStringNotContainsString( 'ada@example.com', $out );
		$this->assertStringNotContainsString( 'Ada Lovelace', $out );
		$this->assertStringContainsString( 'SELECT', $out );
		$this->assertStringContainsString( 'FROM', $out );
		$this->assertStringContainsString( 'wp_users', $out );
		$this->assertSame( $out, Util_Debug::scrub_sql_literals( $out ) );
	}

	/**
	 * Dbcache flush debug / purge logs scrub modification_query literals.
	 *
	 * @since 2.11.1
	 */
	public function test_dbcache_flush_extras_log_copy_scrubs_modification_query() {
		$email = 'ada@example.com';
		$query = "UPDATE wp_users SET user_email = '" . $email . "' WHERE ID = 1";
		$raw   = array( 'modification_query' => $query );

		$log_extras = Util_Debug::scrub_dbcache_flush_extras_for_log( $raw );
		$encoded    = wp_json_encode( $log_extras );

		$this->assertStringNotContainsString( $email, $encoded );
		$this->assertSame( $query, $raw['modification_query'] );
		$this->assertStringContainsString( 'UPDATE wp_users SET user_email = ?', $encoded );

		$other = Util_Debug::scrub_dbcache_flush_extras_for_log(
			array(
				'wpdb_update' => 'wp_2_posts',
				'other_query' => "DELETE FROM wp_posts WHERE post_title = 'Ada'",
				'nested'      => array( 'q' => "SELECT ID FROM wp_users WHERE user_email = '" . $email . "'" ),
			)
		);
		$this->assertSame( 'wp_2_posts', $other['wpdb_update'] );
		$this->assertSame( 'DELETE FROM wp_posts WHERE post_title = ?', $other['other_query'] );
		$this->assertSame( 'SELECT ID FROM wp_users WHERE user_email = ?', $other['nested']['q'] );
	}

	/**
	 * Exponent, binary, and partial decimal literals are each replaced
	 * as one placeholder.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_handles_numeric_forms() {
		$this->assertSame( 'SELECT ?, ?, ?, ?', Util_Debug::scrub_sql_literals( 'SELECT 1e10, 0b1010, .5, 5.' ) );
		$this->assertSame(
			'SELECT meta_value FROM wp_postmeta WHERE meta_value = ? OR meta_value = ? OR meta_value = ?',
			Util_Debug::scrub_sql_literals( 'SELECT meta_value FROM wp_postmeta WHERE meta_value = 1e5 OR meta_value = 2.5E-3 OR meta_value = 7e+12' )
		);
		$this->assertSame( 'SELECT ? FROM wp_posts AS p WHERE p.ID = ?', Util_Debug::scrub_sql_literals( 'SELECT .5e3 FROM wp_posts AS p WHERE p.ID = 9' ) );
	}

	/**
	 * Identifiers that begin with digits are kept whole.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_keeps_digit_leading_identifiers() {
		$this->assertSame(
			'SELECT ID FROM 2026_posts WHERE ID = ?',
			Util_Debug::scrub_sql_literals( 'SELECT ID FROM 2026_posts WHERE ID = 7' )
		);
		$this->assertSame(
			'SELECT 1e5x FROM 3db.wp_posts WHERE wp_posts.9col = ?',
			Util_Debug::scrub_sql_literals( 'SELECT 1e5x FROM 3db.wp_posts WHERE wp_posts.9col = 0x1F' )
		);
		$this->assertSame( 'SELECT ?FROM wp_posts', Util_Debug::scrub_sql_literals( 'SELECT 91.5FROM wp_posts' ) );
	}

	/**
	 * Plus and minus stay as operators, so different statements keep
	 * different shapes.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_keeps_plus_and_minus_operators() {
		$this->assertSame( 'SELECT COUNT(*)-? FROM wp_posts', Util_Debug::scrub_sql_literals( 'SELECT COUNT(*)-1 FROM wp_posts' ) );
		$this->assertSame( 'SELECT COUNT(*)+? FROM wp_posts', Util_Debug::scrub_sql_literals( 'SELECT COUNT(*)+1 FROM wp_posts' ) );
		$this->assertSame( 'UPDATE wp_posts SET menu_order = menu_order--? WHERE ID = ?', Util_Debug::scrub_sql_literals( 'UPDATE wp_posts SET menu_order = menu_order--1 WHERE ID = 4' ) );
	}

	/**
	 * Punctuation inside comments does not swallow the rest of the
	 * statement, and literals inside comments are still replaced.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_handles_comments() {
		$this->assertSame(
			'SELECT /* it?*/ ID FROM wp_posts WHERE ID = ?',
			Util_Debug::scrub_sql_literals( "SELECT /* it's a read */ ID FROM wp_posts WHERE ID = 7" )
		);
		$this->assertSame(
			'SELECT /*!50001 SQL_NO_CACHE */ ID FROM wp_posts WHERE post_title = ?',
			Util_Debug::scrub_sql_literals( "SELECT /*!50001 SQL_NO_CACHE */ ID FROM wp_posts WHERE post_title = 'Ada'" )
		);
		$this->assertSame(
			"SELECT ID -- it?\nFROM wp_posts # ada?\nWHERE ID = ?",
			Util_Debug::scrub_sql_literals( "SELECT ID -- it's note\nFROM wp_posts # ada's\nWHERE ID = 7" )
		);
		$this->assertSame(
			'SELECT ID FROM wp_posts /* ?',
			Util_Debug::scrub_sql_literals( "SELECT ID FROM wp_posts /* 'ada@example.com'" )
		);
	}

	/**
	 * Dbcache purge-log backtrace arguments do not carry string, array,
	 * or numeric values, whether or not a string is SQL. Other modules
	 * keep the raw arguments.
	 *
	 * @since 2.11.1
	 */
	public function test_encode_params_scrubs_dbcache_backtrace_args() {
		$method = new ReflectionMethod( Util_Debug::class, 'encode_params' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$args = array(
			"UPDATE wp_users SET user_email = 'ada@example.com' WHERE ID = 7",
			array( 'display_name' => 'Ada Lovelace' ),
			42,
			'sentinel.ada@example.com',
		);

		$scrubbed = $method->invoke( null, $args, true );
		$this->assertSame( '(?, {?:?}, ?, ?)', $scrubbed );

		$raw = $method->invoke( null, $args, false );
		$this->assertStringContainsString( 'ada@example.com', $raw );

		$this->assertSame( '(?)', $method->invoke( null, 'sentinel.ada@example.com', true ) );
	}

	/**
	 * A dbcache purge log written under add_user_meta() does not include
	 * the meta value from the WordPress metadata frames.
	 *
	 * @since 2.11.1
	 */
	public function test_dbcache_log_purge_omits_non_sql_frame_strings() {
		$sentinel = 'sentinel.ada@example.com';
		$filename = Util_Debug::log_filename( 'dbcache-purge' );
		$before   = \file_exists( $filename ) ? (int) \filesize( $filename ) : 0;

		$log = function () {
			Util_Debug::log_purge( 'dbcache', 'test', array( 'remaining' ) );
		};

		\add_action( 'added_user_meta', $log );
		try {
			\add_user_meta( self::factory()->user->create(), 'billing_email', $sentinel );
		} finally {
			\remove_action( 'added_user_meta', $log );
		}

		\clearstatcache();
		$this->assertFileExists( $filename );
		$entry = (string) \file_get_contents( $filename, false, null, $before );

		$this->assertStringContainsString( 'add_metadata', $entry );
		$this->assertStringNotContainsString( $sentinel, $entry );
		$this->assertStringNotContainsString( 'billing_email', $entry );
	}

	/**
	 * Nested comment openers are scanned once, so time and memory stay
	 * linear in the input length.
	 *
	 * @since 2.11.1
	 */
	public function test_scrub_sql_literals_is_linear_for_nested_comment_openers() {
		$inputs = array(
			'hash comments'  => \str_repeat( 'a{color:#fff}', 20000 ),
			'block comments' => \str_repeat( '/* ', 50000 ),
			'dash comments'  => \str_repeat( "-- 'x' ", 30000 ),
		);

		foreach ( $inputs as $label => $input ) {
			if ( \function_exists( 'memory_reset_peak_usage' ) ) {
				\memory_reset_peak_usage();
			}

			$peak  = \max( \memory_get_usage(), \memory_get_peak_usage() );
			$start = \microtime( true );
			$out   = Util_Debug::scrub_sql_literals( $input );

			$this->assertLessThan( 2.0, \microtime( true ) - $start, $label );
			$this->assertLessThan( 16 * \strlen( $input ) + 1048576, \memory_get_peak_usage() - $peak, $label );
			$this->assertNotSame( '', $out, $label );
		}

		$this->assertSame( 'a{color:#fff} /* ? */ b', Util_Debug::scrub_sql_literals( "a{color:#fff} /* 'x' */ b" ) );
		$this->assertSame( '/* /* ? */ ?', Util_Debug::scrub_sql_literals( "/* /* 'x' */ 'y'" ) );
	}

	/**
	 * A dbcache purge log entry does not include SQL literals from the
	 * calling frames.
	 *
	 * @since 2.11.1
	 */
	public function test_dbcache_log_purge_backtrace_omits_sql_literals() {
		$filename = Util_Debug::log_filename( 'dbcache-purge' );
		$before   = \file_exists( $filename ) ? (int) \filesize( $filename ) : 0;

		$this->sql_literal_scrub_outer_frame( "UPDATE wp_users SET user_email = 'ada@example.com' WHERE ID = 7" );

		\clearstatcache();
		$this->assertFileExists( $filename );
		$entry = (string) \file_get_contents( $filename, false, null, $before );

		$this->assertStringContainsString( 'sql_literal_scrub_outer_frame (?)', $entry );
		$this->assertStringContainsString( 'DELETE FROM wp_users WHERE user_email = ?', $entry );
		$this->assertStringNotContainsString( 'ada@example.com', $entry );
	}

	/**
	 * The debug query log replaces literals in the query column before
	 * the line is written.
	 *
	 * @since 2.11.1
	 */
	public function test_dbcache_query_log_replaces_literals_in_query_column() {
		$email = 'ada@example.com';
		$sql   = "SELECT user_email FROM wp_users WHERE user_email = '" . $email . "' AND ID = 7";

		$caching = new \W3TC\DbCache_WpdbInjection_QueryCaching();
		$next    = new class() {
			/**
			 * Statement passed downstream.
			 *
			 * @var string
			 */
			public $seen = '';

			/**
			 * Run the downstream query.
			 *
			 * @param string $query SQL statement.
			 * @return int
			 */
			public function query( $query ) {
				$this->seen = $query;
				return 1;
			}
		};
		$caching->initialize_injection(
			new class() {
				/**
				 * Connection flag.
				 *
				 * @var bool
				 */
				public $ready = true;

				/**
				 * Start a timer.
				 *
				 * @return void
				 */
				public function timer_start() {
				}

				/**
				 * Stop a timer.
				 *
				 * @return float
				 */
				public function timer_stop() {
					return 0.0;
				}
			},
			$next
		);

		$this->sql_literal_scrub_set_property( $caching, 'debug', true );
		$this->sql_literal_scrub_set_property( $caching, 'use_filters', false );
		$this->sql_literal_scrub_set_property( $caching, 'cache_reject_reason', 'reason-token-keep' );

		$filename = Util_Debug::log_filename( 'dbcache-queries' );
		$before   = \file_exists( $filename ) ? (int) \filesize( $filename ) : 0;

		$caching->query( $sql );

		$handle = $this->sql_literal_scrub_get_property( $caching, 'log_filehandle' );
		if ( \is_resource( $handle ) ) {
			\fflush( $handle );
		}

		\clearstatcache();
		$this->assertFileExists( $filename );
		$entry = (string) \file_get_contents( $filename, false, null, $before );

		$this->assertStringNotContainsString( $email, $entry );
		$this->assertStringContainsString(
			'SELECT user_email FROM wp_users WHERE user_email = ? AND ID = ?',
			$entry
		);
		$this->assertStringContainsString( 'reason-token-keep', $entry );
		$this->assertSame( $sql, $next->seen );
	}

	/**
	 * Set a non-public property on the query-log subject.
	 *
	 * @since 2.11.1
	 *
	 * @param object $object   Subject.
	 * @param string $property Property name.
	 * @param mixed  $value    New value.
	 */
	private function sql_literal_scrub_set_property( $object, $property, $value ) {
		$prop = new \ReflectionProperty( $object, $property );
		if ( \PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		$prop->setValue( $object, $value );
	}

	/**
	 * Read a non-public property on the query-log subject.
	 *
	 * @since 2.11.1
	 *
	 * @param object $object   Subject.
	 * @param string $property Property name.
	 * @return mixed
	 */
	private function sql_literal_scrub_get_property( $object, $property ) {
		$prop = new \ReflectionProperty( $object, $property );
		if ( \PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		return $prop->getValue( $object );
	}

	/**
	 * Calling frame whose argument is the SQL statement.
	 *
	 * @since 2.11.1
	 *
	 * @param string $sql SQL statement.
	 */
	private function sql_literal_scrub_outer_frame( $sql ) {
		$this->sql_literal_scrub_inner_frame();
	}

	/**
	 * Frame that writes the purge log entry.
	 *
	 * @since 2.11.1
	 */
	private function sql_literal_scrub_inner_frame() {
		Util_Debug::log_purge(
			'dbcache',
			'test',
			array( 'remaining', array( 'modification_query' => "DELETE FROM wp_users WHERE user_email = 'ada@example.com'" ) )
		);
	}
}
