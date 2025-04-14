<?php
/**
 * File: Util_WpFile_FilesystemModifyException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemOperationException
 *
 * Thrown when the plugin fails to get correct filesystem rights when it tries to modify manipulate filesystem.
 */
class Util_WpFile_FilesystemOperationException extends \Exception {
	/**
	 * Credentials form
	 *
	 * @var string
	 */
	private $credentials_form;

	/**
	 * Initializes the object with a message and an optional credentials form.
	 *
	 * This constructor sets up the object by assigning a message and, optionally, a credentials form.
	 * It also calls the parent class's constructor to handle shared initialization.
	 *
	 * @param string      $message          The message to associate with the object.
	 * @param string|null $credentials_form Optional. The credentials form content. Defaults to null if not provided.
	 */
	public function __construct( $message, $credentials_form = null ) {
		parent::__construct( $message );
		$this->credentials_form = $credentials_form;
	}

	/**
	 * Retrieves the credentials form associated with the object.
	 *
	 * @return string|null The credentials form assigned during object initialization, or null if none was provided.
	 */
	public function credentials_form() {
		return $this->credentials_form;
	}
}
