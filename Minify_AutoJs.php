<?php
/**
 * File: Minify_AutoJs.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Minify_AutoJs
 */
class Minify_AutoJs {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Processed buffer
	 *
	 * @var string
	 */
	private $buffer;

	/**
	 * JS files to ignore
	 *
	 * @var array
	 */
	private $ignore_js_files;

	/**
	 * Embed type
	 *
	 * @var string
	 */
	private $embed_type;

	/**
	 * Helper object to use
	 *
	 * @var _W3_MinifyHelpers
	 */
	private $minify_helpers;

	/**
	 * Array of processed scripts
	 *
	 * @var array
	 */
	private $debug_minified_urls = array();

	/**
	 * Current list of script files to minify
	 *
	 * @var array
	 */
	private $files_to_minify;

	/**
	 * Current group type
	 *
	 * @var string
	 */
	private $group_type = 'head';

	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Constructor for the Minify_AutoJs class.
	 *
	 * @param object $config          Configuration object containing settings.
	 * @param string $buffer          HTML buffer to process.
	 * @param object $minify_helpers  Helper class for minification operations.
	 *
	 * @return void
	 */
	public function __construct( $config, $buffer, $minify_helpers ) {
		$this->config         = $config;
		$this->debug          = $config->get_boolean( 'minify.debug' );
		$this->buffer         = $buffer;
		$this->minify_helpers = $minify_helpers;

		// ignored files.
		$this->ignore_js_files = $this->config->get_array( 'minify.reject.files.js' );
		$this->ignore_js_files = array_map( array( '\W3TC\Util_Environment', 'normalize_file' ), $this->ignore_js_files );

		// define embed type.
		$this->embed_type = array(
			'head' => $this->config->get_string( 'minify.js.header.embed_type' ),
			'body' => $this->config->get_string( 'minify.js.body.embed_type' ),
		);
	}

	/**
	 * Executes the minification process on the current buffer.
	 *
	 * @return string The modified HTML buffer after processing.
	 */
	public function execute() {
		// find all script tags.
		$buffer_nocomments = preg_replace( '~<!--.*?-->\s*~s', '', $this->buffer );
		$matches           = null;

		// end of <head> means another group of scripts, cannt be combined.
		if ( ! preg_match_all( '~(<script\s*[^>]*>.*?</script>|</head>)~is', $buffer_nocomments, $matches ) ) {
			$matches = null;
		}

		if ( is_null( $matches ) ) {
			return $this->buffer;
		}

		$script_tags = $matches[1];
		$script_tags = apply_filters( 'w3tc_minify_js_script_tags', $script_tags );

		// pass scripts.
		$this->files_to_minify = array(
			'sync'  => array(
				'embed_pos' => 0,
				'files'     => array(),
			),
			'async' => array(
				'embed_pos' => 0,
				'files'     => array(),
			),
			'defer' => array(
				'embed_pos' => 0,
				'files'     => array(),
			),
		);

		$count = count( $script_tags );
		for ( $n = 0; $n < $count; $n++ ) {
			$this->process_script_tag( $script_tags[ $n ], $n );
		}

		$this->flush_collected( 'sync', '' );
		$this->flush_collected( 'async', '' );
		$this->flush_collected( 'defer', '' );

		return $this->buffer;
	}

	/**
	 * Retrieves the list of debug URLs for the minified files.
	 *
	 * @return array List of URLs for debug purposes.
	 */
	public function get_debug_minified_urls() {
		return $this->debug_minified_urls;
	}

