<?php
/**
 * Generates the public W3TC hooks reference from production PHP sources.
 *
 * @package W3TC
 * @since {WP_VERSION}
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions

$root        = \dirname( __DIR__ );
$output_file = $root . '/docs/hooks.md';
$check       = \in_array( '--check', $argv, true );
$self_test   = \in_array( '--self-test', $argv, true );
$hooks       = array();
$excluded    = array( '.git', 'node_modules', 'qa', 'tests', 'tmp', 'vendor' );

if ( $self_test ) {
	run_self_test();
	exit( 0 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ) use ( $excluded ): bool {
			return ! $file->isDir() || ! \in_array( $file->getFilename(), $excluded, true );
		}
	)
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== \strtolower( $file->getExtension() ) ) {
		continue;
	}

	$source_path = $file->getPathname();
	$relative    = \str_replace( '\\', '/', \substr( $source_path, \strlen( $root ) + 1 ) );
	$source      = \file_get_contents( $source_path );

	if ( false === $source ) {
		\fwrite( STDERR, "Unable to read {$relative}.\n" );
		exit( 1 );
	}

	foreach ( extract_hook_calls( $source, $relative ) as $call ) {
		$key = $call['type'] . "\0" . $call['register'] . "\0" . $call['name'];

		if ( ! isset( $hooks[ $key ] ) ) {
			$hooks[ $key ] = array(
				'name'       => $call['name'],
				'type'       => $call['type'],
				'register'   => $call['register'],
				'arities'    => array(),
				'signatures' => array(),
				'sources'    => array(),
			);
		}

		$hooks[ $key ]['arities'][ $call['arity'] ]                          = true;
		$hooks[ $key ]['signatures'][ $call['signature'] ][ $call['arity'] ] = true;
		$hooks[ $key ]['sources'][ $call['source'] ]                         = true;
	}
}

\uasort(
	$hooks,
	static function ( array $left, array $right ): int {
		$name_comparison = \strnatcasecmp( $left['name'], $right['name'] );
		if ( 0 !== $name_comparison ) {
			return $name_comparison;
		}

		$type_comparison = \strcmp( $left['type'], $right['type'] );
		return 0 !== $type_comparison ? $type_comparison : \strcmp( $left['register'], $right['register'] );
	}
);

$markdown = generate_markdown( $hooks );

if ( $check ) {
	$existing = \is_file( $output_file ) ? \file_get_contents( $output_file ) : false;
	if ( $markdown !== $existing ) {
		\fwrite( STDERR, "docs/hooks.md is out of date. Run: php bin/generate-hooks-docs.php\n" );
		exit( 1 );
	}

	echo 'docs/hooks.md is up to date (' . \count( $hooks ) . " hooks).\n";
	exit( 0 );
}

if ( ! \is_dir( \dirname( $output_file ) ) && ! \mkdir( \dirname( $output_file ), 0777, true ) ) {
	\fwrite( STDERR, "Unable to create docs directory.\n" );
	exit( 1 );
}

if ( false === \file_put_contents( $output_file, $markdown ) ) {
	\fwrite( STDERR, "Unable to write docs/hooks.md.\n" );
	exit( 1 );
}

echo 'Generated docs/hooks.md (' . \count( $hooks ) . " hooks).\n";

/**
 * Exercises token compatibility, declarations, placeholders, and arities.
 *
 * @since {WP_VERSION}
 *
 * @return void
 * @throws RuntimeException When a fixture assertion fails.
 */
