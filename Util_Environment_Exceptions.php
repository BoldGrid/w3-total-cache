<?php
/**
 * File: Util_Environment_Exceptions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Environment_Exceptions
 */
class Util_Environment_Exceptions extends \Exception {
	/**
	 * Exceptions
	 *
	 * @var Exception[]
	 */
	private $exceptions;

	/**
	 * Credentials form
	 *
	 * @var string
	 */
	private $credentials_form;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		$this->exceptions = array();
	}

	/**
	 * Push
	 *
	 * @param object $ex Exception.
	 *
	 * @return void
	 */
	public function push( $ex ) {
		if ( $ex instanceof Util_Environment_Exceptions ) {
			foreach ( $ex->exceptions() as $ex2 ) {
				$this->push( $ex2 );
			}
		} else {
			if (
				null === $this->credentials_form &&
				$ex instanceof Util_WpFile_FilesystemOperationException &&
				null !== $ex->credentials_form()
			) {
				$this->credentials_form = $ex->credentials_form();
			}

			$this->exceptions[] = $ex;
		}
	}

	/**
	 * Get exceptions
	 *
	 * @return Exception[]
	 */
	public function exceptions() {
		return $this->exceptions;
	}

	/**
	 * Get credentials form
	 *
	 * @return string
	 */
	public function credentials_form() {
		return $this->credentials_form;
	}

	/**
	 * Get combined message
	 *
	 * @return string
	 */
	public function getCombinedMessage() {
		$s = '';
		foreach ( $this->exceptions as $m ) {
			$s .= $m->getMessage() . "\r\n";
		}

		return $s;
	}
}
