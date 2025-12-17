<?php
define( 'NEWRELIC_API_BASE', 'https://api.newrelic.com' );

/**
 * Interacts with the New Relic REST API v2.
 *
 * deprecated
 *
 * @link https://docs.newrelic.com/docs/apis/rest-api-v2/get-started/introduction-new-relic-rest-api-v2/
 */
class NewRelicAPI {
    /**
     * API key used for authorization.
     *
     * @var string
     */
    private $_api_key;

    /**
     * Cached account id from any application response.
     *
     * @var int|null
     */
    private $account_id_cache = null;

    /**
     * NerdGraph endpoint for account discovery.
     *
     * @var string
     */
    private $nerdgraph_endpoint = 'https://api.newrelic.com/graphql';

    /**
     * @param string $api_key New Relic API Key.
     */
    public function __construct( $api_key ) {
        $this->_api_key = $api_key;
    }

    /**
     * Debug helper with fallback when Util_Debug isn't loaded yet.
     *
     * @param string $message Message.
     * @return void
     */
    private function log_debug( $message ) {
        // no-op (debug logging disabled).
    }

    /**
     * Build request URL including query parameters.
     *
     * @param string $api_call_url API path.
     * @param array  $query        Optional query parameters.
     * @return string
     */
    private function build_url( $api_call_url, $query = array() ) {
        $url = NEWRELIC_API_BASE . $api_call_url;
        if ( ! empty( $query ) ) {
            $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
        }

        return $url;
    }

    /**
     * @param string $api_call_url url path with query string used to define what to get from the NR API.
     * @param array  $query        Optional query parameters.
     * @throws Exception If the request fails.
     * @return string
     */
    private function _get( $api_call_url, $query = array() ) {
        $url      = $this->build_url( $api_call_url, $query );
        $defaults = array(
            'headers' => array(
                'X-Api-Key' => $this->_api_key,
            ),
            'timeout' => 5,
        );

        $start    = microtime( true );
        $this->log_debug( sprintf( 'GET %s start', $url ) );

        $response = wp_remote_get( $url, $defaults );
        $elapsed  = round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            $this->log_debug( sprintf( 'GET %s error (%d ms): %s', $url, $elapsed, $response->get_error_message() ) );
            throw new Exception( 'Could not get data' );
        } elseif ( 200 === (int) $response['response']['code'] ) {
            $this->log_debug( sprintf( 'GET %s success (%d ms)', $url, $elapsed ) );
            return $response['body'];
        }

        switch ( (string) $response['response']['code'] ) {
            case '403':
                $message = __( 'Invalid API key', 'w3-total-cache' );
                break;
            default:
                $message = $response['response']['message'];
        }