function run_self_test(): void {
	$fixture_path = \dirname( __DIR__ ) . '/tests/fixtures/hooks-documentation.php.fixture';
	$fixture      = \file_get_contents( $fixture_path );
	if ( false === $fixture ) {
		throw new RuntimeException( 'Unable to read hooks documentation fixture.' );
	}

	$calls = extract_hook_calls( $fixture, 'fixture.php' );
	if ( 6 !== \count( $calls ) ) {
		throw new RuntimeException( 'Expected six fixture hook calls; function declarations must be ignored.' );
	}

	$filter_arities = array();
	$names          = array();
	foreach ( $calls as $call ) {
		if ( 'w3tc_fixture_hook' === $call['name'] ) {
			$filter_arities[] = $call['arity'];
		}
		$names[ $call['name'] ] = true;
		if ( false !== \strpos( $call['name'], 'w3tc_hook' ) ) {
			throw new RuntimeException( 'Dispatcher declaration parameter was emitted as a hook.' );
		}
	}

	\sort( $filter_arities, SORT_NUMERIC );
	if ( array( 1, 3 ) !== $filter_arities ) {
		throw new RuntimeException( 'Qualified hook calls or their arities were not extracted.' );
	}
	if ( ! isset( $names['w3tc_dynamic_{extension}'] ) ) {
		throw new RuntimeException( 'Dynamic placeholder was not canonicalized.' );
	}
	if ( ! isset( $names['w3tc_fixture_dotted.rgroups'], $names['w3tc_fixture_dotted.cookiegroups.groups'] ) ) {
		throw new RuntimeException( 'Dotted hook literals were dropped; dots inside quoted literals are not concatenation.' );
	}
	if ( ! isset( $names['w3tc_fixture_dotted.prefix.{suffix}'] ) ) {
		throw new RuntimeException( 'Concatenated expression with a dotted literal segment was not normalized.' );
	}

	$expressions = array(
		"'w3tc_ui_config_item_' . \$action_key" => 'w3tc_ui_config_item_{action}',
		"'w3tc_settings_page-' . \$this->_page" => 'w3tc_settings_page-{page}',
		"'w3tc_a.b' . \$w3tc_key . '.c'"        => 'w3tc_a.b{key}.c',
		"'w3tc_x_' . \$w3tc_area['id']"         => 'w3tc_x_{area_id}',
		"'w3tc_dotted.name'"                    => 'w3tc_dotted.name',
		'"w3tc_dotted.name"'                    => 'w3tc_dotted.name',
		'"w3tc_dotted.{$w3tc_extension}.tail"'  => 'w3tc_dotted.{extension}.tail',
	);
	foreach ( $expressions as $expression => $expected ) {
		$actual = normalize_hook_expression( $expression );
		if ( $expected !== $actual ) {
			throw new RuntimeException( 'A concatenated or dotted hook expression did not normalize as expected.' );
		}
	}

	$table = render_table(
		'Fixture filters',
		array(
			array(
				'name'       => 'w3tc_fixture_hook',
				'register'   => 'add_filter()',
				'arities'    => array(
					1 => true,
					3 => true,
				),
				'signatures' => array(
					'$value'                   => array( 1 => true ),
					'$value, $context, $extra' => array( 3 => true ),
				),
				'sources'    => array( 'fixture.php#L1' => true ),
			),
		)
	);
	if ( false === \strpos( $table, '| 1, 3 |' ) ) {
		throw new RuntimeException( 'Multiple callback arities were not rendered.' );
	}

	echo "Hook documentation self-test passed.\n";
}

/**
 * Extracts supported hook calls from one PHP source file.
 *
 * @since {WP_VERSION}
 *
 * @param string $source   PHP source.
 * @param string $relative Source path used in links.
 * @return array<int,array<string,mixed>>
 */
