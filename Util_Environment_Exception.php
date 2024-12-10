<?php
/**
 * File: Util_Environment_Exception.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Environment_Exception
 */
class Util_Environment_Exception extends \Exception {
	/**
	 * Technical message
	 *
	 * @var string
	 */
	private $technical_message;

	/**
	 * Constructor
	 *
	 * @param string $message           Message.
	 * @param string $technical_message Technical message.
	 *
	 * @return void
	 */
	public function __construct( $message, $technical_message = '' ) {
		parent::__construct( $message );
		$this->technical_message = $technical_message;
	}

	/**
	 * Get technical message
	 *
	 * @return string
	 */
	public function technical_message() {
		return $this->technical_message;
	}
}
