<?php
/**
 * Forbidden shared SetupGuide nonce action.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC\Sniffs\Security;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags wp_verify_nonce( ..., 'w3tc_wizard' ) / wp_create_nonce( 'w3tc_wizard' ).
 *
 * @since X.X.X
 */
class NoLegacyWizardNonceSniff implements Sniff {
	/**
	 * @return array
	 */
	public function register() {
		return array( T_STRING );
	}

	/**
	 * @param File $phpcs_file File.
	 * @param int  $stack_ptr  Pointer.
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$name   = $tokens[ $stack_ptr ]['content'];
		if ( 'wp_verify_nonce' !== $name && 'wp_create_nonce' !== $name && 'wp_nonce_field' !== $name ) {
			return;
		}

		$open = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $stack_ptr, null, false, null, true );
		if ( false === $open ) {
			return;
		}

		$close = $tokens[ $open ]['parenthesis_closer'];
		for ( $i = $open; $i < $close; $i++ ) {
			if ( T_CONSTANT_ENCAPSED_STRING !== $tokens[ $i ]['code'] ) {
				continue;
			}
			$raw = $tokens[ $i ]['content'];
			if ( "'w3tc_wizard'" === $raw || '"w3tc_wizard"' === $raw ) {
				$phpcs_file->addError(
					'Shared w3tc_wizard nonce is forbidden; use per-action SetupGuide nonces.',
					$i,
					'Found'
				);
			}
		}
	}
}
