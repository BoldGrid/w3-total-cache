<?php
/**
* $Id$
*
* Copyright (c) 2013, Donovan Schonknecht.  All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* Amazon S3 is a trademark of Amazon.com, Inc. or its affiliates.
*/

/**
* Amazon S3 PHP class
*
* @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
* @version 0.5.1 + Signature v4 support ;)
*/
class S3Compatible
{
	// ACL flags
	const ACL_PRIVATE = 'private';
	const ACL_PUBLIC_READ = 'public-read';

	const ORIGIN_TYPE_S3 = 's3';

	const STORAGE_CLASS_STANDARD = 'STANDARD';

	const SSE_NONE = '';

	/**
	 * The AWS Access key
	 *
	 * @var string
	 * @access private
	 * @static
	 */
	private static $__accessKey = null;

	/**
	 * AWS Secret Key
	 *
	 * @var string
	 * @access private
	 * @static
	 */
	private static $__secretKey = null;

	/**
	 * AWS URI
	 *
	 * @var string
	 * @acess public
	 * @static
	 */
	public static $endpoint = 's3.amazonaws.com';

	/**
	 * AWS Region
	 *
	 * @var string
	 * @acess public
	 * @static
	 */
	public static $region = '';

	/**
	 * Proxy information
	 *
	 * @var null|array
	 * @access public
	 * @static
	 */
	public static $proxy = null;

	/**
	 * Connect using SSL?
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $useSSL = false;

	/**
	 * Use SSL validation?
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $useSSLValidation = true;

	/**
	 * Use SSL version
	 *
	 * @var const
	 * @access public
	 * @static
	 */
	public static $useSSLVersion = 1; //CURL_SSLVERSION_TLSv1;

	/**
	 * Use PHP exceptions?
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $useExceptions = false;

	/**
	 * Time offset applied to time()
	 * @access private
	 * @static
	 */
	private static $__timeOffset = 0;

	/**
	 * SSL client key
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $sslKey = null;

	/**
	 * SSL client certfificate
	 *
	 * @var string
	 * @acess public
	 * @static
	 */
	public static $sslCert = null;

	/**
	 * SSL CA cert (only required if you are having problems with your system CA cert)
	 *
	 * @var string
	 * @access public
	 * @static
	 */
	public static $sslCACert = null;

	/**
	 * AWS Key Pair ID
	 *
	 * @var string
	 * @access private
	 * @static
	 */
	private static $__signingKeyPairId = null;

	/**
	 * Key resource, freeSigningKey() must be called to clear it from memory
	 *
	 * @var bool
	 * @access private
	 * @static
	 */
	private static $__signingKeyResource = false;

	/**
	 * AWS Signature Version
	 *
	 * @var string
	 * @acess public
	 * @static
	 */
	public static $signVer = 'v4';

	/**
	* Constructor - if you're not using the class statically
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param boolean $useSSL Enable SSL
	* @param string $endpoint Amazon URI
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $useSSL = false, $endpoint = 's3.amazonaws.com', $region = '')
	{
		if ($accessKey !== null && $secretKey !== null)
			self::setAuth($accessKey, $secretKey);
		self::$useSSL = $useSSL;
		self::$endpoint = $endpoint;
		self::$region = $region;
	}


	/**
	* Set the service endpoint
	*
	* @param string $host Hostname
	* @return void
	*/
	public function setEndpoint($host)
	{
		self::$endpoint = $host;
	}


	/**
	* Set the service region
	*
	* @param string $region
	* @return void
	*/
	public function setRegion($region)
	{
		self::$region = $region;
	}


	/**
	* Get the service region
	*
	* @return string $region
	* @static
	*/
	public static function getRegion()
	{
		$region = self::$region;

		// parse region from endpoint if not specific
		if (empty($region)) {
			if (preg_match("/s3[.-](?:website-|dualstack\.)?(.+)\.amazonaws\.com/i",self::$endpoint,$match) !== 0 && strtolower($match[1]) !== "external-1") {
				$region = $match[1];
			}
		}

		return empty($region) ? 'us-east-1' : $region;
	}


