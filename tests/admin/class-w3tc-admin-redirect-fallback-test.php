<?php
/**
 * File: class-w3tc-admin-redirect-fallback-test.php
 *
 * ENG3-3682: after Purge All Caches, Util_Admin::redirect() is called with
 * check_referrer true. With no Referer, no redirect query argument, and no
 * page query argument, the Location must be a real admin screen
 * (page=w3tc_dashboard plus the flush note) and must not carry an empty page.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

namespace W3TC {

	/**
	 * Stand-in for the unqualified header() call inside Util_Environment.
	 *
	 * redirect() emits Location and then exit() after do_action( 'w3tc_redirect' ).
	 * Exit would abort the PHPUnit process. This stand-in records Location and
	 * throws before exit. Non-Location headers still reach PHP's header().
	 *
	 * @since X.X.X
	 *
	 * @param string $header        Header line.
	 * @param bool   $replace       Replace a previous header of the same name.
	 * @param int    $response_code HTTP response code.
	 * @return void
	 * @throws \W3tc_Admin_Redirect_Location_Exception When a Location is captured.
	 */
	function header( $header, $replace = true, $response_code = 0 ) {
		if ( \W3tc_Admin_Redirect_Fallback_Test::$capture && is_string( $header ) && 0 === stripos( $header, 'Location:' ) ) {
			\W3tc_Admin_Redirect_Fallback_Test::$captured_location = trim( substr( $header, strlen( 'Location:' ) ) );
			throw new \W3tc_Admin_Redirect_Location_Exception( 'location captured' );
		}

		\header( $header, $replace, $response_code );
	}
}

namespace {

	use W3TC\Util_Admin;

	/**
	 * Thrown when the Location stand-in records a redirect.
	 *
	 * @since X.X.X
	 */
	class W3tc_Admin_Redirect_Location_Exception extends \RuntimeException {}

	/**
	 * Class: W3tc_Admin_Redirect_Fallback_Test
	 *
	 * @since X.X.X
	 */
	class W3tc_Admin_Redirect_Fallback_Test extends \WP_UnitTestCase {

		/**
		 * Whether the Location stand-in should capture instead of sending.
		 *
		 * @since X.X.X
		 *
		 * @var bool
		 */
		public static $capture = false;

		/**
		 * Location URL recorded by the stand-in, without the header name.
		 *
		 * @since X.X.X
		 *
		 * @var string|null
		 */
		public static $captured_location = null;

		/**
		 * Saved $_GET.
		 *
		 * @since X.X.X
		 *
		 * @var array
		 */
		private $saved_get;

		/**
		 * Saved $_POST.
		 *
		 * @since X.X.X
		 *
		 * @var array
		 */
		private $saved_post;

		/**
		 * Saved $_REQUEST.
		 *
		 * @since X.X.X
		 *
		 * @var array
		 */
		private $saved_request;

		/**
		 * Saved HTTP_REFERER, or null when it was absent.
		 *
		 * @since X.X.X
		 *
		 * @var string|null
		 */
		private $saved_referer;

		/**
		 * Whether HTTP_REFERER was set before the test.
		 *
		 * @since X.X.X
		 *
		 * @var bool
		 */
		private $had_referer = false;

		/**
		 * Snapshot request globals that redirect() reads.
		 *
		 * @since X.X.X
		 */
		public function set_up() {
			parent::set_up();

			$this->saved_get     = $_GET;
			$this->saved_post    = $_POST;
			$this->saved_request = $_REQUEST;
			$this->had_referer   = array_key_exists( 'HTTP_REFERER', $_SERVER );
			$this->saved_referer = $this->had_referer ? (string) $_SERVER['HTTP_REFERER'] : null;
		}

		/**
		 * Restore request globals so later tests do not see this redirect state.
		 *
		 * @since X.X.X
		 */
		public function tear_down() {
			$_GET     = $this->saved_get;
			$_POST    = $this->saved_post;
			$_REQUEST = $this->saved_request;

			if ( $this->had_referer ) {
				$_SERVER['HTTP_REFERER'] = $this->saved_referer;
			} else {
				unset( $_SERVER['HTTP_REFERER'] );
			}

			self::$capture           = false;
			self::$captured_location = null;

			parent::tear_down();
		}

		/**
		 * No Referer, no redirect argument, and no page argument must land on
		 * the dashboard with the flush note, never an empty page value.
		 *
		 * @since X.X.X
		 */
		public function test_missing_referer_falls_back_to_dashboard() {
			$this->isolate_redirect_inputs();

			$location = $this->redirect_location(
				array(
					'w3tc_note' => 'flush_all',
				),
				true
			);

			$this->assertStringContainsString( 'w3tc_note=flush_all', $location );
			$this->assertStringNotContainsString(
				'page=&',
				$location,
				'Location must not send an empty page value.'
			);
			$this->assertDoesNotMatchRegularExpression(
				'/(?:^|[?&])page=$/',
				$location,
				'Location must not send an empty page value.'
			);
			$this->assertStringContainsString( 'page=w3tc_dashboard', $location );
		}