function extract_hook_calls( string $source, string $relative ): array {
	$calls  = array();
	$tokens = \token_get_all( $source );
	$line   = 1;
	$count  = \count( $tokens );

	for ( $index = 0; $index < $count; ++$index ) {
		$token      = $tokens[ $index ];
		$token_text = \is_array( $token ) ? $token[1] : $token;
		$token_line = \is_array( $token ) ? $token[2] : $line;
		$line      += \substr_count( $token_text, "\n" );

		if ( ! is_hook_function_token( $token ) || is_function_declaration( $tokens, $index ) ) {
			continue;
		}

		$function = \strtolower( \ltrim( $token[1], '\\' ) );
		if ( false !== \strrpos( $function, '\\' ) ) {
			$function = \substr( $function, \strrpos( $function, '\\' ) + 1 );
		}
		if ( ! \in_array( $function, array( 'apply_filters', 'apply_filters_ref_array', 'do_action', 'do_action_ref_array', 'w3tc_apply_filters', 'w3tc_do_action' ), true ) ) {
			continue;
		}

		$open = $index + 1;
		while ( $open < $count && \is_array( $tokens[ $open ] ) && \in_array( $tokens[ $open ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			++$open;
		}
		if ( $open >= $count || '(' !== $tokens[ $open ] ) {
			continue;
		}

		$arguments = parse_call_arguments( $tokens, $open );
		if ( empty( $arguments[0] ) ) {
			continue;
		}

		$hook_name     = normalize_hook_expression( $arguments[0] );
		$is_early_hook = 0 === \strpos( $function, 'w3tc_' );
		if ( null === $hook_name || ( ! $is_early_hook && ! \preg_match( '/^w3(?:tc)?[-_]/i', $hook_name ) ) ) {
			continue;
		}

		$type       = false !== \strpos( $function, 'apply_filters' ) ? 'Filter' : 'Action';
		$register   = $is_early_hook ? 'w3tc_add_action()' : ( 'Filter' === $type ? 'add_filter()' : 'add_action()' );
		$parameters = \array_slice( $arguments, 1 );
		$calls[]    = array(
			'name'      => $hook_name,
			'type'      => $type,
			'register'  => $register,
			'arity'     => \count( $parameters ),
			'signature' => empty( $parameters ) ? 'None' : \implode( ', ', \array_map( 'normalize_expression', $parameters ) ),
			'source'    => $relative . '#L' . $token_line,
		);
	}

	return $calls;
}

/**
 * Reads top-level arguments from a tokenized function call.
 *
 * @since {WP_VERSION}
 *
 * @param array<int,mixed> $tokens PHP tokens.
 * @param int              $open   Opening parenthesis index.
 * @return array<int,string>
 */
function parse_call_arguments( array $tokens, int $open ): array {
	$arguments = array();
	$current   = '';
	$depth     = 1;
	$count     = \count( $tokens );

	for ( $cursor = $open + 1; $cursor < $count; ++$cursor ) {
		$part      = $tokens[ $cursor ];
		$part_text = \is_array( $part ) ? $part[1] : $part;

		if ( '(' === $part_text || '[' === $part_text || '{' === $part_text ) {
			++$depth;
		} elseif ( ')' === $part_text || ']' === $part_text || '}' === $part_text ) {
			--$depth;
			if ( 0 === $depth ) {
				$arguments[] = \trim( $current );
				break;
			}
		}

		if ( 1 === $depth && ',' === $part_text ) {
			$arguments[] = \trim( $current );
			$current     = '';
			continue;
		}

		$current .= $part_text;
	}

	return $arguments;
}

/**
 * Determines whether a token can name a hook dispatcher function.
 *
 * @since {WP_VERSION}
 *
 * @param mixed $token PHP token.
 * @return bool
 */
function is_hook_function_token( $token ): bool {
	if ( ! \is_array( $token ) ) {
		return false;
	}

	$name_tokens = array( T_STRING );
	if ( \defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
		$name_tokens[] = \constant( 'T_NAME_FULLY_QUALIFIED' );
		$name_tokens[] = \constant( 'T_NAME_QUALIFIED' );
	}

	return \in_array( $token[0], $name_tokens, true );
}

/**
 * Detects a named function declaration rather than a function call.
 *
 * @since {WP_VERSION}
 *
 * @param array<int,mixed> $tokens PHP tokens.
 * @param int              $index  Candidate name index.
 * @return bool
 */
function is_function_declaration( array $tokens, int $index ): bool {
	for ( $cursor = $index - 1; $cursor >= 0; --$cursor ) {
		$token = $tokens[ $cursor ];
		if ( \is_array( $token ) && \in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		if ( '&' === $token || ( \is_array( $token ) && '&' === $token[1] ) ) {
			continue;
		}

		return \is_array( $token ) && T_FUNCTION === $token[0];
	}

	return false;
}

/**
 * Converts a hook-name expression to a stable display pattern.
 *
 * @since {WP_VERSION}
 *
 * @param string $expression PHP expression.
 * @return string|null
 */
function normalize_hook_expression( string $expression ): ?string {
	$parts = split_concatenation( $expression );

	if ( null === $parts ) {
		return null;
	}

	$name = '';
	foreach ( $parts as $part ) {
		$part = \trim( $part );
		if ( \preg_match( '/^([\'"])(.*)\1$/s', $part, $matches ) ) {
			$value = $matches[2];
			$value = \preg_replace_callback(
				'/\{\$([^{}]+)\}/',
				static function ( array $placeholder ): string {
					return '{' . normalize_placeholder( $placeholder[1] ) . '}';
				},
				$value
			);
			$value = \preg_replace( '/\$([A-Za-z_][A-Za-z0-9_]*)/', '{$1}', (string) $value );
			$name .= \stripcslashes( (string) $value );
		} elseif ( \preg_match( '/^\$([A-Za-z_][A-Za-z0-9_]*)(.*)$/s', $part, $matches ) ) {
			$name .= '{' . normalize_placeholder( $matches[1] . $matches[2] ) . '}';
		} else {
			return null;
		}
	}

	return $name;
}

/**
 * Splits an expression on its concatenation operators.
 *
 * Dots inside quoted literals belong to the hook name, so the expression is tokenized and only
 * `.` operators found outside strings and nested constructs are treated as concatenation.
 *
 * @since {WP_VERSION}
 *
 * @param string $expression PHP expression.
 * @return array<int,string>|null Operands, or null when the expression cannot be tokenized.
 */
function split_concatenation( string $expression ): ?array {
	$expression = \trim( $expression );
	if ( '' === $expression ) {
		return null;
	}

	$tokens = \token_get_all( '<?php ' . $expression );
	\array_shift( $tokens );

	$operands = array();
	$current  = '';
	$depth    = 0;

	foreach ( $tokens as $token ) {
		$text = \is_array( $token ) ? $token[1] : $token;

		if ( \is_array( $token ) && \in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		if ( '.' === $token ) {
			if ( 0 === $depth ) {
				$operands[] = \trim( $current );
				$current    = '';
				continue;
			}
		} elseif ( \in_array( $text, array( '(', '[', '{', '${' ), true ) ) {
			++$depth;
		} elseif ( \in_array( $text, array( ')', ']', '}' ), true ) ) {
			--$depth;
		}

		$current .= $text;
	}

	$operands[] = \trim( $current );

	return $operands;
}

/**
 * Canonicalizes a dynamic hook placeholder.
 *
 * @since {WP_VERSION}
 *
 * @param string $placeholder PHP variable or property expression.
 * @return string
 */
function normalize_placeholder( string $placeholder ): string {
	$placeholder = \preg_replace( '/[\s$\'\"]+/', '', $placeholder );
	$placeholder = \preg_replace( '/^this->/', '', (string) $placeholder );
	$placeholder = \preg_replace( '/^_/', '', (string) $placeholder );
	$placeholder = \preg_replace( '/^w3tc_/', '', (string) $placeholder );
	$placeholder = \preg_replace( '/\[([^\]]+)\]/', '_$1', (string) $placeholder );
	$placeholder = \str_replace( '->', '_', (string) $placeholder );
	$placeholder = \preg_replace( '/_+/', '_', (string) $placeholder );

	$aliases = array(
		'action_key'         => 'action',
		'message_action_val' => 'action',
	);
	return $aliases[ $placeholder ] ?? (string) $placeholder;
}

/**
 * Makes a PHP expression compact enough for the generated table.
 *
 * @since {WP_VERSION}
 *
 * @param string $expression PHP expression.
 * @return string
 */
function normalize_expression( string $expression ): string {
	$expression = \preg_replace( '/\s+/', ' ', \trim( $expression ) );
	return null === $expression ? '' : $expression;
}

/**
 * Renders the complete hooks reference.
 *
 * @since {WP_VERSION}
 *
 * @param array<string,array<string,mixed>> $hooks Hook records.
 * @return string
 */
function generate_markdown( array $hooks ): string {
	$actions = array();
	$filters = array();

	foreach ( $hooks as $hook ) {
		if ( 'Action' === $hook['type'] ) {
			$actions[] = $hook;
		} else {
			$filters[] = $hook;
		}
	}

	$markdown  = "# W3 Total Cache hooks\n\n";
	$markdown .= 'W3 Total Cache exposes the actions and filters below for integrations and customizations. ';
	$markdown .= "Names containing `{...}` are dynamic patterns; substitute the value described by the placeholder at runtime.\n\n";
	$markdown .= 'This reference is generated from production PHP sources. Do not edit its tables manually. Run ';
	$markdown .= "`php bin/generate-hooks-docs.php` to regenerate them, or `php bin/generate-hooks-docs.php --check` to verify they are current.\n\n";
	$markdown .= "## Usage\n\n";
	$markdown .= "Register callbacks with the function in the Register with column. Most hooks use WordPress's `add_action()` or `add_filter()`. ";
	$markdown .= "Hooks dispatched before WordPress is available use W3TC's `w3tc_add_action()` for both actions and filters. ";
	$markdown .= 'For WordPress hooks, the Accepted arguments column is the integer to pass as the fourth argument to `add_action()` or `add_filter()`; multiple integers mean the hook is dispatched with different arities. ';
	$markdown .= 'For early hooks registered with `w3tc_add_action()`, the value is informational because that function does not take an accepted-arguments setting. ';
	$markdown .= "For filters, the first parameter is the value your callback must return.\n\n";
	$markdown .= "```php\n";
	$markdown .= "add_filter(\n";
	$markdown .= "\t'w3tc_can_cache',\n";
	$markdown .= "\tstatic function ( \$can_cache, \$content_grabber, \$buffer ) {\n";
	$markdown .= "\t\treturn \$can_cache;\n";
	$markdown .= "\t},\n";
	$markdown .= "\t10,\n";
	$markdown .= "\t3\n";
	$markdown .= ");\n";
	$markdown .= "```\n\n";
	$markdown .= render_table( 'Actions', $actions );
	$markdown .= render_table( 'Filters', $filters );

	return \rtrim( $markdown ) . "\n";
}

/**
 * Renders one hook-type table.
 *
 * @since {WP_VERSION}
 *
 * @param string                         $heading Table heading.
 * @param array<int,array<string,mixed>> $hooks   Hook records.
 * @return string
 */
function render_table( string $heading, array $hooks ): string {
	$markdown  = "## {$heading}\n\n";
	$markdown .= "| Hook | Register with | Accepted arguments | Parameters | Defined in |\n";
	$markdown .= "| --- | --- | ---: | --- | --- |\n";

	foreach ( $hooks as $hook ) {
		$arities = \array_keys( $hook['arities'] );
		$sources = \array_keys( $hook['sources'] );
		\sort( $arities, SORT_NUMERIC );
		\sort( $sources, SORT_NATURAL | SORT_FLAG_CASE );

		$signatures = array();
		foreach ( $hook['signatures'] as $signature => $signature_arities ) {
			$signature_arities = \array_keys( $signature_arities );
			\sort( $signature_arities, SORT_NUMERIC );
			$signatures[] = 1 < \count( $hook['signatures'] ) || 1 < \count( $arities )
				? \implode( ', ', $signature_arities ) . ': ' . $signature
				: $signature;
		}
		\sort( $signatures, SORT_NATURAL | SORT_FLAG_CASE );

		$arity_text     = \implode( ', ', $arities );
		$parameter_text = \implode( '<br>', \array_map( 'markdown_code', $signatures ) );
		$source_text    = \implode(
			'<br>',
			\array_map(
				static function ( string $source ): string {
					list( $path, $line ) = \explode( '#L', $source, 2 );
					return '[' . markdown_escape( $path ) . ':' . $line . '](../' . $path . '#L' . $line . ')';
				},
				$sources
			)
		);

		$markdown .= '| ' . markdown_code( $hook['name'] ) . ' | ' . markdown_code( $hook['register'] ) . ' | ' . $arity_text . ' | ' . $parameter_text . ' | ' . $source_text . " |\n";
	}

	return $markdown . "\n";
}

/**
 * Formats text as inline Markdown code.
 *
 * @since {WP_VERSION}
 *
 * @param string $text Text to format.
 * @return string
 */
function markdown_code( string $text ): string {
	return '`' . \str_replace( '|', '\|', $text ) . '`';
}

/**
 * Escapes text used in a Markdown table.
 *
 * @since {WP_VERSION}
 *
 * @param string $text Text to escape.
 * @return string
 */
function markdown_escape( string $text ): string {
	return \str_replace( array( '\\', '|' ), array( '\\\\', '\|' ), $text );
}
