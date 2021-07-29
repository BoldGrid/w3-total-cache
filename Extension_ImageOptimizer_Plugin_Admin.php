<?php
/**
 * File: Extension_ImageOptimizer_Plugin_Admin.php
 *
 * @since X.X.X
 *
 * @package W3TC
 *
 * phpcs:disable Squiz.PHP.EmbeddedPhp.ContentBeforeOpen, Squiz.PHP.EmbeddedPhp.ContentAfterEnd
 */

namespace W3TC;

/**
 * Class: Extension_ImageOptimizer_Plugin_Admin
 *
 * @since X.X.X
 */
class Extension_ImageOptimizer_Plugin_Admin {
	/**
	 * Image MIME types available for optimization.
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @var array
	 */
	public static $mime_types = array(
		'gif'  => 'image/gif',
		'jpeg' => 'image/jpeg',
		'jpg'  => 'image/jpg',
		'png'  => 'image/png',
	);

	/**
	 * Configuration.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Image Optimizer API class object.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var Extension_ImageOptimizer_API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		$this->config = Dispatcher::config();
	}

	/**
	 * Get extension information.
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @param  array $extensions Extensions.
	 * @param  array $config Configuration.
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$extensions['optimager'] = array(
			'name'             => 'Image Optimizer Service',
			'author'           => 'W3 EDGE',
			'description'      => __(
				'Adds image optimization service options to the media library.',
				'w3-total-cache'
			),
			'author_uri'       => 'https://www.w3-edge.com/',
			'extension_uri'    => 'https://www.w3-edge.com/',
			'extension_id'     => 'optimager',
			'settings_exists'  => true,
			'version'          => '1.0',
			'enabled'          => true,
			'disabled_message' => '',
			'requirements'     => '',
			'path'             => 'w3-total-cache/Extension_ImageOptimizer_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Load the admin extension.
	 *
	 * Runs on the "wp_loaded" action.
	 *
	 * @since X.X.X
	 * @static
	 */
	public static function w3tc_extension_load_admin() {
		$o = new Extension_ImageOptimizer_Plugin_Admin();

		add_action( 'w3tc_extension_page_optimager', array( $o, 'w3tc_extension_page_optimager' ) );

		add_action( 'admin_enqueue_scripts', array( $o, 'admin_enqueue_scripts' ) );

		/**
		 * Filters the Media list table columns.
		 *
		 * @since 2.5.0
		 *
		 * @param string[] $posts_columns An array of columns displayed in the Media list table.
		 * @param bool     $detached      Whether the list table contains media not attached
		 *                                to any posts. Default true.
		 */
		add_filter( 'manage_media_columns', array( $o, 'add_media_column' ) );

		/**
		 * Fires for each custom column in the Media list table.
		 *
		 * Custom columns are registered using the {@see 'manage_media_columns'} filter.
		 *
		 * @since 2.5.0
		 *
		 * @param string $column_name Name of the custom column.
		 * @param int    $post_id     Attachment ID.
		 */
		add_action( 'manage_media_custom_column', array( $o, 'media_column_row' ), 10, 2 );

		// AJAX hooks.
		add_action( 'wp_ajax_w3tc_optimager_submit', array( $o, 'ajax_submit' ) );
		add_action( 'wp_ajax_w3tc_optimager_postmeta', array( $o, 'ajax_get_postmeta' ) );
		add_action( 'wp_ajax_w3tc_optimager_revert', array( $o, 'ajax_revert' ) );

		/**
		 * Ensure all network sites include WebP support.
		 *
		 * @link https://make.wordpress.org/core/2021/06/07/wordpress-5-8-adds-webp-support/
		 */
		add_filter(
			'site_option_upload_filetypes',
			function ( $filetypes ) {
				$filetypes = explode( ' ', $filetypes );
				if ( ! in_array( 'webp', $filetypes, true ) ) {
					$filetypes[] = 'webp';
					$filetypes   = implode( ' ', $filetypes );
				}

				return $filetypes;
			}
		);
	}

