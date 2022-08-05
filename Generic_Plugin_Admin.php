<?php
namespace W3TC;

/**
 * class Generic_Plugin_Admin
 */
class Generic_Plugin_Admin {
	/**
	 * Current page
	 *
	 * @var string
	 */
	private $_page = 'w3tc_dashboard';

	/**
	 * Config
	 */
	private $_config = null;

	private $is_w3tc_page;
	// filled with message data (see Util_Admin::redirect*)
	// if w3tc_message is passed
	private $w3tc_message = null;



	function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs plugin
	 */
	function run() {
		$this->is_w3tc_page = Util_Admin::is_w3tc_admin_page();

		add_action( 'admin_init', array(
				$this,
				'admin_init'
			) );
		add_action( 'admin_init_w3tc_dashboard', array(
				'\W3TC\Generic_WidgetServices',
				'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array(
			'\W3TC\Generic_WidgetCommunity',
			'admin_init_w3tc_dashboard' ) );
		add_action( 'admin_init_w3tc_dashboard', array(
				'\W3TC\Generic_WidgetBoldGrid',
				'admin_init_w3tc_dashboard' ) );

		add_action( 'admin_enqueue_scripts', array(
				$this,
				'admin_enqueue_scripts'
			) );
		add_action( 'admin_print_styles-toplevel_page_w3tc_dashboard', array(
				'\W3TC\Generic_Page_Dashboard',
				'admin_print_styles_w3tc_dashboard'
			) );
		add_action( 'wp_ajax_w3tc_ajax', array(
				$this,
				'wp_ajax_w3tc_ajax'
			) );
		add_action( 'wp_ajax_w3tc_monitoring_score', array(
				$this,
				'wp_ajax_w3tc_monitoring_score'
			) );

		add_action( 'admin_head', array(
				$this,
				'admin_head'
			) );

		if ( is_network_admin() ) {
			add_action( 'network_admin_menu', array(
					$this,
					'network_admin_menu'
				) );
			add_filter( 'network_admin_plugin_action_links_' . W3TC_FILE, array(
					$this,
					'plugin_action_links'
				) );
		} else {
			add_action( 'admin_menu', array(
					$this,
					'admin_menu'
				) );
			add_filter( 'plugin_action_links_' . W3TC_FILE, array(
					$this,
					'plugin_action_links'
				) );
		}

		add_filter( 'favorite_actions', array(
				$this,
				'favorite_actions'
			) );

		add_action( 'in_plugin_update_message-' . W3TC_FILE, array(
				$this,
				'in_plugin_update_message'
			) );

		if ( $this->_config->get_boolean( 'pgcache.enabled' ) || $this->_config->get_boolean( 'minify.enabled' ) ) {
			add_filter( 'pre_update_option_active_plugins', array(
					$this,
					'pre_update_option_active_plugins'
				) );
		}

		$config_labels = new Generic_ConfigLabels();
		add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		$admin_notes = new Generic_AdminNotes();
		add_filter( 'w3tc_notes', array( $admin_notes, 'w3tc_notes' ) );
		add_filter( 'w3tc_errors', array( $admin_notes, 'w3tc_errors' ), 1000 );

		add_action( 'w3tc_ajax_faq', array(
				$this,
				'w3tc_ajax_faq'
			) );

		// load w3tc_message
		$message_id = Util_Request::get_string( 'w3tc_message' );
		if ( $message_id ) {
			$v = get_transient( 'w3tc_message' );

			if ( isset( $v[$message_id] ) ) {
				$this->w3tc_message = $v[$message_id];
				delete_transient( 'w3tc_message' );
			}
		}

		// should be in Support_PluginAdmin, but saving loading file by being here
		add_action( 'admin_print_scripts-performance_page_w3tc_support', array(
				'\W3TC\Support_Page',
				'admin_print_scripts_w3tc_support'
			) );
	}

