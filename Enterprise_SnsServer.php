<?php
/**
 * File: Enterprise_SnsServer.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Enterprise_SnsServer
 *
 * Purge using AmazonSNS object
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Enterprise_SnsServer extends Enterprise_SnsBase {
	/**
	 * Processes incoming SNS messages and handles subscription confirmation or notification actions.
	 *
	 * @param string $message The raw SNS message to be processed.
	 *
	 * @return void
	 *
	 * @throws \Exception If an error occurs while processing the message.
	 */
	public function process_message( $message ) {
		$this->_log( 'Received message' );

		try {
			$message   = new \Aws\Sns\Message( $message );
			$validator = new \Aws\Sns\MessageValidator();
			$error     = '';
			if ( $validator->isValid( $message ) ) {
				$topic_arn = $this->_config->get_string( 'cluster.messagebus.sns.topic_arn' );

				if ( empty( $topic_arn ) || $topic_arn !== $message['TopicArn'] ) {
					throw new \Exception(
						\esc_html(
							sprintf(
								// Translators: 1 Error message.
								\__( 'Not my Topic. Request came from %1$s.', 'w3-total-cache' ),
								$message['TopicArn']
							)
						)
					);
				}

				if ( 'SubscriptionConfirmation' === $message['Type'] ) {
					$this->_subscription_confirmation( $message );
				} elseif ( 'Notification' === $message['Type'] ) {
					$this->_notification( $message['Message'] );
				}
			} else {
				$this->_log( 'Error processing message it was not valid.' );
			}
		} catch ( \Exception $e ) {
			$this->_log( 'Error processing message: ' . $e->getMessage() );
		}
		$this->_log( 'Message processed' );
	}

	/**
	 * Handles subscription confirmation for SNS messages.
	 *
	 * @param array $message The SNS subscription confirmation message.
	 *
	 * @return void
	 */
	private function _subscription_confirmation( $message ) {
		$this->_log( 'Issuing confirm_subscription' );
		$topic_arn = $this->_config->get_string( 'cluster.messagebus.sns.topic_arn' );

		$response = $this->_get_api()->confirmSubscription(
			array(
				'Token'    => $message['Token'],
				'TopicArn' => $topic_arn,
			)
		);

		$this->_log( 'Subscription confirmed: ' . ( 200 === $response['@metadata']['statusCode'] ? 'OK' : 'Error' ) );
	}

	/**
	 * Handles SNS notification actions.
	 *
	 * @param string $v The raw SNS notification message in JSON format.
	 *
	 * @return void
	 */
	private function _notification( $v ) {
		$m = json_decode( $v, true );
		if ( isset( $m['hostname'] ) ) {
			$this->_log( 'Message originated from hostname: ' . $m['hostname'] );
		}

		define( 'DOING_SNS', true );
		$this->_log( 'Actions executing' );
		do_action( 'w3tc_messagebus_message_received' );

		if ( isset( $m['actions'] ) ) {
			$actions = $m['actions'];
			foreach ( $actions as $action ) {
				$this->_execute( $action );
			}
		} else {
			$this->_execute( $m['action'] );
		}

		do_action( 'w3tc_messagebus_message_processed' );
		$this->_log( 'Actions executed' );
	}

	/**
	 * Executes the specified action based on the SNS message.
	 *
	 * @param array $m The action details from the SNS message.
	 *
	 * @return void
	 *
	 * @throws \Exception If an unknown action is encountered.
	 */
	private function _execute( $m ) {
		$action = $m['action'];
		$this->_log( 'Executing action ' . $action );
		// Needed for cache flushing.
		$executor = new CacheFlush_Locally();
		// Needed for cache cleanup.
		$pgcache_admin = Dispatcher::component( 'PgCache_Plugin_Admin' );

		// See which message we got.
		if ( 'dbcache_flush' === $action ) {
			$executor->dbcache_flush();
		} elseif ( 'objectcache_flush' === $action ) {
			$executor->objectcache_flush();
		} elseif ( 'fragmentcache_flush' === $action ) {
			$executor->fragmentcache_flush();
		} elseif ( 'fragmentcache_flush_group' === $action ) {
			$executor->fragmentcache_flush_group( $m['group'] );
		} elseif ( 'minifycache_flush' === $action ) {
			$executor->minifycache_flush();
		} elseif ( 'browsercache_flush' === $action ) {
			$executor->browsercache_flush();
		} elseif ( 'cdn_purge_all' === $action ) {
			$executor->cdn_purge_all( isset( $m['extras'] ) ? $m['extras'] : null );
		} elseif ( 'cdn_purge_files' === $action ) {
			$executor->cdn_purge_files( $m['purgefiles'] );
		} elseif ( 'pgcache_cleanup' === $action ) {
			$pgcache_admin->cleanup_local();
		} elseif ( 'opcache_flush' === $action ) {
			$executor->opcache_flush();
		} elseif ( 'flush_all' === $action ) {
			$executor->flush_all( isset( $m['extras'] ) ? $m['extras'] : null );
		} elseif ( 'flush_group' === $action ) {
			$executor->flush_group( isset( $m['group'] ) ? $m['group'] : null, isset( $m['extras'] ) ? $m['extras'] : null );
		} elseif ( 'flush_post' === $action ) {
			$executor->flush_post( $m['post_id'] );
		} elseif ( 'flush_posts' === $action ) {
			$executor->flush_posts();
		} elseif ( 'flush_url' === $action ) {
			$executor->flush_url( $m['url'] );
		} elseif ( 'prime_post' === $action ) {
			$executor->prime_post( $m['post_id'] );
		} else {
			throw new \Exception(
				\esc_html(
					sprintf(
						// Translators: 1 Unknown action name.
						\__( 'Unknown action %1$s.', 'w3-total-cache' ),
						$action
					)
				)
			);
		}

		$executor->execute_delayed_operations();

		$this->_log( 'succeeded' );
	}
}
