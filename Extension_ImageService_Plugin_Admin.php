<?php
/**
 * File: Extension_ImageService_Plugin_Admin.php
 *
 * @since 2.2.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extension_ImageService_Plugin_Admin
 *
 * @since 2.2.0
 *
 * phpcs:disable Squiz.PHP.EmbeddedPhp.ContentBeforeOpen
 * phpcs:disable Squiz.PHP.EmbeddedPhp.ContentAfterEnd
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_ImageService_Plugin_Admin {
	/**
	 * Image MIME types available for optimization.
	 *
	 * @since 2.2.0
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
	 * @since 2.2.0
	 * @access private
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Image Service API class object.
	 *
	 * @since 2.2.0
	 * @access private
	 *
	 * @var Extension_ImageService_API
	 */
	private $api;

	/**
	 * Was the WP Cron error notice already printed?
	 *
	 * @since  2.8.0
	 * @static
	 * @access private
	 *
	 * @var bool
	 */
	private static $wpcron_notice_printed = false;

	/**
	 * Constructor.
	 *
	 * @since 2.2.0
	 */
	public function __construct() {
		$this->config = Dispatcher::config();
	}

	/**
	 * Get config.
	 *
	 * @since 2.8.0
	 *
	 * @return Config
	 */
	public function get_config(): Config {
		return $this->config;
	}

	/**
	 * Get extension information.
	 *
	 * @since 2.2.0
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
			'Adds the ability to convert images in the Media Library to modern formats (like WebP or AVIF) for better performance.',
			'w3-total-cache'
		);

		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			$description .= sprintf(
				// translators: 1: HTML break, 2: WordPress version string, 3: HTML archor open tag, 4: HTML archor close tag.
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

		$settings_url = esc_url( Util_Ui::admin_url( 'upload.php?page=w3tc_extension_page_imageservice&w3tc_imageservice_action=dismiss_activation_notice' ) );
		$library_url  = esc_url( Util_Ui::admin_url( 'upload.php?mode=list' ) );

		$extensions['imageservice'] = array(
			'name'             => 'Image Converter',
			'author'           => 'BoldGrid',
			'description'      => esc_html( $description ),
			'author_uri'       => 'https://www.boldgrid.com/',
			'extension_uri'    => 'https://www.boldgrid.com/w3-total-cache/?utm_source=w3tc&utm_medium=extension_admin&utm_campaign=imageservice',
			'extension_id'     => 'imageservice',
			'settings_exists'  => false,
			'version'          => '1.0',
			'enabled'          => true,
			'disabled_message' => '',
			'requirements'     => '',
			'path'             => 'w3-total-cache/Extension_ImageService_Plugin.php',
			'notice'           => sprintf(
				// translators: 1: HTML anchor open tag, 2: HTML anchor close tag, 3: HTML anchor open tag, 4: HTML anchor open tag.
				__(
					'Total Cache Image Converter has been activated. Now, you can %1$sadjust the settings%2$s or go to the %3$sMedia Library%2$s to convert images to modern formats like WebP or AVIF.  %4$sLearn more%2$s.',
					'w3-total-cache'
				),
				'<a class="edit" href="' . $settings_url . '">',
				'</a>',
				'<a class="edit" href="' . $library_url . '">',
				'<a target="_blank" href="' . esc_url(
					'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=activation_notice&utm_campaign=imageservice'
				) . '">'
			),
		);

		// The settings and Media Library links are only valid for single and network sites; not the admin section.
		if ( ! is_network_admin() ) {
			$extensions['imageservice']['extra_links'] = array(
				'<a class="edit" href="' . $settings_url . '">' . esc_html__( 'Settings', 'w3-total-cache' ) . '</a>',
				'<a class="edit" href="' . $library_url . '">' . esc_html__( 'Media Library', 'w3-total-cache' ) . '</a>',
			);
		}

		return $extensions;
	}

	/**
	 * Load the admin extension.
	 *
	 * Runs on the "wp_loaded" action.
	 *
	 * @since 2.2.0
	 * @static
	 */
	public static function w3tc_extension_load_admin() {
		$o = new Extension_ImageService_Plugin_Admin();

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
		add_action( 'wp_ajax_w3tc_imageservice_submit', array( $o, 'ajax_submit' ) );
		add_action( 'wp_ajax_w3tc_imageservice_postmeta', array( $o, 'ajax_get_postmeta' ) );
		add_action( 'wp_ajax_w3tc_imageservice_revert', array( $o, 'ajax_revert' ) );
		add_action( 'wp_ajax_w3tc_imageservice_all', array( $o, 'ajax_convert_all' ) );
		add_action( 'wp_ajax_w3tc_imageservice_revertall', array( $o, 'ajax_revert_all' ) );
		add_action( 'wp_ajax_w3tc_imageservice_counts', array( $o, 'ajax_get_counts' ) );
		add_action( 'wp_ajax_w3tc_imageservice_usage', array( $o, 'ajax_get_usage' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $o, 'display_notices' ) );

		/**
		 * Ensure all network sites include support for modern image formats (e.g., WebP/AVIF).
		 *
		 * @link https://make.wordpress.org/core/2021/06/07/wordpress-5-8-adds-webp-support/
		 */
		add_filter(
			'site_option_upload_filetypes',
			function ( $filetypes ) {
				$filetypes = explode( ' ', $filetypes );
				if ( ! in_array( 'webp', $filetypes, true ) ) {
					$filetypes[] = 'webp';
				}

				return implode( ' ', $filetypes );
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
		add_action( 'add_attachment', array( $o, 'auto_convert' ) );

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

		// If auto-convert is enabled, then check WP Cron.
		if ( ! empty( $o->get_config()->get_array( 'imageservice' )['auto'] ) && 'enabled' === $o->get_config()->get_array( 'imageservice' )['auto'] ) {
			add_action( 'pre-upload-ui', array( $o, 'check_wpcron' ) );
		}
	}

	/**
	 * Get all images with postmeta key "w3tc_imageservice".
	 *
	 * @since 2.2.0
	 * @static
	 *
	 * @link https://developer.wordpress.org/reference/classes/wp_query/
	 *
	 * @return WP_Query WP_Query object containing post IDs in the posts property (due to the "fields" argument with value "ids").
	 */
	public static function get_imageservice_attachments(): \WP_Query {
		return new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => self::$mime_types,
				'posts_per_page'         => -1,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true, // phpcs:ignore WordPressVIPMinimum
				'meta_key'               => 'w3tc_imageservice', // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'cache_results'          => false,
			)
		);
	}

	/**
	 * Get all images without postmeta key "w3tc_imageservice".
	 *
	 * @since 2.2.0
	 * @static
	 *
	 * @link https://developer.wordpress.org/reference/classes/wp_query/
	 *
	 * @return WP_Query WP_Query object containing post IDs in the posts property (due to the "fields" argument with value "ids").
	 */
	public static function get_eligible_attachments(): \WP_Query {
		return new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => self::$mime_types,
				'posts_per_page'         => -1,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true, // phpcs:ignore WordPressVIPMinimum
				'meta_key'               => 'w3tc_imageservice', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'           => 'NOT EXISTS',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'cache_results'          => false,
			)
		);
	}

	/**
	 * Get an attachment filesize.
	 *
	 * @since 2.2.0
	 *
	 * @global $wp_filesystem
	 *
	 * @param int $post_id Post id.
	 * @return int
	 */
	public function get_attachment_filesize( $post_id ) {
		WP_Filesystem();
		global $wp_filesystem;

		$size     = 0;
		$filepath = get_attached_file( $post_id );

		if ( $wp_filesystem->exists( $filepath ) ) {
			$size = $wp_filesystem->size( $filepath );
		}

		return $size;
	}

	/**
	 * Get image counts by status.
	 *
	 * @since 2.2.0
	 *
	 * @see self::get_imageservice_attachments()
	 * @see self::get_eligible_attachments()
	 *
	 * @return array
	 */
	public function get_counts() {
		$unconverted_posts  = self::get_eligible_attachments();
		$counts             = array(
			'sending'      => 0,
			'processing'   => 0,
			'converted'    => 0,
			'notconverted' => 0,
			'unconverted'  => $unconverted_posts->post_count,
			'total'        => 0,
		);
		$bytes              = array(
			'sending'      => 0,
			'processing'   => 0,
			'converted'    => 0,
			'notconverted' => 0,
			'unconverted'  => 0,
			'total'        => 0,
		);
		$imageservice_posts = self::get_imageservice_attachments()->posts;

		foreach ( $imageservice_posts as $post_id ) {
			$imageservice_data = get_post_meta( $post_id, 'w3tc_imageservice', true );
			$status            = isset( $imageservice_data['status'] ) ? $imageservice_data['status'] : null;
			$filesize_in       = isset( $imageservice_data['download']["\0*\0data"]['x-filesize-in'] ) ?
				$imageservice_data['download']["\0*\0data"]['x-filesize-in'] : 0;
			$filesize_out      = isset( $imageservice_data['download']["\0*\0data"]['x-filesize-out'] ) ?
				$imageservice_data['download']["\0*\0data"]['x-filesize-out'] : 0;

			switch ( $status ) {
				case 'sending':
					$size = $this->get_attachment_filesize( $post_id );
					++$counts['sending'];
					$bytes['sending'] += $size;
					$bytes['total']   += $size;
					break;
				case 'processing':
					$size = $this->get_attachment_filesize( $post_id );
					++$counts['processing'];
					$bytes['processing'] += $size;
					$bytes['total']      += $size;
					break;
				case 'converted':
					++$counts['converted'];
					$bytes['converted'] += $filesize_in - $filesize_out;
					$bytes['total']     += $filesize_in - $filesize_out;
					break;
				case 'notconverted':
					$size = $this->get_attachment_filesize( $post_id );
					++$counts['notconverted'];
					$bytes['notconverted'] += $size;
					$bytes['total']        += $size;
					break;
				case 'unconverted':
					$size = $this->get_attachment_filesize( $post_id );
					++$counts['unconverted'];
					$bytes['unconverted'] += $size;
					$bytes['total']       += $size;
					break;
				default:
					break;
			}
		}

		foreach ( $unconverted_posts->posts as $post_id ) {
			$size = $this->get_attachment_filesize( $post_id );

			if ( $size ) {
				$bytes['unconverted'] += $size;
				$bytes['total']       += $size;
			}
		}

		$counts['total']             = array_sum( $counts );
		$counts['totalbytes']        = $bytes['total'];
		$counts['sendingbytes']      = $bytes['sending'];
		$counts['processingbytes']   = $bytes['processing'];
		$counts['convertedbytes']    = $bytes['converted'];
		$counts['notconvertedbytes'] = $bytes['notconverted'];
		$counts['unconvertedbytes']  = $bytes['unconverted'];

		return $counts;
	}

	/**
	 * Load the extension settings page view.
	 *
	 * @since 2.2.0
	 *
	 * @see Extension_ImageService_Plugin::get_api()
	 * @see Extension_ImageService_Api::get_usage()
	 */
	public function settings_page() {
		$c      = $this->config;
		$counts = $this->get_counts();

		// Delete transient for displaying activation notice.
		delete_transient( 'w3tc_activation_imageservice' );

		// Save submitted settings.
		$nonce_val                    = Util_Request::get_string( '_wpnonce' );
		$imageservice_compression_val = Util_Request::get_string( 'imageservice___compression' );
		if ( ! empty( $imageservice_compression_val ) && ! empty( $nonce_val ) && wp_verify_nonce( $nonce_val, 'w3tc' ) ) {
			$settings                = $c->get_array( 'imageservice' );
			$settings['compression'] = $imageservice_compression_val;

			$imageservice_auto_val = Util_Request::get_string( 'imageservice___auto' );
			if ( ! empty( $imageservice_auto_val ) ) {
				$settings['auto'] = $imageservice_auto_val;
			}

			$imageservice_visibility_val = Util_Request::get_string( 'imageservice___visibility' );
			if ( ! empty( $imageservice_visibility_val ) ) {
				$settings['visibility'] = $imageservice_visibility_val;
			}

			$imageservice_webp_val = Util_Request::get_string( 'imageservice___webp' );
			$settings['webp']      = ! empty( $imageservice_webp_val );

			$imageservice_avif_val = Util_Request::get_string( 'imageservice___avif' );
			// Only allow AVIF for Pro license holders.
			if ( Util_Environment::is_w3tc_pro( $c ) ) {
				$settings['avif'] = ! empty( $imageservice_avif_val );
			} else {
				$settings['avif'] = false;
			}

			$c->set( 'imageservice', $settings );
			$c->save();

			// Display notice when saving settings.
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved.', 'w3-total-cache' ); ?></p>
			</div>
			<?php
		}

		// Get Image Service usage from the API.
		$usage = Extension_ImageService_Plugin::get_api()->get_usage();

		// Ensure that the monthly limit is represented correctly.
		$usage['limit_monthly'] = $usage['limit_monthly'] ? $usage['limit_monthly'] : __( 'Unlimited', 'w3-total-cache' );

		// Display a notice if WP Cron is not working as expected.
		$this->check_wpcron();

		// Load the page view.
		require W3TC_DIR . '/Extension_ImageService_Page_View.php';
	}

	/**
	 * Add admin menu items (administrators only).
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		// Check if the current user is a contributor or higher.
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		// Add settings submenu to Media top-level menu.
		add_submenu_page(
			'upload.php',
			esc_html__( 'Total Cache Image Converter', 'w3-total-cache' ),
			esc_html__( 'Total Cache Image Converter', 'w3-total-cache' ),
			'edit_posts',
			'w3tc_extension_page_imageservice',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Enqueue scripts and styles for admin pages (author or higher).
	 *
	 * Runs on the "admin_enqueue_scripts" action.
	 *
	 * @since 2.2.0
	 *
	 * @see Util_Ui::admin_url()
	 * @see Licensing_Core::get_tos_choice()
	 */
	public function admin_enqueue_scripts() {
		if ( ! \user_can( \get_current_user_id(), 'upload_files' ) ) {
			return;
		}

		// Enqueue JavaScript for the Media Library (upload) and extension settings admin pages.
		$page_val         = Util_Request::get_string( 'page' );
		$is_settings_page = ! empty( $page_val ) && 'w3tc_extension_page_imageservice' === $page_val && \user_can( \get_current_user_id(), 'manage_options' ); // Administrators only.
		$is_media_page    = 'upload' === get_current_screen()->id;

		if ( $is_settings_page ) {
			wp_enqueue_style( 'w3tc-options' );
			wp_enqueue_style( 'w3tc-bootstrap-css' );
			wp_enqueue_script( 'w3tc-options' );
			wp_localize_script( 'w3tc-lightbox', 'w3tc_nonce', array( wp_create_nonce( 'w3tc' ) ) );
			wp_enqueue_script( 'w3tc-lightbox' );
			wp_enqueue_style( 'w3tc-lightbox' );
		}

		if ( $is_settings_page || $is_media_page ) {
			wp_register_script(
				'w3tc-imageservice',
				esc_url( plugin_dir_url( __FILE__ ) . 'Extension_ImageService_Plugin_Admin.js' ),
				array( 'jquery' ),
				W3TC_VERSION,
				true
			);

			wp_localize_script(
				'w3tc-imageservice',
				'w3tcData',
				array(
					'nonces'      => array(
						'submit'   => wp_create_nonce( 'w3tc_imageservice_submit' ),
						'postmeta' => wp_create_nonce( 'w3tc_imageservice_postmeta' ),
						'revert'   => wp_create_nonce( 'w3tc_imageservice_revert' ),
					),
					'lang'        => array(
						'convert'          => __( 'Convert', 'w3-total-cache' ),
						'convertToWebp'    => __( 'Convert to WebP', 'w3-total-cache' ),
						'convertToAvif'    => __( 'Convert to AVIF', 'w3-total-cache' ),
						'sending'          => __( 'Sending...', 'w3-total-cache' ),
						'submitted'        => __( 'Submitted', 'w3-total-cache' ),
						'processing'       => __( 'Processing...', 'w3-total-cache' ),
						'converted'        => __( 'Converted', 'w3-total-cache' ),
						'notConverted'     => __( 'Not converted', 'w3-total-cache' ),
						'reverting'        => __( 'Reverting...', 'w3-total-cache' ),
						'reverted'         => __( 'Reverted', 'w3-total-cache' ),
						'revert'           => __( 'Revert', 'w3-total-cache' ),
						'error'            => __( 'Error', 'w3-total-cache' ),
						'ajaxFail'         => __( 'Failed to retrieve a response.  Please reload the page to try again.', 'w3-total-cache' ),
						'apiError'         => __( 'API error.  Please reload the page to try again,', 'w3-total-cache' ),
						'refresh'          => __( 'Refresh', 'w3-total-cache' ),
						'refreshing'       => __( 'Refreshing...', 'w3-total-cache' ),
						'settings'         => __( 'Settings', 'w3-total-cache' ),
						'submittedAllDesc' => sprintf(
							// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
							__( 'Images queued for conversion.  Progress can be seen in the %1$sMedia Library%2$s.', 'w3-total-cache' ),
							'<a href="' . esc_url( Util_Ui::admin_url( 'upload.php?mode=list' ) ) . '">',
							'</a>'
						),
						'notConvertedDesc' => sprintf(
							// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
							__( 'The converted image would be larger than the original; conversion canceled.  %1$sLearn more%2$s.', 'w3-total-cache' ),
							'<a target="_blank" href="' . esc_url(
								'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=conversion_canceled&utm_campaign=imageservice#conversion-canceled'
							) . '">',
							'</a>'
						),
					),
					'tos_choice'  => Licensing_Core::get_tos_choice(),
					'track_usage' => $this->config->get_boolean( 'common.track_usage' ),
					'ga_profile'  => ( defined( 'W3TC_DEVELOPER' ) && W3TC_DEVELOPER ) ? 'G-Q3CHQJWERM' : 'G-5TFS8M5TTY',
					'isPro'       => Util_Environment::is_w3tc_pro( $this->config ),
					'settings'    => $this->config->get_array( 'imageservice' ),
					'settingsUrl' => esc_url( Util_Ui::admin_url( 'upload.php?page=w3tc_extension_page_imageservice' ) ),
				)
			);

			wp_enqueue_script( 'w3tc-imageservice' );

			wp_enqueue_style(
				'w3tc-imageservice',
				esc_url( plugin_dir_url( __FILE__ ) . 'Extension_ImageService_Plugin_Admin.css' ),
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
	 * @since 2.2.0
	 *
	 * @param string[] $posts_columns An array of columns displayed in the Media list table.
	 * @param bool     $detached      Whether the list table contains media not attached
	 *                                to any posts. Default true.
	 */
	public function add_media_column( $posts_columns, $detached = true ) {
		// Delete transient for displaying activation notice.
		delete_transient( 'w3tc_activation_imageservice' );

		$posts_columns['imageservice'] = '<span class="w3tc-convert"></span> ' . esc_html__( 'Image Converter', 'w3-total-cache' );

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
		static $settings;

		if ( 'imageservice' === $column_name ) {
			$post              = get_post( $post_id );
			$imageservice_data = get_post_meta( $post_id, 'w3tc_imageservice', true );

			$settings = isset( $settings ) ? $settings : $this->config->get_array( 'imageservice' );

			// Display controls and info for eligible images.
			if ( in_array( $post->post_mime_type, self::$mime_types, true ) ) {
				$filepath = get_attached_file( $post_id );
				$status   = isset( $imageservice_data['status'] ) ? $imageservice_data['status'] : null;

				// Check for old format conversion (backward compatibility).
				// If post_child exists and is valid, treat it as converted regardless of current status.
				if ( isset( $imageservice_data['post_child'] ) && ! empty( $imageservice_data['post_child'] ) ) {
					$child_id = $imageservice_data['post_child'];
					// Verify the child attachment still exists.
					$child_post = get_post( $child_id );
					if ( $child_post ) {
						$child_data = get_post_meta( $child_id, 'w3tc_imageservice', true );
						if ( ! empty( $child_data['is_converted_file'] ) ) {
							$status = 'converted';
							// Update status in database if it wasn't set, so it's recognized consistently.
							if ( empty( $imageservice_data['status'] ) || 'converted' !== $imageservice_data['status'] ) {
								self::update_postmeta( $post_id, array( 'status' => 'converted' ) );
							}
						}
					}
				}

				// Check if image still has the converted file(s).  It could have been deleted.
				if ( 'converted' === $status ) {
					$has_valid_conversion = false;

					// Check new structure (multiple formats).
					if ( isset( $imageservice_data['post_children'] ) && is_array( $imageservice_data['post_children'] ) ) {
						foreach ( $imageservice_data['post_children'] as $format_key => $child_id ) {
							if ( $child_id ) {
								$child_data = get_post_meta( $child_id, 'w3tc_imageservice', true );
								if ( ! empty( $child_data['is_converted_file'] ) ) {
									$has_valid_conversion = true;
									break;
								}
							}
						}
					}

					// Check old structure (backward compatibility).
					if ( ! $has_valid_conversion && isset( $imageservice_data['post_child'] ) ) {
						$child_data = get_post_meta( $imageservice_data['post_child'], 'w3tc_imageservice', true );
						if ( ! empty( $child_data['is_converted_file'] ) ) {
							$has_valid_conversion = true;
						}
					}

					if ( ! $has_valid_conversion ) {
						$status = null;
						$this->remove_optimizations( $post_id );
					}
				}

				// If processed, then show information.
				if ( 'converted' === $status ) {
					// Check if this is an old format conversion (has post_child but not post_children with data).
					$has_old_format_only = isset( $imageservice_data['post_child'] ) && ! empty( $imageservice_data['post_child'] ) &&
						( ! isset( $imageservice_data['post_children'] ) || empty( $imageservice_data['post_children'] ) );

					// Handle multiple formats (new structure).
					if ( ! $has_old_format_only && isset( $imageservice_data['downloads'] ) && is_array( $imageservice_data['downloads'] ) && ! empty( $imageservice_data['downloads'] ) ) {
						// Sort formats to maintain order: WEBP first, then AVIF.
						$sorted_downloads = $imageservice_data['downloads'];
						uksort(
							$sorted_downloads,
							function( $a, $b ) {
								// Define order: webp first, then avif, then others.
								$order = array( 'webp' => 1, 'avif' => 2 );
								$a_order = isset( $order[ $a ] ) ? $order[ $a ] : 99;
								$b_order = isset( $order[ $b ] ) ? $order[ $b ] : 99;
								return $a_order - $b_order;
							}
						);

						// Show all formats that have download info, not just those with post_children.
						// This includes formats that failed or didn't reduce size.
						foreach ( $sorted_downloads as $format_key => $download_data ) {
							// Skip if download_data is an error string and we don't have a post_child for it.
							if ( is_string( $download_data ) && ( ! isset( $imageservice_data['post_children'][ $format_key ] ) || ! $imageservice_data['post_children'][ $format_key ] ) ) {
								// Show error message for failed formats.
								?>
								<div class="w3tc-converted-error">
								<?php
								printf(
									'%1$s: %2$s',
									esc_html( strtoupper( $format_key ) ),
									esc_html( $download_data )
								);
								?>
								</div>
								<?php
								continue;
							}

							if ( ! is_array( $download_data ) ) {
								continue;
							}
							if ( is_array( $download_data ) ) {
								// Headers are now stored normalized (lowercase keys) from cron.
								// Handle both new normalized format and old format for backward compatibility.
								$download_headers = $download_data;

								// If old format with special structure, extract it.
								if ( isset( $download_data["\0*\0data"] ) ) {
									$download_headers = $download_data["\0*\0data"];
								}

								// Normalize keys to lowercase for case-insensitive access.
								$normalized_headers = array();
								foreach ( $download_headers as $key => $value ) {
									// Skip special WordPress array keys.
									if ( "\0" !== substr( $key, 0, 1 ) ) {
										$normalized_headers[ strtolower( $key ) ] = $value;
									}
								}

								$reduced_percent = isset( $normalized_headers['x-filesize-reduced'] ) ?
									$normalized_headers['x-filesize-reduced'] : null;
								$filesize_in     = isset( $normalized_headers['x-filesize-in'] ) ?
									$normalized_headers['x-filesize-in'] : null;
								$filesize_out    = isset( $normalized_headers['x-filesize-out'] ) ?
									$normalized_headers['x-filesize-out'] : null;

								// Check if this format was actually converted (exists in post_children).
								$post_children = isset( $imageservice_data['post_children'] ) && is_array( $imageservice_data['post_children'] ) ?
									$imageservice_data['post_children'] : array();
								$was_converted = isset( $post_children[ $format_key ] ) && ! empty( $post_children[ $format_key ] );

								// If not converted, show "not converted" message instead of statistics.
								if ( ! $was_converted ) {
									?>
									<div class="w3tc-notconverted">
									<?php
									printf(
										'%1$s: %2$s',
										esc_html( strtoupper( $format_key ) ),
										esc_html__( 'Not converted', 'w3-total-cache' )
									);
									?>
									</div>
									<?php
									continue;
								}

								// Display if we have the necessary data and the format was converted.
								if ( $filesize_in && $filesize_out && $reduced_percent ) {
									$reduced_numeric = rtrim( $reduced_percent, '%' );
									$converted_class = (float) $reduced_numeric < 100 ? 'w3tc-converted-reduced' : 'w3tc-converted-increased';
									?>
									<div class="<?php echo esc_attr( $converted_class ); ?>">
									<?php
									printf(
										'%1$s: %2$s &#8594; %3$s (%4$s)',
										esc_html( strtoupper( $format_key ) ),
										esc_html( size_format( $filesize_in ) ),
										esc_html( size_format( $filesize_out ) ),
										esc_html( $reduced_percent )
									);
									?>
									</div>
									<?php
								}
							}
						}
					} else {
						// Handle single format (backward compatibility - old format).
						// Check if old format conversion exists (post_child).
						$has_old_conversion = isset( $imageservice_data['post_child'] ) && ! empty( $imageservice_data['post_child'] );

						if ( $has_old_conversion ) {
							$download_data = isset( $imageservice_data['download'] ) ? $imageservice_data['download'] : null;
							$has_download_data = false;

							// Determine format from child post mime type or default to WEBP.
							$format_label = 'WEBP';
							$child_id = $imageservice_data['post_child'];
							$child_post = get_post( $child_id );
							if ( $child_post && isset( $child_post->post_mime_type ) ) {
								$format_label = strtoupper( str_replace( 'image/', '', $child_post->post_mime_type ) );
							}

							if ( is_array( $download_data ) ) {
								// Headers may be in special WordPress structure.
								$download_headers = isset( $download_data["\0*\0data"] ) ? $download_data["\0*\0data"] : $download_data;

								// Normalize keys to lowercase for case-insensitive access.
								$normalized_headers = array();
								foreach ( $download_headers as $key => $value ) {
									// Skip special WordPress array keys.
									if ( "\0" !== substr( $key, 0, 1 ) ) {
										$normalized_headers[ strtolower( $key ) ] = $value;
									}
								}

								$reduced_percent = isset( $normalized_headers['x-filesize-reduced'] ) ?
									$normalized_headers['x-filesize-reduced'] : null;
								$filesize_in     = isset( $normalized_headers['x-filesize-in'] ) ?
									$normalized_headers['x-filesize-in'] : null;
								$filesize_out    = isset( $normalized_headers['x-filesize-out'] ) ?
									$normalized_headers['x-filesize-out'] : null;

								// If reduced_percent is missing but we have file sizes, calculate it.
								if ( ! $reduced_percent && $filesize_in && $filesize_out ) {
									$filesize_in_int  = (int) $filesize_in;
									$filesize_out_int = (int) $filesize_out;
									if ( $filesize_in_int > 0 ) {
										$reduction = ( ( $filesize_in_int - $filesize_out_int ) / $filesize_in_int ) * 100;
										$reduced_percent = number_format( $reduction, 2 ) . '%';
									}
								}

								// Display if we have the necessary data.
								if ( $filesize_in && $filesize_out ) {
									$has_download_data = true;
									$reduced_numeric = $reduced_percent ? (float) rtrim( $reduced_percent, '%' ) : 0;
									$converted_class = $reduced_numeric > 100 ? 'w3tc-converted-increased' : 'w3tc-converted-reduced';

									// Show statistics with or without percentage.
									?>
									<div class="<?php echo esc_attr( $converted_class ); ?>">
									<?php
									if ( $reduced_percent ) {
										printf(
											'%1$s: %2$s &#8594; %3$s (%4$s)',
											esc_html( $format_label ),
											esc_html( size_format( $filesize_in ) ),
											esc_html( size_format( $filesize_out ) ),
											esc_html( $reduced_percent )
										);
									} else {
										printf(
											'%1$s: %2$s &#8594; %3$s',
											esc_html( $format_label ),
											esc_html( size_format( $filesize_in ) ),
											esc_html( size_format( $filesize_out ) )
										);
									}
									?>
									</div>
									<?php
								}
							} elseif ( $child_post ) {
								// Try to get file sizes from the actual files if download data is missing.
								$original_filepath = get_attached_file( $post_id );
								$converted_filepath = get_attached_file( $child_id );

								if ( $original_filepath && $converted_filepath && file_exists( $original_filepath ) && file_exists( $converted_filepath ) ) {
									$filesize_in  = filesize( $original_filepath );
									$filesize_out = filesize( $converted_filepath );

									if ( $filesize_in > 0 && $filesize_out > 0 ) {
										$has_download_data = true;
										$reduction = ( ( $filesize_in - $filesize_out ) / $filesize_in ) * 100;
										$reduced_percent = number_format( $reduction, 2 ) . '%';
										$reduced_numeric = (float) $reduction;
										$converted_class = $reduced_numeric > 100 ? 'w3tc-converted-increased' : 'w3tc-converted-reduced';
										?>
										<div class="<?php echo esc_attr( $converted_class ); ?>">
										<?php
										printf(
											'%1$s: %2$s &#8594; %3$s (%4$s)',
											esc_html( $format_label ),
											esc_html( size_format( $filesize_in ) ),
											esc_html( size_format( $filesize_out ) ),
											esc_html( $reduced_percent )
										);
										?>
										</div>
										<?php
									}
								}
							}

							// If no download data but conversion exists, show basic converted message.
							if ( ! $has_download_data ) {
								?>
								<div class="w3tc-converted-reduced">
								<?php
								printf(
									'%1$s: %2$s',
									esc_html__( 'WEBP', 'w3-total-cache' ),
									esc_html__( 'Converted', 'w3-total-cache' )
								);
								?>
								</div>
								<?php
							}
						}
					}
				} elseif ( 'notconverted' === $status ) {
					?>
					<div class="w3tc-notconverted">
					<?php
					printf(
						// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
						esc_html__( 'The converted image would be larger than the original; conversion canceled.  %1$sLearn more%2$s.', 'w3-total-cache' ),
						'<a target="_blank" href="' . esc_url(
							'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=conversion_canceled&utm_campaign=imageservice#conversion-canceled'
						) . '">',
						'</a>'
					);
					?>
					</div>
					<?php
				}

				// Determine classes.
				$link_classes = 'w3tc-convert';

				switch ( $status ) {
					case 'processing':
						$link_classes  .= ' w3tc-convert-processing';
						$disabled_class = 'w3tc-disabled';
						$aria_attr      = 'true';
						break;
					case 'converted':
						$disabled_class = 'w3tc-disabled';
						$aria_attr      = 'true';
						break;
					default:
						$disabled_class = '';
						$aria_attr      = 'false';
						break;
				}

				// Print action links.
				?>
				<span class="<?php echo esc_attr( $disabled_class ); ?>">
					<a class="<?php echo esc_attr( $link_classes ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>"
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
					case 'converted':
						// Show which format(s) were converted.
						$converted_formats = array();

						// Check new format (post_children).
						if ( isset( $imageservice_data['post_children'] ) && is_array( $imageservice_data['post_children'] ) ) {
							foreach ( $imageservice_data['post_children'] as $format_key => $child_id ) {
								if ( $child_id ) {
									$child_data = get_post_meta( $child_id, 'w3tc_imageservice', true );
									if ( ! empty( $child_data['is_converted_file'] ) ) {
										$converted_formats[] = strtoupper( $format_key );
									}
								}
							}
						}

						// Check old format (post_child).
						if ( empty( $converted_formats ) && isset( $imageservice_data['post_child'] ) && ! empty( $imageservice_data['post_child'] ) ) {
							$child_post = get_post( $imageservice_data['post_child'] );
							if ( $child_post && isset( $child_post->post_mime_type ) ) {
								$format_label = strtoupper( str_replace( 'image/', '', $child_post->post_mime_type ) );
								$converted_formats[] = $format_label;
							}
						}

						// Sort formats to maintain order: WEBP first, then AVIF.
						if ( ! empty( $converted_formats ) ) {
							usort(
								$converted_formats,
								function( $a, $b ) {
									$order = array( 'WEBP' => 1, 'AVIF' => 2 );
									$a_order = isset( $order[ $a ] ) ? $order[ $a ] : 99;
									$b_order = isset( $order[ $b ] ) ? $order[ $b ] : 99;
									return $a_order - $b_order;
								}
							);
							echo esc_html( implode( '/', $converted_formats ) . ' ' . __( 'Converted', 'w3-total-cache' ) );
						} else {
							esc_html_e( 'Converted', 'w3-total-cache' );
						}
						break;
					case 'notconverted':
					case 'notfound':
						if ( isset( $settings['compression'] ) && 'lossless' === $settings['compression'] ) {
							esc_html_e( 'Settings', 'w3-total-cache' );
						} else {
							esc_html_e( 'Convert', 'w3-total-cache' );
						}
						break;
					default:
						esc_html_e( 'Convert', 'w3-total-cache' );
						break;
				}
				// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
				?>
					</a>
				</span>
				<?php

				// If converted, then show revert link.
				if ( 'converted' === $status ) {
					?>
					<span class="w3tc-revert"> | <a><?php esc_attr_e( 'Revert', 'w3-total-cache' ); ?></a></span>
					<?php
					// Check if WEBP and AVIF already exist.
					$has_webp = false;
					$has_avif = false;

					if ( isset( $imageservice_data['post_children']['webp'] ) && ! empty( $imageservice_data['post_children']['webp'] ) ) {
						$has_webp = true;
					}
					if ( isset( $imageservice_data['post_children']['avif'] ) && ! empty( $imageservice_data['post_children']['avif'] ) ) {
						$has_avif = true;
					}

					// Check old format (post_child).
					if ( isset( $imageservice_data['post_child'] ) && ! empty( $imageservice_data['post_child'] ) ) {
						$child_id = $imageservice_data['post_child'];
						$child_post = get_post( $child_id );
						if ( $child_post && 'image/webp' === $child_post->post_mime_type ) {
							$has_webp = true;
						} elseif ( $child_post && 'image/avif' === $child_post->post_mime_type ) {
							$has_avif = true;
						}
					}

					$settings      = isset( $settings ) ? $settings : $this->config->get_array( 'imageservice' );
					$webp_enabled  = isset( $settings['webp'] ) && ! empty( $settings['webp'] );
					$avif_enabled  = ! isset( $settings['avif'] ) || true === $settings['avif'] || '1' === $settings['avif'] || 1 === $settings['avif'];
					$has_pro       = Util_Environment::is_w3tc_pro( $this->config );
					$avif_enabled  = $avif_enabled && $has_pro;

					// Show additional convert links only when the format is enabled.
					if ( $has_webp && ! $has_avif && $avif_enabled ) {
						?>
						<span class="w3tc-convert-avif"> | <a class="w3tc-convert-format" data-post-id="<?php echo esc_attr( $post_id ); ?>"
							data-status="<?php echo esc_attr( $status ); ?>" data-format="avif" aria-disabled="false"><?php esc_html_e( 'Convert to AVIF', 'w3-total-cache' ); ?></a></span>
						<?php
					}

					if ( $has_avif && ! $has_webp && $webp_enabled ) {
						?>
						<span class="w3tc-convert-webp"> | <a class="w3tc-convert-format" data-post-id="<?php echo esc_attr( $post_id ); ?>"
							data-status="<?php echo esc_attr( $status ); ?>" data-format="webp" aria-disabled="false"><?php esc_html_e( 'Convert to WebP', 'w3-total-cache' ); ?></a></span>
						<?php
					}
				}
			} elseif ( isset( $imageservice_data['is_converted_file'] ) && $imageservice_data['is_converted_file'] ) {
				// W3TC converted image.
				echo esc_html__( 'Attachment id: ', 'w3-total-cache' ) . esc_html( $post->post_parent );
			}
		}
	}

	/**
	 * Add bulk actions.
	 *
	 * @since 2.2.0
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( array $actions ) {
		$actions['w3tc_imageservice_convert'] = 'W3 Total Convert';
		$actions['w3tc_imageservice_revert']  = 'W3 Total Convert Revert';

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @since 2.2.0
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
		$location = remove_query_arg( array( 'w3tc_imageservice_submitted', 'w3tc_imageservice_reverted' ), $location );

		switch ( $doaction ) {
			case 'w3tc_imageservice_convert':
				$stats = $this->submit_images( $post_ids );

				$location = add_query_arg(
					array(
						'w3tc_imageservice_submitted'  => $stats['submitted'],
						'w3tc_imageservice_successful' => $stats['successful'],
						'w3tc_imageservice_skipped'    => $stats['skipped'],
						'w3tc_imageservice_errored'    => $stats['errored'],
						'w3tc_imageservice_invalid'    => $stats['invalid'],
					),
					$location
				);

				break;
			case 'w3tc_imageservice_revert':
				$this->revert_optimizations( $post_ids );

				$location = add_query_arg( 'w3tc_imageservice_reverted', 1, $location );

				break;
			default:
				break;
		}

		return $location;
	}

	/**
	 * Display bulk action results admin notice.
	 *
	 * @since 2.2.0
	 *
	 * @see Util_Environment::is_wpcron_working()
	 * @see self::check_wpcron()
	 *
	 * @uses $_GET['w3tc_imageservice_submitted']  Number of submittions.
	 * @uses $_GET['w3tc_imageservice_successful'] Number of successful submissions.
	 * @uses $_GET['w3tc_imageservice_skipped']    Number of skipped submissions.
	 * @uses $_GET['w3tc_imageservice_errored']    Number of errored submissions.
	 * @uses $_GET['w3tc_imageservice_invalid']    Number of invalid submissions.
	 */
	public function display_notices() {
		$submitted = Util_Request::get_integer( 'w3tc_imageservice_submitted' );
		$is_auto   = ! empty( $this->config->get_array( 'imageservice' )['auto'] ) && 'enabled' === $this->config->get_array( 'imageservice' )['auto'];

		if ( ! empty( $submitted ) ) {
			$successful_val = Util_Request::get_integer( 'w3tc_imageservice_successful' );
			$successful     = ! empty( $successful_val ) ? $successful_val : 0;

			$skipped_val = Util_Request::get_integer( 'w3tc_imageservice_skipped' );
			$skipped     = ! empty( $skipped_val ) ? $skipped_val : 0;

			$errored_val = Util_Request::get_integer( 'w3tc_imageservice_errored' );
			$errored     = ! empty( $errored_val ) ? $errored_val : 0;

			$invalid_val = Util_Request::get_integer( 'w3tc_imageservice_invalid' );
			$invalid     = ! empty( $invalid_val ) ? $invalid_val : 0;

			?>
			<script>history.pushState( null, '', location.href.split( '?' )[0] );</script>

			<div class="updated notice notice-success is-dismissible">
				<p>Total Cache Image Converter</p>
				<p>
			<?php

			printf(
				esc_html(
					// translators: 1: Submissions.
					_n(
						'Submitted %1$u image for processing.',
						'Submitted %1$u images for processing.',
						$submitted,
						'w3-total-cache'
					)
				) . '</p>',
				esc_attr( $submitted )
			);

			// Print extra stats if debug is on.
			if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG ) {
				?>
				<p>
				<?php

				printf(
					// translators: 1: Successes, 2: Skipped, 3: Errored, 4: Invalid.
					esc_html__(
						'Successful: %1$u | Skipped: %2$u | Errored: %3$u | Invalid: %4$u',
						'w3-total-cache'
					),
					esc_attr( $successful ),
					esc_attr( $skipped ),
					esc_attr( $errored ),
					esc_attr( $invalid )
				);
			}

			?>
				</p>
			</div>
			<?php

		} elseif ( ! empty( Util_Request::get_string( 'w3tc_imageservice_reverted' ) ) ) {
			?>
			<script>history.pushState( null, '', location.href.split( '?' )[0] );</script>

			<div class="updated notice notice-success is-dismissible"><p>Total Cache Image Converter</p>
				<p><?php esc_html_e( 'All selected optimizations have been reverted.', 'w3-total-cache' ); ?></p>
			</div>
			<?php
		} elseif ( 'upload' === get_current_screen()->id ) {
			// Media Library: Get the display mode.
			$mode = get_user_option( 'media_library_mode', get_current_user_id() ) ?
				get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

			// If not in list mode, then print a notice to switch to it.
			if ( 'list' !== $mode ) {
				?>
				<div class="notice notice-warning is-dismissible"><p>Total Cache Image Converter -
				<?php
						printf(
							// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
							esc_html__( 'Switch to %1$slist mode%2$s for image format conversions.', 'w3-total-cache' ),
							'<a href="' . esc_attr( Util_Ui::admin_url( 'upload.php?mode=list' ) ) . '">',
							'</a>'
						);
				?>
					</p>
				</div>
				<?php
			} else {
				$this->check_wpcron();
			}
		} elseif ( $is_auto && 'media' === get_current_screen()->id ) {
			$this->check_wpcron();
		}
	}

	/**
	 * Submit images to the API for processing.
	 *
	 * @since 2.2.0
	 *
	 * @global $wp_filesystem
	 *
	 * @see Extension_ImageService_Plugin::get_api()
	 *
	 * @param array $post_ids Post ids.
	 * @return array
	 */
	/**
	 * Check if WEBP conversion already exists for an image.
	 *
	 * @since 2.2.0
	 *
	 * @param int $post_id Post id.
	 * @return bool True if WEBP conversion exists, false otherwise.
	 */
	private function has_webp_conversion( $post_id ) {
		$imageservice_data = get_post_meta( $post_id, 'w3tc_imageservice', true );

		// Check new format (post_children).
		if ( isset( $imageservice_data['post_children']['webp'] ) && ! empty( $imageservice_data['post_children']['webp'] ) ) {
			$child_id = $imageservice_data['post_children']['webp'];
			$child_data = get_post_meta( $child_id, 'w3tc_imageservice', true );
			if ( ! empty( $child_data['is_converted_file'] ) ) {
				return true;
			}
		}

		// Check old format (post_child).
		if ( isset( $imageservice_data['post_child'] ) && ! empty( $imageservice_data['post_child'] ) ) {
			$child_id = $imageservice_data['post_child'];
			$child_post = get_post( $child_id );
			if ( $child_post && 'image/webp' === $child_post->post_mime_type ) {
				$child_data = get_post_meta( $child_id, 'w3tc_imageservice', true );
				if ( ! empty( $child_data['is_converted_file'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Remove a single format optimization without deleting other converted formats.
	 *
	 * @since 2.2.0
	 *
	 * @param int    $post_id    Parent post id.
	 * @param string $format_key Format key (e.g. 'webp', 'avif').
	 * @return void
	 */
	private function remove_optimization_format( $post_id, $format_key ) {
		$postmeta = (array) get_post_meta( $post_id, 'w3tc_imageservice', true );

		// New structure: remove a specific format child attachment.
		if ( isset( $postmeta['post_children'] ) && is_array( $postmeta['post_children'] ) && isset( $postmeta['post_children'][ $format_key ] ) ) {
			$child_id = $postmeta['post_children'][ $format_key ];
			if ( $child_id ) {
				wp_delete_attachment( $child_id, true );
			}
			unset( $postmeta['post_children'][ $format_key ] );
		}

		// Old structure: if post_child is the requested format, remove it.
		if ( isset( $postmeta['post_child'] ) && $postmeta['post_child'] ) {
			$child_post = get_post( $postmeta['post_child'] );
			if ( $child_post && isset( $child_post->post_mime_type ) ) {
				$child_format_key = strtolower( str_replace( 'image/', '', $child_post->post_mime_type ) );
				if ( $child_format_key === $format_key ) {
					wp_delete_attachment( $postmeta['post_child'], true );
					unset( $postmeta['post_child'] );
				}
			}
		}

		// Clean related per-format metadata.
		if ( isset( $postmeta['downloads'] ) && is_array( $postmeta['downloads'] ) ) {
			unset( $postmeta['downloads'][ $format_key ] );
		}
		if ( isset( $postmeta['jobs_status'] ) && is_array( $postmeta['jobs_status'] ) ) {
			unset( $postmeta['jobs_status'][ $format_key ] );
		}
		if ( isset( $postmeta['processing_jobs'] ) && is_array( $postmeta['processing_jobs'] ) ) {
			unset( $postmeta['processing_jobs'][ $format_key ] );
		}

		update_post_meta( $post_id, 'w3tc_imageservice', $postmeta );
	}

	/**
	 * Submit images for conversion.
	 *
	 * @since 2.2.0
	 *
	 * @param array $post_ids Post ids.
	 * @return array
	 */
	public function submit_images( array $post_ids ) {
		// Check WP_Filesystem credentials.
		Util_WpFile::ajax_check_credentials(
			sprintf(
				// translators: 1: HTML achor open tag, 2: HTML anchor close tag.
				__( '%1$sLearn more%2$s.', 'w3-total-cache' ),
				'<a target="_blank" href="' . esc_url(
					'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=conversion_error&utm_campaign=imageservice#unable-to-connect-to-the-filesystem-error'
				) . '">',
				'</a>'
			)
		);

		global $wp_filesystem;

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
				++$stats['skipped'];
				continue;
			}

			// Check if WEBP already exists - if so, only request AVIF.
			$convert_options = array();
			if ( $this->has_webp_conversion( $post_id ) && Util_Environment::is_w3tc_pro( $this->config ) ) {
				// Only request AVIF since WEBP already exists.
				$convert_options['formats'] = array( 'image/avif' );
			}

			// Submit current image.
			$response = Extension_ImageService_Plugin::get_api()->convert( $filepath, $convert_options );
			++$stats['submitted'];

			if ( isset( $response['error'] ) ) {
				++$stats['errored'];
				continue;
			}

			// Handle new API2 response format with jobs array.
			$jobs = isset( $response['jobs'] ) && is_array( $response['jobs'] ) ? $response['jobs'] : array();

			// Backward compatibility: if no jobs array, check for single job_id/signature.
			if ( empty( $jobs ) ) {
				if ( empty( $response['job_id'] ) || empty( $response['signature'] ) ) {
					++$stats['invalid'];
					continue;
				}
				// Convert old format to new format for consistency.
				$jobs = array(
					array(
						'job_id'       => $response['job_id'],
						'signature'    => $response['signature'],
						'mime_type'    => 'image/webp', // Default for old format.
						'status'       => isset( $response['status'] ) ? $response['status'] : 'queued',
						'status_url'   => isset( $response['status_url'] ) ? $response['status_url'] : '',
						'download_url' => isset( $response['download_url'] ) ? $response['download_url'] : '',
					),
				);
			}

			if ( empty( $jobs ) ) {
				++$stats['invalid'];
				continue;
			}

			// Remove old optimizations unless we are only converting a single format.
			$requested_format = null;
			if ( isset( $convert_options['formats'] ) && is_array( $convert_options['formats'] ) && 1 === count( $convert_options['formats'] ) ) {
				$requested_format = reset( $convert_options['formats'] );
			}

			if ( $requested_format ) {
				$format_key = str_replace( 'image/', '', strtolower( $requested_format ) );
				// Only remove the requested format so other formats remain intact.
				$this->remove_optimization_format( $post_id, $format_key );
			} else {
				$this->remove_optimizations( $post_id );
			}

			// Get requested formats from convert options or settings to store in postmeta.
			$requested_formats = array();
			if ( isset( $convert_options['formats'] ) && is_array( $convert_options['formats'] ) ) {
				// Use formats from convert options (e.g., only AVIF if WEBP already exists).
				$requested_formats = $convert_options['formats'];
			} else {
				// Get requested formats from settings.
				$settings = $this->config->get_array( 'imageservice' );
				// Check webp setting - handle both boolean and string values, default to true if not set.
				$webp_enabled = ! isset( $settings['webp'] ) || ( true === $settings['webp'] || '1' === $settings['webp'] || 1 === $settings['webp'] );
				if ( $webp_enabled ) {
					$requested_formats[] = 'image/webp';
				}
				// Check avif setting - handle both boolean and string values, default to true if not set.
				// Only allow AVIF for Pro license holders.
				$avif_enabled = ! isset( $settings['avif'] ) || ( true === $settings['avif'] || '1' === $settings['avif'] || 1 === $settings['avif'] );
				if ( $avif_enabled && Util_Environment::is_w3tc_pro( $this->config ) ) {
					$requested_formats[] = 'image/avif';
				}
				// If no formats are selected, default to WebP for backward compatibility.
				if ( empty( $requested_formats ) ) {
					$requested_formats[] = 'image/webp';
				}
			}

			// Store jobs by format for easy lookup.
			// If we explicitly requested a single format, force the key to that format (API may not echo it back correctly).
			$jobs_by_format = array();
			$forced_mime_type = null;
			if ( isset( $convert_options['formats'] ) && is_array( $convert_options['formats'] ) && 1 === count( $convert_options['formats'] ) ) {
				$forced_mime_type = reset( $convert_options['formats'] );
			}

			if ( $forced_mime_type && ! empty( $jobs ) ) {
				$forced_key = str_replace( 'image/', '', strtolower( $forced_mime_type ) );
				$job0 = $jobs[0];
				$job0['mime_type'] = $forced_mime_type;
				$jobs_by_format[ $forced_key ] = $job0;
			} else {
				foreach ( $jobs as $job ) {
					$mime_type = null;
					if ( isset( $job['mime_type'] ) ) {
						$mime_type = $job['mime_type'];
					} elseif ( isset( $job['mimeTypeOut'] ) ) {
						$mime_type = $job['mimeTypeOut'];
					} elseif ( isset( $job['mime_type_out'] ) ) {
						$mime_type = $job['mime_type_out'];
					}

					// Some APIs may return arrays for mime type; use the first value.
					if ( is_array( $mime_type ) ) {
						$mime_type = reset( $mime_type );
					}

					if ( $mime_type && isset( $job['job_id'] ) && isset( $job['signature'] ) ) {
						$format_key = str_replace( 'image/', '', strtolower( $mime_type ) );
						$job['mime_type'] = $mime_type; // Normalize key for downstream usage.
						$jobs_by_format[ $format_key ] = $job;
					}
				}
			}

			// Save the job info and requested formats.
			self::update_postmeta(
				$post_id,
				array(
					'status'            => 'processing',
					'processing'        => $response, // Store full response for backward compatibility.
					'processing_jobs'   => $jobs_by_format, // Store jobs by format.
					'requested_formats' => $requested_formats,
					'jobs_status'       => array(),
				)
			);

			++$stats['successful'];
		}

		return $stats;
	}

	/**
	 * Revert optimizations of images.
	 *
	 * @since 2.2.0
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
	 * @since 2.2.0
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
		$postmeta = (array) get_post_meta( $post_id, 'w3tc_imageservice', true );
		$postmeta = array_merge( $postmeta, $data );

		return update_post_meta( $post_id, 'w3tc_imageservice', $postmeta );
	}

	/**
	 * Copy postmeta from one post to another.
	 *
	 * @since 2.2.0
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
		$postmeta = (array) get_post_meta( $post_id_1, 'w3tc_imageservice', true );

		// Do not copy "post_child" or "post_children".
		unset( $postmeta['post_child'] );
		unset( $postmeta['post_children'] );
		unset( $postmeta['downloads'] );

		return update_post_meta( $post_id_2, 'w3tc_imageservice', $postmeta );
	}

	/**
	 * Remove optimizations.
	 *
	 * @since 2.2.0
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_delete_attachment/
	 *
	 * @param int $post_id Parent post id.
	 * @return bool|WP_Post|false|null True if optimizations were removed, false or null on failure, or WP_Post for backward compatibility.
	 */
	public function remove_optimizations( $post_id ) {
		$result            = null;
		$has_optimizations = false;

		// Get child post ids.
		$postmeta = (array) get_post_meta( $post_id, 'w3tc_imageservice', true );

		// Handle multiple formats (new structure).
		if ( isset( $postmeta['post_children'] ) && is_array( $postmeta['post_children'] ) ) {
			foreach ( $postmeta['post_children'] as $format_key => $child_id ) {
				if ( $child_id ) {
					// Delete optimization.
					wp_delete_attachment( $child_id, true );
					$has_optimizations = true;
				}
			}
		}

		// Handle single format (backward compatibility).
		$child_id = isset( $postmeta['post_child'] ) ? $postmeta['post_child'] : null;
		if ( $child_id ) {
			// Delete optimization.
			$result = wp_delete_attachment( $child_id, true );
			$has_optimizations = true;
		}

		// Delete postmeta if there were any optimizations.
		if ( $has_optimizations || ! empty( $postmeta ) ) {
			delete_post_meta( $post_id, 'w3tc_imageservice' );
		}

		// Return true if optimizations were removed, or the result for backward compatibility.
		return $has_optimizations ? ( false !== $result ? true : $result ) : $result;
	}

	/**
	 * Handle auto-optimization on image upload.
	 *
	 * @since 2.2.0
	 *
	 * @param int $post_id Post id.
	 */
	public function auto_convert( $post_id ) {
		$settings = $this->config->get_array( 'imageservice' );
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
	 * @since 2.2.0
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

		return $delete;
	}

	/**
	 * AJAX: Submit an image for processing.
	 *
	 * @since 2.2.0
	 *
	 * @global $wp_filesystem
	 *
	 * @see Extension_ImageService_Plugin::get_api()
	 *
	 * @uses $_POST['post_id'] Post id.
	 */
	public function ajax_submit() {
		check_ajax_referer( 'w3tc_imageservice_submit' );

		// Check WP_Filesystem credentials.
		Util_WpFile::ajax_check_credentials(
			sprintf(
				// translators: 1: HTML achor open tag, 2: HTML anchor close tag.
				__( '%1$sLearn more%2$s.', 'w3-total-cache' ),
				'<a target="_blank" href="' . esc_url(
					'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=conversion_error&utm_campaign=imageservice#unable-to-connect-to-the-filesystem-error'
				) . '">',
				'</a>'
			)
		);

		// Check for post id.
		$post_id_val = Util_Request::get_integer( 'post_id' );
		$post_id     = ! empty( $post_id_val ) ? $post_id_val : null;

		if ( ! $post_id ) {
			wp_send_json_error(
				array(
					'error' => __( 'Missing input post id.', 'w3-total-cache' ),
				),
				400
			);
		}

		global $wp_filesystem;

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

		// Check if a specific format was requested (from data-format attribute).
		$format = Util_Request::get_string( 'format' );
		$convert_options = array();

		// If format is specified, only request that format.
		if ( 'avif' === $format && Util_Environment::is_w3tc_pro( $this->config ) ) {
			$convert_options['formats'] = array( 'image/avif' );
		} elseif ( 'webp' === $format ) {
			$convert_options['formats'] = array( 'image/webp' );
		} elseif ( $this->has_webp_conversion( $post_id ) && Util_Environment::is_w3tc_pro( $this->config ) ) {
			// If WEBP already exists and no format specified, only request AVIF.
			$convert_options['formats'] = array( 'image/avif' );
		}

		// Submit the job request.
		$response = Extension_ImageService_Plugin::get_api()->convert( $filepath, $convert_options );

		// Check for non-200 status code.
		if ( isset( $response['code'] ) && 200 !== $response['code'] ) {
			wp_send_json_error(
				$response,
				$response['code']
			);
		}

		// Check for error.
		if ( isset( $response['error'] ) ) {
			wp_send_json_error(
				$response,
				417
			);
		}

		// Handle new API2 response format with jobs array.
		$jobs = isset( $response['jobs'] ) && is_array( $response['jobs'] ) ? $response['jobs'] : array();

		// Backward compatibility: if no jobs array, check for single job_id/signature.
		if ( empty( $jobs ) ) {
			if ( empty( $response['job_id'] ) || empty( $response['signature'] ) ) {
				wp_send_json_error(
					array(
						'error' => __( 'Invalid API response.', 'w3-total-cache' ),
					),
					417
				);
			}
			// Convert old format to new format for consistency.
			$jobs = array(
				array(
					'job_id'       => $response['job_id'],
					'signature'    => $response['signature'],
					'mime_type'    => 'image/webp', // Default for old format.
					'status'       => isset( $response['status'] ) ? $response['status'] : 'queued',
					'status_url'   => isset( $response['status_url'] ) ? $response['status_url'] : '',
					'download_url' => isset( $response['download_url'] ) ? $response['download_url'] : '',
				),
			);
		}

		if ( empty( $jobs ) ) {
			wp_send_json_error(
				array(
					'error' => __( 'Invalid API response.', 'w3-total-cache' ),
				),
				417
			);
		}

		// Remove old optimizations unless we are only converting a single format.
		$requested_format = null;
		if ( isset( $convert_options['formats'] ) && is_array( $convert_options['formats'] ) && 1 === count( $convert_options['formats'] ) ) {
			$requested_format = reset( $convert_options['formats'] );
		}

		if ( $requested_format ) {
			$format_key = str_replace( 'image/', '', strtolower( $requested_format ) );
			// Only remove the requested format so other formats remain intact.
			$this->remove_optimization_format( $post_id, $format_key );
		} else {
			$this->remove_optimizations( $post_id );
		}

		// Get requested formats from convert options or settings to store in postmeta.
		$requested_formats = array();
		if ( isset( $convert_options['formats'] ) && is_array( $convert_options['formats'] ) ) {
			// Use formats from convert options (e.g., only AVIF if WEBP already exists).
			$requested_formats = $convert_options['formats'];
		} else {
			// Get requested formats from settings.
			$settings = $this->config->get_array( 'imageservice' );
			// Check webp setting - handle both boolean and string values, default to true if not set.
			$webp_enabled = ! isset( $settings['webp'] ) || ( true === $settings['webp'] || '1' === $settings['webp'] || 1 === $settings['webp'] );
			if ( $webp_enabled ) {
				$requested_formats[] = 'image/webp';
			}
			// Check avif setting - handle both boolean and string values, default to true if not set.
			// Only allow AVIF for Pro license holders.
			$avif_enabled = ! isset( $settings['avif'] ) || ( true === $settings['avif'] || '1' === $settings['avif'] || 1 === $settings['avif'] );
			if ( $avif_enabled && Util_Environment::is_w3tc_pro( $this->config ) ) {
				$requested_formats[] = 'image/avif';
			}
			// If no formats are selected, default to WebP for backward compatibility.
			if ( empty( $requested_formats ) ) {
				$requested_formats[] = 'image/webp';
			}
		}

		// Store jobs by format for easy lookup.
		// If we explicitly requested a single format, force the key to that format (API may not echo it back correctly).
		$jobs_by_format = array();
		$forced_mime_type = null;
		if ( isset( $convert_options['formats'] ) && is_array( $convert_options['formats'] ) && 1 === count( $convert_options['formats'] ) ) {
			$forced_mime_type = reset( $convert_options['formats'] );
		}

		if ( $forced_mime_type && ! empty( $jobs ) ) {
			$forced_key = str_replace( 'image/', '', strtolower( $forced_mime_type ) );
			$job0 = $jobs[0];
			$job0['mime_type'] = $forced_mime_type;
			$jobs_by_format[ $forced_key ] = $job0;
		} else {
			foreach ( $jobs as $job ) {
				$mime_type = null;
				if ( isset( $job['mime_type'] ) ) {
					$mime_type = $job['mime_type'];
				} elseif ( isset( $job['mimeTypeOut'] ) ) {
					$mime_type = $job['mimeTypeOut'];
				} elseif ( isset( $job['mime_type_out'] ) ) {
					$mime_type = $job['mime_type_out'];
				}

				if ( is_array( $mime_type ) ) {
					$mime_type = reset( $mime_type );
				}

				if ( $mime_type && isset( $job['job_id'] ) && isset( $job['signature'] ) ) {
					$format_key = str_replace( 'image/', '', strtolower( $mime_type ) );
					$job['mime_type'] = $mime_type;
					$jobs_by_format[ $format_key ] = $job;
				}
			}
		}

		// Save the job info and requested formats.
		self::update_postmeta(
			$post_id,
			array(
				'status'            => 'processing',
				'processing'        => $response, // Store full response for backward compatibility.
				'processing_jobs'   => $jobs_by_format, // Store jobs by format.
				'requested_formats' => $requested_formats,
				'jobs_status'       => array(),
			)
		);

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Get the status of an image, from postmeta.
	 *
	 * @since 2.2.0
	 *
	 * @uses $_POST['post_id'] Post id.
	 */
	public function ajax_get_postmeta() {
		check_ajax_referer( 'w3tc_imageservice_postmeta' );

		$post_id_val = Util_Request::get_integer( 'post_id' );
		$post_id     = ! empty( $post_id_val ) ? $post_id_val : null;

		if ( $post_id ) {
			wp_send_json_success( (array) get_post_meta( $post_id, 'w3tc_imageservice', true ) );
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
	 * @since 2.2.0
	 *
	 * @uses $_POST['post_id'] Parent post id.
	 */
	public function ajax_revert() {
		check_ajax_referer( 'w3tc_imageservice_revert' );

		$post_id_val = Util_Request::get_integer( 'post_id' );
		$post_id     = ! empty( $post_id_val ) ? $post_id_val : null;

		if ( $post_id ) {
			// Check if there are any optimizations to revert.
			$postmeta = (array) get_post_meta( $post_id, 'w3tc_imageservice', true );
			$has_optimizations = false;

			if ( isset( $postmeta['post_children'] ) && is_array( $postmeta['post_children'] ) && ! empty( $postmeta['post_children'] ) ) {
				$has_optimizations = true;
			} elseif ( isset( $postmeta['post_child'] ) && $postmeta['post_child'] ) {
				$has_optimizations = true;
			}

			if ( ! $has_optimizations ) {
				wp_send_json_error(
					array(
						'error' => __( 'No converted images found to revert.', 'w3-total-cache' ),
					),
					404
				);
				return;
			}

			$this->remove_optimizations( $post_id );

			// Always return success if we had optimizations to remove.
			wp_send_json_success( array( 'removed' => true ) );
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
	 * AJAX: Convert all images.
	 *
	 * @since 2.2.0
	 *
	 * @see self::get_eligible_attachments()
	 * @see self::submit_images()
	 */
	public function ajax_convert_all() {
		check_ajax_referer( 'w3tc_imageservice_submit' );

		$results = $this->get_eligible_attachments();

		$post_ids = array();

		// Allow plenty of time to complete.
		ignore_user_abort( true );
		set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		foreach ( $results->posts as $post_id ) {
			$post_ids[] = $post_id;
		}

		$stats = $this->submit_images( $post_ids );

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Revert all converted images.
	 *
	 * @since 2.2.0
	 *
	 * @see self::get_imageservice_attachments()
	 * @see self::remove_optimizations()
	 */
	public function ajax_revert_all() {
		check_ajax_referer( 'w3tc_imageservice_submit' );

		$results = $this->get_imageservice_attachments();

		$revert_count = 0;

		// Allow plenty of time to complete.
		ignore_user_abort( true );
		set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		foreach ( $results->posts as $post_id ) {
			if ( $this->remove_optimizations( $post_id ) ) {
				++$revert_count;
			}
		}

		wp_send_json_success( array( 'revert_count' => $revert_count ) );
	}

	/**
	 * AJAX: Get image counts by status.
	 *
	 * @since 2.2.0
	 *
	 * @see get_counts()
	 */
	public function ajax_get_counts() {
		check_ajax_referer( 'w3tc_imageservice_submit' );

		wp_send_json_success( $this->get_counts() );
	}

	/**
	 * AJAX: Get image API usage.
	 *
	 * @since 2.2.0
	 *
	 * @see Extension_ImageService_Plugin::get_api()
	 * @see Extension_ImageService_Api::get_usage()
	 */
	public function ajax_get_usage() {
		check_ajax_referer( 'w3tc_imageservice_submit' );

		wp_send_json_success( Extension_ImageService_Plugin::get_api()->get_usage( true ) );
	}

	/**
	 * Check if WP Cron is working as expected and print an error notice if not.
	 *
	 * @since 2.8.0
	 *
	 * @see Util_Environment::is_wpcron_working()
	 *
	 * @return bool
	 */
	public function check_wpcron(): bool {
		if ( ! self::$wpcron_notice_printed && ! Util_Environment::is_wpcron_working() ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					printf(
						// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
						esc_html__( 'WP Cron is not working as expected, which is required for image format conversions.  %2$sLearn more%3$s.', 'w3-total-cache' ),
						'W3 Total Cache',
						'<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/enable-wp-cron/?utm_source=w3tc&utm_medium=wp_cron&utm_campaign=imageservice' ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<?php

			self::$wpcron_notice_printed = true;

			return false;
		} else {
			return true;
		}
	}
}
