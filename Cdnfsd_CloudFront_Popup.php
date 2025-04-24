<?php
/**
 * File: Cdnfsd_CloudFront_Popup.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC_SKIPLIB_AWS' ) ) {
	require_once W3TC_DIR . '/vendor/autoload.php';
}

/**
 * Class Cdnfsd_CloudFront_Popup
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdnfsd_CloudFront_Popup {
	/**
	 * Initializes the AJAX actions for the CloudFront popup.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$o = new Cdnfsd_CloudFront_Popup();

		add_action( 'w3tc_ajax_cdn_cloudfront_fsd_intro', array( $o, 'w3tc_ajax_cdn_cloudfront_fsd_intro' ) );
		add_action( 'w3tc_ajax_cdn_cloudfront_fsd_list_distributions', array( $o, 'w3tc_ajax_cdn_cloudfront_fsd_list_distributions' ) );
		add_action( 'w3tc_ajax_cdn_cloudfront_fsd_view_distribution', array( $o, 'w3tc_ajax_cdn_cloudfront_fsd_view_distribution' ) );
		add_action( 'w3tc_ajax_cdn_cloudfront_fsd_configure_distribution', array( $o, 'w3tc_ajax_cdn_cloudfront_fsd_configure_distribution' ) );
		add_action( 'w3tc_ajax_cdn_cloudfront_fsd_configure_distribution_skip', array( $o, 'w3tc_ajax_cdn_cloudfront_fsd_configure_distribution_skip' ) );
	}

	/**
	 * Renders the introduction view for the CloudFront setup.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_cloudfront_fsd_intro() {
		$this->render_intro( array() );
	}

	/**
	 * Renders the introduction section of the CloudFront popup.
	 *
	 * @param array $details Array of details required for rendering the introduction.
	 *
	 * @return void
	 */
	private function render_intro( $details ) {
		$config         = Dispatcher::config();
		$url_obtain_key = Util_Ui::url(
			array(
				'page' => 'w3tc_dashboard',
			)
		);

		include W3TC_DIR . '/Cdnfsd_CloudFront_Popup_View_Intro.php';
		exit();
	}

	/**
	 * Lists the CloudFront distributions available for the provided credentials.
	 *
	 * @return void
	 *
	 * @throws \Aws\Exception\AwsException If AWS SDK encounters an error.
	 * @throws \Exception If a general error occurs.
	 */
	public function w3tc_ajax_cdn_cloudfront_fsd_list_distributions() {
		$access_key = Util_Request::get_string( 'access_key' );
		$secret_key = Util_Request::get_string( 'secret_key' );

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			$this->render_intro(
				array(
					'error_message' => 'Can\'t authenticate: Access Key or Secret not valid',
				)
			);
			exit();
		}

		try {
			$api           = $this->_api( $access_key, $secret_key );
			$distributions = $api->listDistributions();
		} catch ( \Aws\Exception\AwsException $ex ) {
			$this->render_intro(
				array(
					'error_message' => 'Can\'t authenticate: ' . $ex->getAwsErrorMessage(),
				)
			);
			exit();
		} catch ( \Exception $ex ) {
			$error_message = 'Can\'t authenticate: ' . $ex->getMessage();

			$this->render_intro(
				array(
					'error_message' => $error_message,
				)
			);
			exit();
		}

		$items = array();

		if ( isset( $distributions['DistributionList']['Items'] ) ) {
			foreach ( $distributions['DistributionList']['Items'] as $i ) {
				if ( empty( $i['Comment'] ) ) {
					$i['Comment'] = $i['DomainName'];
				}
				if ( isset( $i['Origins']['Items'][0]['DomainName'] ) ) {
					$i['Origin_DomainName'] = $i['Origins']['Items'][0]['DomainName'];
				}

				$items[] = $i;
			}
		}

		$details = array(
			'access_key'    => $access_key,
			'secret_key'    => $secret_key,
			'distributions' => $items,
		);

		include W3TC_DIR . '/Cdnfsd_CloudFront_Popup_View_Distributions.php';
		exit();
	}

	/**
	 * Displays detailed information about a specific CloudFront distribution.
	 *
	 * @return void
	 *
	 * @throws \Exception If the distribution cannot be retrieved.
	 */
	public function w3tc_ajax_cdn_cloudfront_fsd_view_distribution() {
		$access_key      = Util_Request::get_string( 'access_key' );
		$secret_key      = Util_Request::get_string( 'secret_key' );
		$distribution_id = Util_Request::get( 'distribution_id', '' );

		$details = array(
			'access_key'           => $access_key,
			'secret_key'           => $secret_key,
			'distribution_id'      => $distribution_id,
			'distribution_comment' => '',
			'origin'               => array(
				'new' => '',
			),
			'forward_querystring'  => array(
				'new' => true,
			),
			'forward_cookies'      => array(
				'new' => true,
			),
			'forward_host'         => array(
				'new' => true,
			),
		);

		if ( empty( $distribution_id ) ) {
			// create new zone mode.
			$details['distribution_comment'] = Util_Request::get( 'comment_new' );
		} else {
			try {
				$api          = $this->_api( $access_key, $secret_key );
				$distribution = $api->getDistribution( array( 'Id' => $distribution_id ) );
			} catch ( \Exception $ex ) {
				$this->render_intro(
					array(
						'error_message' => 'Can\'t obtain zone: ' . $ex->getMessage(),
					)
				);
				exit();
			}

			if ( isset( $distribution['Distribution']['DistributionConfig'] ) ) {
				$c = $distribution['Distribution']['DistributionConfig'];
			} else {
				$c = array();
			}

			if ( ! empty( $c['Comment'] ) ) {
				$details['distribution_comment'] = $c['Comment'];
			} else {
				$details['distribution_comment'] = $c['DomainName'];
			}

			if ( isset( $c['Origins']['Items']['Origin'] ) ) {
				$details['origin']['current'] = $c['Origins']['Items']['Origin'][0]['DomainName'];
				$details['origin']['new']     = $details['origin']['current'];
			}

			if ( isset( $c['DefaultCacheBehavior'] ) && isset( $c['DefaultCacheBehavior']['ForwardedValues'] ) ) {
				$b = $c['DefaultCacheBehavior']['ForwardedValues'];
			} else {
				$b = array();
			}

			$details['forward_querystring']['current'] = (
				isset( $b['QueryString'] ) &&
				'true' === $b['QueryString']
			);
			$details['forward_cookies']['current']     = (
				isset( $b['Cookies'] ) &&
				isset( $b['Cookies']['Forward'] ) &&
				'all' === $b['Cookies']['Forward']
			);

			$details['forward_host']['current'] = false;
			if ( isset( $b['Headers']['Items']['Name'] ) ) {
				foreach ( $b['Headers']['Items']['Name'] as $name ) {
					if ( 'Host' === $name ) {
						$details['forward_host']['current'] = true;
					}
				}
			}
		}

		include W3TC_DIR . '/Cdnfsd_CloudFront_Popup_View_Distribution.php';
		exit();
	}

	/**
	 * Renders changes in a zone value for a given field.
	 *
	 * @param array  $details {
	 *     Zone details containing current and new values.
	 *
	 *     @type array $field {
	 *         The field name to render changes for.
	 *
	 *         @type string $current The current value of the field.
	 *         @type string $new The new value of the field.
	 *     }
	 * }
	 * @param string $field   Field name to render changes for.
	 *
	 * @return void
	 */
	private function render_zone_value_change( $details, $field ) {
		Util_Ui::hidden( 'w3tc-cloudfront-value-' . $field, $field, $details[ $field ]['new'] );

		if ( ! isset( $details[ $field ]['current'] ) || $details[ $field ]['current'] === $details[ $field ]['new'] ) {
			echo esc_html( $details[ $field ]['new'] );
		} else {
			echo 'currently set to <strong>' .
				( empty( $details[ $field ]['current'] ) ? '<empty>' : esc_html( $details[ $field ]['current'] ) ) .
				'</strong><br />';
			echo 'will be changed to <strong>' . esc_html( $details[ $field ]['new'] ) . '</strong><br />';
		}
	}

	/**
	 * Renders changes in a boolean zone value for a given field.
	 *
	 * @param array  $details {
	 *     Zone details containing current and new values.
	 *
	 *     @type array $field {
	 *         The field name to render changes for.
	 *
	 *         @type string $current The current value of the field.
	 *         @type string $new The new value of the field.
	 *     }
	 * }
	 * @param string $field The field name to render changes for.
	 *
	 * @return void
	 */
	private function render_zone_boolean_change( $details, $field ) {
		Util_Ui::hidden( 'w3tc-cloudfront-boolean-' . $field, $field, $details[ $field ]['new'] );

		if ( ! isset( $details[ $field ]['current'] ) ) {
			echo 'will be set to <strong>' . esc_html( $this->render_zone_boolean( $details[ $field ]['new'] ) ) . '</strong>';
		} elseif ( $details[ $field ]['current'] === $details[ $field ]['new'] ) {
			echo '<strong>' . esc_html( $this->render_zone_boolean( $details[ $field ]['new'] ) ) . '</strong>';
		} else {
			echo 'currently set to <strong>' . esc_html( $this->render_zone_boolean( $details[ $field ]['current'] ) ) .
				'</strong><br />will be changed to <strong>' . esc_html( $this->render_zone_boolean( $details[ $field ]['new'] ) ) .
				'</strong><br />';
		}
	}

	/**
	 * Renders a boolean value as 'enabled' or 'disabled'.
	 *
	 * @param int $v Boolean value (0 or 1).
	 *
	 * @return void
	 */
	private function render_zone_boolean( $v ) {
		if ( empty( $v ) ) {
			echo 'disabled';
		} else {
			echo 'enabled';
		}
	}

	/**
	 * Renders changes in a zone IP value for a given field.
	 *
	 * @param array  $details {
	 *     Zone details containing current and new values.
	 *
	 *     @type array $field {
	 *         The field name to render changes for.
	 *
	 *         @type string $current The current value of the field.
	 *         @type string $new The new value of the field.
	 *     }
	 * }
	 * @param string $field   Field name to render changes for.
	 *
	 * @return void
	 */
	private function render_zone_ip_change( $details, $field ) {
		Util_Ui::textbox( '', $field, $details[ $field ]['new'] );

		if ( isset( $details[ $field ]['current'] ) && $details[ $field ]['current'] !== $details[ $field ]['new'] ) {
			echo '<p class="description">currently set to <strong>' . esc_html( $details[ $field ]['current'] ) . '</strong></p>';
		}
	}

	/**
	 * Configures a CloudFront distribution based on provided inputs.
	 *
	 * @return void
	 *
	 * @throws \Aws\Exception\AwsException If AWS SDK encounters an error.
	 * @throws \Exception If a general error occurs during configuration.
	 */
	public function w3tc_ajax_cdn_cloudfront_fsd_configure_distribution() {
		$access_key      = Util_Request::get_string( 'access_key' );
		$secret_key      = Util_Request::get_string( 'secret_key' );
		$distribution_id = Util_Request::get( 'distribution_id', '' );

		$origin_id = wp_rand();

		$distribution = array(
			'DistributionConfig' => array(
				'CallerReference'      => $origin_id,
				'Comment'              => Util_Request::get( 'distribution_comment' ),
				'DefaultCacheBehavior' => array(
					'AllowedMethods'             => array(
						'CachedMethods' => array(
							'Items'    => array( 'HEAD', 'GET' ),
							'Quantity' => 2,
						),
						'Items'         => array( 'HEAD', 'GET' ),
						'Quantity'      => 2,
					),
					'Compress'                   => true,
					'DefaultTTL'                 => 86400,
					'FieldLevelEncryptionId'     => '',
					'ForwardedValues'            => array(
						'Cookies'              => array(
							'Forward' => 'all',
						),
						'Headers'              => array(
							'Quantity' => 1,
							'Items'    => array(
								'Name' => 'Host',
							),
						),
						'QueryString'          => true,
						'QueryStringCacheKeys' => array(
							'Quantity' => 0,
						),
					),
					'LambdaFunctionAssociations' => array( 'Quantity' => 0 ),
					'MinTTL'                     => 0,
					'SmoothStreaming'            => false,
					'TargetOriginId'             => $origin_id,
					'TrustedSigners'             => array(
						'Enabled'  => false,
						'Quantity' => 0,
					),
					'ViewerProtocolPolicy'       => 'allow-all',
				),
				'Enabled'              => true,
				'Origins'              => array(
					'Items'    => array(
						array(
							'DomainName'         => Util_Request::get( 'origin' ),
							'Id'                 => $origin_id,
							'OriginPath'         => '',
							'CustomHeaders'      => array( 'Quantity' => 0 ),
							'CustomOriginConfig' => array(
								'HTTPPort'             => 80,
								'HTTPSPort'            => 443,
								'OriginProtocolPolicy' => 'match-viewer',
							),
						),
					),
					'Quantity' => 1,
				),
				'Aliases'              => array(
					'Quantity' => 0,
				),
			),
		);

		try {
			$api = $this->_api( $access_key, $secret_key );
			if ( empty( $distribution_id ) ) {

				$response        = $api->createDistribution( $distribution );
				$distribution_id = $response['Distribution']['Id'];
			} else {
				$distribution['Id'] = $distribution_id;
				$response           = $api->UpdateDistribution( $distribution );
			}
		} catch ( \Aws\Exception\AwsException $ex ) {
			$this->render_intro(
				array(
					'error_message' => 'Unable to create distribution: ' . $ex->getAwsErrorMessage(),
				)
			);
			exit();
		} catch ( \Exception $ex ) {
			$this->render_intro(
				array(
					'error_message' => 'Failed to configure distribution: ' . $ex->getMessage(),
				)
			);
			exit();
		}

		$distribution_domain = $response['Distribution']['DomainName'];

		$c = Dispatcher::config();
		$c->set( 'cdnfsd.cloudfront.access_key', $access_key );
		$c->set( 'cdnfsd.cloudfront.secret_key', $secret_key );
		$c->set( 'cdnfsd.cloudfront.distribution_id', $distribution_id );
		$c->set( 'cdnfsd.cloudfront.distribution_domain', $distribution_domain );

		$c->save();

		$details = array(
			'name'             => $distribution['DistributionConfig']['Comment'],
			'home_domain'      => Util_Environment::home_url_host(),
			'dns_cname_target' => $distribution_domain,
		);

		include W3TC_DIR . '/Cdnfsd_CloudFront_Popup_View_Success.php';
		exit();
	}

	/**
	 * Skips distribution configuration and saves the provided details.
	 *
	 * @return void
	 *
	 * @throws \Exception If the distribution details cannot be retrieved.
	 */
	public function w3tc_ajax_cdn_cloudfront_fsd_configure_distribution_skip() {
		$access_key      = Util_Request::get_string( 'access_key' );
		$secret_key      = Util_Request::get_string( 'secret_key' );
		$distribution_id = Util_Request::get( 'distribution_id', '' );

		$origin_id = wp_rand();

		try {
			$api          = $this->_api( $access_key, $secret_key );
			$distribution = $api->getDistribution( array( 'Id' => $distribution_id ) );
		} catch ( \Exception $ex ) {
			$this->render_intro(
				array(
					'error_message' => 'Failed to configure distribution: ' . $ex->getMessage(),
				)
			);
			exit();
		}

		if ( isset( $distribution['Distribution']['DomainName'] ) ) {
			$distribution_domain = $distribution['Distribution']['DomainName'];
		} else {
			$distribution_domain = 'n/a';
		}

		$c = Dispatcher::config();
		$c->set( 'cdnfsd.cloudfront.access_key', $access_key );
		$c->set( 'cdnfsd.cloudfront.secret_key', $secret_key );
		$c->set( 'cdnfsd.cloudfront.distribution_id', $distribution_id );
		$c->set( 'cdnfsd.cloudfront.distribution_domain', $distribution_domain );
		$c->save();

		$details = array(
			'name'             => $distribution['Distribution']['Comment'],
			'home_domain'      => Util_Environment::home_url_host(),
			'dns_cname_target' => $distribution_domain,
		);

		include W3TC_DIR . '/Cdnfsd_CloudFront_Popup_View_Success.php';
		exit();
	}

	/**
	 * Creates an AWS CloudFront API client instance.
	 *
	 * @param string $access_key AWS Access Key for authentication.
	 * @param string $secret_key AWS Secret Key for authentication.
	 *
	 * @return \Aws\CloudFront\CloudFrontClient Instance of the CloudFront API client.
	 */
	private function _api( $access_key, $secret_key ) {
		if ( empty( $access_key ) && empty( $secret_key ) ) {
			$credentials = \Aws\Credentials\CredentialProvider::defaultProvider();
		} else {
			$credentials = new \Aws\Credentials\Credentials( $access_key, $secret_key );
		}

		return new \Aws\CloudFront\CloudFrontClient(
			array(
				'credentials' => $credentials,
				'region'      => 'us-east-1',
				'version'     => '2018-11-05',
			)
		);
	}
}