	/**
	* Set AWS access key and secret key
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return void
	*/
	public static function setAuth($accessKey, $secretKey)
	{
		self::$__accessKey = $accessKey;
		self::$__secretKey = $secretKey;
	}


	/**
	* Check if AWS keys have been set
	*
	* @return boolean
	*/
	public static function hasAuth() {
		return (self::$__accessKey !== null && self::$__secretKey !== null);
	}


	/**
	* Set AWS time correction offset (use carefully)
	*
	* This can be used when an inaccurate system time is generating
	* invalid request signatures.  It should only be used as a last
	* resort when the system time cannot be changed.
	*
	* @param string $offset Time offset (set to zero to use AWS server time)
	* @return void
	*/
	public static function setTimeCorrectionOffset($offset = 0)
	{
		if ($offset == 0)
		{
			$rest = new S3Request('HEAD');
			$rest = $rest->getResponse();
			$awstime = $rest->headers['date'];
			$systime = time();
			$offset = $systime > $awstime ? -($systime - $awstime) : ($awstime - $systime);
		}
		self::$__timeOffset = $offset;
	}


	/**
	* Set signing key
	*
	* @param string $keyPairId AWS Key Pair ID
	* @param string $signingKey Private Key
	* @param boolean $isFile Load private key from file, set to false to load string
	* @return boolean
	*/
	public static function setSigningKey($keyPairId, $signingKey, $isFile = true)
	{
		self::$__signingKeyPairId = $keyPairId;
		if ((self::$__signingKeyResource = openssl_pkey_get_private($isFile ?
		file_get_contents($signingKey) : $signingKey)) !== false) return true;
		self::triggerError('S3Compatible::setSigningKey(): Unable to open load private key: '.$signingKey, __FILE__, __LINE__);
		return false;
	}


	/**
	* Set Signature Version
	*
	* @param string $version of signature ('v4' or 'v2')
	* @return void
	*/
	public static function setSignatureVersion($version = 'v2')
	{
		self::$signVer = $version;
	}


	/**
	* Free signing key from memory, MUST be called if you are using setSigningKey()
	*
	* @return void
	*/
	public static function freeSigningKey()
	{
		if (self::$__signingKeyResource !== false)
			openssl_free_key(self::$__signingKeyResource);
	}


	/**
	* Internal error handler
	*
	* @internal Internal error handler
	* @param string $message Error message
	* @param string $file Filename
	* @param integer $line Line number
	* @param integer $code Error code
	* @return void
	*/
	private static function triggerError($message, $file, $line, $code = 0)
	{
		if (self::$useExceptions)
			throw new S3Exception($message, $file, $line, $code);
		else
			trigger_error($message, E_USER_WARNING);
	}


	/**
	* Create input info array for putObject()
	*
	* @param string $file Input file
	* @param mixed $md5sum Use MD5 hash (supply a string if you want to use your own)
	* @return array | false
	*/
	public static function inputFile($file, $md5sum = true)
	{
		if (!file_exists($file) || !is_file($file) || !is_readable($file))
		{
			self::triggerError('S3Compatible::inputFile(): Unable to open input file: '.$file, __FILE__, __LINE__);
			return false;
		}
		clearstatcache(false, $file);
		return array('file' => $file, 'size' => filesize($file), 'md5sum' => $md5sum !== false ?
		(is_string($md5sum) ? $md5sum : base64_encode(md5_file($file, true))) : '', 'sha256sum' => hash_file('sha256', $file));
	}


