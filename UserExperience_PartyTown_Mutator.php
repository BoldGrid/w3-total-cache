<?php
/**
 * File: UserExperience_PartyTown_Mutator.php
 *
 * PartyTown feature mutator for buffer processing.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience PartyTown Mutator.
 *
 * @since X.X.X
 */
class UserExperience_PartyTown_Mutator {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $config;

	/**
	 * Array of includes.
	 *
	 * @var array
	 */
	private $includes = array();

	/**
	 * Page buffer.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * User Experience PartyTown Mutator constructor.
	 *
	 * @since X.X.X
	 *
	 * @param object $config Config object.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * Runs User Experience PartyTown Mutator.
	 *
	 * @since X.X.X
	 *
	 * @param string $buffer Buffer string containing browser output.
	 *
	 * @return string
	 */
	public function run( $buffer ) {
		$r = apply_filters(
			'w3tc_partytown_mutator_before',
			array(
				'buffer' => $buffer,
			)
		);

		$this->buffer = $r['buffer'];

		// Sets includes whose matches will be stripped site-wide.
		$this->includes = $this->config->get_array(
			array(
				'user-experience-partytown',
				'includes',
			)
		);

		$this->buffer = preg_replace_callback(
			'~(<link[^>]+href[^>]+>)|(<script[^>]+src[^>]+></script>)~is',
			array( $this, 'modify_content' ),
			$this->buffer
		);

		return $this->buffer;
	}

	/**
	 * Modifies matched link/script tag from HTML content.
	 *
	 * @since X.X.X
	 *
	 * @param array $matches array of matched CSS/JS entries.
	 *
	 * @return string
	 */
	public function modify_content( $matches ) {
		$content = $matches[0];

		// Early return if not the main query or content not a match.
		if ( ! is_main_query() || ! $this->is_content_included( $content ) ) {
			return $content;
		}

		// Check if it's a script tag and type="text/partytown" is not already present.
		if ( strpos( $content, '<script' ) !== false && strpos( $content, 'type="text/partytown"' ) === false ) {
			$content = preg_replace( '/<script(\s|>)/', '<script type="text/partytown"$1', $content, 1 );
		}

		return $content;
	}

	/**
	 * Checks if content matches defined patterns for PartTown offload.
	 *
	 * @since X.X.X
	 *
	 * @param string $content script tag string.
	 *
	 * @return boolean
	 */
	private function is_content_included( $content ) {
		global $wp;

		foreach ( $this->includes as $include ) {
			if ( ! empty( $include ) ) {
				if ( strpos( $content, $include ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}
}
