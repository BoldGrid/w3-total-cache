<?php
/**
 * File: Extension_ImageService_Cron.php
 *
 * @since 2.2.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extension_ImageService_Cron
 *
 * @since 2.2.0
 */
class Extension_ImageService_Cron {
	/**
	 * Add cron job/event.
	 *
	 * @since 2.2.0
	 * @static
	 */
	public static function add_cron() {
		if ( ! wp_next_scheduled( 'w3tc_imageservice_cron' ) ) {
			wp_schedule_event( time(), 'ten_seconds', 'w3tc_imageservice_cron' );
		}
	}

	/**
	 * Add cron schedule.
	 *
	 * @since 2.2.0
	 * @static
	 *
	 * @param array $schedules Schedules.
	 */
	public static function add_schedule( array $schedules = array() ) {
		$schedules['ten_seconds'] = array(
			'interval' => 10,
			'display'  => esc_html__( 'Every Ten Seconds', 'w3-total-cache' ),
		);
		return $schedules;
	}

	/**
	 * Remove cron job/event.
	 *
	 * @since 2.2.0
	 * @static
	 */
	public static function delete_cron() {
		$timestamp = wp_next_scheduled( 'w3tc_imageservice_cron' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'w3tc_imageservice_cron' );
		}
	}

	/**
	 * Run the cron event.
	 *
	 * @since 2.2.0
	 *
	 * @see Extension_ImageService_Plugin_Admin::get_imageservice_attachments()
	 * @see Extension_ImageService_Plugin::get_api()
	 *
	 * @global $wp_filesystem WP_Filesystem.
	 */
	public static function run() {
		// Get all attachment post IDs with postmeta key "w3tc_imageservice".
		$results = Extension_ImageService_Plugin_Admin::get_imageservice_attachments();

		// If there are matches, then load dependencies before use.
		if ( $results->have_posts() ) {
			require_once __DIR__ . '/Extension_ImageService_Plugin_Admin.php';

			$wp_upload_dir = wp_upload_dir();

			global $wp_filesystem;

			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		foreach ( $results->posts as $post_id ) {
			$postmeta = empty( $post_id ) ? null : get_post_meta( $post_id, 'w3tc_imageservice', true );
			$status   = isset( $postmeta['status'] ) ? $postmeta['status'] : null;

			// Handle new format with multiple jobs (processing_jobs) or old format (processing).
			$processing_jobs = isset( $postmeta['processing_jobs'] ) && is_array( $postmeta['processing_jobs'] ) ?
				$postmeta['processing_jobs'] : array();

			// Backward compatibility: convert old format to new format.
			// Only do this for items still marked as processing to avoid re-checking completed jobs.
			if ( 'processing' === $status && empty( $processing_jobs ) && isset( $postmeta['processing']['job_id'] ) && isset( $postmeta['processing']['signature'] ) ) {
				$processing_jobs = array(
					'webp' => array(
						'job_id'    => $postmeta['processing']['job_id'],
						'signature' => $postmeta['processing']['signature'],
						'mime_type' => 'image/webp',
					),
				);
			}

			// Process items that have jobs to check, regardless of overall status.
			// This ensures we continue processing even if some jobs complete and status changes.
			if ( ! empty( $processing_jobs ) ) {
				// Get the Image Service API object (singlton).
				$api = Extension_ImageService_Plugin::get_api();

				// Process each job separately.
				$all_jobs_ready = true;
				$has_error      = false;
				$has_notfound   = false;
				$jobs_status    = isset( $postmeta['jobs_status'] ) ? $postmeta['jobs_status'] : array();

				// Initialize arrays for storing multiple formats before processing jobs.
				$post_children = isset( $postmeta['post_children'] ) ? $postmeta['post_children'] : array();
				$downloads     = isset( $postmeta['downloads'] ) ? $postmeta['downloads'] : array();

				// Migrate legacy single-format data to multi-format storage when present.
				$legacy_child_id = isset( $postmeta['post_child'] ) ? $postmeta['post_child'] : null;
				$legacy_format   = 'webp';
				if ( $legacy_child_id ) {
					$legacy_post = get_post( $legacy_child_id );
					if ( $legacy_post && isset( $legacy_post->post_mime_type ) ) {
						$legacy_format = strtolower( str_replace( 'image/', '', $legacy_post->post_mime_type ) );
					}

					// Preserve legacy converted attachment reference.
					if ( empty( $post_children[ $legacy_format ] ) ) {
						$legacy_child_meta = get_post_meta( $legacy_child_id, 'w3tc_imageservice', true );
						if ( ! empty( $legacy_child_meta['is_converted_file'] ) ) {
							$post_children[ $legacy_format ] = $legacy_child_id;
						}
					}
				}

				// Preserve legacy download headers for stats display.
				if ( ! empty( $postmeta['download'] ) && empty( $downloads[ $legacy_format ] ) ) {
					$downloads[ $legacy_format ] = $postmeta['download'];
				}

				// Track which jobs are complete and should be removed from processing_jobs.
				$completed_jobs = array();

				foreach ( $processing_jobs as $format_key => $job ) {
					if ( ! isset( $job['job_id'] ) || ! isset( $job['signature'] ) ) {
						continue;
					}

					// Check the status of this job.
					$response = $api->get_status( $job['job_id'], $job['signature'] );

					// Store status for this job.
					$jobs_status[ $format_key ] = $response;

					// Stop checking jobs that are no longer found.
					if ( ( isset( $response['code'] ) && 404 === (int) $response['code'] ) || ( isset( $response['status'] ) && 'notfound' === $response['status'] ) ) {
						$has_notfound     = true;
						$all_jobs_ready   = false;
						$completed_jobs[] = $format_key;
						continue;
					}

					// Check if this job is ready for pickup/download.
					if ( isset( $response['status'] ) && 'pickup' === $response['status'] ) {
						// Get mime_type from job or response.
						$mime_type = isset( $job['mime_type'] ) ? $job['mime_type'] : ( isset( $response['mime_type'] ) ? $response['mime_type'] : 'image/webp' );

						// Download image for this format using this job's job_id and signature.
						// Pass mime_type_out to ensure API returns the correct format (e.g. AVIF vs WEBP).
						$download_response = $api->download( $job['job_id'], $job['signature'], $mime_type );
						$download_headers  = wp_remote_retrieve_headers( $download_response );
						$is_error          = isset( $download_response['error'] );

						// Convert headers to array and normalize keys to lowercase for consistent access.
						$headers_array = array();
						if ( ! $is_error && $download_headers ) {
							if ( is_object( $download_headers ) && method_exists( $download_headers, 'getAll' ) ) {
								// Requests_Utility_CaseInsensitiveDictionary - get all headers.
								$all_headers = $download_headers->getAll();
								foreach ( $all_headers as $key => $value ) {
									$headers_array[ strtolower( $key ) ] = $value;
								}
							} else {
								// Already an array or other structure.
								$temp_headers = (array) $download_headers;
								foreach ( $temp_headers as $key => $value ) {
									// Skip special WordPress array keys.
									if ( "\0" !== substr( $key, 0, 1 ) ) {
										$headers_array[ strtolower( $key ) ] = $value;
									}
								}
								// Also check for the special data structure.
								if ( isset( $temp_headers["\0*\0data"] ) ) {
									foreach ( $temp_headers["\0*\0data"] as $key => $value ) {
										$headers_array[ strtolower( $key ) ] = $value;
									}
								}
							}
						}

						// Determine if file size was reduced by comparing input and output sizes.
						$filesize_in  = isset( $headers_array['x-filesize-in'] ) ? (int) $headers_array['x-filesize-in'] : 0;
						$filesize_out = isset( $headers_array['x-filesize-out'] ) ? (int) $headers_array['x-filesize-out'] : 0;
						$is_reduced   = ! $is_error && $filesize_in > 0 && $filesize_out > 0 && $filesize_out < $filesize_in;

						// Determine actual output mime type/format from headers (API may return a different output than requested).
						$mime_type_out  = isset( $headers_array['x-mime-type-out'] ) ? $headers_array['x-mime-type-out'] :
							( isset( $headers_array['content-type'] ) ? $headers_array['content-type'] : $mime_type );
						$format_key_out = strtolower( str_replace( 'image/', '', $mime_type_out ) );

						// Delete existing converted file for the actual output format if it exists.
						if ( isset( $post_children[ $format_key_out ] ) ) {
							wp_delete_attachment( $post_children[ $format_key_out ], true );
							unset( $post_children[ $format_key_out ] );
						}

						// Store download information for this format (store normalized headers).
						// Always store download info, even if there's an error or no size reduction.
						$downloads[ $format_key_out ] = $is_error ? $download_response['error'] : $headers_array;

						// If the job key doesn't match the actual output, remove any stale entry keyed by the job key.
						if ( $format_key_out !== $format_key ) {
							unset( $downloads[ $format_key ] );
							unset( $post_children[ $format_key ] );
						}

						// Skip saving file if error or if converted image is larger, but continue to next job.
						if ( $is_error ) {
							$has_error      = true;
							$all_jobs_ready = false;
							// Job completed (with error) - remove from processing_jobs.
							$completed_jobs[] = $format_key;
							continue;
						}

						if ( ! $is_reduced ) {
							// Image wasn't reduced (output is same size or larger), skip saving but continue to next job.
							$all_jobs_ready = false;
							// Job completed (no size reduction) - remove from processing_jobs.
							$completed_jobs[] = $format_key;
							continue;
						}

						// Get original file info for saving.
						$original_filepath = get_attached_file( $post_id );
						$original_size     = wp_getimagesize( $original_filepath );
						$original_filename = basename( get_attached_file( $post_id ) );
						$original_filedir  = str_replace( '/' . $original_filename, '', $original_filepath );

						// Save the file.
						$extension    = $format_key_out;
						$new_filename = preg_replace( '/\.[^.]+$/', '', $original_filename ) . '.' . $extension;
						$new_filepath = $original_filedir . '/' . $new_filename;

						if ( is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
							$wp_filesystem->put_contents( $new_filepath, wp_remote_retrieve_body( $download_response ) );
						} else {
							Util_File::file_put_contents_atomic( $new_filepath, wp_remote_retrieve_body( $download_response ) );
						}

						// Insert as attachment post.
						$attachment_id = wp_insert_attachment(
							array(
								'guid'           => $new_filepath,
								'post_mime_type' => $mime_type_out,
								'post_title'     => preg_replace( '/\.[^.]+$/', '', $new_filename ),
								'post_content'   => '',
								'post_status'    => 'inherit',
								'post_parent'    => $post_id,
								'comment_status' => 'closed',
							),
							$new_filepath,
							$post_id,
							false,
							false
						);

						// Copy postmeta data to the new attachment.
						Extension_ImageService_Plugin_Admin::copy_postmeta( $post_id, $attachment_id );

						// Store the attachment ID for this format.
						$post_children[ $format_key_out ] = $attachment_id;

						// Mark the downloaded file as the converted one.
						Extension_ImageService_Plugin_Admin::update_postmeta(
							$attachment_id,
							array( 'is_converted_file' => true )
						);

						// In order to filter/hide converted files in the media list, add a meta key.
						update_post_meta( $attachment_id, 'w3tc_imageservice_file', $extension );

						// Generate the metadata for the attachment, and update the database record.
						$attach_data           = wp_generate_attachment_metadata( $attachment_id, $new_filepath );
						$attach_data['width']  = isset( $attach_data['width'] ) ? $attach_data['width'] : $original_size[0];
						$attach_data['height'] = isset( $attach_data['height'] ) ? $attach_data['height'] : $original_size[1];
						wp_update_attachment_metadata( $attachment_id, $attach_data );

						// Job successfully completed - remove from processing_jobs.
						$completed_jobs[] = $format_key;
					} elseif ( isset( $response['status'] ) && 'complete' === $response['status'] ) {
						// Job completed but no pickup - mark as error for this format.
						$has_error      = true;
						$all_jobs_ready = false;
						// Job completed (even with error) - remove from processing_jobs.
						$completed_jobs[] = $format_key;
					} else {
						// Job still processing - keep in processing_jobs.
						$all_jobs_ready = false;
					}
				}

				// Remove completed jobs from processing_jobs.
				foreach ( $completed_jobs as $format_key ) {
					unset( $processing_jobs[ $format_key ] );
				}

				// Save jobs status.
				Extension_ImageService_Plugin_Admin::update_postmeta(
					$post_id,
					array( 'jobs_status' => $jobs_status )
				);

				// Determine overall status based on all jobs.
				$has_converted = ! empty( $post_children );
				$status        = 'notconverted'; // Default status if no conversions were successful.
				if ( $has_converted ) {
					$status = 'converted';
				} elseif ( $has_error ) {
					$status = 'error';
				}
				if ( ! $has_converted && ! $has_error && $has_notfound && empty( $processing_jobs ) ) {
					$status = 'notfound';
				}

				// If there are still jobs processing, keep status as 'processing'.
				if ( ! empty( $processing_jobs ) ) {
					$status = 'processing';
				}

				// Save the download information, status, and remaining processing_jobs.
				Extension_ImageService_Plugin_Admin::update_postmeta(
					$post_id,
					array(
						'post_children'   => $post_children,
						'downloads'       => $downloads,
						'status'          => $status,
						'processing_jobs' => $processing_jobs,
					)
				);

				// For backward compatibility, also set post_child to the first converted format.
				if ( ! empty( $post_children ) ) {
					$first_format = reset( $post_children );
					Extension_ImageService_Plugin_Admin::update_postmeta(
						$post_id,
						array( 'post_child' => $first_format )
					);
				}
			}
		}
	}
}