	/**
	* Put an object
	*
	* @param mixed $input Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param array $requestHeaders Array of request headers or content type as a string
	* @param constant $storageClass Storage class constant
	* @param constant $serverSideEncryption Server-side encryption
	* @return boolean
	*/
	public static function putObject($input, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $requestHeaders = array(), $storageClass = self::STORAGE_CLASS_STANDARD, $serverSideEncryption = self::SSE_NONE)
	{
		if ($input === false) return false;
		$rest = new S3Request('PUT', $bucket, $uri, self::$endpoint);

		if (!is_array($input)) $input = array(
			'data' => $input, 'size' => strlen($input),
			'md5sum' => base64_encode(md5($input, true)),
			'sha256sum' => hash('sha256', $input)
		);

		// Data
		if (isset($input['fp']))
			$rest->fp =& $input['fp'];
		elseif (isset($input['file']))
			$rest->fp = @fopen($input['file'], 'rb');
		elseif (isset($input['data']))
			$rest->data = $input['data'];

		// Content-Length (required)
		if (isset($input['size']) && $input['size'] >= 0)
			$rest->size = $input['size'];
		else {
			if (isset($input['file'])) {
				clearstatcache(false, $input['file']);
				$rest->size = filesize($input['file']);
			}
			elseif (isset($input['data']))
				$rest->size = strlen($input['data']);
		}

		// Custom request headers (Content-Type, Content-Disposition, Content-Encoding)
		if (is_array($requestHeaders))
			foreach ($requestHeaders as $h => $v)
				strpos($h, 'x-amz-') === 0 ? $rest->setAmzHeader($h, $v) : $rest->setHeader($h, $v);
		elseif (is_string($requestHeaders)) // Support for legacy contentType parameter
			$input['type'] = $requestHeaders;

		// Content-Type
		if (!isset($input['type']))
		{
			if (isset($requestHeaders['Content-Type']))
				$input['type'] =& $requestHeaders['Content-Type'];
			elseif (isset($input['file']))
				$input['type'] = self::getMIMEType($input['file']);
			else
				$input['type'] = 'application/octet-stream';
		}

		if ($storageClass !== self::STORAGE_CLASS_STANDARD) // Storage class
			$rest->setAmzHeader('x-amz-storage-class', $storageClass);

		if ($serverSideEncryption !== self::SSE_NONE) // Server-side encryption
			$rest->setAmzHeader('x-amz-server-side-encryption', $serverSideEncryption);

		// We need to post with Content-Length and Content-Type, MD5 is optional
		if ($rest->size >= 0 && ($rest->fp !== false || $rest->data !== false))
		{
			$rest->setHeader('Content-Type', $input['type']);
			if (isset($input['md5sum'])) $rest->setHeader('Content-MD5', $input['md5sum']);

			if (isset($input['sha256sum'])) $rest->setAmzHeader('x-amz-content-sha256', $input['sha256sum']);

			$rest->setAmzHeader('x-amz-acl', $acl);
			foreach ($metaHeaders as $h => $v) $rest->setAmzHeader('x-amz-meta-'.$h, $v);
			$rest->getResponse();
		} else
			$rest->response->error = array('code' => 0, 'message' => 'Missing input parameters');

		if ($rest->response->error === false && $rest->response->code !== 200)
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		if ($rest->response->error !== false)
		{
			self::triggerError(sprintf("S3Compatible::putObject(): [%s] %s",
			$rest->response->error['code'], $rest->response->error['message']), __FILE__, __LINE__);
			return false;
		}
		return true;
	}


	/**
	* Put an object from a file (legacy function)
	*
	* @param string $file Input file path
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public static function putObjectFile($file, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = null)
	{
		return self::putObject(self::inputFile($file), $bucket, $uri, $acl, $metaHeaders, $contentType);
	}


	/**
	* Put an object from a string (legacy function)
	*
	* @param string $string Input data
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param constant $acl ACL constant
	* @param array $metaHeaders Array of x-amz-meta-* headers
	* @param string $contentType Content type
	* @return boolean
	*/
	public static function putObjectString($string, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaHeaders = array(), $contentType = 'text/plain')
	{
		return self::putObject($string, $bucket, $uri, $acl, $metaHeaders, $contentType);
	}


