<?php
/**
 * File: Util_WpFile_FilesystemChmodException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemChmodException
 */
class Util_WpFile_FilesystemChmodException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Filename
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * Permission
	 *
	 * @var string
	 */
	private $permission;

	/**
	 * Constructor for initializing an object with a message, credentials form, filename, and permission.
	 *
	 * This constructor initializes the class with a message and credentials form using
	 * the parent class's constructor, and also sets the `filename` and `permission` properties.
	 *
	 * @param string $message          The message to initialize the object with.
	 * @param mixed  $credentials_form The credentials form associated with the object.
	 * @param string $filename         The filename associated with the object.
	 * @param string $permission       The permission associated with the object.
	 */
	public function __construct( $message, $credentials_form, $filename, $permission ) {
		parent::__construct( $message, $credentials_form );

		$this->filename   = $filename;
		$this->permission = $permission;
	}

	/**
	 * Returns the filename associated with the object.
	 *
	 * This method provides access to the `filename` property of the object.
	 *
	 * @return string The filename.
	 */
	public function filename() {
		return $this->filename;
	}

	/**
	 * Returns the permission associated with the object.
	 *
	 * This method provides access to the `permission` property of the object.
	 *
	 * @return string The permission.
	 */
	public function permission() {
		return $this->permission;
	}
}
