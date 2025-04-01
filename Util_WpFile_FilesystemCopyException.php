<?php
/**
 * File: Util_WpFile_FilesystemCopyException.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_WpFile_FilesystemCopyException
 */
class Util_WpFile_FilesystemCopyException extends Util_WpFile_FilesystemOperationException {
	/**
	 * Source filename
	 *
	 * @var string
	 */
	private $source_filename;

	/**
	 * Destination filename
	 *
	 * @var string
	 */
	private $destination_filename;

	/**
	 * Initializes the object with a message, credentials form, source filename, and destination filename.
	 *
	 * This constructor sets up the object by assigning values to the provided message,
	 * credentials form, source filename, and destination filename properties. It also invokes
	 * the parent class's constructor to initialize shared properties.
	 *
	 * @param string $message              The message to associate with the object.
	 * @param string $credentials_form     The credentials form content.
	 * @param string $source_filename      The name of the source file.
	 * @param string $destination_filename The name of the destination file.
	 */
	public function __construct(
		$message,
		$credentials_form,
		$source_filename,
		$destination_filename
	) {
		parent::__construct( $message, $credentials_form );

		$this->source_filename      = $source_filename;
		$this->destination_filename = $destination_filename;
	}

	/**
	 * Retrieves the source filename associated with the object.
	 *
	 * @return string The source filename assigned during object initialization.
	 */
	public function source_filename() {
		return $this->source_filename;
	}

	/**
	 * Retrieves the destination filename associated with the object.
	 *
	 * @return string The destination filename assigned during object initialization.
	 */
	public function destination_filename() {
		return $this->destination_filename;
	}
}