	/**
	* Get an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param mixed $saveTo Filename or resource to write to
	* @return mixed
	*/
	public static function getObject($bucket, $uri, $saveTo = false)
	{
		$rest = new S3Request('GET', $bucket, $uri, self::$endpoint);
		if ($saveTo !== false)
		{
			if (is_resource($saveTo))
				$rest->fp =& $saveTo;
			else
				if (($rest->fp = @fopen($saveTo, 'wb')) !== false)
					$rest->file = realpath($saveTo);
				else
					$rest->response->error = array('code' => 0, 'message' => 'Unable to open save file for writing: '.$saveTo);
		}
		if ($rest->response->error === false) $rest->getResponse();

		if ($rest->response->error === false && $rest->response->code !== 200)
			$rest->response->error = array('code' => $rest->response->code, 'message' => 'Unexpected HTTP status');
		if ($rest->response->error !== false)
		{
			self::triggerError(sprintf("S3Compatible::getObject({$bucket}, {$uri}): [%s] %s",
			$rest->response->error['code'], $rest->response->error['message']), __FILE__, __LINE__);
			return false;
		}
		return $rest->response;
	}


	/**
	* Get object information
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param boolean $returnInfo Return response information
	* @return mixed | false
	*/
	public static function getObjectInfo($bucket, $uri, $returnInfo = true)
	{
		$rest = new S3Request('HEAD', $bucket, $uri, self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && ($rest->code !== 200 && $rest->code !== 404))
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false)
		{
			self::triggerError(sprintf("S3Compatible::getObjectInfo({$bucket}, {$uri}): [%s] %s",
			$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
			return false;
		}
		return $rest->code == 200 ? $returnInfo ? $rest->headers : true : false;
	}


	/**
	* Delete an object
	*
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @return boolean
	*/
	public static function deleteObject($bucket, $uri)
	{
		$rest = new S3Request('DELETE', $bucket, $uri, self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204)
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		if ($rest->error !== false)
		{
			self::triggerError(sprintf("S3Compatible::deleteObject(): [%s] %s",
			$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
			return false;
		}
		return true;
	}


	/**
	* Get MIME type for file
	*
	* To override the putObject() Content-Type, add it to $requestHeaders
	*
	* To use fileinfo, ensure the MAGIC environment variable is set
	*
	* @internal Used to get mime types
	* @param string &$file File path
	* @return string
	*/
	private static function getMIMEType(&$file) {
		$type = Util_Mime::get_mime_type($file);
		return $type;
	}


	/**
	* Generate the auth string: "AWS AccessKey:Signature"
	*
	* @internal Used by S3Request::getResponse()
	* @param string $string String to sign
	* @return string
	*/
	public static function getSignature($string)
	{
		return 'AWS '.self::$__accessKey.':'.self::getHash($string);
	}


	/**
	* Creates a HMAC-SHA1 hash
	*
	* This uses the hash extension if loaded
	*
	* @internal Used by getSignature()
	* @param string $string String to sign
	* @return string
	*/
	private static function getHash($string)
	{
		return base64_encode(extension_loaded('hash') ?
		hash_hmac('sha1', $string, self::$__secretKey, true) : pack('H*', sha1(
		(str_pad(self::$__secretKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
		pack('H*', sha1((str_pad(self::$__secretKey, 64, chr(0x00)) ^
		(str_repeat(chr(0x36), 64))) . $string)))));
	}


	/**
	* Generate the headers for AWS Signature V4
	* @internal Used by S3Request::getResponse()
	* @param array $aheaders amzHeaders
	* @param array $headers
	* @param string $method
	* @param string $uri
	* @param string $data
	* @param array $parameters
	* @return array $headers
	*/
	public static function getSignatureV4($aHeaders, $headers, $method='GET', $uri='', $data = '', $parameters=array())
	{
		$service = self::ORIGIN_TYPE_S3;
		$region = S3Compatible::getRegion();

		$algorithm = 'AWS4-HMAC-SHA256';
		$amzHeaders = array();
		$amzRequests = array();

		$amzDate =  gmdate( 'Ymd\THis\Z' );
		$amzDateStamp = gmdate( 'Ymd' );

		// amz-date ISO8601 format ? for aws request
		$amzHeaders['x-amz-date'] = $amzDate;

		// CanonicalHeaders
		foreach ( $headers as $k => $v ) {
			$amzHeaders[ strtolower( $k ) ] = trim( $v );
		}
		foreach ( $aHeaders as $k => $v ) {
			$amzHeaders[ strtolower( $k ) ] = trim( $v );
		}
		uksort( $amzHeaders, 'strcmp' );

		// payload
		$payloadHash = isset($amzHeaders['x-amz-content-sha256']) ? $amzHeaders['x-amz-content-sha256'] :  hash('sha256', $data);

		$uriQmPos = strpos($uri, '?');

		// CanonicalRequests
		$amzRequests[] = $method;
		$amzRequests[] = ($uriQmPos === false ? $uri : substr($uri, 0, $uriQmPos));
		$amzRequests[] = http_build_query($parameters);
		// add header as string to requests
		foreach ( $amzHeaders as $k => $v ) {
			$amzRequests[] = $k . ':' . $v;
		}
		// add a blank entry so we end up with an extra line break
		$amzRequests[] = '';
		// SignedHeaders
		$amzRequests[] = implode( ';', array_keys( $amzHeaders ) );
		// payload hash
		$amzRequests[] = $payloadHash;
		// request as string
		$amzRequestStr = implode("\n", $amzRequests);

		// CredentialScope
		$credentialScope = array();
		$credentialScope[] = $amzDateStamp;
		$credentialScope[] = $region;
		$credentialScope[] = $service;
		$credentialScope[] = 'aws4_request';

		// stringToSign
		$stringToSign = array();
		$stringToSign[] = $algorithm;
		$stringToSign[] = $amzDate;
		$stringToSign[] = implode('/', $credentialScope);
		$stringToSign[] =  hash('sha256', $amzRequestStr);
		// as string
		$stringToSignStr = implode("\n", $stringToSign);

		// Make Signature
		$kSecret = 'AWS4' . self::$__secretKey;
		$kDate = hash_hmac( 'sha256', $amzDateStamp, $kSecret, true );
		$kRegion = hash_hmac( 'sha256', $region, $kDate, true );
		$kService = hash_hmac( 'sha256', $service, $kRegion, true );
		$kSigning = hash_hmac( 'sha256', 'aws4_request', $kService, true );

		$signature = hash_hmac( 'sha256', $stringToSignStr, $kSigning );

		$authorization = array(
			'Credential=' . self::$__accessKey . '/' . implode( '/', $credentialScope ),
			'SignedHeaders=' . implode( ';', array_keys( $amzHeaders ) ),
			'Signature=' . $signature,
		);

		$authorizationStr = $algorithm . ' ' . implode( ',', $authorization );

		$resultHeaders = array(
			'X-AMZ-DATE' => $amzDate,
			'Authorization' => $authorizationStr
		);
		if (!isset($aHeaders['x-amz-content-sha256'])) $resultHeaders['x-amz-content-sha256'] = $payloadHash;

		return $resultHeaders;
	}


}

/**
 * S3 Request class
 *
 * @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 * @version 0.5.0-dev
 */
final class S3Request
{
	/**
	 * AWS URI
	 *
	 * @var string
	 * @access private
	 */
	private $endpoint;

	/**
	 * Verb
	 *
	 * @var string
	 * @access private
	 */
	private $verb;

	/**
	 * S3 bucket name
	 *
	 * @var string
	 * @access private
	 */
	private $bucket;

	/**
	 * Object URI
	 *
	 * @var string
	 * @access private
	 */
	private $uri;

	/**
	 * Final object URI
	 *
	 * @var string
	 * @access private
	 */
	private $resource = '';

	/**
	 * Additional request parameters
	 *
	 * @var array
	 * @access private
	 */
	private $parameters = array();

	/**
	 * Amazon specific request headers
	 *
	 * @var array
	 * @access private
	 */
	private $amzHeaders = array();

	/**
	 * HTTP request headers
	 *
	 * @var array
	 * @access private
	 */
	private $headers = array(
		'Host' => '', 'Date' => '', 'Content-MD5' => '', 'Content-Type' => ''
	);

	/**
	 * Use HTTP PUT?
	 *
	 * @var bool
	 * @access public
	 */
	public $fp = false;

	/**
	 * PUT file size
	 *
	 * @var int
	 * @access public
	 */
	public $size = 0;

	/**
	 * PUT post fields
	 *
	 * @var array
	 * @access public
	 */
	public $data = false;

	/**
	 * S3 request respone
	 *
	 * @var object
	 * @access public
	 */
	public $response;


	/**
	* Constructor
	*
	* @param string $verb Verb
	* @param string $bucket Bucket name
	* @param string $uri Object URI
	* @param string $endpoint AWS endpoint URI
	* @return mixed
	*/
	function __construct($verb, $bucket = '', $uri = '', $endpoint = 's3.amazonaws.com')
	{

		$this->endpoint = $endpoint;
		$this->verb = $verb;
		$this->bucket = $bucket;
		$this->uri = $uri !== '' ? '/'.str_replace('%2F', '/', rawurlencode($uri)) : '/';

		if ($this->bucket !== '')
		{
			if ($this->dnsBucketName($this->bucket))
			{
				$this->headers['Host'] = $this->bucket.'.'.$this->endpoint;
				$this->resource = '/'.$this->bucket.$this->uri;
			}
			else
			{
				$this->headers['Host'] = $this->endpoint;
				if ($this->bucket !== '') $this->uri = '/'.$this->bucket.$this->uri;
				$this->bucket = '';
				$this->resource = $this->uri;
			}
		}
		else
		{
			$this->headers['Host'] = $this->endpoint;
			$this->resource = $this->uri;
		}


		$this->headers['Date'] = gmdate('D, d M Y H:i:s T');
		$this->response = new STDClass;
		$this->response->error = false;
		$this->response->body = null;
		$this->response->headers = array();
	}


	/**
	* Set request parameter
	*
	* @param string $key Key
	* @param string $value Value
	* @return void
	*/
	public function setParameter($key, $value)
	{
		$this->parameters[$key] = $value;
	}


	/**
	* Set request header
	*
	* @param string $key Key
	* @param string $value Value
	* @return void
	*/
	public function setHeader($key, $value)
	{
		$this->headers[$key] = $value;
	}


	/**
	* Set x-amz-meta-* header
	*
	* @param string $key Key
	* @param string $value Value
	* @return void
	*/
	public function setAmzHeader($key, $value)
	{
		$this->amzHeaders[$key] = $value;
	}


	/**
	* Get the S3 response
	*
	* @return object | false
	*/
	public function getResponse()
	{
		$query = '';
		if (sizeof($this->parameters) > 0)
		{
			$query = substr($this->uri, -1) !== '?' ? '?' : '&';
			foreach ($this->parameters as $var => $value)
				if ($value == null || $value == '') $query .= $var.'&';
				else $query .= $var.'='.rawurlencode($value).'&';
			$query = substr($query, 0, -1);
			$this->uri .= $query;

			if (array_key_exists('acl', $this->parameters) ||
			array_key_exists('location', $this->parameters) ||
			array_key_exists('torrent', $this->parameters) ||
			array_key_exists('website', $this->parameters) ||
			array_key_exists('logging', $this->parameters))
				$this->resource .= $query;
		}
		$url = (S3Compatible::$useSSL ? 'https://' : 'http://') . ($this->headers['Host'] !== '' ? $this->headers['Host'] : $this->endpoint) . $this->uri;

		//var_dump('bucket: ' . $this->bucket, 'uri: ' . $this->uri, 'resource: ' . $this->resource, 'url: ' . $url);

		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'S3/php');

		if (S3Compatible::$useSSL)
		{
			// Set protocol version
			curl_setopt($curl, CURLOPT_SSLVERSION, S3Compatible::$useSSLVersion);

			// SSL Validation can now be optional for those with broken OpenSSL installations
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, S3Compatible::$useSSLValidation ? 2 : 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, S3Compatible::$useSSLValidation ? 1 : 0);

			if (S3Compatible::$sslKey !== null) curl_setopt($curl, CURLOPT_SSLKEY, S3Compatible::$sslKey);
			if (S3Compatible::$sslCert !== null) curl_setopt($curl, CURLOPT_SSLCERT, S3Compatible::$sslCert);
			if (S3Compatible::$sslCACert !== null) curl_setopt($curl, CURLOPT_CAINFO, S3Compatible::$sslCACert);
		}

		curl_setopt($curl, CURLOPT_URL, $url);

		if (S3Compatible::$proxy != null && isset(S3Compatible::$proxy['host']))
		{
			curl_setopt($curl, CURLOPT_PROXY, S3Compatible::$proxy['host']);
			curl_setopt($curl, CURLOPT_PROXYTYPE, S3Compatible::$proxy['type']);
			if (isset(S3Compatible::$proxy['user'], S3Compatible::$proxy['pass']) && S3Compatible::$proxy['user'] != null && S3Compatible::$proxy['pass'] != null)
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, sprintf('%s:%s', S3Compatible::$proxy['user'], S3Compatible::$proxy['pass']));
		}

		// Headers
		$headers = array(); $amz = array();
		foreach ($this->amzHeaders as $header => $value)
			if (strlen($value) > 0) $headers[] = $header.': '.$value;
		foreach ($this->headers as $header => $value)
			if (strlen($value) > 0) $headers[] = $header.': '.$value;

		// Collect AMZ headers for signature
		foreach ($this->amzHeaders as $header => $value)
			if (strlen($value) > 0) $amz[] = strtolower($header).':'.$value;

		// AMZ headers must be sorted
		if (sizeof($amz) > 0)
		{
			//sort($amz);
			usort($amz, array(&$this, 'sortMetaHeadersCmp'));
			$amz = "\n".implode("\n", $amz);
		} else $amz = '';

		if (S3Compatible::hasAuth())
		{
			// Authorization string (CloudFront stringToSign should only contain a date)
			if ($this->headers['Host'] == 'cloudfront.amazonaws.com')
				$headers[] = 'Authorization: ' . S3Compatible::getSignature($this->headers['Date']);
			else
			{
				if (S3Compatible::$signVer == 'v2')
				{
					$headers[] = 'Authorization: ' . S3Compatible::getSignature(
						$this->verb."\n".
						$this->headers['Content-MD5']."\n".
						$this->headers['Content-Type']."\n".
						$this->headers['Date'].$amz."\n".
						$this->resource
					);
				} else {
					$amzHeaders = S3Compatible::getSignatureV4(
						$this->amzHeaders,
						$this->headers,
						$this->verb,
						$this->uri,
						$this->data,
						$this->parameters
					);
					foreach ($amzHeaders as $k => $v) {
						$headers[] = $k .': '. $v;
					}
				}
			}
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, 'responseWriteCallback'));
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this, 'responseHeaderCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Request types
		switch ($this->verb)
		{
			case 'GET': break;
			case 'PUT': case 'POST': // POST only used for CloudFront
				if ($this->fp !== false)
				{
					curl_setopt($curl, CURLOPT_PUT, true);
					curl_setopt($curl, CURLOPT_INFILE, $this->fp);
					if ($this->size >= 0)
						curl_setopt($curl, CURLOPT_INFILESIZE, $this->size);
				}
				elseif ($this->data !== false)
				{
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
				}
				else
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
			break;
			case 'HEAD':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
				curl_setopt($curl, CURLOPT_NOBODY, true);
			break;
			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;
			default: break;
		}

		// Execute, grab errors
		if (curl_exec($curl))
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		else
			$this->response->error = array(
				'code' => curl_errno($curl),
				'message' => curl_error($curl),
				'resource' => $this->resource
			);

		@curl_close($curl);

		// Parse body into XML
		if ($this->response->error === false && isset($this->response->headers['type']) &&
		$this->response->headers['type'] == 'application/xml' && isset($this->response->body))
		{
			$this->response->body = simplexml_load_string($this->response->body);

			// Grab S3 errors
			if (!in_array($this->response->code, array(200, 204, 206)) &&
			isset($this->response->body->Code, $this->response->body->Message))
			{
				$this->response->error = array(
					'code' => (string)$this->response->body->Code,
					'message' => (string)$this->response->body->Message
				);
				if (isset($this->response->body->Resource))
					$this->response->error['resource'] = (string)$this->response->body->Resource;
				unset($this->response->body);
			}
		}

		// Clean up file resources
		if ($this->fp !== false && is_resource($this->fp)) fclose($this->fp);

		return $this->response;
	}

	/**
	* Sort compare for meta headers
	*
	* @internal Used to sort x-amz meta headers
	* @param string $a String A
	* @param string $b String B
	* @return integer
	*/
	private function sortMetaHeadersCmp($a, $b)
	{
		$lenA = strpos($a, ':');
		$lenB = strpos($b, ':');
		$minLen = min($lenA, $lenB);
		$ncmp = strncmp($a, $b, $minLen);
		if ($lenA == $lenB) return $ncmp;
		if (0 == $ncmp) return $lenA < $lenB ? -1 : 1;
		return $ncmp;
	}

	/**
	* CURL write callback
	*
	* @param resource &$curl CURL resource
	* @param string &$data Data
	* @return integer
	*/
	private function responseWriteCallback(&$curl, &$data)
	{
		if (in_array($this->response->code, array(200, 206)) && $this->fp !== false)
			return fwrite($this->fp, $data);
		else
		{
			if (isset($this->response->body))
				$this->response->body .= $data;
			else
				$this->response->body = $data;
		}
		return strlen($data);
	}


	/**
	* Check DNS conformity
	*
	* @param string $bucket Bucket name
	* @return boolean
	*/
	private function dnsBucketName($bucket)
	{
		if (strlen($bucket) > 63 || preg_match("/[^a-z0-9\.-]/", $bucket) > 0) return false;
		if (S3Compatible::$useSSL && strstr($bucket, '.') !== false) return false;
		if (strstr($bucket, '-.') !== false) return false;
		if (strstr($bucket, '..') !== false) return false;
		if (!preg_match("/^[0-9a-z]/", $bucket)) return false;
		if (!preg_match("/[0-9a-z]$/", $bucket)) return false;
		return true;
	}


	/**
	* CURL header callback
	*
	* @param resource $curl CURL resource
	* @param string $data Data
	* @return integer
	*/
	private function responseHeaderCallback($curl, $data)
	{
		if (($strlen = strlen($data)) <= 2) return $strlen;
		if (substr($data, 0, 4) == 'HTTP')
			$this->response->code = (int)substr($data, 9, 3);
		else
		{
			$data = trim($data);
			if (strpos($data, ': ') === false) return $strlen;
			list($header, $value) = explode(': ', $data, 2);
			$header = strtolower($header);
			if ($header == 'last-modified')
				$this->response->headers['time'] = strtotime($value);
			elseif ($header == 'date')
				$this->response->headers['date'] = strtotime($value);
			elseif ($header == 'content-length')
				$this->response->headers['size'] = (int)$value;
			elseif ($header == 'content-type')
				$this->response->headers['type'] = $value;
			elseif ($header == 'etag')
				$this->response->headers['hash'] = $value[0] == '"' ? substr($value, 1, -1) : $value;
			elseif (preg_match('/^x-amz-meta-.*$/', $header))
				$this->response->headers[$header] = $value;
		}
		return $strlen;
	}

}

/**
 * S3 exception class
 *
 * @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 * @version 0.5.0-dev
 */

class S3Exception extends Exception {
	/**
	 * Class constructor
	 *
	 * @param string $message Exception message
	 * @param string $file File in which exception was created
	 * @param string $line Line number on which exception was created
	 * @param int $code Exception code
	 */
	function __construct($message, $file, $line, $code = 0)
	{
		parent::__construct($message, $code);
		$this->file = $file;
		$this->line = $line;
	}
}
