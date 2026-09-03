<?php
/**
 * Require control-byte stripping inside escape_header_value.
 *
 * @package W3TC
 * @since   2.10.6
 */

namespace W3TC\Sniffs\Security;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Ensures Cache_File_Generic::escape_header_value strips CR/LF/NUL and
 * does not strip Link angle brackets.
 *
 * @since 2.10.6
 */
class RequireSanitizeDirectiveValueSniff implements Sniff {
	/**
	 * @return array
	 */
	public function register() {
		return array( T_FUNCTION );
	}

	/**
	 * @param File $phpcs_file File.
	 * @param int  $stack_ptr  Pointer.
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$filename = basename( $phpcs_file->getFilename() );
		if ( 'Cache_File_Generic.php' !== $filename && 0 !== strpos( $filename, 'escape_header_' ) ) {
			return;
		}

		$tokens = $phpcs_file->getTokens();
		$name   = $phpcs_file->getDeclarationName( $stack_ptr );
		if ( 'escape_header_value' !== $name ) {
			return;
		}

		if ( ! isset( $tokens[ $stack_ptr ]['scope_opener'], $tokens[ $stack_ptr ]['scope_closer'] ) ) {
			return;
		}

		$start           = $tokens[ $stack_ptr ]['scope_opener'];
		$end             = $tokens[ $stack_ptr ]['scope_closer'];
		$found_controls  = false;
		$found_brackets  = false;
		for ( $i = $start; $i < $end; $i++ ) {
			if ( T_CONSTANT_ENCAPSED_STRING !== $tokens[ $i ]['code'] ) {
				continue;
			}
			$raw = $tokens[ $i ]['content'];
			if ( false !== strpos( $raw, '<>' ) ) {
				$found_brackets = true;
			}
			if (
				false !== strpos( $raw, '\\x00' )
				&& false !== strpos( $raw, '\\r' )
				&& false !== strpos( $raw, '\\n' )
			) {
				$found_controls = true;
			}
		}

		if ( ! $found_controls ) {
			$phpcs_file->addError(
				'escape_header_value() must strip CR/LF/NUL before writing Header directives.',
				$stack_ptr,
				'MissingSanitize'
			);
		}

		if ( $found_brackets ) {
			$phpcs_file->addError(
				'escape_header_value() must not strip Link angle brackets from Header values.',
				$stack_ptr,
				'StripsLinkBrackets'
			);
		}
	}
}
