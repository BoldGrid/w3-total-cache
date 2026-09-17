<?php
/**
 * Generates the public W3TC hooks reference from production PHP sources.
 *
 * @since {WP_VERSION}
 */

declare(strict_types=1);

$root        = \dirname( __DIR__ );
$output_file = $root . '/docs/hooks.md';
$check       = \in_array( '--check', $argv, true );
$hooks       = array();
$excluded    = array( '.git', 'node_modules', 'qa', 'tests', 'tmp', 'vendor' );
$iterator    = new RecursiveIteratorIterator(
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

	$path     = $file->getPathname();
	$relative = \str_replace( '\\', '/', \substr( $path, \strlen( $root ) + 1 ) );
	$source   = \file_get_contents( $path );

	if ( false === $source ) {
		\fwrite( STDERR, "Unable to read {$relative}.\n" );
		exit( 1 );
	}

	$tokens = \token_get_all( $source );
	$line   = 1;
	$count  = \count( $tokens );

	for ( $index = 0; $index < $count; ++$index ) {
		$token      = $tokens[ $index ];
		$token_text = \is_array( $token ) ? $token[1] : $token;
		$token_line = \is_array( $token ) ? $token[2] : $line;
		$line      += \substr_count( $token_text, "\n" );

		if ( ! \is_array( $token ) || T_STRING !== $token[0] ) {
			continue;
		}

		$function = \strtolower( $token[1] );
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

		$arguments = array();
		$current   = '';
		$depth     = 1;

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

		if ( empty( $arguments[0] ) ) {
			continue;
		}

		$hook_name = normalize_hook_expression( $arguments[0] );
		$is_early_hook = 0 === \strpos( $function, 'w3tc_' );
		if ( null === $hook_name || ( ! $is_early_hook && ! \preg_match( '/^w3(?:tc)?[-_]/i', $hook_name ) ) ) {
			continue;
		}

		$type       = false !== \strpos( $function, 'apply_filters' ) ? 'Filter' : 'Action';
		$register   = $is_early_hook ? 'w3tc_add_action()' : ( 'Filter' === $type ? 'add_filter()' : 'add_action()' );
		$parameters = \array_slice( $arguments, 1 );
		$signature  = empty( $parameters ) ? 'None' : \implode( ', ', \array_map( 'normalize_expression', $parameters ) );
		$key        = $type . "\0" . $register . "\0" . $hook_name;

		if ( ! isset( $hooks[ $key ] ) ) {
			$hooks[ $key ] = array(
				'name'       => $hook_name,
				'type'       => $type,
				'register'   => $register,
				'signatures' => array(),
				'sources'    => array(),
			);
		}

		$hooks[ $key ]['signatures'][ $signature ] = true;
		$hooks[ $key ]['sources'][ $relative . '#L' . $token_line ] = true;
	}
}

\uasort(
	$hooks,
	static function ( array $left, array $right ): int {
		return \strnatcasecmp( $left['name'], $right['name'] )
			?: \strcmp( $left['type'], $right['type'] )
			?: \strcmp( $left['register'], $right['register'] );
	}
);

$markdown = generate_markdown( $hooks );

if ( $check ) {
	$existing = \is_file( $output_file ) ? \file_get_contents( $output_file ) : false;
	if ( $markdown !== $existing ) {
		\fwrite( STDERR, "docs/hooks.md is out of date. Run: php bin/generate-hooks-docs.php\n" );
		exit( 1 );
	}

	echo "docs/hooks.md is up to date (" . \count( $hooks ) . " hooks).\n";
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

echo "Generated docs/hooks.md (" . \count( $hooks ) . " hooks).\n";

/**
 * Converts a hook-name expression to a stable display pattern.
 *
 * @since {WP_VERSION}
 *
 * @param string $expression PHP expression.
 * @return string|null
 */
function normalize_hook_expression( string $expression ): ?string {
	$expression = \trim( $expression );
	$parts      = \preg_split( '/\s*\.\s*/', $expression );

	if ( false === $parts ) {
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
					return '{' . \preg_replace( '/[\s$]+/', '', $placeholder[1] ) . '}';
				},
				$value
			);
			$value = \preg_replace( '/\$([A-Za-z_][A-Za-z0-9_]*)/', '{$1}', (string) $value );
			$name .= \stripcslashes( (string) $value );
		} elseif ( \preg_match( '/^\$([A-Za-z_][A-Za-z0-9_]*)(.*)$/s', $part, $matches ) ) {
			$name .= '{' . $matches[1] . \preg_replace( '/\s+/', '', $matches[2] ) . '}';
		} else {
			return null;
		}
	}

	return $name;
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
	$markdown .= "W3 Total Cache exposes the actions and filters below for integrations and customizations. ";
	$markdown .= "Names containing `{...}` are dynamic patterns; substitute the value described by the placeholder at runtime.\n\n";
	$markdown .= "This reference is generated from production PHP sources. Do not edit its tables manually. Run ";
	$markdown .= "`php bin/generate-hooks-docs.php` to regenerate them, or `php bin/generate-hooks-docs.php --check` to verify they are current.\n\n";
	$markdown .= "## Usage\n\n";
	$markdown .= "Register callbacks with the function in the Register with column. Most hooks use WordPress's `add_action()` or `add_filter()`. ";
	$markdown .= "Hooks dispatched before WordPress is available use W3TC's `w3tc_add_action()` for both actions and filters. ";
	$markdown .= "Use the Parameters column to set the callback's accepted argument count. ";
	$markdown .= "For filters, the first parameter is the value your callback must return.\n\n";
	$markdown .= "```php\n";
	$markdown .= "add_filter(\n";
	$markdown .= "\t'w3tc_can_cache',\n";
	$markdown .= "\tstatic function ( \$can_cache ) {\n";
	$markdown .= "\t\treturn \$can_cache;\n";
	$markdown .= "\t}\n";
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
 * @param string                              $heading Table heading.
 * @param array<int,array<string,mixed>>       $hooks   Hook records.
 * @return string
 */
function render_table( string $heading, array $hooks ): string {
	$markdown  = "## {$heading}\n\n";
	$markdown .= "| Hook | Register with | Parameters | Defined in |\n";
	$markdown .= "| --- | --- | --- | --- |\n";

	foreach ( $hooks as $hook ) {
		$signatures = \array_keys( $hook['signatures'] );
		$sources    = \array_keys( $hook['sources'] );
		\sort( $signatures, SORT_NATURAL | SORT_FLAG_CASE );
		\sort( $sources, SORT_NATURAL | SORT_FLAG_CASE );

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

		$markdown .= '| ' . markdown_code( $hook['name'] ) . ' | ' . markdown_code( $hook['register'] ) . ' | ' . $parameter_text . ' | ' . $source_text . " |\n";
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