	/**
	 * Load the extension settings page view.
	 *
	 * @since X.X.X
	 */
	public function w3tc_extension_page_optimager() {
		$c = $this->config;

		require W3TC_DIR . '/Extension_ImageOptimizer_Page_View.php';
	}

	/**
	 * Enqueue scripts and styles for admin pages.
	 *
	 * Runs on the "admin_enqueue_scripts" action.
	 *
	 * @since X.X.X
	 */
	public function admin_enqueue_scripts() {
		// Enqueue JavaScript for the Media Library (upload) admin page.
		if ( 'upload' === get_current_screen()->id ) {
			wp_register_script(
				'w3tc-optimager',
				esc_url( plugin_dir_url( __FILE__ ) . 'Extension_ImageOptimizer_Plugin_Admin.js' ),
				array( 'jquery' ),
				W3TC_VERSION,
				true
			);

			wp_localize_script(
				'w3tc-optimager',
				'w3tcData',
				array(
					'nonces' => array(
						'submit'   => wp_create_nonce( 'w3tc_optimager_submit' ),
						'postmeta' => wp_create_nonce( 'w3tc_optimager_postmeta' ),
						'revert'   => wp_create_nonce( 'w3tc_optimager_revert' ),
					),
					'lang'   => array(
						'optimize'   => __( 'Optimize', 'w3-total_cache' ),
						'sending'    => __( 'Sending', 'w3-total_cache' ),
						'processing' => __( 'Processing', 'w3-total_cache' ),
						'optimized'  => __( 'Optimized', 'w3-total_cache' ),
						'reoptimize' => __( 'Reoptimize', 'w3-total_cache' ),
						'reverting'  => __( 'Reverting', 'w3-total_cache' ),
						'revert'     => __( 'Revert', 'w3-total_cache' ),
						'error'      => __( 'Error', 'w3-total_cache' ),
						'changed'    => __( 'Changed', 'w3-total_cache' ),
					),
				)
			);

			wp_enqueue_script( 'w3tc-optimager' );

			wp_enqueue_style(
				'w3tc-optimager',
				esc_url( plugin_dir_url( __FILE__ ) . 'Extension_ImageOptimizer_Plugin_Admin.css' ),
				array(),
				W3TC_VERSION,
				'all'
			);
		}
	}

	/**
	 * Add image optimization controls to the Media Library table in list view.
	 *
	 * Runs on the "manage_media_columns" filter.
	 *
	 * @since X.X.X
	 *
	 * @param string[] $posts_columns An array of columns displayed in the Media list table.
	 * @param bool     $detached      Whether the list table contains media not attached
	 *                                to any posts. Default true.
	 */
	public function add_media_column( $posts_columns, $detached = true ) {
		$posts_columns['optimager'] = '<span class="w3tc-optimize"></span> Total Optimizer';

		return $posts_columns;
	}

