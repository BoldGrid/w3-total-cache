<?php

namespace W3TC;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * 
 * This class defines utility functions for Azure blob storage access using Managed Identity.
 * @author  Zubair <zmohammed@microsoft.com>
 */
class CdnEngine_Azure_MI_Utility {

    const ENTRA_API_VERSION = '2019-08-01';
    const ENTRA_RESOURCE_URI = 'https://storage.azure.com';
    const BLOB_API_VERSION = '2020-10-02';

    /**
     * This function retrieves the access token from the managed identity endpoint.
     * @return string $access_token
     * @throws RuntimeException
     */
    public static function getAccessToken($entra_client_id) {
        // Get environment variables
        $identity_header = getenv('IDENTITY_HEADER');
        $identity_endpoint = getenv('IDENTITY_ENDPOINT');

         // Validate variables
        if (empty($identity_endpoint) || empty($identity_header) || empty($entra_client_id)) {
            throw new \RuntimeException("Error: getAccessToken - missing required environment variables.");
        }

        // Construct URL for cURL request
        $url = $identity_endpoint . '?' . http_build_query([
            'api-version' => self::ENTRA_API_VERSION,
            'resource' => self::ENTRA_RESOURCE_URI,
            'client_id' => $entra_client_id,
        ]);

        // Initialize and execute cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-IDENTITY-HEADER: $identity_header"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: getAccessToken - cURL request failed: $error");
            return $access_token;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response_headers = curl_getinfo($ch);

        curl_close($ch);
        if ($httpCode != 200) {
            throw new \RuntimeException("Error: getAccessToken - HTTP request failed with status code $httpCode");
        }
        if (!$response) {
            throw new \RuntimeException("Error: getAccessToken - invalid response data: $response");
        }

        // Parse JSON response and extract access_token
        $json_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Error: getAccessToken - failed to parse the JSON response: " . json_last_error_msg());
        }
        if (!isset($json_response['access_token'])) {
            throw new \RuntimeException("Error: getAccessToken - no token found in response data: $response");
        }