		/**
		 * A same-site admin Referer stays the redirect target.
		 *
		 * @since X.X.X
		 */
		public function test_same_site_admin_referer_is_preserved() {
			$this->isolate_redirect_inputs();

			$referer                 = admin_url( 'admin.php?page=w3tc_general' );
			$_SERVER['HTTP_REFERER'] = $referer;

			$location = $this->redirect_location(
				array(
					'w3tc_note' => 'flush_all',
				),
				true
			);

			$referer_base = strtok( $referer, '?' );
			$this->assertIsString( $referer_base );
			$this->assertStringStartsWith( $referer_base, $location );
			$this->assertStringContainsString( 'page=w3tc_general', $location );
			$this->assertStringContainsString( 'w3tc_note=flush_all', $location );
			$this->assertStringNotContainsString( 'page=w3tc_dashboard', $location );
		}

		/**
		 * An explicit non-empty page slug is kept when no Referer is present.
		 *
		 * @since X.X.X
		 */
		public function test_explicit_page_slug_is_kept() {
			$this->isolate_redirect_inputs();

			$location = $this->redirect_location(
				array(
					'w3tc_note' => 'flush_all',
				),
				true,
				'w3tc_cdn'
			);

			$this->assertStringContainsString( 'page=w3tc_cdn', $location );
			$this->assertStringContainsString( 'w3tc_note=flush_all', $location );
			$this->assertStringNotContainsString( 'page=w3tc_dashboard', $location );
			$this->assertStringNotContainsString( 'page=&', $location );
		}

		/**
		 * A page argument sent with an empty value also falls back to the dashboard.
		 *
		 * @since X.X.X
		 */
		public function test_empty_request_page_falls_back_to_dashboard() {
			$this->isolate_redirect_inputs();

			$_GET['page']     = '';
			$_REQUEST['page'] = '';

			$location = $this->redirect_location(
				array(
					'w3tc_note' => 'flush_all',
				),
				true
			);

			$this->assertStringContainsString( 'page=w3tc_dashboard', $location );
			$this->assertStringNotContainsString( 'page=&', $location );
		}

		/**
		 * The custom-message redirect lands on the dashboard when the request has no page.
		 *
		 * @since X.X.X
		 */
		public function test_custom_messages2_missing_page_falls_back_to_dashboard() {
			$this->isolate_redirect_inputs();

			$location = $this->capture_location(
				function () {
					Util_Admin::redirect_with_custom_messages2(
						array(
							'notes' => array( 'Done.' ),
						)
					);
				}
			);

			$this->assertStringContainsString( 'page=w3tc_dashboard', $location );
			$this->assertStringContainsString( 'w3tc_message=', $location );
			$this->assertStringNotContainsString( 'page=&', $location );
		}

		/**
		 * The custom-message redirect keeps the request page slug.
		 *
		 * @since X.X.X
		 */
		public function test_custom_messages2_request_page_is_kept() {
			$this->isolate_redirect_inputs();

			$_GET['page']     = 'w3tc_general';
			$_REQUEST['page'] = 'w3tc_general';

			$location = $this->capture_location(
				function () {
					Util_Admin::redirect_with_custom_messages2(
						array(
							'notes' => array( 'Done.' ),
						)
					);
				}
			);

			$this->assertStringContainsString( 'page=w3tc_general', $location );
			$this->assertStringNotContainsString( 'page=w3tc_dashboard', $location );
		}

		/**
		 * Drop query inputs that Util_Admin::redirect() consults.
		 *
		 * @since X.X.X
		 */
		private function isolate_redirect_inputs() {
			unset(
				$_GET['page'],
				$_GET['redirect'],
				$_POST['page'],
				$_POST['redirect'],
				$_REQUEST['page'],
				$_REQUEST['redirect'],
				$_SERVER['HTTP_REFERER']
			);
		}

		/**
		 * Run Util_Admin::redirect() and return the Location URL.
		 *
		 * @since X.X.X
		 *
		 * @param array       $params         Query parameters, including the note.
		 * @param bool        $check_referrer Whether to honor HTTP_REFERER.
		 * @param string|null $page           Explicit page slug, or null to omit the argument.
		 * @return string
		 */
		private function redirect_location( array $params, $check_referrer, $page = null ) {
			return $this->capture_location(
				function () use ( $params, $check_referrer, $page ) {
					if ( null === $page ) {
						Util_Admin::redirect( $params, $check_referrer );
					} else {
						Util_Admin::redirect( $params, $check_referrer, $page );
					}
				}
			);
		}

		/**
		 * Run a redirect callback and return the Location URL.
		 *
		 * @since X.X.X
		 *
		 * @param callable $redirect Callback that sends the redirect.
		 * @return string
		 */
		private function capture_location( callable $redirect ) {
			self::$captured_location = null;
			self::$capture           = true;

			$location = null;

			try {
				$redirect();

				$this->fail( 'Expected a Location header.' );
			} catch ( W3tc_Admin_Redirect_Location_Exception $exception ) {
				$location = self::$captured_location;
			} finally {
				self::$capture = false;
			}

			$this->assertIsString( $location );

			return $location;
		}
	}
}