	/**
	 * Fires for each custom column in the Media list table.
	 *
	 * Custom columns are registered using the {@see 'manage_media_columns'} filter.
	 * Runs on the "manage_media_custom_column" action.
	 *
	 * @since 2.5.0
	 *
	 * @param string $column_name Name of the custom column.
	 * @param int    $post_id     Attachment ID.
	 */
	public function media_column_row( $column_name, $post_id ) {
		if ( 'optimager' === $column_name ) {
			$post           = get_post( $post_id );
			$optimager_data = get_post_meta( $post_id, 'w3tc_optimager', true );

			if ( in_array( $post->post_mime_type, self::$mime_types, true ) ) {
				$filepath = get_attached_file( $post_id );
				$status   = isset( $optimager_data['status'] ) ? $optimager_data['status'] : null;

				// Check if image still has the optimized file.  It could have been deleted.
				if ( 'optimized' === $status && isset( $optimager_data['post_child'] ) ) {
					$child_data = get_post_meta( $optimager_data['post_child'], 'w3tc_optimager', true );

					if ( empty( $child_data['is_optimized_file'] ) ) {
						$status = null;
						delete_post_meta( $post_id, 'w3tc_optimager' );
					}
				}

				?>
				<span class="w3tc-optimize<?php
				if ( 'optimized' === $status ) {
					echo ' w3tc-optimized';
				}
				?>"></span>
				<input type="submit" id="w3tc-<?php echo esc_attr( $post_id ); ?>-optimize" class="button w3tc-optimize" value="<?php
				// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
				switch ( $status ) {
					case 'sending':
						esc_attr_e( 'Sending', 'w3-total-cache' );
						break;
					case 'processing':
						esc_attr_e( 'Processing', 'w3-total-cache' );
						break;
					case 'optimized':
						esc_attr_e( 'Reoptimize', 'w3-total-cache' );
						break;
					default:
						esc_attr_e( 'Optimize', 'w3-total-cache' );
						break;
				}
				// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
				?>" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-status="<?php echo esc_attr( $status ); ?>"
				<?php
				if ( 'processing' === $status ) {
					?>disabled="disabled"<?php
				}
				?> />
				<?php

				// If optimized, then show revert button and information.
				if ( 'optimized' === $status ) {
					?>
					&nbsp; <input type="submit" id="w3tc-<?php echo esc_attr( $post_id ); ?>-unoptimize" class="button w3tc-unoptimize"
						value="<?php esc_attr_e( 'Revert', 'w3-total-cache' ); ?>" \>
					<?php

					$optimized_percent = isset( $optimager_data['download']["\0*\0data"]['x-filesize-out-percent'] ) ?
						$optimager_data['download']["\0*\0data"]['x-filesize-out-percent'] : null;
					$reduced_percent   = isset( $optimager_data['download']["\0*\0data"]['x-filesize-reduced'] ) ?
						$optimager_data['download']["\0*\0data"]['x-filesize-reduced'] : null;

					if ( $optimized_percent ) {
						$optimized_class = rtrim( $optimized_percent, '%' ) > 100 ? 'w3tc-optimized-increased' : 'w3tc-optimized-reduced';
						?>
						<div class="<?php echo esc_attr( $optimized_class ); ?>">
						<?php
						echo esc_html(
							$optimized_percent . ' (' . __( 'Changed: ', 'w3-total-cache' ) . $reduced_percent . ')'
						);
						?>
						</div>
						<?php
					}
				}
			} elseif ( isset( $optimager_data['is_optimized_file'] ) && $optimager_data['is_optimized_file'] ) {
				// W3TC optimized image.
				?>
				<span class="w3tc-optimize w3tc-optimized"></span>
				<?php
				echo esc_html__( 'Attachment id: ', 'w3-total-cache' ) . esc_html( $post->post_parent );
			}
		}
	}

	/**
	 * Update postmeta.
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @link https://developer.wordpress.org/reference/functions/update_post_meta/
	 *
	 * @param int   $post_id  Post id.
	 * @param array $data Postmeta data.
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure or if the value
	 *                  passed to the function is the same as the one that is already in the database.
	 */
	public static function update_postmeta( $post_id, array $data ) {
		$postmeta = (array) get_post_meta( $post_id, 'w3tc_optimager', true );
		$postmeta = array_merge( $postmeta, $data );

		return update_post_meta( $post_id, 'w3tc_optimager', $postmeta );
	}

	/**
	 * Copy postmeta from one post to another.
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @link https://developer.wordpress.org/reference/functions/update_post_meta/
	 *
	 * @param int $post_id_1 Post id 1.
	 * @param int $post_id_2 Post id 2.
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure or if the value
	 *                  passed to the function is the same as the one that is already in the database.
	 */
	public static function copy_postmeta( $post_id_1, $post_id_2 ) {
		$postmeta = (array) get_post_meta( $post_id_1, 'w3tc_optimager', true );

		// Do not copy "post_child".
		unset( $postmeta['post_child'] );

		return update_post_meta( $post_id_2, 'w3tc_optimager', $postmeta );
	}

