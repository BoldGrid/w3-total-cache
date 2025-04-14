<?php
/**
 * File: Util_WpFile_FilesystemModifyException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemModifyException
 */
class Util_WpFile_FilesystemModifyException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Modifcation description
	 *
	 * @var string
	 */
	private $modification_description;

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
	 * Initializes the object with a message, credentials form, modification description, filename, and file contents.
	 *
	 * This constructor sets up the object by assigning values to the provided message, credentials form,
	 * modification description, filename, and optional file contents. It also invokes the parent class's
	 * constructor to initialize shared properties.
	 *
	 * @param string $message The message to associate with the object.
	 * @param string $credentials_form The credentials form content.
	 * @param string $modification_description A description of the modification being made.
	 * @param string $filename The name of the file associated with the modification.
	 * @param string $file_contents Optional. The contents of the file. Defaults to an empty string.
	 */
	public function __construct(
		$message,
		$credentials_form,
		$modification_description,
		$filename,
		$file_contents = ''
	) {
		parent::__construct( $message, $credentials_form );

		$this->modification_description = $modification_description;
		$this->filename                 = $filename;
		$this->file_contents            = $file_contents;
	}

	/**
	 * Retrieves the modification description associated with the object.
	 *
	 * @return string The modification description assigned during object initialization.
	 */
	public function modification_description() {
		return $this->modification_description;
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
	 * @return string The file contents assigned during object initialization. If no content was provided,
	 *                it defaults to an empty string.
	 */
	public function file_contents() {
		return $this->file_contents;
	}
}
