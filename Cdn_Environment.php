<?php
/**
 * File: Cdn_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Environment
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_Environment {
	/**
	 * Constructor for the Cdn_Environment class.
	 *
	 * Initializes the class by adding filters to the 'w3tc_browsercache_rules_section_extensions' and
	 * 'w3tc_browsercache_rules_section' hooks. This allows the class to modify browser cache rules
	 * when certain conditions are met.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'w3tc_browsercache_rules_section_extensions', array( $this, 'w3tc_browsercache_rules_section_extensions' ), 10, 3 );
		add_filter( 'w3tc_browsercache_rules_section', array( $this, 'w3tc_browsercache_rules_section' ), 10, 3 );
	}

	/**
	 * Handles environment adjustments when a request is made from the WordPress admin area.
	 *
	 * This method checks the configuration and forces all checks if necessary, then updates the rules
	 * based on the CDN configuration. If there are any exceptions during this process, they are
	 * collected and thrown at the end.
	 *
	 * @param Config $config           The configuration object containing the settings to be checked.
	 * @param bool   $force_all_checks Whether to force all checks, bypassing any cached results.
	 *
	 * @return void
	 *
	 * @throws Util_Environment_Exception Environment exception.
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		if ( $config->get_boolean( 'config.check' ) || $force_all_checks ) {
			if ( $config->get_boolean( 'cdn.enabled' ) ) {
				$this->rules_add( $config, $exs );
			} else {
				$this->rules_remove( $exs );
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Handles CDN-related changes triggered by an event.
	 *
	 * This method processes the CDN settings based on the given event. It may unschedule or schedule cron jobs
	 * related to queue processing and file uploads based on configuration changes.
	 *
	 * @param Config      $config     The configuration object containing the CDN settings.
	 * @param string      $event      The event that triggered the action.
	 * @param Config|null $old_config The old configuration values before the update.
	 *
	 * @return void
	 *
	 * @throws Util_Environment_Exception Environment exception.
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
		if ( $config->get_boolean( 'cdn.enabled' ) && ! Cdn_Util::is_engine_mirror( $config->get_string( 'cdn.engine' ) ) ) {
			if ( null !== $old_config && $config->get_integer( 'cdn.queue.interval' ) !== $old_config->get_integer( 'cdn.queue.interval' ) ) {
				$this->unschedule_queue_process();
			}

			if ( ! wp_next_scheduled( 'w3_cdn_cron_queue_process' ) ) {
				wp_schedule_event( time(), 'w3_cdn_cron_queue_process', 'w3_cdn_cron_queue_process' );
			}
		} else {
			$this->unschedule_queue_process();
		}

		if (
			$config->get_boolean( 'cdn.enabled' ) &&
			$config->get_boolean( 'cdn.autoupload.enabled' ) &&
			! Cdn_Util::is_engine_mirror( $config->get_string( 'cdn.engine' ) )
		) {
			if ( null !== $old_config && $config->get_integer( 'cdn.autoupload.interval' ) !== $old_config->get_integer( 'cdn.autoupload.interval' ) ) {
				$this->unschedule_upload();
			}

			if ( ! wp_next_scheduled( 'w3_cdn_cron_upload' ) ) {
				wp_schedule_event( time(), 'w3_cdn_cron_upload', 'w3_cdn_cron_upload' );
			}
		} else {
			$this->unschedule_upload();
		}

		$exs = new Util_Environment_Exceptions();

		if ( $config->get_boolean( 'cdn.enabled' ) ) {
			try {
				$this->handle_tables(
					'activate' === $event, // drop state on activation.
					true
				);
			} catch ( \Exception $ex ) {
				$exs->push( $ex );
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Handles necessary changes after the deactivation of the CDN.
	 *
	 * This method ensures that CDN-related rules and database tables are cleaned up and removed
	 * when the CDN module is deactivated.
	 *
	 * @return void
	 *
	 * @throws Util_Environment_Exception Environment exception.
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->rules_remove( $exs );
		$this->handle_tables( true, false );

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Retrieves the rules required for the CDN based on the configuration.
	 *
	 * This method generates and returns the necessary CDN rewrite rules, including those for FTP
	 * and browser cache, based on the current configuration.
	 *
	 * @param Config $config The configuration object containing the CDN settings.
	 *
	 * @return array|null The CDN rewrite rules or null if the CDN is not enabled.
	 */
	public function get_required_rules( $config ) {
		if ( ! $config->get_boolean( 'cdn.enabled' ) ) {
			return null;
		}

		$rewrite_rules = array();
		$rules         = $this->rules_generate( $config );

		if ( strlen( $rules ) > 0 ) {
			if ( 'ftp' === $config->get_string( 'cdn.engine' ) ) {
				$common          = Dispatcher::component( 'Cdn_Core' );
				$domain          = $common->get_cdn()->get_domain();
				$cdn_rules_path  = sprintf( 'ftp://%s/%s', $domain, Util_Rule::get_cdn_rules_path() );
				$rewrite_rules[] = array(
					'filename' => $cdn_rules_path,
					'content'  => $rules,
				);
			}

			$path            = Util_Rule::get_browsercache_rules_cache_path();
			$rewrite_rules[] = array(
				'filename' => $path,
				'content'  => $rules,
			);
		}
		return $rewrite_rules;
	}

	/**
	 * Retrieves instructions for configuring the CDN based on the current settings.
	 *
	 * This method returns the instructions for database setup when the CDN module is enabled.
	 *
	 * @param Config $config The configuration object containing the CDN settings.
	 *
	 * @return array|null The instructions for configuring the CDN, or null if CDN is not enabled.
	 */
	public function get_instructions( $config ) {
		if ( ! $config->get_boolean( 'cdn.enabled' ) ) {
			return null;
		}

		$instructions   = array();
		$instructions[] = array(
			'title'   => __( 'CDN module: Required Database SQL', 'w3-total-cache' ),
			'content' => $this->generate_table_sql(),
			'area'    => 'database',
		);

		return $instructions;
	}

	/**
	 * Generates CDN rules specific to FTP configuration.
	 *
	 * This method generates CDN rules specifically for FTP configurations, adding additional
	 * logic if required based on the configuration.
	 *
	 * @param Config $config The configuration object containing the CDN settings.
	 *
	 * @return string The generated FTP-specific CDN rules.
	 */
	public function rules_generate_for_ftp( $config ) {
		return $this->rules_generate( $config, true );
	}

	/**
	 * Handles the creation and removal of necessary database tables for the CDN.
	 *
	 * This method drops existing tables and creates new ones as required for storing CDN queue
	 * and path map data. The method also handles charset and collation settings for the tables.
	 *
	 * @param bool $drop Whether to drop the existing tables.
	 * @param bool $create Whether to create the tables if necessary.
	 *
	 * @return void
	 *
	 * @throws Util_Environment_Exception Environment exception.
	 */
	private function handle_tables( $drop, $create ) {
		global $wpdb;

		$tablename_queue = $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE;
		$tablename_map   = $wpdb->base_prefix . W3TC_CDN_TABLE_PATHMAP;

		if ( $drop ) {
			$sql = "DROP TABLE IF EXISTS `$tablename_queue`;";
			$wpdb->query( $sql );
			$sql = "DROP TABLE IF EXISTS `$tablename_map`;";
			$wpdb->query( $sql );
		}

		if ( ! $create ) {
			return;
		}

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		$sql = "CREATE TABLE IF NOT EXISTS `$tablename_queue` (
			`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`local_path` varchar(500) NOT NULL DEFAULT '',
			`remote_path` varchar(500) NOT NULL DEFAULT '',
			`command` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '1 - Upload, 2 - Delete, 3 - Purge',
			`last_error` varchar(150) NOT NULL DEFAULT '',
			`date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (`id`),
			KEY `date` (`date`)
			) $charset_collate;";

		$wpdb->query( $sql );
		if ( ! $wpdb->result ) {
			throw new Util_Environment_Exception(
				esc_html(
					sprintf(
						// Translators: 1 Tablename for queue.
						__( 'Can\'t create table %1$s.', 'w3-total-cache' ),
						$tablename_queue
					)
				)
			);
		}

		$sql = "CREATE TABLE IF NOT EXISTS `$tablename_map` (
			-- Relative file path.
			-- For reference, not actually used for finding files.
			path TEXT NOT NULL,
			-- MD5 hash of remote path, used for finding files.
			path_hash VARCHAR(32) CHARACTER SET ascii NOT NULL,
			type tinyint(1) NOT NULL DEFAULT '0',
			-- Google Drive: document identifier
			remote_id VARCHAR(200) CHARACTER SET ascii,
			PRIMARY KEY (path_hash),
			KEY `remote_id` (`remote_id`)
			) $charset_collate";

		$wpdb->query( $sql );
		if ( ! $wpdb->result ) {
			throw new Util_Environment_Exception(
				esc_html(
					sprintf(
						// Translators: 1 Tablename for map.
						__( 'Can\'t create table %1$s.', 'w3-total-cache' ),
						$tablename_map
					)
				)
			);
		}
	}

	/**
	 * Generates the SQL query to create the CDN queue table.
	 *
	 * This method constructs the SQL query needed to create the CDN queue table in the database. It includes the table's
	 * columns and their types, as well as any necessary character set and collation settings. The query ensures that the
	 * table is dropped if it exists and creates a new one with the appropriate schema.
	 *
	 * @return string The SQL query to create the CDN queue table.
	 */
	private function generate_table_sql() {
		global $wpdb;
		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		$sql  = sprintf( 'DROP TABLE IF EXISTS `%s%s`;', $wpdb->base_prefix, W3TC_CDN_TABLE_QUEUE );
		$sql .= "\n" . sprintf(
			"CREATE TABLE IF NOT EXISTS `%s%s` (
				`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`local_path` varchar(500) NOT NULL DEFAULT '',
				`remote_path` varchar(500) NOT NULL DEFAULT '',
				`command` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '1 - Upload, 2 - Delete, 3 - Purge',
				`last_error` varchar(150) NOT NULL DEFAULT '',
				`date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (`id`),
				KEY `date` (`date`)
				) $charset_collate;",
			$wpdb->base_prefix,
			W3TC_CDN_TABLE_QUEUE
		);

		return $sql;
	}

	/**
	 * Unschedules the cron job for processing the CDN queue.
	 *
	 * This method checks if the cron job for processing the CDN queue is scheduled. If it is, it clears the scheduled hook
	 * to stop the cron job from running in the future.
	 *
	 * @return void
	 */
	private function unschedule_queue_process() {
		if ( wp_next_scheduled( 'w3_cdn_cron_queue_process' ) ) {
			wp_clear_scheduled_hook( 'w3_cdn_cron_queue_process' );
		}
	}

	/**
	 * Unschedules the cron job for uploading to the CDN.
	 *
	 * This method checks if the cron job for uploading files to the CDN is scheduled. If it is, it clears the scheduled hook
	 * to stop the cron job from running in the future.
	 *
	 * @return void
	 */
	private function unschedule_upload() {
		if ( wp_next_scheduled( 'w3_cdn_cron_upload' ) ) {
			wp_clear_scheduled_hook( 'w3_cdn_cron_upload' );
		}
	}

	/**
	 * Adds CDN-related rules to the configuration.
	 *
	 * This method adds CDN-related rules to the specified configuration. It leverages the `Util_Rule` class to insert these
	 * rules into the appropriate cache file. The rules are wrapped with markers to ensure they are added in the correct position.
	 *
	 * @param object $config The configuration object containing CDN settings.
	 * @param array  $exs    The existing rules to which the new rules will be added.
	 *
	 * @return void
	 */
	private function rules_add( $config, $exs ) {
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			$this->rules_generate( $config ),
			W3TC_MARKER_BEGIN_CDN,
			W3TC_MARKER_END_CDN,
			array(
				W3TC_MARKER_BEGIN_MINIFY_CORE        => 0,
				W3TC_MARKER_BEGIN_PGCACHE_CORE       => 0,
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE => 0,
				W3TC_MARKER_BEGIN_WORDPRESS          => 0,
				W3TC_MARKER_END_PGCACHE_CACHE        => strlen( W3TC_MARKER_END_PGCACHE_CACHE ) + 1,
				W3TC_MARKER_END_MINIFY_CACHE         => strlen( W3TC_MARKER_END_MINIFY_CACHE ) + 1,
			)
		);
	}

	/**
	 * Removes CDN-related rules from the configuration.
	 *
	 * This method removes any CDN-related rules from the specified configuration. It uses the `Util_Rule` class to remove
	 * these rules, which are wrapped with markers to identify their location in the configuration file.
	 *
	 * @param array $exs The existing rules from which the CDN-related rules will be removed.
	 *
	 * @return void
	 */
	private function rules_remove( $exs ) {
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			W3TC_MARKER_BEGIN_CDN,
			W3TC_MARKER_END_CDN
		);
	}


	/**
	 * Generates the appropriate rules based on the environment.
	 *
	 * This method generates rules for the CDN based on the server environment. It detects whether the server is using Nginx,
	 * LiteSpeed, or Apache and calls the corresponding method to generate the rules for that environment.
	 *
	 * @param object $config   The configuration object containing CDN settings.
	 * @param bool   $cdnftp   Whether FTP settings should be included in the rules.
	 *
	 * @return string The generated CDN rules for the environment.
	 */
	private function rules_generate( $config, $cdnftp = false ) {
		if ( Util_Environment::is_nginx() ) {
			$o = new Cdn_Environment_Nginx( $config );
			return $o->generate( $cdnftp );
		} elseif ( Util_Environment::is_litespeed() ) {
			$o = new Cdn_Environment_LiteSpeed( $config );
			return $o->generate( $cdnftp );
		} else {
			return $this->rules_generate_apache( $config, $cdnftp );
		}
	}

	/**
	 * Generates Apache-specific CDN rules.
	 *
	 * This method generates the CDN rules specifically for Apache-based environments. It adds rules for canonical URLs, CORS,
	 * and other CDN-specific headers.
	 *
	 * @param object $config   The configuration object containing CDN settings.
	 * @param bool   $cdnftp   Whether FTP settings should be included in the rules.
	 *
	 * @return string The generated Apache-specific CDN rules.
	 */
	private function rules_generate_apache( $config, $cdnftp ) {
		$rules = '';
		if ( $config->get_boolean( 'cdn.canonical_header' ) ) {
			$rules .= $this->canonical( $cdnftp, $config->get_boolean( 'cdn.cors_header' ) );
		}

		if ( $config->get_boolean( 'cdn.cors_header' ) ) {
			$rules .= $this->allow_origin( $cdnftp );
		}

		if ( strlen( $rules ) > 0 ) {
			$rules = W3TC_MARKER_BEGIN_CDN . "\n" . $rules . W3TC_MARKER_END_CDN . "\n";
		}

		return $rules;
	}

	/**
	 * Generates the canonical URL rule.
	 *
	 * This method generates the Apache or Nginx rewrite rules to enforce canonical URLs for the CDN. It ensures that requests
	 * are properly redirected to the correct HTTP or HTTPS version of the URL.
	 *
	 * @param bool $cdnftp      Whether FTP settings should be included in the rule.
	 * @param bool $cors_header Whether CORS headers should be included in the rule.
	 *
	 * @return string The generated canonical URL rule.
	 */
	private function canonical( $cdnftp = false, $cors_header = true ) {
		$mime_types = include W3TC_INC_DIR . '/mime/other.php';
		$extensions = array_keys( $mime_types );

		$extensions_lowercase = array_map( 'strtolower', $extensions );
		$extensions_uppercase = array_map( 'strtoupper', $extensions );

		$host = $cdnftp ? Util_Environment::home_url_host() : '%{HTTP_HOST}';

		$rules  = '<FilesMatch "\\.(' . implode( '|', array_merge( $extensions_lowercase, $extensions_uppercase ) ) . ")$\">\n";
		$rules .= "   <IfModule mod_rewrite.c>\n";
		$rules .= "      RewriteEngine On\n";
		$rules .= "      RewriteCond %{HTTPS} !=on\n";
		$rules .= "      RewriteRule .* - [E=CANONICAL:http://$host%{REQUEST_URI},NE]\n";
		$rules .= "      RewriteCond %{HTTPS} =on\n";
		$rules .= "      RewriteRule .* - [E=CANONICAL:https://$host%{REQUEST_URI},NE]\n";
		$rules .= "   </IfModule>\n";
		$rules .= "   <IfModule mod_headers.c>\n";
		$rules .= '      Header set Link "<%{CANONICAL}e>; rel=\"canonical\""' . "\n";
		$rules .= "   </IfModule>\n";

		$rules .= "</FilesMatch>\n";

		return $rules;
	}

	/**
	 * Generates the CORS allow origin rule.
	 *
	 * This method generates the Apache or Nginx header rule for allowing cross-origin resource sharing (CORS). It sets the
	 * `Access-Control-Allow-Origin` header to allow any origin, which is useful for CDN assets.
	 *
	 * @param bool $cdnftp Whether FTP settings should be included in the rule.
	 *
	 * @return string The generated CORS allow origin rule.
	 */
	private function allow_origin( $cdnftp = false ) {
		$r  = "<IfModule mod_headers.c>\n";
		$r .= "    Header set Access-Control-Allow-Origin \"*\"\n";
		$r .= "</IfModule>\n";

		if ( ! $cdnftp ) {
			return $r;
		} else {
			return "<FilesMatch \"\.(ttf|ttc|otf|eot|woff|woff2|font.css)$\">\n" . $r . "</FilesMatch>\n";
		}
	}

	/**
	 * Adds browser cache extension rules for CDN in the configuration.
	 *
	 * This method adds browser caching rules for CDN extensions to the specified configuration. It detects the server type
	 * (Nginx or LiteSpeed) and generates the appropriate extension rules based on the server.
	 *
	 * @param array  $extensions The existing list of extensions to be cached.
	 * @param object $config     The configuration object containing CDN settings.
	 * @param string $section    The section of the configuration to add the rules to.
	 *
	 * @return array The updated list of extensions with CDN-related rules.
	 */
	public function w3tc_browsercache_rules_section_extensions( $extensions, $config, $section ) {
		if ( Util_Environment::is_nginx() ) {
			$o          = new Cdn_Environment_Nginx( $config );
			$extensions = $o->w3tc_browsercache_rules_section_extensions( $extensions, $section );
		} elseif ( Util_Environment::is_litespeed() ) {
			$o          = new Cdn_Environment_LiteSpeed( $config );
			$extensions = $o->w3tc_browsercache_rules_section_extensions( $extensions, $section );
		}

		return $extensions;
	}

	/**
	 * Adds browser cache section rules for CDN in the configuration.
	 *
	 * This method adds browser caching rules for the CDN to the specified section in the configuration. It works specifically
	 * for LiteSpeed servers, ensuring that the correct rules are added for CDN caching.
	 *
	 * @param string $section_rules The existing section rules to be updated.
	 * @param object $config        The configuration object containing CDN settings.
	 * @param string $section       The section of the configuration to add the rules to.
	 *
	 * @return string The updated section rules with CDN-related browser cache rules.
	 */
	public function w3tc_browsercache_rules_section( $section_rules, $config, $section ) {
		if ( Util_Environment::is_litespeed() ) {
			$o             = new Cdn_Environment_LiteSpeed( $config );
			$section_rules = $o->w3tc_browsercache_rules_section( $section_rules, $section );
		}
		return $section_rules;
	}
}