	/**
	 * AJAX: Submit an image for processing.
	 *
	 * @since X.X.X
	 *
	 * @uses $_POST['post_id'] Post id.
	 */
	public function ajax_submit() {
		check_ajax_referer( 'w3tc_optimager_submit' );

		// Check for post id.
		$post_id = isset( $_POST['post_id'] ) ? (int) sanitize_key( $_POST['post_id'] ) : null;

		if ( ! $post_id ) {
			wp_send_json_error(
				array(
					'error' => __( 'Missing input post id.', 'w3-total-cache' ),
				),
				400
			);
		}

		// Verify the image file exists.
		$filepath = get_attached_file( $post_id );

		if ( ! file_exists( $filepath ) ) {
			wp_send_json_error(
				array(
					'error' => sprintf(
						// translators: 1: Image filepath.
						__( 'File "%1$s" does not exist.', 'w3-total-cache' ),
						$filepath
					),
				),
				412
			);
		}

		// Submit the job request.
		require_once __DIR__ . '/Extension_ImageOptimizer_Api.php';

		$api      = new Extension_ImageOptimizer_Api();
		$response = $api->convert( $filepath );

		// Check for WP Error.
		if ( isset( $response['error'] ) ) {
			wp_send_json_error(
				$response,
				417
			);
		}

		// Check for valid response data.
		if ( empty( $response['job_id'] ) || empty( $response['signature'] ) ) {
			wp_send_json_error(
				array(
					'error' => __( 'Invalid API response.', 'w3-total-cache' ),
				),
				417
			);
		}

		// Save the job info.
		$postmeta['status']     = 'processing';
		$postmeta['processing'] = $response;

		self::update_postmeta( $post_id, $postmeta );

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Get the status of an image, from postmeta.
	 *
	 * @since X.X.X
	 *
	 * @uses $_POST['post_id'] Post id.
	 */
	public function ajax_get_postmeta() {
		check_ajax_referer( 'w3tc_optimager_postmeta' );

		$post_id = isset( $_POST['post_id'] ) ? (int) sanitize_key( $_POST['post_id'] ) : null;

		if ( $post_id ) {
			wp_send_json_success( (array) get_post_meta( $post_id, 'w3tc_optimager', true ) );
		} else {
			wp_send_json_error(
				array(
					'error' => __( 'Missing input post id.', 'w3-total-cache' ),
				),
				400
			);
		}
	}

	/**
	 * AJAX: Revert an optimization.
	 *
	 * @since X.X.X
	 *
	 * @uses $_POST['post_id'] Parent post id.
	 */
	public function ajax_revert() {
		check_ajax_referer( 'w3tc_optimager_revert' );

		$post_id = isset( $_POST['post_id'] ) ? (int) sanitize_key( $_POST['post_id'] ) : null;

		if ( $post_id ) {
			// Get child post id.
			$postmeta = (array) get_post_meta( $post_id, 'w3tc_optimager', true );
			$child_id = isset( $postmeta['post_child'] ) ? $postmeta['post_child'] : null;

			if ( $child_id ) {
				// Delete postmeta.
				delete_post_meta( $post_id, 'w3tc_optimager' );

				// Delete optimization.
				wp_send_json_success( wp_delete_attachment( $child_id, false ) );
			} else {
				wp_send_json_error(
					array(
						'error' => __( 'Missing optimized attachment id.', 'w3-total-cache' ),
					),
					410
				);
			}
		} else {
			wp_send_json_error(
				array(
					'error' => __( 'Missing input post id.', 'w3-total-cache' ),
				),
				400
			);
		}
	}
}
