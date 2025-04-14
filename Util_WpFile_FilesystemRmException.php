<?php
/**
 * File: Util_WpFile_FilesystemRmException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemRmException
 */
class Util_WpFile_FilesystemRmException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Filename
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * Initializes the object with a message, a credentials form, and a filename.
	 *
	 * This constructor sets up the object by assigning a message, credentials form,
	 * and filename. It also calls the parent class's constructor to handle shared initialization.
	 *
	 * @param string $message The message to associate with the object.
	 * @param string $credentials_form The credentials form content.
	 * @param string $filename The name or path of the file associated with the object.
	 */
	public function __construct( $message, $credentials_form, $filename ) {
		parent::__construct( $message, $credentials_form );

		$this->filename = $filename;
	}

	/**
	 * Retrieves the filename associated with the object.
	 *
	 * @return string The filename assigned during object initialization.
	 */
	public function filename() {
		return $this->filename;
	}
}