	/**
	 * Processes a single script tag in the buffer.
	 *
	 * @param string $script_tag       The HTML script tag to process.
	 * @param int    $script_tag_number The index of the script tag being processed.
	 *
	 * @return void
	 */
	private function process_script_tag( $script_tag, $script_tag_number ) {
		if ( $this->debug ) {
			Minify_Core::log( 'processing tag ' . substr( $script_tag, 0, 150 ) );
		}

		$tag_pos = strpos( $this->buffer, $script_tag );
		if ( false === $tag_pos ) {
			// script is external but not found, skip processing it.
			if ( $this->debug ) {
				Minify_Core::log( 'script not found:' . $script_tag );
			}

			return;
		}

		$match = null;
		if ( ! preg_match( '~<script\s+[^<>]*src=["\']?([^"\'> ]+)["\'> ]~is', $script_tag, $match ) ) {
			$match = null;
		}

		if ( is_null( $match ) ) {
			$data = array(
				'script_tag_original' => $script_tag,
				'script_tag_new'      => $script_tag,
				'script_tag_number'   => $script_tag_number,
				'script_tag_pos'      => $tag_pos,
				'should_replace'      => false,
				'buffer'              => $this->buffer,
			);

			$data         = apply_filters( 'w3tc_minify_js_do_local_script_minification', $data );
			$this->buffer = $data['buffer'];

			if ( $data['should_replace'] ) {
				$this->buffer = substr_replace(
					$this->buffer,
					$data['script_tag_new'],
					$tag_pos,
					strlen( $script_tag )
				);
			}

			// it's not external script, have to flush what we have before it.
			if ( $this->debug ) {
				Minify_Core::log( 'its not src=, flushing' );
			}

			$this->flush_collected( 'sync', $script_tag );

			if ( preg_match( '~</head>~is', $script_tag, $match ) ) {
				$this->group_type = 'body';
			}

			return;
		}

		$script_src = $match[1];
		$script_src = Util_Environment::url_relative_to_full( $script_src );
		$file       = Util_Environment::url_to_docroot_filename( $script_src );

		$step1_result = $this->minify_helpers->is_file_for_minification( $script_src, $file );
		if ( 'url' === $step1_result ) {
			$file = $script_src;
		}

		$step1 = ! empty( $step1_result );
		$step2 = ! in_array( $file, $this->ignore_js_files, true );

		$do_tag_minification = $step1 && $step2;
		$do_tag_minification = apply_filters( 'w3tc_minify_js_do_tag_minification', $do_tag_minification, $script_tag, $file );

		if ( ! $do_tag_minification ) {
			if ( $this->debug ) {
				Minify_Core::log(
					'file ' . $file .
					' didnt pass minification check:' .
					' file_for_min: ' . ( $step1 ? 'true' : 'false' ) .
					' ignore_js_files: ' . ( $step2 ? 'true' : 'false' )
				);
			}

			$data = array(
				'script_tag_original' => $script_tag,
				'script_tag_new'      => $script_tag,
				'script_tag_number'   => $script_tag_number,
				'script_tag_pos'      => $tag_pos,
				'script_src'          => $script_src,
				'should_replace'      => false,
				'buffer'              => $this->buffer,
			);

			$data         = apply_filters( 'w3tc_minify_js_do_excluded_tag_script_minification', $data );
			$this->buffer = $data['buffer'];

			if ( $data['should_replace'] ) {
				$this->buffer = substr_replace(
					$this->buffer,
					$data['script_tag_new'],
					$tag_pos,
					strlen( $script_tag )
				);
			}

			$this->flush_collected( 'sync', $script_tag );

			return;
		}

		$this->debug_minified_urls[] = $file;
		$this->buffer                = substr_replace(
			$this->buffer,
			'',
			$tag_pos,
			strlen( $script_tag )
		);

		$m = null;
		if ( ! preg_match( '~\s+(async|defer)[> ]~is', $script_tag, $m ) ) {
			$sync_type = 'sync';

			// for head group - put minified file at the place of first script
			// for body - put at the place of last script, to make as more DOM
			// objects available as possible.
			if (
				count( $this->files_to_minify[ $sync_type ]['files'] ) <= 0 ||
				'body' === $this->group_type
			) {
				$this->files_to_minify[ $sync_type ]['embed_pos'] = $tag_pos;
			}
		} else {
			$sync_type                                        = strtolower( $m[1] );
			$this->files_to_minify[ $sync_type ]['embed_pos'] = $tag_pos;
		}

		$this->files_to_minify[ $sync_type ]['files'][] = $file;

		if ( 'minify' === $this->config->get_string( 'minify.js.method' ) ) {
			$this->flush_collected( $sync_type, '' );
		}
	}

	/**
	 * Flushes the collected scripts for a given synchronization type.
	 *
	 * @param string $sync_type        The synchronization type ('sync', 'async', 'defer').
	 * @param string $last_script_tag  The last script tag in the group being processed.
	 *
	 * @return void
	 */
	private function flush_collected( $sync_type, $last_script_tag ) {
		if ( count( $this->files_to_minify[ $sync_type ]['files'] ) <= 0 ) {
			return;
		}

		$do_flush_collected = apply_filters( 'w3tc_minify_js_do_flush_collected', true, $last_script_tag, $this, $sync_type );
		if ( ! $do_flush_collected ) {
			return;
		}

		// build minified script tag.
		if ( 'sync' === $sync_type ) {
			$embed_type = $this->embed_type[ $this->group_type ];
		} elseif ( 'async' === $sync_type ) {
			$embed_type = 'nb-async';
		} elseif ( 'defer' === $sync_type ) {
			$embed_type = 'nb-defer';
		}

		$data = array(
			'files_to_minify' => $this->files_to_minify[ $sync_type ]['files'],
			'embed_pos'       => $this->files_to_minify[ $sync_type ]['embed_pos'],
			'embed_type'      => $embed_type,
			'buffer'          => $this->buffer,
		);

		$data         = apply_filters( 'w3tc_minify_js_step', $data );
		$this->buffer = $data['buffer'];

		if ( ! empty( $data['files_to_minify'] ) ) {
			$url = $this->minify_helpers->get_minify_url_for_files( $data['files_to_minify'], 'js' );

			$script = '';
			if ( ! is_null( $url ) ) {
				$script .= $this->minify_helpers->generate_script_tag( $url, $data['embed_type'] );
			}

			$data['script_to_embed_url']  = $url;
			$data['script_to_embed_body'] = $script;
			$data                         = apply_filters( 'w3tc_minify_js_step_script_to_embed', $data );
			$this->buffer                 = $data['buffer'];

			if ( $this->config->getf_boolean( 'minify.js.http2push' ) ) {
				$this->minify_helpers->http2_header_add( $data['script_to_embed_url'], 'script' );
			}

			// replace.
			$this->buffer = substr_replace(
				$this->buffer,
				$data['script_to_embed_body'],
				$data['embed_pos'],
				0
			);

			foreach ( $this->files_to_minify as $key => $i ) {
				if ( $key !== $sync_type && $i['embed_pos'] >= $data['embed_pos'] ) {
					$this->files_to_minify[ $key ]['embed_pos'] += strlen( $data['script_to_embed_body'] );
				}
			}
		}

		$this->files_to_minify[ $sync_type ] = array(
			'embed_pos' => 0,
			'files'     => array(),
		);
	}
}
