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
     * @param string $api_key New Relic API Key.
     */
    public function __construct( $api_key ) {
        $this->_api_key = $api_key;
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
        );

        $response = wp_remote_get( $url, $defaults );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Could not get data' );
        } elseif ( 200 === (int) $response['response']['code'] ) {
            return $response['body'];
        }

        switch ( (string) $response['response']['code'] ) {
            case '403':
                $message = __( 'Invalid API key', 'w3-total-cache' );
                break;
            default:
                $message = $response['response']['message'];
        }

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
        );
        $response = wp_remote_request( $url, $defaults );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Could not put data' );
        } elseif ( in_array( (int) $response['response']['code'], array( 200, 201 ), true ) ) {
            return true;
        }

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
            foreach ( $data['applications'] as $application ) {
                if ( isset( $application['id'], $application['name'] ) ) {
                    $applications[ (int) $application['id'] ] = $application['name'];
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

        return $summary;
    }

    /**
     * Return key value array with information connected to account.
     *
     * @return array|mixed|null
     */
    public function get_account() {
        $data = $this->decode_response( $this->_get( '/v2/applications.json' ) );

        if ( isset( $data['applications'][0] ) ) {
            $application  = $data['applications'][0];
            $product_name = 'Standard';

            return array(
                'id'           => isset( $application['account_id'] ) ? (int) $application['account_id'] : null,
                'subscription' => array(
                    'product-name' => $product_name,
                ),
                'license-key'  => isset( $application['license_key'] ) ? $application['license_key'] : null,
            );
        }

        return null;
    }

    /**
     * Get key value array with application settings.
     *
     * @param int $account_id     Deprecated.
     * @param int $application_id Application ID.
     * @return array|mixed
     */
    public function get_application_settings( $account_id, $application_id ) {
        $data = $this->decode_response( $this->_get( "/v2/applications/{$application_id}.json" ) );

        if ( isset( $data['application']['settings'] ) ) {
            return $data['application']['settings'];
        }

        return array();
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
        $query = array(
            'from'      => $begin,
            'to'        => $to,
            'summarize' => $summary ? 'true' : 'false',
            'names'     => $metrics,
            'values'    => array( $field ),
        );

        $data = $this->decode_response(
            $this->_get( "/v2/applications/{$application_id}/metrics/data.json", $query )
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