        return $json_response['access_token'];
    }


    public static function getBlobProperties($entra_client_id, $storage_account, $container, $blob) {
        $access_token = self::getAccessToken($entra_client_id);
        $url = "https://$storage_account.blob.core.windows.net/$container/$blob";
        $date = gmdate('D, d M Y H:i:s T', time());

		$headers = [
            "Authorization: Bearer " . $access_token,
            "x-ms-version: " . self::BLOB_API_VERSION,
            "x-ms-date: $date",
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: getBlobProperties - cURL request failed: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) {
            throw new \RuntimeException("Error: getBlobProperties - HTTP request failed with status code $httpCode");
        }

        if (!$response) {
            throw new \RuntimeException("Error: getBlobProperties - invalid response data: $response");
        }
        return self::parseHeaders($response);
    }


    public static function createBlockBlob($entra_client_id, $storage_account, $container, $blob, $contents, $content_type=null, $content_md5=null, $cache_control=null) {
        $access_token = self::getAccessToken($entra_client_id);
        $url = "https://$storage_account.blob.core.windows.net/$container/$blob";
        $date = gmdate('D, d M Y H:i:s T', time());
        
		$headers = [
            "Authorization: Bearer " . $access_token,
            "x-ms-version: " . self::BLOB_API_VERSION,
            "x-ms-date: $date",
            "x-ms-blob-type: BlockBlob",
        ];

        if ($content_type) {
            $headers[] = "x-ms-blob-content-type: $content_type";
        }
        if ($content_md5) {
            $headers[] = "x-ms-blob-content-md5: $content_md5";
        }
        if ($cache_control) {
            $headers[] = "x-ms-blob-cache-control: $cache_control";
        }

        $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: createBlockBlob - cURL request failed: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 201) {
            throw new \RuntimeException("Error: createBlockBlob - HTTP request failed with status code $httpCode");
        }

        if (!$response) {
            throw new \RuntimeException("Error: createBlockBlob - invalid response data: $response");
        }
        return self::parseHeaders($response);
    }

    
    public static function deleteBlob($entra_client_id, $storage_account, $container, $blob) {
        $access_token = self::getAccessToken($entra_client_id);
        $url = "https://$storage_account.blob.core.windows.net/$container/$blob";
        $date = gmdate('D, d M Y H:i:s T', time());
        
        $headers = [
            "Authorization: Bearer " . $access_token,
            "x-ms-version: " . self::BLOB_API_VERSION,
            "x-ms-date: $date",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: deleteBlob - cURL request failed: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 202) {
            throw new \RuntimeException("Error: deleteBlob - HTTP request failed with status code $httpCode");
        }

        if (!$response) {
            throw new \RuntimeException("Error: deleteBlob - invalid response data: $response");
        }
        return self::parseHeaders($response);
    }


    public static function createContainer($entra_client_id, $storage_account, $container, $public_access_type='blob') {
        $access_token = self::getAccessToken($entra_client_id);
        $url = "https://$storage_account.blob.core.windows.net/$container?restype=container";
        $date = gmdate('D, d M Y H:i:s T', time());
        
        $headers = [
            "Authorization: Bearer " . $access_token,
            "x-ms-version: " . self::BLOB_API_VERSION,
            "x-ms-date: $date",
            "Content-Length: 0",
        ];

        if ($public_access_type) {
            $headers[] = "x-ms-blob-public-access: $public_access_type";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: createContainer - cURL request failed: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 201) {
            throw new \RuntimeException("Error: createContainer - HTTP request failed with status code $httpCode");
        }

        if (!$response) {
            throw new \RuntimeException("Error: createContainer - invalid response data: $response");
        }
        return self::parseHeaders($response);
    }


    public static function listContainers($entra_client_id, $storage_account) {
        $access_token = self::getAccessToken($entra_client_id);
        $url = "https://$storage_account.blob.core.windows.net/?comp=list";
        $date = gmdate('D, d M Y H:i:s T', time());
        
        $headers = [
            "Authorization: Bearer " . $access_token,
            "x-ms-version: " . self::BLOB_API_VERSION,
            "x-ms-date: $date",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: listContainers - cURL request failed: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) {
            throw new \RuntimeException("Error: listContainers - HTTP request failed with status code $httpCode");
        }

        if (!$response) {
            throw new \RuntimeException("Error: listContainers - invalid response data: $response");
        }

        # Parse XML response to array
        $xml = simplexml_load_string($response);
        $json = json_encode($xml);
        $response = json_decode($json,TRUE);        

        return $response;
    }

    public static function getBlob($entra_client_id, $storage_account, $container, $blob) {
        $access_token = self::getAccessToken($entra_client_id);
        $url = "https://$storage_account.blob.core.windows.net/$container/$blob";
        $date = gmdate('D, d M Y H:i:s T', time());

        $headers = [
            "Authorization Bearer " . $access_token,
            "x-ms-version: " . self::BLOB_API_VERSION,
            "x-ms-date: $date",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new \RuntimeException("Error: getBlob - cURL request failed: $error");
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) {
            throw new \RuntimeException("Error: getBlob - HTTP request failed with status code $httpCode");
        }

        if (!$response) {
            throw new \RuntimeException("Error: getBlob - invalid response data: $response");
        }

        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $headers = self::parseHeaders($header);

        $response = [
            'headers' => $headers,
            'data' => $body
        ];
        return $response;
    }


    public static function parseHeaders($header) {
        $headers = [];
        $header_text = substr($header, 0, strpos($header, "\r\n\r\n"));
        $header_parts = explode("\r\n", $header_text);
        foreach ($header_parts as $header) {
            if (strpos($header, ':') !== false) {
                $header_parts = explode(":", $header);
                $headers[$header_parts[0]] = trim($header_parts[1]);
            }
        }
        return $headers;
    }
}

?>