	/**
	 * Load action
	 *
	 * @return void
	 */
	function load() {
		$this->add_help_tabs();
		$this->_page = Util_Admin::get_current_page();

		// run plugin action
		$action = false;
		foreach ( $_REQUEST as $key => $value ) {
			if ( substr( $key, 0, 5 ) == 'w3tc_' ) {
				$action = $key;
				break;
			}
		}

		$executor = new Root_AdminActions();

		if ( $action && $executor->exists( $action ) ) {
			if ( !wp_verify_nonce( Util_Request::get_string( '_wpnonce' ), 'w3tc' ) )
				wp_nonce_ays( 'w3tc' );

			try {
				$executor->execute( $action );
			} catch ( \Exception $e ) {
				$key = 'admin_action_failed_' . $action;
				Util_Admin::redirect_with_custom_messages( array(),
					array( $key => $e->getMessage() ) );
			}

			exit();
		}
	}

	public function wp_ajax_w3tc_ajax() {
		if ( !wp_verify_nonce( Util_Request::get_string( '_wpnonce' ), 'w3tc' ) )
			wp_nonce_ays( 'w3tc' );

		try {
			$base_capability = apply_filters( 'w3tc_ajax_base_capability_', 'manage_options' );
			$capability = apply_filters( 'w3tc_ajax_capability_' . Util_Request::get_string( 'w3tc_action' ),
				$base_capability );
			if ( !empty( $capability ) && !current_user_can( $capability ) )
				throw new \Exception( 'no permissions' );

			do_action( 'w3tc_ajax' );
			do_action( 'w3tc_ajax_' . Util_Request::get_string( 'w3tc_action' ) );
		} catch ( \Exception $e ) {
			echo esc_html( $e->getMessage() );
		}

		exit();
	}

	public function wp_ajax_w3tc_monitoring_score() {
		if ( !$this->_config->get_boolean( 'widget.pagespeed.show_in_admin_bar' ) )
			exit();

		$score = '';

		$modules = Dispatcher::component( 'ModuleStatus' );
		$score = apply_filters( 'w3tc_monitoring_score', $score );

		header( "Content-Type: application/x-javascript; charset=UTF-8" );
		echo 'document.getElementById("w3tc_monitoring_score") && ( document.getElementById("w3tc_monitoring_score").innerHTML = "' .
			esc_html( strtr( $score, '"', '.' ) ) . '" );';

		exit();
	}

