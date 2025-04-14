<?php
/**
 * File: Util_WpFile_FilesystemRmdirException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemRmdirException
 */
class Util_WpFile_FilesystemRmdirException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Credentials form
	 *
	 * @var string
	 */
	private $folder;

	/**
	 * Initializes the object with a message and an optional credentials form.
	 *
	 * This constructor sets up the object by assigning a message and, optionally, a credentials form.
	 * It also calls the parent class's constructor to handle shared initialization.
	 *
	 * @param string      $message          The message to associate with the object.
	 * @param string|null $credentials_form Optional. The credentials form content. Defaults to null if not provided.
	 * @param string      $folder           The folder that caused the exception.
	 */
	public function __construct( $message, $credentials_form, $folder ) {
		parent::__construct( $message, $credentials_form );

		$this->folder = $folder;
	}

	/**
	 * Retrieves the folder associated with the object.
	 *
	 * @return string The folder path or name assigned to the object.
	 */
	public function folder() {
		return $this->folder;
	}
}
