<?php
/**
 * File: Util_WpFile_FilesystemWriteException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemWriteException
 */
class Util_WpFile_FilesystemWriteException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Filename
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * File contents
	 *
	 * @var string
	 */
	private $file_contents;

	/**
	 * Initializes the object with a message, a credentials form, a filename, and file contents.
	 *
	 * This constructor sets up the object with the specified message, credentials form,
	 * filename, and the content of the file. It also invokes the parent class constructor.
	 *
	 * @param string $message The message to associate with the object.
	 * @param string $credentials_form The credentials form content.
	 * @param string $filename The name or path of the file associated with the object.
	 * @param string $file_contents The contents of the file.
	 */
	public function __construct( $message, $credentials_form, $filename, $file_contents ) {
		parent::__construct( $message, $credentials_form );

		$this->filename      = $filename;
		$this->file_contents = $file_contents;
	}

	/**
	 * Retrieves the filename associated with the object.
	 *
	 * @return string The filename assigned during object initialization.
	 */
	public function filename() {
		return $this->filename;
	}

	/**
	 * Retrieves the contents of the file associated with the object.
	 *
	 * @return string The file contents assigned during object initialization.
	 */
	public function file_contents() {
		return $this->file_contents;
	}
}
