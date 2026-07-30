<?php
/**
 * File: Cdn_Core_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Core_Admin
 *
 * W3 Total Cache CDN Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 */
class Cdn_Core_Admin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Constructor for the Cdn_Core_Admin class.
	 *
	 * Initializes the configuration by dispatching the configuration object.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Purges the CDN files associated with an attachment.
	 *
	 * This method retrieves the files associated with the given attachment ID and purges them from the CDN.
	 *
	 * @param int   $attachment_id The ID of the attachment to purge.
	 * @param array $results      Reference to an array that will store the purge results.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_attachment( $attachment_id, &$results ) {
		$common = Dispatcher::component( 'Cdn_Core' );
		$files  = $common->get_attachment_files( $attachment_id );

		return $common->purge( $files, $results );
	}

	/**
	 * Updates the queue entry with the latest error message.
	 *
	 * This method updates the `last_error` field and the `date` for a specific queue item.
	 *
	 * @param int    $queue_id    The ID of the queue item to update.
	 * @param string $last_error  The error message to store.
	 *
	 * @return int|false The number of affected rows, or false on failure.
	 */
	public function queue_update( $queue_id, $last_error ) {
		global $wpdb;

		$sql = sprintf( 'UPDATE %s SET last_error = "%s", date = NOW() WHERE id = %d', $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE, esc_sql( $last_error ), $queue_id );

		return $wpdb->query( $sql );
	}

	/**
	 * Deletes a queue item from the database.
	 *
	 * This method removes a specific queue entry identified by its ID.
	 *
	 * @param int $queue_id The ID of the queue item to delete.
	 *
	 * @return int|false The number of affected rows, or false on failure.
	 */
	public function queue_delete( $queue_id ) {
		global $wpdb;

		$sql = sprintf( 'DELETE FROM %s WHERE id = %d', $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE, $queue_id );

		return $wpdb->query( $sql );
	}

	/**
	 * Empties the queue based on a command.
	 *
	 * This method deletes all queue entries that match the specified command.
	 *
	 * @param int $command The command identifier to filter the queue entries.
	 *
	 * @return int|false The number of affected rows, or false on failure.
	 */
	public function queue_empty( $command ) {
		global $wpdb;

		$sql = sprintf( 'DELETE FROM %s WHERE command = %d', $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE, $command );

		return $wpdb->query( $sql );
	}

	/**
	 * Retrieves a list of queue items from the database.
	 *
	 * This method fetches queue entries, optionally limiting the number of results returned.
	 *
	 * @param int|null $w3tc_limit The maximum number of queue entries to retrieve, or null for no limit.
	 *
	 * @return array An associative array of queue items, grouped by command.
	 */
	public function queue_get( $w3tc_limit = null ) {
		global $wpdb;

		$sql = sprintf( 'SELECT * FROM %s%s ORDER BY date', $wpdb->base_prefix, W3TC_CDN_TABLE_QUEUE );

		if ( $w3tc_limit ) {
			$sql .= sprintf( ' LIMIT %d', $w3tc_limit );
		}

		$results = $wpdb->get_results( $sql );
		$queue   = array();

		if ( $results ) {
			foreach ( (array) $results as $w3tc_result ) {
				$queue[ $w3tc_result->command ][] = $w3tc_result;
			}
		}

		return $queue;
	}

	/**
	 * Processes items in the queue and performs the respective CDN actions.
	 *
	 * This method processes the queued commands (upload, delete, or purge) and interacts with the CDN to perform
	 * the necessary operations on the files. The results are handled accordingly, updating the queue.
	 *
	 * @param int $w3tc_limit The maximum number of items to process from the queue.
	 *
	 * @return int The number of items successfully processed.
	 */
	public function queue_process( $w3tc_limit ) {
		$items = 0;

		$commands      = $this->queue_get( $w3tc_limit );
		$force_rewrite = $this->_config->get_boolean( 'cdn.force.rewrite' );

		if ( count( $commands ) ) {
			$common = Dispatcher::component( 'Cdn_Core' );
			$cdn    = $common->get_cdn();

			foreach ( $commands as $command => $queue ) {
				$files   = array();
				$results = array();
				$map     = array();

				foreach ( $queue as $w3tc_result ) {
					$files[]                         = $common->build_file_descriptor( $w3tc_result->local_path, $w3tc_result->remote_path );
					$map[ $w3tc_result->local_path ] = $w3tc_result->id;
					++$items;
				}

				switch ( $command ) {
					case W3TC_CDN_COMMAND_UPLOAD:
						foreach ( $files as $w3tc_file ) {
							$local_file_name  = $w3tc_file['local_path'];
							$remote_file_name = $w3tc_file['remote_path'];
							if ( ! file_exists( $local_file_name ) ) {
								Dispatcher::create_file_for_cdn( $local_file_name );
							}
						}

						$cdn->upload( $files, $results, $force_rewrite );

						foreach ( $results as $w3tc_result ) {
							if ( W3TC_CDN_RESULT_OK === $w3tc_result['result'] ) {
								Dispatcher::on_cdn_file_upload( $w3tc_result['local_path'] );
							}
						}
						break;

					case W3TC_CDN_COMMAND_DELETE:
						$cdn->delete( $files, $results );
						break;

					case W3TC_CDN_COMMAND_PURGE:
						$cdn->purge( $files, $results );
						break;
				}

				foreach ( $results as $w3tc_result ) {
					if ( W3TC_CDN_RESULT_OK === $w3tc_result['result'] ) {
						$this->queue_delete( $map[ $w3tc_result['local_path'] ] );
					} else {
						$this->queue_update( $map[ $w3tc_result['local_path'] ], $w3tc_result['error'] );
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Exports library attachments to a CDN.
	 *
	 * This method retrieves a list of attachment files and their metadata from the WordPress database, processes
	 * each attachment to generate a file descriptor, and uploads the files to a Content Delivery Network (CDN).
	 * It also handles pagination via the limit and offset parameters and updates the provided count and total values
	 * with the number of results and the total attachment count respectively.
	 *
	 * @param int   $w3tc_limit        The number of attachments to retrieve. If set to 0, no limit is applied.
	 * @param int   $w3tc_offset       The offset for retrieving attachments. Defaults to 0.
	 * @param int   $w3tc_count        The variable to store the number of attachments retrieved.
	 * @param int   $total        The variable to store the total number of attachments.
	 * @param array $results      The variable to store the results of the upload process.
	 * @param int   $timeout_time The timeout duration for the upload request in seconds. Defaults to 0 (no timeout).
	 *
	 * @return void
	 */
	public function export_library( $w3tc_limit, $w3tc_offset, &$w3tc_count, &$total, &$results, $timeout_time = 0 ) {
		global $wpdb;

		$w3tc_count = 0;
		$total      = 0;

		$upload_info = Util_Http::upload_info();

		if ( $upload_info ) {
			$sql = sprintf(
				'SELECT
					pm.meta_value AS file,
					pm2.meta_value AS metadata
					FROM
					%sposts AS p
					LEFT JOIN
					%spostmeta AS pm ON p.ID = pm.post_ID AND pm.meta_key = "_wp_attached_file"
					LEFT JOIN
					%spostmeta AS pm2 ON p.ID = pm2.post_ID AND pm2.meta_key = "_wp_attachment_metadata"
					WHERE
					p.post_type = "attachment"  AND (pm.meta_value IS NOT NULL OR pm2.meta_value IS NOT NULL)
					GROUP BY
					p.ID
					ORDER BY
					p.ID',
				$wpdb->prefix,
				$wpdb->prefix,
				$wpdb->prefix
			);

			if ( $w3tc_limit ) {
				$sql .= sprintf( ' LIMIT %d', $w3tc_limit );

				if ( $w3tc_offset ) {
					$sql .= sprintf( ' OFFSET %d', $w3tc_offset );
				}
			}

			$posts = $wpdb->get_results( $sql );

			if ( $posts ) {
				$w3tc_count = count( $posts );
				$total      = $this->get_attachments_count();
				$files      = array();

				$common = Dispatcher::component( 'Cdn_Core' );

				foreach ( $posts as $post ) {
					$post_files = array();

					if ( $post->file ) {
						$w3tc_file = $common->normalize_attachment_file( $post->file );

						$local_file  = $upload_info['basedir'] . '/' . $w3tc_file;
						$remote_file = ltrim( $upload_info['baseurlpath'] . $w3tc_file, '/' );

						$post_files[] = $common->build_file_descriptor( $local_file, $remote_file );
					}

					if ( $post->metadata ) {
						$metadata = @unserialize( $post->metadata, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

						/**
						 * `allowed_classes => false` returns `__PHP_Incomplete_Class`
						 * for crafted object payloads, but `Cdn_Core::get_metadata_files()`
						 * dereferences `$metadata['file']` / `['sizes']` directly. Coerce
						 * non-arrays (corrupted postmeta or crafted payload) to an empty
						 * array so we just emit zero files instead of fataling.
						 */
						if ( ! is_array( $metadata ) ) {
							$metadata = array();
						}

						$post_files = array_merge( $post_files, $common->get_metadata_files( $metadata ) );
					}

					$post_files = apply_filters( 'w3tc_cdn_add_attachment', $post_files );

					$files = array_merge( $files, $post_files );
				}

				$common = Dispatcher::component( 'Cdn_Core' );
				$common->upload( $files, false, $results, $timeout_time );
			}
		}
	}

	/**
	 * Import external files into the media library.
	 *
	 * This method processes posts with links or images, checking if the external files exist in the media library.
	 * If the files do not exist, it downloads or copies them to the server, inserts them as attachments, and updates
	 * the post content to reference the new media URLs. Logs the results of the import process.
	 *
	 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment
	 *
	 * @param int   $w3tc_limit   The number of posts to process.
	 * @param int   $w3tc_offset  The offset for the posts to process.
	 * @param int   $w3tc_count   The number of posts processed.
	 * @param int   $total   The total number of posts to import.
	 * @param array $results An array to hold the results of the import process, including file paths and errors.
	 *
	 * @return void
	 */
	public function import_library( $w3tc_limit, $w3tc_offset, &$w3tc_count, &$total, &$results ) {
		global $wpdb;

		$w3tc_count = 0;
		$total      = 0;
		$results    = array();

		$upload_info                   = Util_Http::upload_info();
		$uploads_use_yearmonth_folders = get_option( 'uploads_use_yearmonth_folders' );
		$document_root                 = Util_Environment::document_root();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_import' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		if ( $upload_info ) {
			/**
			 * Search for posts with links or images
			 */
			$sql = sprintf(
				'SELECT
					ID,
					post_content,
					post_date
					FROM
					%sposts
					WHERE
					post_status = "publish"
					AND (post_type = "post" OR post_type = "page")
					AND (post_content LIKE "%%src=%%"
					OR post_content LIKE "%%href=%%")',
				$wpdb->prefix
			);

			if ( $w3tc_limit ) {
				$sql .= sprintf( ' LIMIT %d', $w3tc_limit );

				if ( $w3tc_offset ) {
					$sql .= sprintf( ' OFFSET %d', $w3tc_offset );
				}
			}

			$posts = $wpdb->get_results( $sql );

			if ( $posts ) {
				$w3tc_count        = count( $posts );
				$total             = $this->get_import_posts_count();
				$regexp            = '~(' . $this->get_regexp_by_mask( $this->_config->get_string( 'cdn.import.files' ) ) . ')$~';
				$w3tc_config_state = Dispatcher::config_state();
				$import_external   = $w3tc_config_state->get_boolean( 'cdn.import.external' );

				foreach ( $posts as $post ) {
					$matches      = null;
					$replaced     = array();
					$attachments  = array();
					$post_content = $post->post_content;

					/**
					 * Search for all link and image sources
					 */
					if ( preg_match_all( '~(href|src)=[\'"]?([^\'"<>\s]+)[\'"]?~', $post_content, $matches, PREG_SET_ORDER ) ) {
						foreach ( $matches as $w3tc_match ) {
							list( $search, $attribute, $origin ) = $w3tc_match;

							/**
							 * Check if $search is already replaced
							 */
							if ( isset( $replaced[ $search ] ) ) {
								continue;
							}

							$error       = '';
							$w3tc_result = false;

							$src = Util_Environment::normalize_file_minify( $origin );
							$dst = '';

							/**
							 * Check if file exists in the library
							 */
							if ( stristr( $origin, $upload_info['baseurl'] ) === false ) {
								/**
								 * Check file extension
								 */
								$check_src = $src;

								if ( Util_Environment::is_url( $check_src ) ) {
									$qpos = strpos( $check_src, '?' );

									if ( false !== $qpos ) {
										$check_src = substr( $check_src, 0, $qpos );
									}
								}

								if ( preg_match( $regexp, $check_src ) ) {
									/**
									 * Check for already uploaded attachment
									 */
									if ( isset( $attachments[ $src ] ) ) {
										list( $dst, $dst_url ) = $attachments[ $src ];
										$w3tc_result           = true;
									} else {
										if ( $uploads_use_yearmonth_folders ) {
											$upload_subdir = gmdate( 'Y/m', strtotime( $post->post_date ) );
											$upload_dir    = sprintf( '%s/%s', $upload_info['basedir'], $upload_subdir );
											$upload_url    = sprintf( '%s/%s', $upload_info['baseurl'], $upload_subdir );
										} else {
											$upload_subdir = '';
											$upload_dir    = $upload_info['basedir'];
											$upload_url    = $upload_info['baseurl'];
										}

										$src_filename  = pathinfo( $src, PATHINFO_FILENAME );
										$src_extension = pathinfo( $src, PATHINFO_EXTENSION );

										/**
										 * Get available filename
										 */
										for ( $w3tc_i = 0; ; $w3tc_i++ ) {
											$dst = sprintf( '%s/%s%s%s', $upload_dir, $src_filename, ( $w3tc_i ? $w3tc_i : '' ), ( $src_extension ? '.' . $src_extension : '' ) );

											if ( ! file_exists( $dst ) ) {
												break;
											}
										}

										$dst_basename = basename( $dst );
										$dst_url      = sprintf( '%s/%s', $upload_url, $dst_basename );
										$dst_path     = ltrim( str_replace( $document_root, '', Util_Environment::normalize_path( $dst ) ), '/' );

										if ( $upload_subdir ) {
											Util_File::mkdir( $upload_subdir, 0777, $upload_info['basedir'] );
										}

										$download_result = false;

										/**
										 * Check if file is remote URL
										 */
										if ( Util_Environment::is_url( $src ) ) {
											/**
											 * Download file
											 */
											if ( $import_external ) {
												$download_result = Util_Http::download( $src, $dst );

												if ( ! $download_result ) {
													$error = 'Unable to download file';
												}
											} else {
												$error = 'External file import is disabled';
											}
										} else {
											/**
											 * Otherwise copy file from local path
											 */
											$src_path = $document_root . '/' . urldecode( $src );

											if ( file_exists( $src_path ) ) {
												$download_result = @copy( $src_path, $dst );

												if ( ! $download_result ) {
													$error = 'Unable to copy file';
												}
											} else {
												$error = 'Source file doesn\'t exists';
											}
										}

										/**
										 * Check if download or copy was successful
										 */
										if ( $download_result ) {
											$title     = $dst_basename;
											$guid      = ltrim( $upload_info['baseurlpath'] . $title, ',' );
											$mime_type = Util_Mime::get_mime_type( $dst );

											$GLOBALS['wp_rewrite'] = new \WP_Rewrite();

											/**
											 * Insert attachment
											 */
											$id = wp_insert_attachment(
												array(
													'post_mime_type' => $mime_type,
													'guid'           => $guid,
													'post_title'     => $title,
													'post_content'   => '',
													'post_parent'    => $post->ID,
												),
												$dst
											);

											if ( ! is_wp_error( $id ) ) {
												/**
												 * Generate attachment metadata and upload to CDN
												 */
												require_once ABSPATH . 'wp-admin/includes/image.php';
												wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dst ) );

												$attachments[ $src ] = array(
													$dst,
													$dst_url,
												);

												$w3tc_result = true;
											} else {
												$error = 'Unable to insert attachment';
											}
										}
									}

									/**
									 * If attachment was successfully created then replace links
									 */
									if ( $w3tc_result ) {
										$replace = sprintf( '%s="%s"', $attribute, $dst_url );

										// replace $search with $replace.
										$post_content = str_replace( $search, $replace, $post_content );

										$replaced[ $search ] = $replace;
										$error               = 'OK';
									}
								} else {
									$error = 'File type rejected';
								}
							} else {
								$error = 'File already exists in the media library';
							}

							/**
							 * Add new entry to the log file
							 */

							$results[] = array(
								'src'    => $src,
								'dst'    => $dst_path,
								'result' => $w3tc_result,
								'error'  => $error,
							);
						}
					}

					/**
					 * If post content was chenged then update DB
					 */
					if ( $post_content !== $post->post_content ) {
						wp_update_post(
							array(
								'ID'           => $post->ID,
								'post_content' => $post_content,
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Renames domain URLs in post content.
	 *
	 * This method searches for URLs in published posts and pages that match a given set of domain names, and renames
	 * them with the new base URL. The renaming process involves identifying `src` and `href` attributes within post
	 * content and replacing any matched URLs that reference the old domain with the new base URL, updating the post
	 * content accordingly.
	 *
	 * @param array $names   An array of domain names to be renamed.
	 * @param int   $w3tc_limit   The maximum number of posts to process at once (optional).
	 * @param int   $w3tc_offset  The offset for pagination (optional).
	 * @param int   $w3tc_count   The number of posts processed.
	 * @param int   $total   The total number of posts that require renaming.
	 * @param array $results An array of results showing old and new URLs with status.
	 *
	 * @return void
	 */
	public function rename_domain( $names, $w3tc_limit, $w3tc_offset, &$w3tc_count, &$total, &$results ) {
		global $wpdb;

		@set_time_limit( $this->_config->get_integer( 'timelimit.domain_rename' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$w3tc_count = 0;
		$total      = 0;
		$results    = array();

		$upload_info = Util_Http::upload_info();

		foreach ( $names as $w3tc_index => $w3tc_name ) {
			$names[ $w3tc_index ] = str_ireplace( 'www.', '', $w3tc_name );
		}

		if ( $upload_info ) {
			$sql = sprintf(
				'SELECT
					ID,
					post_content,
					post_date
					FROM
					%sposts
					WHERE
					post_status = "publish"
					AND (post_type = "post" OR post_type = "page")
					AND (post_content LIKE "%%src=%%"
					OR post_content LIKE "%%href=%%")',
				$wpdb->prefix
			);

			if ( $w3tc_limit ) {
				$sql .= sprintf( ' LIMIT %d', $w3tc_limit );

				if ( $w3tc_offset ) {
					$sql .= sprintf( ' OFFSET %d', $w3tc_offset );
				}
			}

			$posts = $wpdb->get_results( $sql );

			if ( $posts ) {
				$w3tc_count   = count( $posts );
				$total        = $this->get_rename_posts_count();
				$names_quoted = array_map( array( '\W3TC\Util_Environment', 'preg_quote' ), $names );

				foreach ( $posts as $post ) {
					$matches      = null;
					$post_content = $post->post_content;
					$regexp       = '~(href|src)=[\'"]?(https?://(www\.)?(' . implode( '|', $names_quoted ) . ')' . Util_Environment::preg_quote( $upload_info['baseurlpath'] ) . '([^\'"<>\s]+))[\'"]~';

					if ( preg_match_all( $regexp, $post_content, $matches, PREG_SET_ORDER ) ) {
						foreach ( $matches as $w3tc_match ) {
							$old_url      = $w3tc_match[2];
							$new_url      = sprintf( '%s/%s', $upload_info['baseurl'], $w3tc_match[5] );
							$post_content = str_replace( $old_url, $new_url, $post_content );

							$results[] = array(
								'old'    => $old_url,
								'new'    => $new_url,
								'result' => true,
								'error'  => 'OK',
							);
						}
					}

					if ( $post_content !== $post->post_content ) {
						wp_update_post(
							array(
								'ID'           => $post->ID,
								'post_content' => $post_content,
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Retrieves the count of attachments in the WordPress database.
	 *
	 * This method queries the WordPress database to count the number of attachments that are stored in the posts table,
	 * ensuring that there is an associated `_wp_attached_file` or `_wp_attachment_metadata` meta key. This is useful for
	 * tracking the number of media files stored in the system.
	 *
	 * @return int The total number of attachments.
	 */
	public function get_attachments_count() {
		global $wpdb;

		$sql = sprintf(
			'SELECT COUNT(DISTINCT p.ID)
				FROM %sposts AS p
				LEFT JOIN %spostmeta AS pm ON p.ID = pm.post_ID
				AND
				pm.meta_key = "_wp_attached_file"
				LEFT JOIN %spostmeta AS pm2 ON p.ID = pm2.post_ID
				AND
				pm2.meta_key = "_wp_attachment_metadata"
				WHERE
				p.post_type = "attachment"
				AND
				(pm.meta_value IS NOT NULL OR pm2.meta_value IS NOT NULL)',
			$wpdb->prefix,
			$wpdb->prefix,
			$wpdb->prefix
		);

		return $wpdb->get_var( $sql );
	}

	/**
	 * Retrieves the count of posts and pages that contain media references (e.g., `src` or `href` attributes).
	 *
	 * This method counts the number of posts and pages in the WordPress database that have `src` or `href` attributes
	 * in their content, which typically indicate the presence of media (e.g., images, links, or other assets).
	 *
	 * @return int The total number of posts and pages with media references.
	 */
	public function get_import_posts_count() {
		global $wpdb;

		$sql = sprintf(
			'SELECT
				COUNT(*)
				FROM
				%sposts
				WHERE
				post_status = "publish"
				AND
				(post_type = "post"
				OR
				post_type = "page")
				AND
				(post_content LIKE "%%src=%%"
				OR
				post_content LIKE "%%href=%%")',
			$wpdb->prefix
		);

		return $wpdb->get_var( $sql );
	}

	/**
	 * Retrieves the count of posts and pages that require domain renaming based on media references.
	 *
	 * This method is essentially a shortcut to `get_import_posts_count()` and returns the number of posts and pages
	 * that need their URLs updated during a domain rename operation.
	 *
	 * @return int The total number of posts and pages to be renamed.
	 */
	public function get_rename_posts_count() {
		return $this->get_import_posts_count();
	}

	/**
	 * Generates a regular expression pattern based on a given mask.
	 *
	 * This method takes a mask (e.g., wildcard pattern) and converts it into a valid regular expression, where `*`
	 * and `?` are replaced by patterns that match any sequence of characters. This allows for more flexible matching
	 * of domain names or other patterns within content.
	 *
	 * @param string $mask The mask to convert into a regular expression.
	 *
	 * @return string The generated regular expression pattern.
	 */
	public function get_regexp_by_mask( $mask ) {
		$mask = trim( $mask );
		$mask = Util_Environment::preg_quote( $mask );

		$mask = str_replace(
			array(
				'\*',
				'\?',
				';',
			),
			array(
				'@ASTERISK@',
				'@QUESTION@',
				'|',
			),
			$mask
		);

		$regexp = str_replace(
			array(
				'@ASTERISK@',
				'@QUESTION@',
			),
			array(
				'[^\\?\\*:\\|"<>]*',
				'[^\\?\\*:\\|"<>]',
			),
			$mask
		);

		return $regexp;
	}

	/**
	 * Adds custom actions to the media row in the WordPress admin interface.
	 *
	 * This method adds a custom action link to the media file row in the WordPress admin, allowing an admin user to
	 * purge the media file from the CDN. The link includes a nonce for security purposes.
	 *
	 * @param array   $actions An array of existing action links for the media.
	 * @param WP_Post $post    The current post object representing the media item.
	 *
	 * @return array The updated array of action links.
	 */
	public function media_row_actions( $actions, $post ) {
		$actions = array_merge(
			$actions,
			array(
				'cdn_purge' => sprintf(
					'<a href="%s">' . __( 'Purge from CDN', 'w3-total-cache' ) . '</a>',
					Util_Nonce::admin_nonce_url(
						sprintf(
							'admin.php?page=w3tc_dashboard&w3tc_cdn_purge_attachment&attachment_id=%d',
							$post->ID
						),
						'w3tc_cdn_purge_attachment'
					)
				),
			)
		);

		return $actions;
	}

	/**
	 * Checks if the CDN is running based on the current configuration.
	 *
	 * This method verifies the CDN engine configuration and ensures that all necessary
	 * settings (like API keys, buckets, domains, etc.) are provided and valid. It checks
	 * for different CDN engines, including FTP, S3, Cloudflare (CF), Azure, and others.
	 * The method returns true if the CDN is correctly configured and operational,
	 * and false otherwise.
	 *
	 * @return bool True if the CDN is running, false otherwise.
	 */
	public function is_running() {
		/**
		 * CDN
		 */
		$running = true;

		/**
		 * Check CDN settings
		 */
		$w3tc_cdn_engine = $this->_config->get_string( 'cdn.engine' );

		switch ( true ) {
			case (
				'ftp' === $w3tc_cdn_engine &&
				! count( $this->_config->get_array( 'cdn.ftp.domain' ) )
			):
				$running = false;
				break;

			case (
				's3' === $w3tc_cdn_engine &&
				(
					'' === $this->_config->get_string( 'cdn.s3.key' ) ||
					'' === $this->_config->get_string( 'cdn.s3.secret' ) ||
					'' === $this->_config->get_string( 'cdn.s3.bucket' )
				)
			):
				$running = false;
				break;

			case (
				'cf' === $w3tc_cdn_engine &&
				(
					'' === $this->_config->get_string( 'cdn.cf.key' ) ||
					'' === $this->_config->get_string( 'cdn.cf.secret' ) ||
					'' === $this->_config->get_string( 'cdn.cf.bucket' ) ||
					(
						'' === $this->_config->get_string( 'cdn.cf.id' ) &&
						! count( $this->_config->get_array( 'cdn.cf.cname' ) )
					)
				)
			):
				$running = false;
				break;

			case (
				'cf2' === $w3tc_cdn_engine &&
				(
					'' === $this->_config->get_string( 'cdn.cf2.key' ) ||
					'' === $this->_config->get_string( 'cdn.cf2.secret' ) ||
					(
						'' === $this->_config->get_string( 'cdn.cf2.id' ) &&
						! count( $this->_config->get_array( 'cdn.cf2.cname' ) )
					)
				)
			):
				$running = false;
				break;

			case (
				'rscf' === $w3tc_cdn_engine &&
				(
					'' === $this->_config->get_string( 'cdn.rscf.user' ) ||
					'' === $this->_config->get_string( 'cdn.rscf.key' ) ||
					'' === $this->_config->get_string( 'cdn.rscf.container' ) ||
					! count( $this->_config->get_array( 'cdn.rscf.cname' ) )
				)
			):
				$running = false;
				break;

			case (
				'azure' === $w3tc_cdn_engine &&
				(
					'' === $this->_config->get_string( 'cdn.azure.user' ) ||
					'' === $this->_config->get_string( 'cdn.azure.key' ) ||
					'' === $this->_config->get_string( 'cdn.azure.container' )
				)
			):
				$running = false;
				break;

			case (
				'azuremi' === $w3tc_cdn_engine &&
				(
					'' === $this->_config->get_string( 'cdn.azuremi.user' ) ||
					'' === $this->_config->get_string( 'cdn.azuremi.clientid' ) ||
					'' === $this->_config->get_string( 'cdn.azuremi.container' )
				)
			):
				$running = false;
				break;

			case (
				'mirror' === $w3tc_cdn_engine &&
				! count( $this->_config->get_array( 'cdn.mirror.domain' ) )
			):
				$running = false;
				break;
		}

		return $running;
	}
}