        $body_snippet = isset( $response['body'] ) ? substr( $response['body'], 0, 500 ) : '';
        $this->log_debug( sprintf( 'GET %s failed (%d ms): %s %s Body: %s', $url, $elapsed, $response['response']['code'], $message, $body_snippet ) );
        throw new Exception( $message, $response['response']['code'] );
    }

    /**
     * @param string $api_call_url url path with query string used to define what to get from the NR API.
     * @param array  $params       key value array.
     * @throws Exception If the request fails.
     * @return bool
     */
    private function _put( $api_call_url, $params ) {
        $url      = $this->build_url( $api_call_url );
        $defaults = array(
            'method'  => 'PUT',
            'headers' => array(
                'X-Api-Key' => $this->_api_key,
            ),
            'body'    => $params,
            'timeout' => 5,
        );
        $start    = microtime( true );
        $this->log_debug( sprintf( 'PUT %s start', $url ) );

        $response = wp_remote_request( $url, $defaults );
        $elapsed  = round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            $this->log_debug( sprintf( 'PUT %s error (%d ms): %s', $url, $elapsed, $response->get_error_message() ) );
            throw new Exception( 'Could not put data' );
        } elseif ( in_array( (int) $response['response']['code'], array( 200, 201 ), true ) ) {
            $this->log_debug( sprintf( 'PUT %s success (%d ms)', $url, $elapsed ) );
            return true;
        }

        $this->log_debug( sprintf( 'PUT %s failed (%d ms): %s %s', $url, $elapsed, $response['response']['code'], $response['response']['message'] ) );
        throw new Exception( $response['response']['message'], $response['response']['code'] );
    }

    /**
     * Decode JSON response and guard against unexpected payloads.
     *
     * @param string $response Raw response string.
     * @return array
     * @throws Exception If JSON is invalid.
     */
    private function decode_response( $response ) {
        $decoded = json_decode( $response, true );
        if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
            throw new Exception( 'Received unexpected response' );
        }

        return $decoded;
    }

    /**
     * Get applications connected with the API key.
     *
     * @param int $account_id Deprecated.
     * @return array
     */
    public function get_applications( $account_id ) {
        $applications = array();
        $data         = $this->decode_response( $this->_get( '/v2/applications.json' ) );

        if ( isset( $data['applications'] ) && is_array( $data['applications'] ) ) {
            $this->log_debug( '[get_applications] received ' . count( $data['applications'] ) . ' apps' );
            foreach ( $data['applications'] as $application ) {
                if ( isset( $application['id'], $application['name'] ) ) {
                    $applications[ (int) $application['id'] ] = $application['name'];
                }

                if ( isset( $application['account_id'] ) && null === $this->account_id_cache ) {
                    $this->account_id_cache = (int) $application['account_id'];
                }
            }
        }

        return $applications;
    }

    /**
     * Get the application summary data for the provided application.
     *
     * @param int $account_id     Deprecated.
     * @param int $application_id Application ID.
     * @return array array(metric name => metric value)
     */
    public function get_application_summary( $account_id, $application_id ) {
        $summary = array();
        $data    = $this->decode_response( $this->_get( "/v2/applications/{$application_id}.json" ) );

        if ( empty( $data['application'] ) ) {
            return $summary;
        }

        $application = $data['application'];

        if ( isset( $application['application_summary'] ) && is_array( $application['application_summary'] ) ) {
            $app_summary = $application['application_summary'];
            if ( isset( $app_summary['apdex_score'] ) ) {
                $summary['Apdex'] = $app_summary['apdex_score'];
            }
            if ( isset( $app_summary['error_rate'] ) ) {
                $summary['Error Rate'] = $app_summary['error_rate'];
            }
            if ( isset( $app_summary['throughput'] ) ) {
                $summary['Throughput'] = $app_summary['throughput'];
            }
            if ( isset( $app_summary['response_time'] ) ) {
                $summary['Response Time'] = $app_summary['response_time'];
            }
        }

        if ( isset( $application['end_user_summary']['response_time'] ) ) {
            $summary['Application Busy'] = $application['end_user_summary']['response_time'];
        }

        // Fetch additional metrics for DB / CPU / Memory.
        $extra_metrics = array(
            // Use Datastore/all for DB; fall back to average_value if response time is absent.
            'DB'     => array(
                'name'           => 'Datastore/all',
                'field'          => 'average_response_time',
                'fallback_field' => 'average_value',
            ),
            'CPU'    => array( 'name' => 'CPU/User Time', 'field' => 'average_value' ),
            'Memory' => array( 'name' => 'Memory/Physical', 'field' => 'average_value' ),
        );

        foreach ( $extra_metrics as $label => $meta ) {
            $metric_data = $this->get_metric_data(
                0,
                $application_id,
                gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-1 day' ) ),
                gmdate( 'Y-m-d\TH:i:s\Z' ),
                array( $meta['name'] ),
                $meta['field'],
                true
            );

            if ( ! empty( $metric_data ) ) {
                $first = reset( $metric_data );
                $value = null;
                if ( isset( $first->{$meta['field']} ) && '' !== $first->{$meta['field']} ) {
                    $value = $first->{$meta['field']};
                } elseif ( isset( $meta['fallback_field'] ) && isset( $first->{$meta['fallback_field']} ) ) {
                    $value = $first->{$meta['fallback_field']};
                }

                if ( null !== $value ) {
                    $summary[ $label ] = $value;
                }
            }
        }

        return $summary;
    }

    /**
     * Get a single application with all attributes.
     *
     * @param int $application_id Application ID.
     * @return array|null
     */
    public function get_application( $application_id ) {
        $data = $this->decode_response( $this->_get( "/v2/applications/{$application_id}.json" ) );
        if ( isset( $data['application'] ) && is_array( $data['application'] ) ) {
            if ( isset( $data['application']['account_id'] ) ) {
                $this->account_id_cache = (int) $data['application']['account_id'];
            }
            $this->log_debug( '[get_application] app id ' . $application_id . ' payload: ' . wp_json_encode( $data['application'] ) );
            return $data['application'];
        }

        return null;
    }

    /**
     * Attempt to fetch the first account via NerdGraph (GraphQL) API.
     *
     * @return int|null
     */
    private function get_account_from_nerdgraph() {
        $query = '{ actor { accounts { id name } } }';
        $body  = wp_json_encode( array( 'query' => $query ) );
        $args  = array(
            'method'  => 'POST',
            'headers' => array(
                'API-Key'      => $this->_api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => $body,
            'timeout' => 5,
        );

        $this->log_debug( '[nerdgraph] POST ' . $this->nerdgraph_endpoint . ' start' );
        $response = wp_remote_post( $this->nerdgraph_endpoint, $args );
        if ( is_wp_error( $response ) ) {
            $this->log_debug( '[nerdgraph] error: ' . $response->get_error_message() );
            return null;
        }

        $code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
        $this->log_debug( '[nerdgraph] response code ' . $code );
        if ( 200 !== $code ) {
            return null;
        }

        $decoded = json_decode( $response['body'], true );
        if ( isset( $decoded['data']['actor']['accounts'][0]['id'] ) ) {
            $account_id = (int) $decoded['data']['actor']['accounts'][0]['id'];
            $this->log_debug( '[nerdgraph] resolved account_id=' . $account_id );
            return $account_id;
        }

        return null;
    }

    /**
     * Return key value array with information connected to account.
     *
     * @return array|mixed|null
     */
    public function get_account() {
        static $account_cache = null;

        if ( null !== $account_cache ) {
            return $account_cache;
        }

        // First try NerdGraph for a reliable account id.
        $ng_account_id = $this->get_account_from_nerdgraph();
        if ( $ng_account_id ) {
            $account_cache = array(
                'id'           => $ng_account_id,
                'subscription' => array(
                    'product-name' => 'Standard',
                ),
                'license-key'  => null,
            );
            $this->account_id_cache = $ng_account_id;
            return $account_cache;
        }

        // Derive account data from the first application (accounts endpoint not reliable).
        $data = $this->decode_response( $this->_get( '/v2/applications.json' ) );

        if ( isset( $data['applications'][0] ) ) {
            $application   = $data['applications'][0];
            $account_cache = array(
                'id'           => isset( $application['account_id'] ) ? (int) $application['account_id'] : $this->account_id_cache,
                'subscription' => array(
                    'product-name' => 'Standard',
                ),
                'license-key'  => isset( $application['license_key'] ) ? $application['license_key'] : null,
            );
            $this->log_debug( '[get_account] derived account_id=' . $account_cache['id'] . ' from application payload.' );
        } elseif ( isset( $this->account_id_cache ) ) {
            $account_cache = array(
                'id'           => $this->account_id_cache,
                'subscription' => array(
                    'product-name' => 'Standard',
                ),
                'license-key'  => null,
            );
        }

        if ( is_null( $account_cache ) ) {
            $this->log_debug( '[get_account] Unable to derive account_id from applications payload.' );
            if ( ! empty( $data['applications'] ) ) {
                $sample = $data['applications'][0];
                $this->log_debug( '[get_account] Sample application payload: ' . wp_json_encode( $sample ) );
            }
        }

        return $account_cache;
    }

    /**
     * Get key value array with application settings.
     *
     * @param int $account_id     Deprecated.
     * @param int $application_id Application ID.
     * @return array|mixed
     */
    public function get_application_settings( $account_id, $application_id ) {
        $data      = $this->decode_response( $this->_get( "/v2/applications/{$application_id}.json" ) );
        $settings  = array();
        $app_block = isset( $data['application'] ) ? $data['application'] : array();

        if ( isset( $app_block['settings'] ) ) {
            $settings = $app_block['settings'];
        }

        // Normalize to expected keys used by the view.
        $normalized = array(
            'application-id' => isset( $app_block['id'] ) ? $app_block['id'] : '',
            'name'           => isset( $app_block['name'] ) ? $app_block['name'] : '',
            'alerts-enabled' => isset( $settings['use_server_side_config'] ) ? ( $settings['use_server_side_config'] ? 'true' : 'false' ) : 'false',
            'app-apdex-t'    => isset( $settings['app_apdex_threshold'] ) ? $settings['app_apdex_threshold'] : '',
            'rum-apdex-t'    => isset( $settings['end_user_apdex_threshold'] ) ? $settings['end_user_apdex_threshold'] : '',
            'rum-enabled'    => isset( $settings['enable_real_user_monitoring'] ) ? ( $settings['enable_real_user_monitoring'] ? 'true' : 'false' ) : 'false',
        );

        return $normalized;
    }

    /**
     * Update application settings. verifies the keys in provided settings array is acceptable.
     *
     * @param int   $account_id     Deprecated.
     * @param int   $application_id Application ID.
     * @param array $settings       Settings to update.
     * @return bool
     */
    public function update_application_settings( $account_id, $application_id, $settings ) {
        $supported = array( 'alerts_enabled', 'app_apdex_t', 'rum_apdex_t', 'rum_enabled' );
        $call      = "/v2/applications/{$application_id}.json";
        $params    = array();

        foreach ( $settings as $key => $value ) {
            if ( in_array( $key, $supported, true ) ) {
                $params[ $key ] = $value;
            }
        }

        $payload = array(
            'application' => array(
                'settings' => $params,
            ),
        );

        return $this->_put( $call, $payload );
    }

    /**
     * Returns the available metric names for provided application.
     *
     * @param int    $application_id Application ID.
     * @param string $regex          Optional regex to filter metric names.
     * @param string $limit          Optional limit (not used, kept for BC).
     * @return array|mixed
     */
    public function get_metric_names( $application_id, $regex = '', $limit = '' ) {
        $data    = $this->decode_response( $this->_get( "/v2/applications/{$application_id}/metrics.json" ) );
        $metrics = array();

        if ( isset( $data['metrics'] ) && is_array( $data['metrics'] ) ) {
            foreach ( $data['metrics'] as $metric ) {
                if ( ! isset( $metric['name'] ) ) {
                    continue;
                }

                $name = $metric['name'];

                if ( $regex && ! @preg_match( '/' . str_replace( '/', '\/', $regex ) . '/', $name ) ) {
                    continue;
                }

                $metrics[] = (object) array(
                    'name' => $name,
                );
            }
        }

        return $metrics;
    }

    /**
     * Gets the metric data for the provided metric names.
     *
     * @param string $account_id     Deprecated.
     * @param string $application_id Application ID.
     * @param string $begin          XML date in GMT.
     * @param string $to             XML date in GMT.
     * @param array  $metrics        Metric names.
     * @param string $field          Field to retrieve.
     * @param bool   $summary        If values should be merged or overtime.
     * @return array|mixed
     */
    public function get_metric_data( $account_id, $application_id, $begin, $to, $metrics, $field, $summary = true ) {
        if ( empty( $metrics ) ) {
            return array();
        }

        $metrics = is_array( $metrics ) ? $metrics : array( $metrics );

        // Manually build query string to satisfy NR format: names[]=...&values[]=...
        $parts   = array(
            'from=' . rawurlencode( $begin ),
            'to=' . rawurlencode( $to ),
            'summarize=' . ( $summary ? 'true' : 'false' ),
        );
        foreach ( $metrics as $name ) {
            $parts[] = 'names[]=' . rawurlencode( $name );
        }
        $parts[] = 'values[]=' . rawurlencode( $field );

        $url = "/v2/applications/{$application_id}/metrics/data.json?" . implode( '&', $parts );
        $data = $this->decode_response(
            $this->_get( $url )
        );

        if ( ! isset( $data['metric_data']['metrics'] ) || ! is_array( $data['metric_data']['metrics'] ) ) {
            return array();
        }

        $metric_data = array();
        foreach ( $data['metric_data']['metrics'] as $metric ) {
            $formatted = array(
                'name' => isset( $metric['name'] ) ? $metric['name'] : '',
            );

            if ( isset( $metric['timeslices'][0]['values'] ) && is_array( $metric['timeslices'][0]['values'] ) ) {
                foreach ( $metric['timeslices'][0]['values'] as $key => $value ) {
                    $formatted[ $key ] = $value;
                }
            }

            $metric_data[] = (object) $formatted;
        }

        return $metric_data;
    }
}