	/**
	 * Admin init
	 *
	 * @return void
	 */
	function admin_init() {
		// special handling for deactivation link, it's plugins.php file
		if ( Util_Request::get_string( 'action' ) == 'w3tc_deactivate_plugin' ) {
			Util_Activation::deactivate_plugin();
		}

		$page_val = Util_Request::get_string( 'page' );
		if ( ! empty( $page_val ) ) {
			do_action( 'admin_init_' . $page_val );
		}
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_register_style( 'w3tc-options', plugins_url( 'pub/css/options.css', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_style( 'w3tc-lightbox', plugins_url( 'pub/css/lightbox.css', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_style( 'w3tc-widget', plugins_url( 'pub/css/widget.css', W3TC_FILE ), array(), W3TC_VERSION );

		wp_register_script( 'w3tc-metadata', plugins_url( 'pub/js/metadata.js', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_script( 'w3tc-options', plugins_url( 'pub/js/options.js', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_script( 'w3tc-lightbox', plugins_url( 'pub/js/lightbox.js', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_script( 'w3tc-widget', plugins_url( 'pub/js/widget.js', W3TC_FILE ), array(), W3TC_VERSION );
		wp_register_script( 'w3tc-jquery-masonry', plugins_url( 'pub/js/jquery.masonry.min.js', W3TC_FILE ), array( 'jquery' ), W3TC_VERSION );

		// New feature count for the Feature Showcase.
		wp_register_script( 'w3tc-feature-counter', plugins_url( 'pub/js/feature-counter.js', W3TC_FILE ), array(), W3TC_VERSION, true );

		wp_localize_script(
			'w3tc-feature-counter',
			'W3TCFeatureShowcaseData',
			array(
				'unseenCount' => FeatureShowcase_Plugin_Admin::get_unseen_count(),
			)
		);

		wp_enqueue_script( 'w3tc-feature-counter' );

		// Messages.
		if ( !is_null( $this->w3tc_message ) &&
			isset( $this->w3tc_message['actions'] ) &&
			is_array( $this->w3tc_message['actions'] ) ) {
			foreach ( $this->w3tc_message['actions'] as $action )
				do_action( 'w3tc_message_action_' . $action );
		}
		// for testing.
		$w3tc_message_action_val = Util_Request::get_string( 'w3tc_message_action' );
		if ( ! empty( $w3tc_message_action_val ) ) {
			do_action( 'w3tc_message_action_' . $w3tc_message_action_val );
		}
	}

	// Define icon styles for the custom post type
	function admin_head() {
		$page = Util_Request::get_string( 'page', null );

		if ( ( ! is_multisite() || is_super_admin() ) && false !== strpos( $page, 'w3tc' ) && 'w3tc_setup_guide' !== $page && ! get_site_option( 'w3tc_setupguide_completed' ) ) {
			$config       = new Config();
			$state_master = Dispatcher::config_state_master();

			if ( ! $config->get_boolean( 'pgcache.enabled' ) && $state_master->get_integer( 'common.install' ) > strtotime( 'NOW - 1 WEEK' ) ) {
				wp_redirect( esc_url( network_admin_url( 'admin.php?page=w3tc_setup_guide' ) ) );
			}
		}

		if ( 'w3tc_dashboard' === $page ) {
?>
			<script type="text/javascript">
			jQuery(function() {
				jQuery('#normal-sortables').masonry({
					itemSelector: '.postbox'
				});
			});
			</script>
			<?php
		}

		if ( $this->_config->get_boolean( 'common.track_usage' ) && $this->is_w3tc_page ) {
			$current_user = wp_get_current_user();
			$page = Util_Request::get_string( 'page' );
			if ( $page == 'w3tc_extensions' )
				$page = 'extensions/' . Util_Request::get_string( 'extension' );

			if ( defined( 'W3TC_DEBUG' ) && W3TC_DEBUG )
				$profile = 'UA-2264433-7';
			else
				$profile = 'UA-2264433-8';

			$state = Dispatcher::config_state();
?>
			<script type="text/javascript">
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','https://api.w3-edge.com/v1/analytics','w3tc_ga');

			if (window.w3tc_ga) {
				w3tc_ga('create', '<?php echo esc_html( $profile ); ?>', 'auto');
				w3tc_ga('set', {
					'dimension1': 'w3-total-cache',
					'dimension2': '<?php echo esc_html( W3TC_VERSION ); ?>',
					'dimension3': '<?php global $wp_version; echo esc_html( $wp_version ); ?>',
					'dimension4': 'php<?php echo esc_html( phpversion() ); ?>',
					'dimension5': '<?php echo esc_attr( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '' ); ?>',
					'dimension6': 'mysql<?php global $wpdb; echo esc_attr( $wpdb->db_version() ); ?>',
					'dimension7': '<?php echo esc_url( Util_Environment::home_url_host() ); ?>',
					'dimension9': '<?php echo esc_attr( $state->get_string( 'common.install_version' ) ); ?>',
					'dimension10': '<?php echo esc_attr( Util_Environment::w3tc_edition( $this->_config ) ); ?>',
					'dimension11': '<?php echo esc_attr( Util_Widget::list_widgets() ); ?>',

					'page': '<?php echo esc_attr( $page ); ?>'
				});

				w3tc_ga('send', 'pageview');
			}

			</script>
			<?php
		}

?>
		<style type="text/css" media="screen">
		li.toplevel_page_w3tc_dashboard .wp-menu-image:before{
			content:'\0041';
			top: 2px;
			font-family: 'w3tc';
		}
		</style>
		<script>
		jQuery(document).ready( function($) {
			$('#toplevel_page_w3tc_dashboard ul li').find('a[href*="w3tc_faq"]')
				.prop('target','_blank')
				.prop('href', <?php echo json_encode(W3TC_FAQ_URL) ?>);
		});
		</script>
		<?php
	}


	function network_admin_menu() {
		$this->_admin_menu( 'manage_network_options' );
	}

	function admin_menu() {
		$this->_admin_menu( 'manage_options' );
	}

	/**
	 * Admin menu
	 *
	 * @return void
	 */
	private function _admin_menu( $base_capability ) {
		$base_capability = apply_filters( 'w3tc_capability_menu',
			$base_capability );

		if ( current_user_can( $base_capability ) ) {
			$menus = Dispatcher::component( 'Root_AdminMenu' );
			$submenu_pages = $menus->generate( $base_capability );

			/**
			 * Only admin can modify W3TC settings
			 */
			foreach ( $submenu_pages as $submenu_page ) {
				add_action( 'load-' . $submenu_page,
					array( $this, 'load' ) );

				add_action( 'admin_print_styles-' . $submenu_page,
					array( $this, 'admin_print_styles' ) );

				add_action( 'admin_print_scripts-' . $submenu_page,
					array( $this, 'admin_print_scripts' ) );
			}

			global $pagenow;
			if ( $pagenow == 'plugins.php' ) {
				add_action( 'admin_print_scripts', array( $this, 'load_plugins_page_js' ) );
				add_action( 'admin_print_styles', array( $this, 'print_plugins_page_css' ) );
			}

			global $pagenow;
			if ( 'plugins.php' === $pagenow || $this->is_w3tc_page ||
				! empty( Util_Request::get_string( 'w3tc_note' ) ) ||
				! empty( Util_Request::get_string( 'w3tc_error' ) ) ||
				! empty( Util_Request::get_string( 'w3tc_message' ) ) ) {
				/**
				 * Only admin can see W3TC notices and errors
				 */
				add_action( 'admin_notices', array(
						$this,
						'admin_notices'
					) );
				add_action( 'network_admin_notices', array(
						$this,
						'admin_notices'
					) );
			}
		}
	}

	/**
	 * Print styles
	 *
	 * @return void
	 */
	function admin_print_styles() {
		wp_enqueue_style( 'w3tc-options' );
		wp_enqueue_style( 'w3tc-lightbox' );
	}

	/**
	 * Print scripts.
	 */
	function admin_print_scripts() {
		wp_enqueue_script( 'w3tc-metadata' );
		wp_enqueue_script( 'w3tc-options' );
		wp_enqueue_script( 'w3tc-lightbox' );

		if ( $this->is_w3tc_page ) {
			wp_localize_script(
				'w3tc-options',
				'w3tc_nonce',
				array( wp_create_nonce( 'w3tc' ) )
			);

			wp_localize_script(
				'w3tc-options',
				'w3tcData',
				array(
					'cdnEnabled'       => $this->_config->get_boolean( 'cdn.enabled' ),
					'cdnEngine'        => $this->_config->get_string( 'cdn.engine' ),
					'cdnFlushManually' => $this->_config->get_boolean( 'cdn.flush_manually' ),
					'cfWarning'        => wp_kses(
						sprintf(
							// translators: 1: HTML break, 2: HTML anchor open tag, 3: HTML anchor close tag, 4: HTML anchor open tag.
							__(
								'Please see %2$sAmazon\'s CloudFront documentation -- Paying for file invalidation%3$s:%1$sThe first 1,000 invalidation paths that you submit per month are free; you pay for each invalidation path over 1,000 in a month.%1$sYou can disable automatic purging by enabling %4$sOnly purge CDN manually%3$s.',
								'w3-total-cache'
							),
							'<br />',
							'<a target="_blank" href="https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/Invalidation.html#PayingForInvalidation">',
							'</a>',
							'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_cdn#advanced' ) ) . '">'
						),
						array(
							'a'  => array(
								'target' => array(),
								'href'   => array(),
							),
							'br' => array(),
						)
					),
				)
			);
		}

		switch ( $this->_page ) {
		case 'w3tc_minify':
		case 'w3tc_cachegroups':
			wp_enqueue_script(
				'w3tc_cachegroups',
				plugins_url( 'CacheGroups_Plugin_Admin_View.js', W3TC_FILE ),
				array(
					'jquery',
					'jquery-ui-sortable',
				),
				W3TC_VERSION,
				true
			);
		case 'w3tc_cdn':
			wp_enqueue_script( 'jquery-ui-sortable' );
			break;
		}
		if ( $this->_page=='w3tc_cdn' )
			wp_enqueue_script( 'jquery-ui-dialog' );
		if ( $this->_page=='w3tc_dashboard' )
			wp_enqueue_script( 'w3tc-jquery-masonry' );
	}


	function load_plugins_page_js() {
		wp_enqueue_script( 'w3tc-options' );
	}

	function print_plugins_page_css() {
		echo "
			<style type=\"text/css\">
			.w3tc-missing-files ul {
				margin-left: 20px;
				list-style-type: disc;
			}
			#w3tc {
				padding: 0;
			}
			#w3tc span {
				font-size: 0.6em;
				font-style: normal;
				text-shadow: none;
			}
			ul.w3tc-incomp-plugins, ul.w3-bullet-list {
				list-style: disc outside;
				margin-left: 17px;
				margin-top: 0;
				margin-bottom: 0;
			}
			ul.w3tc-incomp-plugins li div {
				width: 170px;
				display: inline-block;
			}
			</style>";
	}

	/**
	 * Contextual help list filter
	 *
	 * @param string  $list
	 * @return string
	 */
	function add_help_tabs() {
		$screen = get_current_screen();
		$sections = Generic_Faq::sections();
		$n = 0;

		foreach ( $sections as $section => $data ) {
			$content = '<div class="w3tchelp_content" data-section="' .
				$section . '"></div>';

			$screen->add_help_tab( array(
					'id' => 'w3tc_faq_' . $n,
					'title' => $section,
					'content' => $content
				) );
			$n++;
		}
	}

	public function w3tc_ajax_faq() {
		$section = Util_Request::get_string( 'section' );

		$entries = Generic_Faq::parse( $section );
		$response = array();

		ob_start();
		include W3TC_DIR . '/Generic_Plugin_Admin_View_Faq.php';
		$content = ob_get_contents();
		ob_end_clean();

		echo json_encode( array( 'content' => $content ) );
	}



	/**
	 * Plugin action links filter
	 *
	 * @param array   $links
	 * @return array
	 */
	function plugin_action_links( $links ) {
		array_unshift( $links,
			'<a class="edit" href="admin.php?page=w3tc_general">Settings</a>' );
		array_unshift( $links,
			'<a class="edit" style="color: red" href="admin.php?page=w3tc_support">Premium Support</a>' );


		if ( !is_writable( WP_CONTENT_DIR ) ||
			!is_writable( Util_Rule::get_browsercache_rules_cache_path() ) ) {
			$delete_link = '<a href="' .
				wp_nonce_url( admin_url( 'plugins.php?action=w3tc_deactivate_plugin' ), 'w3tc' ) .
				'">Uninstall</a>';
			array_unshift( $links, $delete_link );
		}

		return $links;
	}

	/**
	 * favorite_actions filter
	 *
	 * @param array   $actions
	 * @return array
	 */
	function favorite_actions( $actions ) {
		$actions[wp_nonce_url( admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_all' ), 'w3tc' )] = array(
			__( 'Empty Caches', 'w3-total-cache' ),
			apply_filters( 'w3tc_capability_favorite_action_flush_all', 'manage_options' )
		);

		return $actions;
	}

	/**
	 * Active plugins pre update option filter
	 *
	 * @param string  $new_value
	 * @return string
	 */
	function pre_update_option_active_plugins( $new_value ) {
		$old_value = (array) get_option( 'active_plugins' );

		if ( $new_value !== $old_value && in_array( W3TC_FILE, (array) $new_value ) && in_array( W3TC_FILE, (array) $old_value ) ) {
			$state_note = Dispatcher::config_state_note();
			$state_note->set( 'common.show_note.plugins_updated', true );
		}

		return $new_value;
	}

	/**
	 * Show plugin changes
	 *
	 * @return void
	 */
	function in_plugin_update_message() {
		$response = Util_Http::get( W3TC_README_URL );

		if ( is_wp_error( $response ) || $response['response']['code'] != 200 )
			return;

		$matches = null;
		$regexp = '~==\s*Changelog\s*==\s*=\s*[0-9.]+\s*=(.*)(=\s*' . preg_quote( W3TC_VERSION ) . '\s*=|$)~Uis';

		$body = $response['body'];
		if ( !preg_match( $regexp, $body, $matches ) )
			return;

		$changelog = (array) preg_split( '~[\r\n]+~', trim( $matches[1] ) );

		echo '<div style="color: #f00;">' . esc_html__( 'Take a minute to update, here\'s why:', 'w3-total-cache' ) . '</div><div style="font-weight: normal;height:300px;overflow:auto">';
		$ul = false;

		foreach ( $changelog as $index => $line ) {
			if ( preg_match( '~^\s*\*\s*~', $line ) ) {
				if ( !$ul ) {
					echo '<ul style="list-style: disc; margin-left: 20px;margin-top:0;">';
					$ul = true;
				}
				$line = preg_replace( '~^\s*\*\s*~', '', htmlspecialchars( $line ) );
				echo '<li style="width: 50%; margin: 0; float: left; ' . ( $index % 2 == 0 ? 'clear: left;' : '' ) . '">' . esc_html( $line ) . '</li>';
			} else {
				if ( $ul ) {
					echo '</ul><div style="clear: left;"></div>';
					$ul = false;
				}
			}
		}

		if ( $ul )
			echo '</ul><div style="clear: left;"></div>';

		echo '</div>';
	}

	/**
	 * Admin notices action
	 *
	 * @return void
	 */
	function admin_notices() {
		$cookie_domain = Util_Admin::get_cookie_domain();

		$error_messages = array(
			'fancy_permalinks_disabled_pgcache' => sprintf( __( 'Fancy permalinks are disabled. Please %s it first, then re-attempt to enabling enhanced disk mode.', 'w3-total-cache' ), Util_Ui::button_link( 'enable', 'options-permalink.php' ) ),
			'fancy_permalinks_disabled_browsercache' => sprintf( __( 'Fancy permalinks are disabled. Please %s it first, then re-attempt to enabling the \'Do not process 404 errors for static objects with WordPress\'.', 'w3-total-cache' ), Util_Ui::button_link( 'enable', 'options-permalink.php' ) ),
			'support_request' => __( 'Failed to send support request.', 'w3-total-cache' ),
			'support_request_type' => __( 'Please select request type.', 'w3-total-cache' ),
			'support_request_url' => __( 'Please enter the address of the site in the site <acronym title="Uniform Resource Locator">URL</acronym> field.', 'w3-total-cache' ),
			'support_request_name' => __( 'Please enter your name in the Name field', 'w3-total-cache' ),
			'support_request_email' => __( 'Please enter valid email address in the E-Mail field.', 'w3-total-cache' ),
			'support_request_phone' => __( 'Please enter your phone in the phone field.', 'w3-total-cache' ),
			'support_request_subject' => __( 'Please enter subject in the subject field.', 'w3-total-cache' ),
			'support_request_description' => __( 'Please describe the issue in the issue description field.', 'w3-total-cache' ),
			'support_request_wp_login' => __( 'Please enter an administrator login. Create a temporary one just for this support case if needed.', 'w3-total-cache' ),
			'support_request_wp_password' => __( 'Please enter WP Admin password, be sure it\'s spelled correctly.', 'w3-total-cache' ),
			'support_request_ftp_host' => __( 'Please enter <acronym title="Secure Shell">SSH</acronym> or <acronym title="File Transfer Protocol">FTP</acronym> host for the site.', 'w3-total-cache' ),
			'support_request_ftp_login' => __( 'Please enter <acronym title="Secure Shell">SSH</acronym> or <acronym title="File Transfer Protocol">FTP</acronym> login for the server. Create a temporary one just for this support case if needed.', 'w3-total-cache' ),
			'support_request_ftp_password' => __( 'Please enter <acronym title="Secure Shell">SSH</acronym> or <acronym title="File Transfer Protocol">FTP</acronym> password for the <acronym title="File Transfer Protocol">FTP</acronym> account.', 'w3-total-cache' ),
			'support_request' => __( 'Unable to send the support request.', 'w3-total-cache' ),
			'config_import_no_file' => __( 'Please select config file.', 'w3-total-cache' ),
			'config_import_upload' => __( 'Unable to upload config file.', 'w3-total-cache' ),
			'config_import_import' => __( 'Configuration file could not be imported.', 'w3-total-cache' ),
			'config_reset' => sprintf( __( 'Default settings could not be restored. Please run <strong>chmod 777 %s</strong> to make the configuration file write-able, then try again.', 'w3-total-cache' ), W3TC_CONFIG_DIR ),
			'cdn_purge_attachment' => __( 'Unable to purge attachment.', 'w3-total-cache' ),
			'pgcache_purge_post' => __( 'Unable to purge post.', 'w3-total-cache' ),
			'enable_cookie_domain' => sprintf( __( '<strong>%swp-config.php</strong> could not be written, please edit config and add:<br /><strong style="color:#f00;">define(\'COOKIE_DOMAIN\', \'%s\');</strong> before <strong style="color:#f00;">require_once(ABSPATH . \'wp-settings.php\');</strong>.', 'w3-total-cache' ), ABSPATH, addslashes( $cookie_domain ) ),
			'disable_cookie_domain' => sprintf( __( '<strong>%swp-config.php</strong> could not be written, please edit config and add:<br /><strong style="color:#f00;">define(\'COOKIE_DOMAIN\', false);</strong> before <strong style="color:#f00;">require_once(ABSPATH . \'wp-settings.php\');</strong>.', 'w3-total-cache' ), ABSPATH ),
			'pull_zone' => __( 'Pull Zone could not be automatically created.', 'w3-total-cache' )
		);

		$note_messages = array(
			'config_save' => __( 'Plugin configuration successfully updated.', 'w3-total-cache' ),
			'flush_all' => __( 'All caches successfully emptied.', 'w3-total-cache' ),
			'flush_memcached' => __( 'Memcached cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_opcode' => __( 'Opcode cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_file' => __( 'Disk cache(s) successfully emptied.', 'w3-total-cache' ),
			'flush_pgcache' => __( 'Page cache successfully emptied.', 'w3-total-cache' ),
			'flush_dbcache' => __( 'Database cache successfully emptied.', 'w3-total-cache' ),
			'flush_objectcache' => __( 'Object cache successfully emptied.', 'w3-total-cache' ),
			'flush_fragmentcache' => __( 'Fragment cache successfully emptied.', 'w3-total-cache' ),
			'flush_minify' => __( 'Minify cache successfully emptied.', 'w3-total-cache' ),
			'flush_browser_cache' => __( 'Media Query string has been successfully updated.', 'w3-total-cache' ),
			'flush_varnish' => __( 'Varnish servers successfully purged.', 'w3-total-cache' ),
			'flush_cdn' => __( '<acronym title="Content Delivery Network">CDN</acronym> was successfully purged.', 'w3-total-cache' ),
			'support_request' => __( 'The support request has been successfully sent.', 'w3-total-cache' ),
			'config_import' => __( 'Settings successfully imported.', 'w3-total-cache' ),
			'config_reset' => __( 'Settings successfully restored.', 'w3-total-cache' ),
			'preview_enable' => __( 'Preview mode was successfully enabled', 'w3-total-cache' ),
			'preview_disable' => __( 'Preview mode was successfully disabled', 'w3-total-cache' ),
			'preview_deploy' => __( 'Preview settings successfully deployed. Preview mode remains enabled until it\'s disabled. Continue testing new settings or disable preview mode if done.', 'w3-total-cache' ),
			'cdn_purge_attachment' => __( 'Attachment successfully purged.', 'w3-total-cache' ),
			'pgcache_purge_post' => __( 'Post successfully purged.', 'w3-total-cache' ),
			'new_relic_save' => __( 'New relic settings have been updated.', 'w3-total-cache' ),
			'add_in_removed' => __( 'The add-in has been removed.', 'w3-total-cache' ),
			'enabled_edge' => __( 'Edge mode has been enabled.', 'w3-total-cache' ),
			'disabled_edge' => __( 'Edge mode has been disabled.', 'w3-total-cache' ),
			'pull_zone' => __( 'Pull Zone was automatically created.', 'w3-total-cache' )
		);

		$errors = array();
		$notes = array();
		$environment_error_present = false;

		$error = Util_Request::get_string( 'w3tc_error' );
		if ( isset( $error_messages[$error] ) )
			$errors[$error] = $error_messages[$error];

		$note = Util_Request::get_string( 'w3tc_note' );
		if ( isset( $note_messages[$note] ) )
			$notes[$note] = $note_messages[$note];

		// print errors happened during last request execution,
		// when we decided to redirect with error message instead of
		// printing it directly (to avoid reexecution on refresh)
		if ( !is_null( $this->w3tc_message ) ) {
			$v = $this->w3tc_message;
			if ( isset( $v['errors'] ) && is_array( $v['errors'] ) ) {
				foreach ( $v['errors'] as $error ) {
					if ( isset( $error_messages[$error] ) )
						$errors[] = $error_messages[$error];
					else
						$errors[] = $error;
				}
			}
			if ( isset( $v['notes'] ) && is_array( $v['notes'] ) ) {
				foreach ( $v['notes'] as $note ) {
					if ( isset( $note_messages[$note] ) )
						$notes[] = $note_messages[$note];
					else
						$notes[] = $note;
				}
			}
		}

		/*
		 * Filesystem environment fix, if needed
		 */
		try {
			$environment = Dispatcher::component( 'Root_Environment' );
			$environment->fix_in_wpadmin( $this->_config );

			if ( ! empty( Util_Request::get_string( 'upgrade' ) ) ) {
				$notes[] = __( 'Required files and directories have been automatically created', 'w3-total-cache' );
			}
		} catch ( Util_Environment_Exceptions $exs ) {
			$r = Util_Activation::parse_environment_exceptions( $exs );
			$n = 1;

			foreach ( $r['before_errors'] as $e ) {
				$errors['generic_env_' . $n] = $e;
				$n++;
			}

			if ( strlen( $r['required_changes'] ) > 0 ) {
				$changes_style = 'border: 1px solid black; ' .
					'background: white; ' .
					'margin: 10px 30px 10px 30px; ' .
					'padding: 10px; display: none';
				$ftp_style = 'border: 1px solid black; background: white; ' .
					'margin: 10px 30px 10px 30px; ' .
					'padding: 10px; display: none';
				$ftp_form = str_replace( 'class="wrap"', '',
					$exs->credentials_form() );
				$ftp_form = str_replace( '<form ', '<form name="w3tc_ftp_form" ',
					$ftp_form );
				$ftp_form = str_replace( '<fieldset>', '', $ftp_form );
				$ftp_form = str_replace( '</fieldset>', '', $ftp_form );
				$ftp_form = str_replace( 'id="upgrade" class="button"',
					'id="upgrade" class="button w3tc-button-save"', $ftp_form );

				$error = '<strong>W3 Total Cache Error:</strong> ' .
					'Files and directories could not be automatically ' .
					'created to complete the installation. ' .
					'<table>' .
					'<tr>' .
					'<td>Please execute commands manually</td>' .
					'<td>' .
					Util_Ui::button( 'View required changes', '',
					'w3tc-show-required-changes' ) .
					'</td>' .
					'</tr>' .
					'<tr>' .
					'<td>or use FTP form to allow ' .
					'<strong>W3 Total Cache</strong> make it automatically.' .
					'</td>' .
					'<td>' .
					Util_Ui::button( 'Update via FTP', '', 'w3tc-show-ftp-form' ) .
					'</td>' .
					'</tr></table>'.

					'<div class="w3tc-required-changes" style="' .
					$changes_style . '">' . $r['required_changes'] . '</div>' .
					'<div class="w3tc-ftp-form" style="' . $ftp_style . '">' .
					$ftp_form . '</div>';

				$environment_error_present = true;
				$errors['generic_ftp'] = $error;
			}

			foreach ( $r['later_errors'] as $e ) {
				$errors[ 'generic_env_' . $n ] = $e;
				$n++;
			}
		}

		$errors = apply_filters( 'w3tc_errors', $errors );
		$notes  = apply_filters( 'w3tc_notes', $notes );

		/**
		 * Show messages.
		 */
		foreach ( $notes as $key => $note ) {
			echo wp_kses(
				sprintf(
					'<div class="updated w3tc_note" id="%1$s"><p>%2$s</p></div>',
					esc_attr( $key ),
					$note
				),
				array(
					'div'   => array(
						'class' => array(),
						'id'    => array(),
					),
					'input' => array(
						'class'   => array(),
						'name'    => array(),
						'onclick' => array(),
						'type'    => array(),
						'value'   => array(),
					),
					'p'     => array(),
				)
			);
		}

		foreach ( $errors as $key => $error ) {
				printf(
					'<div class="error w3tc_error" id="%1$s"><p>%2$s</p></div>',
					esc_attr( $key ),
					$error // phpcs:ignore
				);
		}
	}
}
