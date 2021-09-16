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
	 * @global $wp_version WordPress core version.
	 *
	 * @param  array $extensions Extensions.
	 * @param  array $config Configuration.
	 * @return array
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		global $wp_version;

		$description = __(
			'Adds the ability to convert images into the modern WEBP format for better performance.',
			'w3-total-cache'
		);

		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			$description .= sprintf(
				__(
					'%1$sThis extension works best in WordPress version 5.8 and higher.  You are running WordPress version %2$s.  Please %3$supdate now%4$s to benefit from this feature.',
					'w3-total-cache'
				),
				'<br />',
				$wp_version,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
				'</a>'
			);
		}

		$extensions['optimager'] = array(
			'name'             => 'Image Service',
			'author'           => 'W3 EDGE',
			'description'      => esc_html( $description ),
			'author_uri'       => 'https://www.w3-edge.com/',
			'extension_uri'    => 'https://www.w3-edge.com/',
			'extension_id'     => 'optimager',
			'settings_exists'  => true,
			'version'          => '1.0',
			'enabled'          => true,
			'disabled_message' => '',
			'requirements'     => '',
			'path'             => 'w3-total-cache/Extension_ImageOptimizer_Plugin.php',
			'extra_links'      => array(
				'<a class="edit" href="' . esc_attr( Util_Ui::admin_url( 'upload.php?mode=list' ) ) . '">' .
					esc_html__( 'Media Library', 'w3-total-cache' ) . '</a>',
			),
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

		// Settings page.
		add_action( 'w3tc_extension_page_optimager', array( $o, 'w3tc_extension_page_optimager' ) );

		// Enqueue scripts.
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
		add_action( 'wp_ajax_w3tc_optimager_all', array( $o, 'ajax_optimize_all' ) );
		add_action( 'wp_ajax_w3tc_optimager_revertall', array( $o, 'ajax_revert_all' ) );
		add_action( 'wp_ajax_w3tc_optimager_counts', array( $o, 'ajax_get_counts' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $o, 'w3tc_optimager_notices' ) );

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

		// Add bulk actions.
		add_filter( 'bulk_actions-upload', array( $o, 'add_bulk_actions' ) );

		/**
		 * Fires when a custom bulk action should be handled.
		 *
		 * The redirect link should be modified with success or failure feedback
		 * from the action to be used to display feedback to the user.
		 *
		 * The dynamic portion of the hook name, `$screen`, refers to the current screen ID.
		 *
		 * @since 4.7.0
		 *
		 * @link https://core.trac.wordpress.org/browser/tags/5.8/src/wp-admin/upload.php#L206
		 *
		 * @param string $sendback The redirect URL.
		 * @param string $doaction The action being taken.
		 * @param array  $items    The items to take the action on. Accepts an array of IDs of posts,
		 *                         comments, terms, links, plugins, attachments, or users.
		 */
		add_filter( 'handle_bulk_actions-upload', array( $o, 'handle_bulk_actions' ), 10, 3 );

		/**
		 * Handle auto-optimization on upload.
		 *
		 * @link https://core.trac.wordpress.org/browser/tags/5.8/src/wp-includes/post.php#L4401
		 * @link https://developer.wordpress.org/reference/hooks/add_attachment/
		 *
		 * Fires once an attachment has been added.
		 *
		 * @since 2.0.0
		 *
		 * @param int $post_ID Attachment ID.
		 */
		add_action( 'add_attachment', array( $o, 'auto_optimize' ) );

		/**
		 * Delete optimizations on parent image delation.
		 *
		 * @link https://core.trac.wordpress.org/browser/tags/5.8/src/wp-includes/post.php#L6134
		 * @link https://developer.wordpress.org/reference/hooks/pre_delete_attachment/
		 *
		 * Filters whether an attachment deletion should take place.
		 *
		 * @since 5.5.0
		 *
		 * @param bool|null $delete       Whether to go forward with deletion.
		 * @param WP_Post   $post         Post object.
		 * @param bool      $force_delete Whether to bypass the Trash.
		 */
		add_filter( 'pre_delete_attachment', array( $o, 'cleanup_optimizations' ), 10, 3 );

		// Add admin menu items.
		add_action( 'admin_menu', array( $o, 'admin_menu' ) );
	}

	/**
	 * Get all images with postmeta key "w3tc_optimager".
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @link https://developer.wordpress.org/reference/classes/wp_query/
	 *
	 * @return
	 */
	public static function get_optimager_attachments() {
		return new \WP_Query(
			array(
				'post_type'           => 'attachment',
				'post_status'         => 'inherit',
				'post_mime_type'      => self::$mime_types,
				'posts_per_page'      => -1,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
				'meta_key'            => 'w3tc_optimager', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	/**
	 * Get all images without postmeta key "w3tc_optimager".
	 *
	 * @since X.X.X
	 * @static
	 *
	 * @link https://developer.wordpress.org/reference/classes/wp_query/
	 *
	 * @return
	 */
	public static function get_eligible_attachments() {
		return new \WP_Query(
			array(
				'post_type'           => 'attachment',
				'post_status'         => 'inherit',
				'post_mime_type'      => self::$mime_types,
				'posts_per_page'      => -1,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
				'meta_key'            => 'w3tc_optimager', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'        => 'NOT EXISTS',
			)
		);
	}

	/**
	 * Get image counts by status.
	 *
	 * @since X.X.X
	 *
	 * @global $wp_filesystem
	 *
	 * @see self::get_optimager_attachments()
	 * @see self::get_eligible_attachments()
	 *
	 * @return array
	 */
	public function get_optimager_counts() {
		WP_Filesystem();
		global $wp_filesystem;

		$unoptimized_posts = self::get_eligible_attachments();
		$counts            = array(
			'sending'         => 0,
			'processing'      => 0,
			'optimized'       => 0,
			'unoptimized'     => $unoptimized_posts->post_count,
			'total'           => 0,
		);
		$optimized_bytes   = 0;
		$total_bytes       = 0;
		$optimager_posts   = self::get_optimager_attachments()->posts;

		foreach ( $optimager_posts as $post ) {
			$optimager_data = get_post_meta( $post->ID, 'w3tc_optimager', true );
			$status         = isset( $optimager_data['status'] ) ? $optimager_data['status'] : null;
			$filesize_in    = isset( $optimager_data['download']["\0*\0data"]['x-filesize-in'] ) ?
				$optimager_data['download']["\0*\0data"]['x-filesize-in'] : 0;
			$filesize_out   = isset( $optimager_data['download']["\0*\0data"]['x-filesize-out'] ) ?
				$optimager_data['download']["\0*\0data"]['x-filesize-out'] : 0;

			switch ( $status ) {
				case 'sending':
					$counts['sending']++;
					break;
				case 'processing':
					$counts['processing']++;
					break;
				case 'optimized':
					$counts['optimized']++;
					$optimized_bytes += $filesize_in - $filesize_out;
					break;
				case 'unoptimized':
					$counts['unoptimized']++;
					break;
				default:
					break;
			}

			$total_bytes += $filesize_in - $filesize_out;
		}

		foreach ( $unoptimized_posts->posts as $post ) {
			$filepath = get_attached_file( $post->ID );

			if ( $wp_filesystem->exists( $filepath ) ) {
				$total_bytes += $wp_filesystem->size( $filepath );
			}
		}

		$counts['total']           = array_sum( $counts );
		$counts['total_bytes']     = $total_bytes;
		$counts['optimized_bytes'] = $optimized_bytes;

		return $counts;
	}

	/**
	 * Load the extension settings page view.
	 *
	 * @since X.X.X
	 */
	public function w3tc_extension_page_optimager() {
		$c      = $this->config;
		$counts = $this->get_optimager_counts();

		delete_transient( 'w3tc_activation_optimager' );

		require W3TC_DIR . '/Extension_ImageOptimizer_Page_View.php';
	}

	/**
	 * Add admin menu items.
	 *
	 * @since X.X.X
	 */
	public function admin_menu() {
		// Add settings submenu to Media top-level menu.
		add_submenu_page(
			'upload.php',
			esc_html__( 'W3 Image Service', 'w3-total-cache' ),
			esc_html__( 'W3 Image Service', 'w3-total-cache' ),
			'edit_posts',
			'admin.php?page=w3tc_extensions&extension=optimager&action=view'
		);
	}

	/**
	 * Enqueue scripts and styles for admin pages.
	 *
	 * Runs on the "admin_enqueue_scripts" action.
	 *
	 * @since X.X.X
	 */
	public function admin_enqueue_scripts() {
		// Enqueue JavaScript for the Media Library (upload) and extension settings admin pages.
		$is_settings_page = isset( $_GET['extension'] ) && 'optimager' === $_GET['extension'];
		$is_media_page    = 'upload' === get_current_screen()->id;

		if ( $is_settings_page || $is_media_page ) {
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
						'control'  => wp_create_nonce( 'w3tc_optimager_control' ),
					),
					'lang'   => array(
						'optimize'      => __( 'Optimize', 'w3-total_cache' ),
						'sending'       => __( 'Sending...', 'w3-total_cache' ),
						'processing'    => __( 'Processing...', 'w3-total_cache' ),
						'optimized'     => __( 'Optimized', 'w3-total_cache' ),
						'reverting'     => __( 'Reverting...', 'w3-total_cache' ),
						'reverted'      => __( 'Reverted', 'w3-total_cache' ),
						'revert'        => __( 'Revert', 'w3-total_cache' ),
						'error'         => __( 'Error', 'w3-total_cache' ),
						'reduced'       => __( 'Reduced', 'w3-total_cache' ),
						'notoptimized'  => __( 'Not optimized; image would be larger.', 'w3-total_cache' ),
						'AjaxFail'      => __( 'Failed to retrieve a response.  Please reload the page to try again.', 'w3-total_cache' ),
						'ApiError'      => __( 'API error.  Please reload the page to try again,', 'w3-total_cache' ),
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
	 * Add image service controls to the Media Library table in list view.
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
		$posts_columns['optimager'] = '<span class="w3tc-optimize"></span> ' . esc_html__( 'Image Service', 'w3-total-cache' );

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
	 * @see self::remove_optimizations()
	 *
	 * @link https://developer.wordpress.org/reference/functions/size_format/
	 *
	 * @param string $column_name Name of the custom column.
	 * @param int    $post_id     Attachment ID.
	 */
	public function media_column_row( $column_name, $post_id ) {
		if ( 'optimager' === $column_name ) {
			delete_transient( 'w3tc_activation_optimager' );

			$post           = get_post( $post_id );
			$optimager_data = get_post_meta( $post_id, 'w3tc_optimager', true );

			// Display controls and info for eligible images.
			if ( in_array( $post->post_mime_type, self::$mime_types, true ) ) {
				$filepath = get_attached_file( $post_id );
				$status   = isset( $optimager_data['status'] ) ? $optimager_data['status'] : null;

				// Check if image still has the optimized file.  It could have been deleted.
				if ( 'optimized' === $status && isset( $optimager_data['post_child'] ) ) {
					$child_data = get_post_meta( $optimager_data['post_child'], 'w3tc_optimager', true );

					if ( empty( $child_data['is_optimized_file'] ) ) {
						$status = null;
						$this->remove_optimizations( $post_id );
					}
				}

				// If processed, then show information.
				if ( 'optimized' === $status ) {
					$optimized_percent = isset( $optimager_data['download']["\0*\0data"]['x-filesize-out-percent'] ) ?
						$optimager_data['download']["\0*\0data"]['x-filesize-out-percent'] : null;
					$reduced_percent   = isset( $optimager_data['download']["\0*\0data"]['x-filesize-reduced'] ) ?
						$optimager_data['download']["\0*\0data"]['x-filesize-reduced'] : null;
					$filesize_in       = isset( $optimager_data['download']["\0*\0data"]['x-filesize-in'] ) ?
						$optimager_data['download']["\0*\0data"]['x-filesize-in'] : null;
					$filesize_out      = isset( $optimager_data['download']["\0*\0data"]['x-filesize-out'] ) ?
						$optimager_data['download']["\0*\0data"]['x-filesize-out'] : null;

					if ( $optimized_percent ) {
						$optimized_class = rtrim( $optimized_percent, '%' ) > 100 ? 'w3tc-optimized-increased' : 'w3tc-optimized-reduced';
						?>
						<div class="<?php echo esc_attr( $optimized_class ); ?>">
						<?php
						printf(
							'%1$s &#8594; %2$s (%3$s)',
							size_format( $filesize_in ),
							size_format( $filesize_out ),
							$reduced_percent
						);
						?>
						</div>
						<?php
					}
				} elseif ( 'notoptimized' === $status ) {
					?>
					<div class="w3tc-optimized-increased"><?php esc_html_e( 'Not optimized; image would be larger.', 'w3-total-cache' ); ?></div>
					<?php
				}

				// Determine classes.
				$link_classes   = 'w3tc-optimize';
				$disabled_class = '';
				$aria_attr = 'false';

				if ( 'processing' === $status ) {
					$link_classes  .= ' w3tc-optimize-processing';
					$disabled_class = 'w3tc-disabled';
					$aria_attr = 'true';
				}

				// Print action links.
				?>
				<span class="<?php echo esc_attr( $disabled_class ); ?>">
					<a href="#" class="<?php echo esc_attr( $link_classes ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>"
						data-status="<?php echo esc_attr( $status ); ?>" aria-disabled="<?php echo esc_attr( $aria_attr ); ?>">
				<?php
				// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
				switch ( $status ) {
					case 'sending':
						esc_html_e( 'Sending...', 'w3-total-cache' );
						break;
					case 'processing':
						esc_html_e( 'Processing...', 'w3-total-cache' );
						break;
					case 'optimized':
						esc_html_e( 'Optimized', 'w3-total-cache' );
						break;
					default:
						esc_html_e( 'Optimize', 'w3-total-cache' );
						break;
				}
				// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
				?>
					</a>
				</span>
				<?php

				// If optimized, then show revert link.
				if ( 'optimized' === $status ) {
					?>
					<span class="w3tc-revert"> | <a href="#"><?php esc_attr_e( 'Revert', 'w3-total-cache' ); ?></a></span>
					<?php
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
	 * Add bulk actions.
	 *
	 * @since X.X.X
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( array $actions ) {
		$actions['w3tc_optimager_optimize'] = 'W3 Total Optimize';
		$actions['w3tc_optimager_revert'] = 'W3 Total Optimize Revert';

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @since X.X.X
	 *
	 * @see self::submit_images()
	 * @see self::revert_optimizations()
	 *
	 * @link https://developer.wordpress.org/reference/hooks/handle_bulk_actions-screen/
	 * @link https://make.wordpress.org/core/2016/10/04/custom-bulk-actions/
	 * @link https://core.trac.wordpress.org/browser/tags/5.8/src/wp-admin/upload.php#L206
	 *
	 * @since WordPress 4.7.0
	 *
	 * @param string $location The redirect URL.
	 * @param string $doaction The action being taken.
	 * @param array  $post_ids The items to take the action on. Accepts an array of IDs of attachments.
	 * @return string
	 */
	public function handle_bulk_actions( $location, $doaction, array $post_ids ) {
		// Remove custom query args.
		$location = remove_query_arg( array( 'w3tc_optimager_submitted', 'w3tc_optimager_reverted' ), $location );

		switch ( $doaction ) {
			case 'w3tc_optimager_optimize':
				$stats = $this->submit_images( $post_ids );

				$location = add_query_arg(
					array(
						'w3tc_optimager_submitted'  => $stats['submitted'],
						'w3tc_optimager_successful' => $stats['successful'],
						'w3tc_optimager_skipped'    => $stats['skipped'],
						'w3tc_optimager_errored'    => $stats['errored'],
						'w3tc_optimager_invalid'    => $stats['invalid'],
					),
					$location
				);

				break;
			case 'w3tc_optimager_revert':
				$this->revert_optimizations( $post_ids );

				$location = add_query_arg( 'w3tc_optimager_reverted', 1, $location );

				break;
			default:
				break;
		}

		return $location;
	}

	/**
	 * Display bulk action results admin notice.
	 *
	 * @since X.X.X
	 *
	 * @uses $_GET['w3tc_optimager_submitted']  Number of submittions.
	 * @uses $_GET['w3tc_optimager_successful'] Number of successful submissions.
	 * @uses $_GET['w3tc_optimager_skipped']    Number of skipped submissions.
	 * @uses $_GET['w3tc_optimager_errored']    Number of errored submissions.
	 * @uses $_GET['w3tc_optimager_invalid']    Number of invalid submissions.
	 */
	public function w3tc_optimager_notices() {
		if ( isset( $_GET['w3tc_optimager_submitted'] ) ) {
			$submitted  = intval( $_GET['w3tc_optimager_submitted'] );
			$successful = isset( $_GET['w3tc_optimager_successful'] ) ? intval( $_GET['w3tc_optimager_successful'] ) : 0;
			$skipped    = isset( $_GET['w3tc_optimager_skipped'] ) ? intval( $_GET['w3tc_optimager_skipped'] ) : 0;
			$errored    = isset( $_GET['w3tc_optimager_errored'] ) ? intval( $_GET['w3tc_optimager_errored'] ) : 0;
			$invalid    = isset( $_GET['w3tc_optimager_invalid'] ) ? intval( $_GET['w3tc_optimager_invalid'] ) : 0;

			printf(
				'<div class="updated notice notice-success is-dismissible"><p><span class="w3tc-optimize"></span> Image Service</p><p>' .
				// translators: 1: Submissions.
				_n(
					'Submitted %1$u image for processing.',
					'Submitted %1$u images for processing.',
					$submitted,
					'w3-total-cache'
				) . '</p>',
				$submitted
			);

			// Print extra stats if debug is on.
			if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
				printf(
					'<p>' .
					// translators: 1: Successes, 2: Skipped, 3: Errored, 4: Invalid.
					__(
						'Successful: %1$u | Skipped: %2$u | Errored: %3$u | Invalid: %4$u',
						'w3-total-cache'
					) . '</p>',
					$successful,
					$skipped,
					$errored,
					$invalid
				);
			}

			echo '</div>';

		} elseif ( isset( $_GET['w3tc_optimager_reverted'] ) ) {
			?>
			<div class="updated notice notice-success is-dismissible"><p><span class="w3tc-optimize"></span> Image Service</p>
				<p><?php _e( 'All selected optimizations have been reverted.', 'w3-total-cache' ); ?></p>
			</div>
			<?php
		} elseif ( 'upload' === get_current_screen()->id ) {
			// Media Library: Get the display mode.
			$mode  = get_user_option( 'media_library_mode', get_current_user_id() ) ?
				get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

			// If not in list mode, then print a notice to switch to it.
			if ( 'list' !== $mode ) {
				?>
				<div class="notice notice-warning is-dismissible"><p><span class="w3tc-optimize"></span> Image Service</p>
					<p>
				<?php
						printf(
							// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
							__( 'Switch to %1$slist mode%2$s for image service controls.', 'w3-total-cache' ),
							'<a href="' . esc_attr( Util_Ui::admin_url( 'upload.php?mode=list' ) ) . '">',
							'</a>'
						);
				?>
					</p>
				</div>
    			<?php
			}
		} elseif ( isset( $_GET['w3tc_optimager_action'] ) && 'dismiss_activation_notice' === $_GET['w3tc_optimager_action'] ) {
			delete_transient( 'w3tc_activation_optimager' );
			wp_redirect( remove_query_arg( 'w3tc_optimager_action' ) );
		} elseif ( get_transient( 'w3tc_activation_optimager' ) ) {
			// Activation notice.
			$page       = isset( $_GET['page'] ) ? $_GET['page'] : null;
			$skip_pages = array(
				'w3tc_setup_guide',
				'w3tc_extensions',
			);

			/**
			 * Accept filtering for skipped pages.
			 *
			 * @since X.X.X
			 *
			 * @param array $skip_pages Skip any array values if matching $_GET['page'].
			 */
			$skip_pages = apply_filters( 'w3tc_activation_notice_skip_pages', $skip_pages );

			if ( ! in_array( $page, $skip_pages, true ) ) {
				?>
				<div class="updated notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag.
							__( 'Thank you for activating %1$sW3 Total Cache%2$s and choosing to increase your website performance.', 'w3-total-cache' ),
							'<strong>',
							'</strong>'
						);
						?>
					</p>
					<p>
				<?php
				printf(
					// translators: 1: HTML anchor open tag (Settings page link), 2: HTML anchor close tag, 3: HTML anchor open tag (Media Library link), 4: HTML anchor open tag (dismiss notice).
					__(
						'We now offer an image conversion service to support the latest WEBP image format.  Configure your settings and convert all of your images now using our %1$sbulk tools%2$s, select images to convert in your %3$sMedia Library%2$s, or %4$shide this notice%2$s.',
						'w3-total-cache'
					),
					'<a href="' . esc_attr( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions&extension=optimager&action=view' ) ) . '">',
					'</a>',
					'<a href="' . esc_attr( Util_Ui::admin_url( 'upload.php?mode=list' ) ) . '">',
					'<a href="' . esc_attr( add_query_arg( 'w3tc_optimager_action', 'dismiss_activation_notice' ) ) . '">'
				);
				?>
					</p>
				</div>
				<?php
			}
		}

	}

	/**
	 * Submit images to the API for processing.
	 *
	 * @since X.X.X
	 *
	 * @global $wp_filesystem
	 *
	 * @param array $post_ids
	 * @return array
	 */
	public function submit_images( array $post_ids ) {
		WP_Filesystem();
		global $wp_filesystem;

		require_once __DIR__ . '/Extension_ImageOptimizer_Api.php';

		$api = new Extension_ImageOptimizer_Api();

		$stats = array(
			'skipped'    => 0,
			'submitted'  => 0,
			'successful' => 0,
			'errored'    => 0,
			'invalid'    => 0,
		);

		foreach ( $post_ids as $post_id ) {
			// Skip silently (do not count) if not an allowed MIME type.
			if ( ! in_array( get_post_mime_type( $post_id ), self::$mime_types, true ) ) {
				continue;
			}

			$filepath = get_attached_file( $post_id );

			// Skip if attachment file does not exist.
			if ( ! $wp_filesystem->exists( $filepath ) ) {
				$stats['skipped']++;
				continue;
			}

			// Submit current image.
			$response = $api->convert( $filepath );
			$stats['submitted']++;

			if ( isset( $response['error'] ) ) {
				$stats['errored']++;
				continue;
			}

			if ( empty( $response['job_id'] ) || empty( $response['signature'] ) ) {
				$stats['invalid']++;
				continue;
			}

			// Remove old optimizations.
			$this->remove_optimizations( $post_id );

			// Save the job info.
			self::update_postmeta(
				$post_id,
				array(
					'status'     => 'processing',
					'processing' => $response,
				)
			);

			$stats['successful']++;
		}

		return $stats;
	}

	/**
	 * Revert optimizations of images.
	 *
	 * @since X.X.X
	 *
	 * @param array $post_ids Attachment post ids.
	 */
	public function revert_optimizations( array $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			// Skip if not an allowed MIME type.
			if ( ! in_array( get_post_mime_type( $post_id ), self::$mime_types, true ) ) {
				continue;
			}

			$this->remove_optimizations( $post_id );
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
	 * Remove optimizations.
	 *
	 * @since X.X.X
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_delete_attachment/
	 *
	 * @param int $post_id Parent post id.
	 * @return WP_Post|false|null Post data on success, false or null on failure.
	 */
	public function remove_optimizations( $post_id ) {
		$result = null;

		// Get child post id.
		$postmeta = (array) get_post_meta( $post_id, 'w3tc_optimager', true );
		$child_id = isset( $postmeta['post_child'] ) ? $postmeta['post_child'] : null;

		if ( $child_id ) {
			// Delete optimization.
			$result = wp_delete_attachment( $child_id, true );
		}

		// Delete postmeta.
		delete_post_meta( $post_id, 'w3tc_optimager' );

		return $result;
	}

	/**
	 * Handle auto-optimization on image upload.
	 *
	 * @since X.X.X
	 *
	 * @param int $post_id Post id.
	 */
	public function auto_optimize( $post_id ) {
		$settings = $this->config->get_array( 'optimager' );
		$enabled  = isset( $settings['auto'] ) && 'enabled' === $settings['auto'];

		if ( $enabled && in_array( get_post_mime_type( $post_id ), self::$mime_types, true ) ) {
			$this->submit_images( array( $post_id ) );
		}
	}

	/**
	 * Delete optimizations on parent image delation.
	 *
	 * Does not filter the WordPress operation.  We use this as an action trigger.
	 *
	 * @since X.X.X
	 *
	 * @param bool|null $delete       Whether to go forward with deletion.
	 * @param WP_Post   $post         Post object.
	 * @param bool      $force_delete Whether to bypass the Trash.
	 * @return null
	 */
	public function cleanup_optimizations( $delete, $post, $force_delete ) {
		if ( $force_delete ) {
			$this->remove_optimizations( $post->ID );
		}

		return null;
	}

	/**
	 * AJAX: Submit an image for processing.
	 *
	 * @since X.X.X
	 *
	 * @global $wp_filesystem
	 *
	 * @uses $_POST['post_id'] Post id.
	 */
	public function ajax_submit() {
		check_ajax_referer( 'w3tc_optimager_submit' );

		WP_Filesystem();
		global $wp_filesystem;

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

		if ( ! $wp_filesystem->exists( $filepath ) ) {
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

		// Remove old optimizations.
		$this->remove_optimizations( $post_id );

		// Save the job info.
		self::update_postmeta(
			$post_id,
			array(
				'status'     => 'processing',
				'processing' => $response,
			)
		);

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
			$result = $this->remove_optimizations( $post_id );

			if ( $result ) {
				wp_send_json_success( $result );
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

	/**
	 * AJAX: Optimize all images.
	 *
	 * @since X.X.X
	 *
	 * @see self::get_eligible_attachments()
	 * @see self::submit_images()
	 */
	public function ajax_optimize_all() {
		check_ajax_referer( 'w3tc_optimager_submit' );

		$results = $this->get_eligible_attachments();

		$post_ids = array();

		// Allow plenty of time to complete.
		ignore_user_abort( true );
		set_time_limit(0);

		foreach ( $results->posts as $post ) {
			$post_ids[] = $post->ID;
		}

		$stats = $this->submit_images( $post_ids );

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Revert all optimized images.
	 *
	 * @since X.X.X
	 *
	 * @see self::get_optimager_attachments()
	 * @see self::remove_optimizations()
	 */
	public function ajax_revert_all() {
		check_ajax_referer( 'w3tc_optimager_submit' );

		$results = $this->get_optimager_attachments();

		$revert_count = 0;

		// Allow plenty of time to complete.
		ignore_user_abort( true );
		set_time_limit(0);

		foreach ( $results->posts as $post ) {
			if ( $this->remove_optimizations( $post->ID ) ) {
				$revert_count++;
			}
		}

		wp_send_json_success( array( 'revert_count' => $revert_count ) );
	}

	/**
	 * AJAX: Get image counts by status.
	 *
	 * @since X.X.X
	 *
	 * @see get_optimager_counts()
	 */
	public function ajax_get_counts() {
		check_ajax_referer( 'w3tc_optimager_submit' );

		wp_send_json_success( $this->get_optimager_counts() );
	}
}
