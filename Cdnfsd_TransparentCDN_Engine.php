<?php
namespace W3TC;

/**
 * 
 */

if (!defined('W3TC_CDN_TRANSPARENTCDN_PURGE_URL')) define('W3TC_CDN_TRANSPARENTCDN_PURGE_URL', 'https://api.transparentcdn.com/v1/companies/%s/invalidate/');
if (!defined('W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL')) define('W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL', ' https://api.transparentcdn.com/v1/oauth2/access_token/');

class Cdn_TransparentCDN_Api {
	var $_token;
	var $_config;
    /**
     * PHP5 Constructor
     *
     * @param array $config
     */
    function __construct( $config = array() ) {
        $config = array_merge(array(
            'company_id' => '',
            'client_id' => '',
            'client_secret' => ''
        ), $config);
		$this->_config = $config;
    }

    function _debuglog($s) {
        file_put_contents('/tmp/kk', $s, FILE_APPEND);
    }
    /**
     * Purges URL
     *
     * @param array $urls
     * @param array $results
     * @return boolean
     */
    function purge($urls) {
        if (empty($this->_config['company_id'])) {
            return false;
        }
        if (empty($this->_config['client_id'])) {
            return false;
        }
        if (empty($this->_config['client_secret'])) {
            return false;
        }
        // We ask for the authorization token.
        $this->_get_token();

		$invalidation_urls = array();
		
		foreach($urls as $url) { //Oh array_map+lambdas, how I miss u...
			 $invalidation_urls[] = $url;
        }
        if(count($invalidation_urls)==0 ) {
            $invalidation_urls[] = "";
        }


        if ($this->_purge_content($invalidation_urls, $error)) {
                return true;
        } else {
                return false;
        }

		return $results;
        
    }
   /**
     * Purge content
     *
     * @param string $path
     * @param int $type
     * @param string $error
     * @return boolean
     */
    function _purge_content($files, &$error) {
        $url = sprintf(W3TC_CDN_TRANSPARENTCDN_PURGE_URL, $this->_config['company_id']);
        $args = array(
            'method' => 'POST',
            'user-agent' => W3TC_POWERED_BY,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $this->_token)
            ),
            'body' => json_encode(array(
                'urls' => $files
            ))
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error = implode('; ', $response->get_error_messages());
            return false;
        }

        switch ($response['response']['code']) {
            case 200:
                $body = json_decode($response['body']);
                if(is_array($body->urls_to_send) && count($body->urls_to_send)>0 ){
                    return true; //hemos invalidado al menos una URL.
                }
                else if( 0 < count($files) && "" !== $files[0] ){ //HACK!!!
                    $error = __('Invalid Request URL', 'w3-total-cache');
                    return false;
                }
                return true;

            case 400:
                if(count($files) > 0 && $files[0] == "") return true; #Caso de la prueba.
                $error = __('Invalid Request Parameter', 'w3-total-cache');
                return false;

            case 403:
                $error = __('Authentication Failure or Insufficient Access Rights', 'w3-total-cache');
                return false;

            case 404:
                $error = __('Invalid Request URI', 'w3-total-cache');
                return false;

            case 500:
                $error = __('Server Error', 'w3-total-cache');
                return false;
        }

        $error = __('Unknown error');

        return false;
    }


    /**
     * Purges CDN completely
     * @param $results
     * @return bool
     */
    function purge_all(&$results) {
        //TODO: Implementar mediante bans el * ? 
        return false;
    }

    /**
    * Obtiene el token a usar como autorizacion en las peticiones de invalidacion a transparent.
    * //TODO: Mejor control de errores.
    * @return bool
    */
    function _get_token(){
        $client_id = $this->_config['client_id'];
        $client_secret = $this->_config['client_secret'];
        $args = array(
            'method' => 'POST',
            'user-agent' => W3TC_POWERED_BY,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => "grant_type=client_credentials&client_id=$client_id&client_secret=$client_secret");

        $response = wp_remote_request(W3TC_CDN_TRANSPARENTCDN_AUTHORIZATION_URL, $args);

        if (is_wp_error($response)) {
            $error = implode('; ', $response->get_error_messages());
            return false;
        }
        $body = $response['body'];
        $jobj = json_decode($body);
        $this->_token = $jobj->access_token;
        return true;
    }
}

class Cdnfsd_TransparentCDN_Engine {
	private $config;



	function __construct( $config = array() ) {
		$this->config = $config;
	}



	function flush_urls( $urls ) {
		if ( empty( $this->config['client_id'] ) ) {
			throw new \Exception( __( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_TransparentCDN_Api( $this->config );

		try {
			$result = $api->purge( $urls );
			throw new \Exception(__('Problem purging'));
			
		} catch ( \Exception $ex ) {
			if ( $ex->getMessage() === 'Validation Failure: Purge url must contain one of your hostnames' ) {
				throw new \Exception(__('CDN site is not configured correctly: Delivery Domain must match your site domain'));
			} else {
				throw $ex;
			}
		}
	}



	/**
	 * Flushes CDN completely
	 */
	function flush_all() {
		if ( empty( $this->config['client_id'] ) ) {
			throw new \Exception( __( 'API key not specified.', 'w3-total-cache' ) );
		}

		$api = new Cdn_TransparentCDN_Api( $this->config );

		$items = array();
		$items[] = array( 'url' => home_url( '/' ),
			'recursive' => true,
		);

		try {
			$r = $api->purge( array( 'items' => $items ) );
		} catch ( \Exception $ex ) {
			if ( $ex->getMessage() === 'Validation Failure: Purge url must contain one of your hostnames' ) {
				throw new \Exception(__('CDN site is not configured correctly: Delivery Domain must match your site domain'));
			} else {
				throw $ex;
			}
		}
	}
}
