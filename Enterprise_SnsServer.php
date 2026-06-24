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
	 * @param string $w3tc_message The raw SNS message to be processed.
	 *
	 * @return void
	 *
	 * @throws \Exception If an error occurs while processing the message.
	 */
	public function process_message( $w3tc_message ) {
		$this->_log( 'Received message' );

		try {
			$w3tc_message = new \Aws\Sns\Message( $w3tc_message );
			$validator    = new \Aws\Sns\MessageValidator();
			$error        = '';
			if ( $validator->isValid( $w3tc_message ) ) {
				$topic_arn = $this->_config->get_string( 'cluster.messagebus.sns.topic_arn' );

				if ( empty( $topic_arn ) || $topic_arn !== $w3tc_message['TopicArn'] ) {
					throw new \Exception(
						\esc_html(
							sprintf(
								// Translators: 1 Error message.
								\__( 'Not my Topic. Request came from %1$s.', 'w3-total-cache' ),
								$w3tc_message['TopicArn']
							)
						)
					);
				}

				if ( 'SubscriptionConfirmation' === $w3tc_message['Type'] ) {
					$this->_subscription_confirmation( $w3tc_message );
				} elseif ( 'Notification' === $w3tc_message['Type'] ) {
					$this->_notification( $w3tc_message['Message'] );
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
	 * @param array $w3tc_message The SNS subscription confirmation message.
	 *
	 * @return void
	 */
	private function _subscription_confirmation( $w3tc_message ) {
		$this->_log( 'Issuing confirm_subscription' );
		$topic_arn = $this->_config->get_string( 'cluster.messagebus.sns.topic_arn' );

		$response = $this->_get_api()->confirmSubscription(
			array(
				'Token'    => $w3tc_message['Token'],
				'TopicArn' => $topic_arn,
			)
		);

		$this->_log( 'Subscription confirmed: ' . ( 200 === $response['@metadata']['statusCode'] ? 'OK' : 'Error' ) );
	}

	/**
	 * Handles SNS notification actions.
	 *
	 * The notification body may include a `blog_id` (multisite) and a
	 * `host` (multisite hostname) so a cluster-wide invalidation message can
	 * target a specific blog's caches. Earlier versions of the plugin
	 * applied these fields by mutating `$w3tc_w3_current_blog_id` and
	 * `$_SERVER['HTTP_HOST']` from `pub/sns.php` *before* signature
	 * validation, which let an unauthenticated request set the host the
	 * subsequent WordPress bootstrap saw. The mutation
	 * is now performed here, post-validation, with strict allowlisting
	 * against the actually configured site hostnames, and only when
	 * WordPress is in multisite mode.
	 *
	 * @since 2.10.0 Moved blog/host switch out of `pub/sns.php`; added host allowlist.
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

		/**
		 * Switch blog context AFTER signature validation, only on multisite,
		 * and only when the message names a blog the validator has approved.
		 * `switch_to_blog()` correctly updates $blog_id and option lookups
		 * without relying on `$_SERVER['HTTP_HOST']`.
		 *
		 * Fail-closed: if the message specifies a `blog_id` that cannot be
		 * validated (unknown blog, host-allowlist mismatch), refuse to
		 * process the actions at all. Applying a cache-invalidation in the
		 * CURRENT (i.e. the multisite primary) blog when the message was
		 * intended for a different one is silent data corruption on a
		 * cache-coherency bus.
		 */
		$switched = false;
		if ( \is_multisite() && isset( $m['blog_id'] ) && \is_numeric( $m['blog_id'] ) ) {
			$requested_blog_id = (int) $m['blog_id'];
			$blog_details      = \get_blog_details( $requested_blog_id, false );

			if ( ! $blog_details ) {
				$this->_log( sprintf( 'Refused message: blog_id %d not found.', $requested_blog_id ) );
				return;
			}

			if ( isset( $m['host'] ) && \is_string( $m['host'] ) && '' !== $m['host'] ) {
				// Allowlist: the requested host must match the blog's stored domain.
				$expected_host = isset( $blog_details->domain ) ? (string) $blog_details->domain : '';
				if ( '' === $expected_host || \strcasecmp( $expected_host, (string) $m['host'] ) !== 0 ) {
					$this->_log(
						sprintf(
							'Refused message: host "%s" does not match blog %d (%s).',
							(string) $m['host'],
							$requested_blog_id,
							$expected_host
						)
					);
					return;
				}
			}

			/**
			 * When the message supplies `blog_id` but no `host`, we fall
			 * through to switch_to_blog() without a host-allowlist check. That
			 * is only safe because `process_message()` (caller) has already
			 * matched the SNS `TopicArn` against this site's configured topic.
			 * The TopicArn is unique per cluster, so a message that reaches
			 * this branch is, by construction, an authenticated message
			 * targeting one of this cluster's blogs. Do NOT loosen the
			 * TopicArn check upstream without also adding a host requirement
			 * here — together they constrain incoming signed messages to the
			 * blogs this multisite owns, so a signed message addressed to a
			 * foreign blog cannot be redirected here.
			 */
			\switch_to_blog( $requested_blog_id );
			$switched = true;
		}

		if ( ! defined( 'W3TC_DOING_SNS' ) ) {
			define( 'W3TC_DOING_SNS', true );
		}
		$this->_log( 'Actions executing' );
		do_action( 'w3tc_messagebus_message_received' );

		try {
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
		} finally {
			if ( $switched ) {
				\restore_current_blog();
			}
		}
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
