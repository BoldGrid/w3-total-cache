<?php
/**
 * File: Util_WpFile_FilesystemMkdirException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemMkdirException
 */
class Util_WpFile_FilesystemMkdirException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Folder
	 *
	 * @var string
	 */
	private $folder;

	/**
	 * Initializes the object with a message, credentials form, and folder.
	 *
	 * This constructor sets up the object by assigning values to the provided message,
	 * credentials form, and folder properties. It also invokes the parent class's constructor
	 * to initialize shared properties.
	 *
	 * @param string $message The message to associate with the object.
	 * @param string $credentials_form The credentials form content.
	 * @param string $folder The folder associated with the object.
	 */
	public function __construct( $message, $credentials_form, $folder ) {
		parent::__construct( $message, $credentials_form );

		$this->folder = $folder;
	}

	/**
	 * Retrieves the folder associated with the object.
	 *
	 * @return string The folder assigned during object initialization.
	 */
	public function folder() {
		return $this->folder;
	}
}
