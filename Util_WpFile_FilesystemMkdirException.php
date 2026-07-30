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
	private $w3tc_folder;

	/**
	 * Initializes the object with a message, credentials form, and folder.
	 *
	 * This constructor sets up the object by assigning values to the provided message,
	 * credentials form, and folder properties. It also invokes the parent class's constructor
	 * to initialize shared properties.
	 *
	 * @param string $w3tc_message The message to associate with the object.
	 * @param string $credentials_form The credentials form content.
	 * @param string $w3tc_folder The folder associated with the object.
	 */
	public function __construct( $w3tc_message, $credentials_form, $w3tc_folder ) {
		parent::__construct( $w3tc_message, $credentials_form );

		$this->w3tc_folder = $w3tc_folder;
	}

	/**
	 * Retrieves the folder associated with the object.
	 *
	 * @return string The folder assigned during object initialization.
	 */
	public function folder() {
		return $this->w3tc_folder;
	}
}
