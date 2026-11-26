<?php
/**
 * File: PgCache_UriAnchor.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Anchors page-cache URI expressions to a path segment.
 *
 * Page cache and the MAx Cache directive both match through this helper.
 *
 * @since 2.11.1
 */
class PgCache_UriAnchor {
	/**
	 * Anchors one URI expression to a path segment.
	 *
	 * Each alternative is anchored on its own, including alternatives inside a group.
	 * An anchor or separator that alternative already matches is left in place, so a
	 * directory expression still matches descendants and the same directory nested
	 * under another path.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr URI expression.
	 *
	 * @return string
	 */
	public static function apply( $expr ) {
		$expr = \trim( (string) $expr );
		if ( '' === $expr ) {
			return '';
		}

		$expr = self::escape_tilde( $expr );
		$expr = self::seal_extended_comments( $expr );

		$anchored = array();
		foreach ( self::split_alternation( $expr ) as $branch ) {
			if ( '' === $branch ) {
				continue;
			}
			$anchored[] = self::anchor_branch( $branch );
		}

		return \implode( '|', $anchored );
	}

	/**
	 * Quotes an unquoted tilde so it can sit inside the expression delimiter.
	 *
	 * A tilde preceded by an odd number of backslashes is already quoted.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr URI expression.
	 * @return string
	 */
	private static function escape_tilde( $expr ) {
		$out     = '';
		$length  = \strlen( $expr );
		$slashes = 0;

		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $expr[ $i ];
			if ( '\\' === $ch ) {
				++$slashes;
				$out .= $ch;
				continue;
			}

			if ( '~' === $ch && 0 === ( $slashes % 2 ) ) {
				$out .= '\\';
			}

			$slashes = 0;
			$out    .= $ch;
		}

		return $out;
	}

	/**
	 * Turns extended-mode comments into comment groups and drops insignificant space.
	 *
	 * A trailing # comment would otherwise run into syntax added around the expression.
	 * The replacement stays on one line, and parentheses in the comment text are removed
	 * so they cannot close the comment group.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr URI expression.
	 * @return string
	 */
	private static function seal_extended_comments( $expr ) {
		$length = \strlen( $expr );
		$out    = '';
		$stack  = array( false );
		$class  = false;
		$quoted = false;

		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $expr[ $i ];

			if ( $quoted ) {
				$out .= $ch;
				if ( '\\' === $ch && $i + 1 < $length ) {
					$out .= $expr[ ++$i ];
					if ( 'E' === $expr[ $i ] ) {
						$quoted = false;
					}
				}
				continue;
			}

			if ( '\\' === $ch && $i + 1 < $length ) {
				$out .= $ch . $expr[ $i + 1 ];
				if ( 'Q' === $expr[ $i + 1 ] ) {
					$quoted = true;
				}
				++$i;
				continue;
			}

			if ( $class ) {
				$out .= $ch;
				if ( ']' === $ch ) {
					$class = false;
				}
				continue;
			}

			if ( self::extended_comment_open( $expr, $i ) ) {
				$close = \strpos( $expr, ')', $i + 3 );
				if ( false === $close ) {
					$out .= \substr( $expr, $i );
					break;
				}
				$out .= \substr( $expr, $i, $close - $i + 1 );
				$i    = (int) $close;
				continue;
			}

			$extended = $stack[ \count( $stack ) - 1 ];
			if ( $extended && self::is_extended_whitespace( $ch ) ) {
				continue;
			}

			if ( $extended && '#' === $ch ) {
				$end = $i + 1;
				while ( $end < $length && "\n" !== $expr[ $end ] ) {
					++$end;
				}
				$text = \substr( $expr, $i + 1, $end - $i - 1 );
				$text = \str_replace( array( '(', ')' ), '', $text );
				$out .= '(?#' . $text . ')';
				$i    = $end;
				if ( $end >= $length ) {
					break;
				}
				continue;
			}

			if ( '[' === $ch ) {
				$class = true;
				$out  .= $ch;
				$next  = $i + 1;
				if ( $next < $length && '^' === $expr[ $next ] ) {
					$out .= $expr[ $next ];
					$i    = $next;
					++$next;
				}
				if ( $next < $length && ']' === $expr[ $next ] ) {
					$out .= $expr[ $next ];
					$i    = $next;
				}
				continue;
			}

			if ( '(' === $ch ) {
				$stack[] = $extended;
				$out    .= $ch;
				$advance = self::note_group_flags( $expr, $i, $stack );
				if ( $advance > $i ) {
					$out .= \substr( $expr, $i + 1, $advance - $i );
					$i    = $advance;
				}
				continue;
			}

			if ( ')' === $ch && \count( $stack ) > 1 ) {
				\array_pop( $stack );
				$out .= $ch;
				continue;
			}

			$out .= $ch;
		}

		return $out;
	}

	/**
	 * Whether an index opens a comment group.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $index Index to test.
	 * @return bool
	 */
	private static function extended_comment_open( $expr, $index ) {
		return 0 === \strncmp( \substr( $expr, $index, 3 ), '(?#', 3 );
	}

	/**
	 * Whether a character is whitespace an extended-mode expression ignores.
	 *
	 * @since 2.11.1
	 *
	 * @param string $ch Character.
	 * @return bool
	 */
	private static function is_extended_whitespace( $ch ) {
		return ' ' === $ch || "\t" === $ch || "\n" === $ch || "\r" === $ch || "\f" === $ch || "\v" === $ch;
	}

	/**
	 * Records the extended-mode flag for a group and returns the last opener index.
	 *
	 * A standalone option group updates the enclosing expression. A scoped group
	 * updates only the frame pushed for it.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $open  Index of the opening parenthesis.
	 * @param bool[] $stack Extended-mode flag for each open group. Updated in place.
	 * @return int
	 */
	private static function note_group_flags( $expr, $open, &$stack ) {
		$top = \count( $stack ) - 1;
		if ( ! isset( $expr[ $open + 1 ] ) || '?' !== $expr[ $open + 1 ] ) {
			return $open;
		}

		if ( self::is_lookaround_open( $expr, $open ) ) {
			$mark = $expr[ $open + 2 ];
			return ( '<' === $mark ) ? $open + 3 : $open + 2;
		}

		$mark_at = $open + 2;
		if ( isset( $expr[ $mark_at ] ) && ( ':' === $expr[ $mark_at ] || '>' === $expr[ $mark_at ] || '|' === $expr[ $mark_at ] ) ) {
			return $mark_at;
		}

		$flags_end = self::option_flags_end( $expr, $mark_at );
		if ( null !== $flags_end && isset( $expr[ $flags_end ] ) ) {
			if ( ')' === $expr[ $flags_end ] ) {
				$updated           = self::extended_after_flags( $expr, $mark_at, $flags_end, $stack[ $top - 1 ] );
				$stack[ $top - 1 ] = $updated;
				$stack[ $top ]     = $updated;
				return $flags_end - 1;
			}
			if ( ':' === $expr[ $flags_end ] ) {
				$stack[ $top ] = self::extended_after_flags( $expr, $mark_at, $flags_end, $stack[ $top ] );
				return $flags_end;
			}
		}

		$body = self::group_body_offset( $expr, $open );
		if ( null !== $body && $body > $open ) {
			return $body - 1;
		}

		return $open;
	}

	/**
	 * Extended-mode flag after inline option letters.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr     Expression.
	 * @param int    $start    Index of the first flag character.
	 * @param int    $end      Index of the colon or parenthesis that ends the flags.
	 * @param bool   $extended Flag in effect before these letters.
	 * @return bool
	 */
	private static function extended_after_flags( $expr, $start, $end, $extended ) {
		$enable = true;
		for ( $i = $start; $i < $end; $i++ ) {
			$ch = $expr[ $i ];
			if ( '^' === $ch && $i === $start ) {
				$extended = false;
				$enable   = true;
				continue;
			}
			if ( '-' === $ch ) {
				$enable = false;
				continue;
			}
			if ( 'x' === $ch ) {
				$extended = $enable;
			}
		}

		return $extended;
	}

	/**
	 * Splits a URI expression on alternation that is not inside a group or class.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr URI expression.
	 *
	 * @return string[]
	 */
	private static function split_alternation( $expr ) {
		$parts   = array();
		$current = '';
		$depth   = 0;
		$class   = false;
		$length  = \strlen( $expr );

		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $expr[ $i ];
			if ( '\\' === $ch && $i + 1 < $length ) {
				$current .= $ch . $expr[ $i + 1 ];
				++$i;
				continue;
			}

			if ( $class ) {
				$current .= $ch;
				if ( ']' === $ch ) {
					$class = false;
				}
				continue;
			}

			if ( '[' === $ch ) {
				$class    = true;
				$current .= $ch;
				continue;
			}

			if ( '(' === $ch ) {
				++$depth;
				$current .= $ch;
				continue;
			}

			if ( ')' === $ch && $depth > 0 ) {
				--$depth;
				$current .= $ch;
				continue;
			}

			if ( '|' === $ch && 0 === $depth ) {
				$parts[] = $current;
				$current = '';
				continue;
			}

			$current .= $ch;
		}

		$parts[] = $current;

		return $parts;
	}

	/**
	 * Anchors one alternative of a URI expression.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr One alternative.
	 *
	 * @return string
	 */
	private static function anchor_branch( $expr ) {
		$internal = self::anchor_branch_internal( $expr );
		if ( null !== $internal ) {
			return $internal;
		}

		$peeled = self::peel_leading_markers( $expr );
		if ( '' === $peeled['rest'] ) {
			return $peeled['prefix'];
		}

		return $peeled['prefix'] . self::apply_boundaries( $peeled['rest'], true, true );
	}

	/**
	 * Rewrites one alternative when a group inside it needs its own boundaries.
	 *
	 * Returns null when the alternative only needs the boundaries applied around it.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr One alternative.
	 * @return string|null
	 */
	private static function anchor_branch_internal( $expr ) {
		$peeled = self::peel_leading_markers( $expr );
		$prefix = $peeled['prefix'];
		$expr   = $peeled['rest'];
		if ( '' === $expr ) {
			return null;
		}

		$distributed = self::anchor_mixed_group( $expr );
		if ( null !== $distributed ) {
			return $prefix . $distributed;
		}

		$quantified = self::anchor_quantified_group( $expr );
		if ( null !== $quantified ) {
			return $prefix . $quantified;
		}

		$rewritten = self::rewrite_trailing_mixed_group( $expr );
		$trail     = true;
		if ( null !== $rewritten ) {
			$expr  = $rewritten;
			$trail = false;
		}

		$leading = self::rewrite_leading_mixed_group( $expr );
		$lead    = true;
		if ( null !== $leading ) {
			$expr = $leading;
			$lead = false;
		}

		if ( $lead && $trail ) {
			return null;
		}

		return $prefix . self::apply_boundaries( $expr, $lead, $trail );
	}

	/**
	 * Moves leading option groups and comments off an expression.
	 *
	 * They consume nothing, so the boundary belongs to the text that follows.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return array Keys prefix and rest.
	 */
	private static function peel_leading_markers( $expr ) {
		$prefix = '';

		while ( '' !== $expr && '(' === $expr[0] ) {
			$close = self::standalone_option_close( $expr, 0 );
			if ( null === $close ) {
				$close = self::comment_close( $expr, 0 );
			}
			if ( null === $close || self::quantifier_at( $expr, $close + 1 ) ) {
				break;
			}

			$prefix .= \substr( $expr, 0, $close + 1 );
			$expr    = \substr( $expr, $close + 1 );
		}

		return array(
			'prefix' => $prefix,
			'rest'   => $expr,
		);
	}

	/**
	 * Anchors a group whose positive repetition covers the whole expression.
	 *
	 * The boundary belongs to the whole repetition. A boundary inside the group
	 * would be required again on every iteration.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string|null
	 */
	private static function anchor_quantified_group( $expr ) {
		if ( '' === $expr || '(' !== $expr[0] ) {
			return null;
		}

		$close = self::group_close( $expr, 0 );
		if ( null === $close ) {
			return null;
		}

		$rest = \substr( $expr, $close + 1 );
		$span = self::quantifier_at_start( $rest );
		if ( null === $span || $span['min'] < 1 || \strlen( $rest ) !== $span['length'] ) {
			return null;
		}

		return self::apply_repeated_boundaries( \substr( $expr, 0, $close + 1 + $span['length'] ) );
	}

	/**
	 * Adds path boundaries around a repeated group, outside the repetition.
	 *
	 * When the repetition can end on a separator, that separator is the trailing
	 * boundary and a descendant can follow it. Otherwise the match must end.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Repeated group, including its quantifier.
	 * @return string
	 */
	private static function apply_repeated_boundaries( $expr ) {
		$kind     = self::leading_kind( $expr );
		$anchored = '(?:' . $expr . ')';

		if ( 'slash' === $kind ) {
			$anchored = '(?:^|(?=/))' . $anchored;
		} elseif ( 'mixed' === $kind ) {
			$anchored = '(?:^|(?<=/)|(?=/))' . $anchored;
		} elseif ( 'caret' !== $kind ) {
			$anchored = '(?:^|(?<=/))' . $anchored;
		}

		if ( ! self::trailing_boundary( $expr ) ) {
			$anchored .= '(?:$|[/?#]|(?<=[/?#]))';
		}

		return $anchored;
	}

	/**
	 * Adds a leading or trailing path boundary around one expression.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param bool   $lead  Whether to add a leading boundary.
	 * @param bool   $trail Whether to add a trailing boundary.
	 *
	 * @return string
	 */
	private static function apply_boundaries( $expr, $lead, $trail ) {
		$anchored = '(?:' . $expr . ')';
		if ( $lead ) {
			$kind = self::leading_kind( $expr );
			if ( 'slash' === $kind ) {
				$anchored = '(?:^|(?=/))' . $anchored;
			} elseif ( 'caret' !== $kind ) {
				// Lookbehind keeps the slash out of a surrounding capture.
				$anchored = '(?:^|(?<=/))' . $anchored;
			}
		}

		if ( $trail && ! self::trailing_boundary( $expr ) ) {
			$anchored .= '(?:$|[/?#])';
		}

		return $anchored;
	}

	/**
	 * Anchors each alternative of a group whose edges disagree.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string|null Rewritten expression, or null when the group can stay whole.
	 */
	private static function anchor_mixed_group( $expr ) {
		$group = self::edge_group( $expr, false );
		if ( null === $group || '' !== $group['suffix'] ) {
			return null;
		}

		$alts = self::group_alts( $group['body'] );
		if ( 1 === \count( $alts ) ) {
			$inner = self::anchor_branch_internal( $alts[0] );
			if ( null === $inner ) {
				return null;
			}

			// The original parentheses stay so a nested group keeps its number and name.
			return $group['wrapper'] . $inner . ')';
		}
		if ( \count( $alts ) < 2 || ! self::alts_disagree( $alts ) ) {
			return null;
		}

		$parts = array();
		foreach ( $alts as $alt ) {
			$parts[] = self::anchor_branch( $alt );
		}

		// The original parentheses stay so a nested group keeps its number and name.
		return $group['wrapper'] . \implode( '|', $parts ) . ')';
	}

	/**
	 * Pushes a trailing boundary into a mixed group at the end of an expression.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string|null Rewritten expression, or null when no rewrite is needed.
	 */
	private static function rewrite_trailing_mixed_group( $expr ) {
		$group = self::edge_group( $expr, true );
		if ( null === $group || 0 === $group['open'] ) {
			return null;
		}

		$pushed = self::push_trailing_through( \substr( $expr, $group['open'] ) );
		if ( null === $pushed ) {
			return null;
		}

		return $group['prefix'] . $pushed;
	}

	/**
	 * Pushes a trailing boundary through wrappers into a mixed group.
	 *
	 * A group with one child is not mixed itself. The child is read the same way
	 * so a directory alternative keeps the separator it already matched.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Group expression.
	 * @return string|null
	 */
	private static function push_trailing_through( $expr ) {
		$group = self::edge_group( $expr, false );
		if ( null === $group || '' !== $group['suffix'] ) {
			return null;
		}

		$alts = self::group_alts( $group['body'] );
		if ( 1 === \count( $alts ) ) {
			$inner = self::push_trailing_through( $alts[0] );
			if ( null === $inner ) {
				return null;
			}

			return $group['wrapper'] . $inner . ')';
		}
		if ( \count( $alts ) < 2 ) {
			return null;
		}

		$boundary = array();
		foreach ( $alts as $alt ) {
			$boundary[ self::trailing_boundary( $alt ) ? 'y' : 'n' ] = true;
		}
		if ( \count( $boundary ) < 2 ) {
			return null;
		}

		$parts = array();
		foreach ( $alts as $alt ) {
			$bound   = self::trailing_boundary( $alt ) ? '' : '(?:$|[/?#])';
			$parts[] = $alt . $bound;
		}

		// The original parentheses stay so a nested group keeps its number and name.
		return $group['wrapper'] . \implode( '|', $parts ) . ')';
	}

	/**
	 * Pushes a leading boundary into a mixed group at the start of an expression.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string|null Rewritten expression, or null when no rewrite is needed.
	 */
	private static function rewrite_leading_mixed_group( $expr ) {
		$group = self::edge_group( $expr, false );
		if ( null === $group || '' === $group['suffix'] ) {
			return null;
		}

		$pushed = self::push_leading_through( \substr( $expr, 0, \strlen( $expr ) - \strlen( $group['suffix'] ) ) );
		if ( null === $pushed ) {
			return null;
		}

		return $pushed . $group['suffix'];
	}

	/**
	 * Pushes a leading boundary through wrappers into a mixed group.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Group expression.
	 * @return string|null
	 */
	private static function push_leading_through( $expr ) {
		$group = self::edge_group( $expr, false );
		if ( null === $group || '' !== $group['suffix'] ) {
			return null;
		}

		$alts = self::group_alts( $group['body'] );
		if ( 1 === \count( $alts ) ) {
			$inner = self::push_leading_through( $alts[0] );
			if ( null === $inner ) {
				return null;
			}

			return $group['wrapper'] . $inner . ')';
		}
		if ( \count( $alts ) < 2 ) {
			return null;
		}

		$kinds = array();
		foreach ( $alts as $alt ) {
			$kinds[ self::leading_kind( $alt ) ] = true;
		}
		if ( \count( $kinds ) < 2 && ! isset( $kinds['mixed'] ) ) {
			return null;
		}

		$parts = array();
		foreach ( $alts as $alt ) {
			$parts[] = self::apply_boundaries( $alt, true, false );
		}

		// The original parentheses stay so a nested group keeps its number and name.
		return $group['wrapper'] . \implode( '|', $parts ) . ')';
	}

	/**
	 * Whether a group's alternatives need different boundaries.
	 *
	 * @since 2.11.1
	 *
	 * @param string[] $alts Alternatives.
	 *
	 * @return bool
	 */
	private static function alts_disagree( $alts ) {
		$kinds    = array();
		$boundary = array();
		foreach ( $alts as $alt ) {
			$kind = self::leading_kind( $alt );
			$edge = self::trailing_boundary( $alt ) ? 'y' : 'n';

			$kinds[ $kind ]    = true;
			$boundary[ $edge ] = true;
		}

		return \count( $kinds ) > 1 || isset( $kinds['mixed'] ) || \count( $boundary ) > 1;
	}

	/**
	 * Alternatives inside a group body.
	 *
	 * Empty alternatives stay in the list so they receive their own boundary.
	 *
	 * @since 2.11.1
	 *
	 * @param string $body Group body.
	 *
	 * @return string[]
	 */
	private static function group_alts( $body ) {
		return self::split_alternation( $body );
	}

	/**
	 * How an expression begins: caret, slash, other, or mixed.
	 *
	 * A leading lookaround, comment, or option group is skipped so the kind follows
	 * the first character the expression consumes. A group counts as the edge its
	 * alternatives share. Mixed means those alternatives do not share one edge.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string
	 */
	private static function leading_kind( $expr ) {
		$expr = self::skip_leading_assertions( $expr );
		if ( '' === $expr ) {
			return 'other';
		}
		if ( '^' === $expr[0] ) {
			return 'caret';
		}
		if ( '/' === $expr[0] || 0 === \strncmp( $expr, '\\/', 2 ) ) {
			return 'slash';
		}
		if ( self::leading_separator_class( $expr ) ) {
			return 'slash';
		}

		$through = self::leading_kind_through_quantifier( $expr );
		if ( null !== $through ) {
			return $through;
		}

		$group = self::edge_group( $expr, false );
		if ( null === $group ) {
			return 'other';
		}

		$kinds = array();
		foreach ( self::group_alts( $group['body'] ) as $alt ) {
			$kinds[ self::leading_kind( $alt ) ] = true;
		}
		if ( array() === $kinds || isset( $kinds['mixed'] ) || \count( $kinds ) !== 1 ) {
			return 'mixed';
		}

		$keys = \array_keys( $kinds );
		return $keys[0];
	}

	/**
	 * Leading kind of a group that a positive repetition always starts on.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string|null
	 */
	private static function leading_kind_through_quantifier( $expr ) {
		if ( '' === $expr || '(' !== $expr[0] ) {
			return null;
		}

		$close = self::group_close( $expr, 0 );
		if ( null === $close ) {
			return null;
		}

		$span = self::quantifier_at_start( \substr( $expr, $close + 1 ) );
		if ( null === $span || $span['min'] < 1 ) {
			return null;
		}

		return self::leading_kind( \substr( $expr, 0, $close + 1 ) );
	}

	/**
	 * Span of a quantifier at the start of an expression.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return array|null Keys min and length.
	 */
	private static function quantifier_at_start( $expr ) {
		$length = \strlen( $expr );
		if ( 0 === $length || self::is_escaped( $expr, 0 ) ) {
			return null;
		}

		$ch = $expr[0];
		if ( '+' === $ch || '*' === $ch || '?' === $ch ) {
			$len = 1;
			if ( $length > 1 && ( '?' === $expr[1] || '+' === $expr[1] ) ) {
				++$len;
			}

			return array(
				'min'    => ( '+' === $ch ) ? 1 : 0,
				'length' => $len,
			);
		}

		if ( '{' !== $ch ) {
			return null;
		}

		$end = \strpos( $expr, '}', 1 );
		if ( false === $end ) {
			return null;
		}

		$slice = \substr( $expr, 0, $end + 1 );
		if ( $end + 1 < $length && ( '?' === $expr[ $end + 1 ] || '+' === $expr[ $end + 1 ] ) ) {
			$slice .= $expr[ $end + 1 ];
		}

		return self::quantifier_span( $slice );
	}

	/**
	 * Whether every way the expression can finish already ends on a path boundary.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return bool
	 */
	private static function trailing_boundary( $expr ) {
		if ( self::ends_on_boundary_char( $expr ) ) {
			return true;
		}

		$group = self::edge_group( $expr, true );
		if ( null !== $group ) {
			$alts = self::group_alts( $group['body'] );
			if ( array() === $alts ) {
				return false;
			}

			foreach ( $alts as $alt ) {
				if ( ! self::trailing_boundary( $alt ) ) {
					return false;
				}
			}

			return true;
		}

		return self::trailing_after_marker( $expr );
	}

	/**
	 * Whether a separator already consumed before a repetition or lookaround is the boundary.
	 *
	 * A positive minimum consumes that separator. A quantifier on a lookaround, comment,
	 * or option group does not, because those groups consume nothing.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return bool
	 */
	private static function trailing_after_marker( $expr ) {
		$length = \strlen( $expr );
		if ( 0 === $length ) {
			return false;
		}

		$span = self::quantifier_span( $expr );
		if ( null !== $span && $span['min'] >= 1 ) {
			return self::trailing_boundary( \substr( $expr, 0, $length - $span['length'] ) );
		}

		if ( null !== $span ) {
			$atom = self::strip_one_trailing_marker( \substr( $expr, 0, $length - $span['length'] ) );
			if ( null === $atom ) {
				return false;
			}

			return self::trailing_boundary( $atom );
		}

		$marker = self::strip_one_trailing_marker( $expr );
		if ( null === $marker ) {
			return false;
		}

		return self::trailing_boundary( $marker );
	}

	/**
	 * Whether the expression ends with a separator or an end anchor.
	 *
	 * A character class that matches only separators counts as that separator.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return bool
	 */
	private static function ends_on_boundary_char( $expr ) {
		$length = \strlen( $expr );
		if ( 0 === $length ) {
			return false;
		}
		if ( self::ends_with_separator_class( $expr ) ) {
			return true;
		}

		$last = $expr[ $length - 1 ];
		if ( '/' === $last ) {
			return true;
		}
		if ( '$' !== $last ) {
			return false;
		}

		$slashes = 0;
		for ( $i = $length - 2; $i >= 0 && '\\' === $expr[ $i ]; $i-- ) {
			++$slashes;
		}

		return 0 === ( $slashes % 2 );
	}

	/**
	 * Drops leading lookaround so the following character can be classified.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @return string
	 */
	private static function skip_leading_assertions( $expr ) {
		while ( '' !== $expr && '(' === $expr[0] ) {
			$close = self::leading_skippable_close( $expr );
			if ( null === $close ) {
				break;
			}

			$expr = \substr( $expr, $close + 1 );
		}

		return $expr;
	}

	/**
	 * Close index of a leading lookaround, comment, or standalone option group.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return int|null
	 */
	private static function leading_skippable_close( $expr ) {
		$close = self::standalone_option_close( $expr, 0 );
		if ( null === $close ) {
			$close = self::comment_close( $expr, 0 );
		}
		if ( null === $close && self::is_lookaround_open( $expr, 0 ) ) {
			$close = self::group_close( $expr, 0 );
		}
		if ( null === $close || self::quantifier_at( $expr, $close + 1 ) ) {
			return null;
		}

		return $close;
	}

	/**
	 * Drops one trailing lookaround, comment, or standalone option group.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return string|null Remainder, or null when the expression does not end on one.
	 */
	private static function strip_one_trailing_marker( $expr ) {
		$length = \strlen( $expr );
		if ( $length < 3 || ')' !== $expr[ $length - 1 ] || self::quantifier_at( $expr, $length ) ) {
			return null;
		}

		$open = self::group_open_for_close( $expr, $length - 1 );
		if ( null === $open || ! self::is_trailing_marker( $expr, $open, $length - 1 ) ) {
			return null;
		}

		return \substr( $expr, 0, $open );
	}

	/**
	 * Whether the group opening at $open and closing at $close consumes nothing.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $open  Index of the opening parenthesis.
	 * @param int    $close Index of the closing parenthesis.
	 *
	 * @return bool
	 */
	private static function is_trailing_marker( $expr, $open, $close ) {
		if ( self::is_lookaround_open( $expr, $open ) ) {
			return true;
		}

		$option = self::standalone_option_close( $expr, $open );
		if ( $option === $close ) {
			return true;
		}

		$comment = self::comment_close( $expr, $open );
		return $comment === $close;
	}

	/**
	 * Whether a group is a lookahead or lookbehind.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @param int    $open Index of the opening parenthesis.
	 *
	 * @return bool
	 */
	private static function is_lookaround_open( $expr, $open ) {
		$length = \strlen( $expr );
		if ( $open < 0 || $open + 2 >= $length || '(' !== $expr[ $open ] || '?' !== $expr[ $open + 1 ] ) {
			return false;
		}

		$mark = $expr[ $open + 2 ];
		if ( '=' === $mark || '!' === $mark ) {
			return true;
		}

		if ( '<' !== $mark || ! isset( $expr[ $open + 3 ] ) ) {
			return false;
		}

		$next = $expr[ $open + 3 ];
		return '=' === $next || '!' === $next;
	}

	/**
	 * Close index of a standalone option group, or null.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @param int    $open Index of the opening parenthesis.
	 *
	 * @return int|null
	 */
	private static function standalone_option_close( $expr, $open ) {
		$length = \strlen( $expr );
		if ( $open < 0 || $open + 2 >= $length || '(' !== $expr[ $open ] || '?' !== $expr[ $open + 1 ] ) {
			return null;
		}

		$end = self::option_flags_end( $expr, $open + 2 );
		if ( null === $end || ! isset( $expr[ $end ] ) || ')' !== $expr[ $end ] ) {
			return null;
		}

		return $end;
	}

	/**
	 * Close index of a comment group, or null.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @param int    $open Index of the opening parenthesis.
	 *
	 * @return int|null
	 */
	private static function comment_close( $expr, $open ) {
		if ( 0 !== \strncmp( \substr( $expr, $open, 3 ), '(?#', 3 ) ) {
			return null;
		}

		$close = \strpos( $expr, ')', $open + 3 );
		if ( false === $close ) {
			return null;
		}

		return (int) $close;
	}

	/**
	 * Index of the colon or parenthesis that ends inline option flags.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $start Index of the first flag character.
	 *
	 * @return int|null
	 */
	private static function option_flags_end( $expr, $start ) {
		$length = \strlen( $expr );
		$i      = $start;
		$saw    = false;

		if ( $i < $length && '^' === $expr[ $i ] ) {
			$saw = true;
			++$i;
		}

		$hyphen = false;
		for ( ; $i < $length; $i++ ) {
			$ch = $expr[ $i ];
			if ( self::is_option_letter( $ch ) ) {
				$saw = true;
				continue;
			}
			if ( '-' === $ch && ! $hyphen ) {
				$hyphen = true;
				$saw    = true;
				continue;
			}
			break;
		}

		if ( ! $saw || $i >= $length ) {
			return null;
		}
		if ( ':' === $expr[ $i ] || ')' === $expr[ $i ] ) {
			return $i;
		}

		return null;
	}

	/**
	 * Whether a character is a PCRE inline option letter.
	 *
	 * @since 2.11.1
	 *
	 * @param string $ch Character.
	 *
	 * @return bool
	 */
	private static function is_option_letter( $ch ) {
		return false !== \strpos( 'imsxUJXn', $ch );
	}

	/**
	 * Span of a trailing quantifier.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 *
	 * @return array|null Keys min and length, or null when the expression does not end on one.
	 */
	private static function quantifier_span( $expr ) {
		$length = \strlen( $expr );
		if ( 0 === $length ) {
			return null;
		}

		$end = $length;
		if ( $end >= 2 ) {
			$last = $expr[ $end - 1 ];
			$prev = $expr[ $end - 2 ];
			if ( ( '?' === $last || '+' === $last )
				&& ( '*' === $prev || '+' === $prev || '?' === $prev || '}' === $prev )
				&& ! self::is_escaped( $expr, $end - 2 ) ) {
				--$end;
			}
		}

		if ( $end < 1 || self::is_escaped( $expr, $end - 1 ) ) {
			return null;
		}

		$ch = $expr[ $end - 1 ];
		if ( '+' === $ch || '*' === $ch || '?' === $ch ) {
			return array(
				'min'    => ( '+' === $ch ) ? 1 : 0,
				'length' => $length - $end + 1,
			);
		}

		if ( '}' !== $ch ) {
			return null;
		}

		$open = $end - 1;
		while ( $open > 0 ) {
			--$open;
			if ( '{' === $expr[ $open ] ) {
				break;
			}
			$digit = $expr[ $open ];
			if ( ! self::is_digit( $digit ) && ',' !== $digit ) {
				return null;
			}
		}

		if ( '{' !== $expr[ $open ] || self::is_escaped( $expr, $open ) ) {
			return null;
		}

		$body = \substr( $expr, $open + 1, $end - $open - 2 );
		$min  = self::brace_minimum( $body );
		if ( null === $min ) {
			return null;
		}

		return array(
			'min'    => $min,
			'length' => $length - $open,
		);
	}

	/**
	 * Minimum of a brace quantifier body, or null when the body is not one.
	 *
	 * @since 2.11.1
	 *
	 * @param string $body Text between the braces.
	 *
	 * @return int|null
	 */
	private static function brace_minimum( $body ) {
		$length = \strlen( $body );
		if ( 0 === $length || ! self::is_digit( $body[0] ) ) {
			return null;
		}

		$i   = 0;
		$min = 0;
		while ( $i < $length && self::is_digit( $body[ $i ] ) ) {
			$min = ( 10 * $min ) + ( \ord( $body[ $i ] ) - 48 );
			++$i;
		}

		if ( $i === $length ) {
			return $min;
		}
		if ( ',' !== $body[ $i ] ) {
			return null;
		}

		++$i;
		if ( $i === $length ) {
			return $min;
		}

		$digits = 0;
		while ( $i < $length && self::is_digit( $body[ $i ] ) ) {
			++$i;
			++$digits;
		}

		if ( $i !== $length || 0 === $digits ) {
			return null;
		}

		return $min;
	}

	/**
	 * Whether a character is an ASCII digit.
	 *
	 * @since 2.11.1
	 *
	 * @param string $ch Character.
	 *
	 * @return bool
	 */
	private static function is_digit( $ch ) {
		return $ch >= '0' && $ch <= '9';
	}

	/**
	 * Whether the character at an index is escaped by a backslash.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $index Index to test.
	 *
	 * @return bool
	 */
	private static function is_escaped( $expr, $index ) {
		$slashes = 0;
		for ( $i = $index - 1; $i >= 0 && '\\' === $expr[ $i ]; $i-- ) {
			++$slashes;
		}

		return 1 === ( $slashes % 2 );
	}

	/**
	 * Whether the expression ends with a character class of only separators.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @return bool
	 */
	private static function ends_with_separator_class( $expr ) {
		$length = \strlen( $expr );
		if ( $length < 3 || ']' !== $expr[ $length - 1 ] ) {
			return false;
		}

		$span = self::class_span( $expr, $length - 1 );
		if ( null === $span ) {
			return false;
		}

		return self::class_is_separator_only(
			\substr( $expr, $span['open'] + 1, $span['end'] - $span['open'] - 1 )
		);
	}

	/**
	 * Whether the expression consumes a separator class that matches a slash first.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @return bool
	 */
	private static function leading_separator_class( $expr ) {
		if ( '' === $expr || '[' !== $expr[0] ) {
			return false;
		}

		$span = self::class_span( $expr, null );
		if ( null === $span || 0 !== $span['open'] ) {
			return false;
		}

		$body = \substr( $expr, 1, $span['end'] - 1 );
		return self::class_is_separator_only( $body ) && false !== \strpos( $body, '/' );
	}

	/**
	 * Span of a character class.
	 *
	 * A null $end selects the class that opens the expression.
	 *
	 * @since 2.11.1
	 *
	 * @param string   $expr Expression.
	 * @param int|null $end  Index of the closing bracket, or null for a leading class.
	 * @return array|null Keys open and end.
	 */
	private static function class_span( $expr, $end ) {
		$length = \strlen( $expr );
		$open   = null;

		for ( $i = 0; $i < $length; $i++ ) {
			if ( '\\' === $expr[ $i ] && $i + 1 < $length ) {
				++$i;
				continue;
			}

			if ( null === $open ) {
				if ( '[' === $expr[ $i ] ) {
					$open = $i;
				}
				continue;
			}

			if ( ']' !== $expr[ $i ] ) {
				continue;
			}

			$body_start = $open + 1;
			if ( $i === $body_start || ( $i === $body_start + 1 && '^' === $expr[ $body_start ] ) ) {
				continue;
			}

			if ( null === $end || $i === $end ) {
				if ( null !== $end || 0 === $open ) {
					return array(
						'open' => $open,
						'end'  => $i,
					);
				}
			}

			$open = null;
		}

		return null;
	}

	/**
	 * Whether a character class matches only path separators.
	 *
	 * @since 2.11.1
	 *
	 * @param string $body Class body, without the brackets.
	 * @return bool
	 */
	private static function class_is_separator_only( $body ) {
		$length = \strlen( $body );
		if ( 0 === $length || '^' === $body[0] ) {
			return false;
		}

		for ( $i = 0; $i < $length; $i++ ) {
			$ch = $body[ $i ];
			if ( '\\' === $ch ) {
				if ( $i + 1 >= $length ) {
					return false;
				}
				$next = $body[ ++$i ];
				if ( '/' !== $next && '?' !== $next && '#' !== $next ) {
					return false;
				}
				continue;
			}

			if ( '/' !== $ch && '?' !== $ch && '#' !== $ch ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Unquantified group at the start or end of an expression.
	 *
	 * Lookaround groups are left untouched. Named groups, atomic groups, branch
	 * resets, and scoped option groups are read. The returned body excludes the
	 * group wrapper.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr   Expression.
	 * @param bool   $at_end Whether to read the group that closes the expression.
	 *
	 * @return array|null Keys open, body, prefix, suffix, wrapper, and noncapturing.
	 */
	private static function edge_group( $expr, $at_end ) {
		$length = \strlen( $expr );
		if ( $length < 2 ) {
			return null;
		}

		if ( $at_end ) {
			if ( ')' !== $expr[ $length - 1 ] ) {
				return null;
			}
			$open = self::group_open_for_close( $expr, $length - 1 );
		} else {
			if ( '(' !== $expr[0] ) {
				return null;
			}
			$open = 0;
		}
		if ( null === $open ) {
			return null;
		}

		$body_at = self::group_body_offset( $expr, $open );
		$close   = self::group_close( $expr, $open );
		if ( null === $body_at || null === $close || self::quantifier_at( $expr, $close + 1 ) ) {
			return null;
		}

		return array(
			'open'         => $open,
			'body'         => \substr( $expr, $body_at, $close - $body_at ),
			'prefix'       => \substr( $expr, 0, $open ),
			'suffix'       => \substr( $expr, $close + 1 ),
			'wrapper'      => \substr( $expr, $open, $body_at - $open ),
			'noncapturing' => self::group_is_noncapturing( $expr, $open ),
		);
	}

	/**
	 * Whether a group does not add a capture of its own.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @param int    $open Index of the opening parenthesis.
	 *
	 * @return bool
	 */
	private static function group_is_noncapturing( $expr, $open ) {
		$head = \substr( $expr, $open, 3 );
		if ( '(?:' === $head || '(?>' === $head || '(?|' === $head ) {
			return true;
		}

		if ( ! isset( $expr[ $open + 1 ] ) || '?' !== $expr[ $open + 1 ] ) {
			return false;
		}

		$end = self::option_flags_end( $expr, $open + 2 );
		return null !== $end && isset( $expr[ $end ] ) && ':' === $expr[ $end ];
	}

	/**
	 * Index where a group's body starts.
	 *
	 * Plain, non-capturing, atomic, branch-reset, named, and scoped option groups
	 * are recognized. Lookaround and standalone option groups have no body here.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @param int    $open Index of the opening parenthesis.
	 *
	 * @return int|null
	 */
	private static function group_body_offset( $expr, $open ) {
		$length = \strlen( $expr );
		if ( $open < 0 || $open >= $length || '(' !== $expr[ $open ] ) {
			return null;
		}

		$next = $open + 1;
		if ( $next >= $length || '?' !== $expr[ $next ] ) {
			return $next;
		}

		$mark = $next + 1;
		if ( $mark >= $length ) {
			return null;
		}

		$ch = $expr[ $mark ];
		if ( ':' === $ch || '>' === $ch || '|' === $ch ) {
			return $mark + 1;
		}

		if ( '<' === $ch ) {
			if ( isset( $expr[ $mark + 1 ] ) && ( '=' === $expr[ $mark + 1 ] || '!' === $expr[ $mark + 1 ] ) ) {
				return null;
			}

			return self::named_body_offset( $expr, $mark + 1, '>' );
		}

		if ( "'" === $ch ) {
			return self::named_body_offset( $expr, $mark + 1, "'" );
		}

		if ( 'P' === $ch && isset( $expr[ $mark + 1 ] ) && '<' === $expr[ $mark + 1 ] ) {
			return self::named_body_offset( $expr, $mark + 2, '>' );
		}

		$flags_end = self::option_flags_end( $expr, $mark );
		if ( null !== $flags_end && isset( $expr[ $flags_end ] ) && ':' === $expr[ $flags_end ] ) {
			return $flags_end + 1;
		}

		return null;
	}

	/**
	 * Index of the body after a named-group name, or null when the name is not one.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr   Expression.
	 * @param int    $start  Index of the first name character.
	 * @param string $closer Character that ends the name.
	 *
	 * @return int|null
	 */
	private static function named_body_offset( $expr, $start, $closer ) {
		$end = \strpos( $expr, $closer, $start );
		if ( false === $end ) {
			return null;
		}

		$name = \substr( $expr, $start, $end - $start );
		if ( ! self::is_group_name( $name ) ) {
			return null;
		}

		return $end + 1;
	}

	/**
	 * Whether a string is a PCRE group name.
	 *
	 * @since 2.11.1
	 *
	 * @param string $name Name.
	 *
	 * @return bool
	 */
	private static function is_group_name( $name ) {
		$length = \strlen( $name );
		if ( 0 === $length || ! self::is_name_start( $name[0] ) ) {
			return false;
		}

		for ( $i = 1; $i < $length; $i++ ) {
			if ( ! self::is_name_char( $name[ $i ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a character can start a group name.
	 *
	 * @since 2.11.1
	 *
	 * @param string $ch Character.
	 *
	 * @return bool
	 */
	private static function is_name_start( $ch ) {
		return ( $ch >= 'A' && $ch <= 'Z' ) || ( $ch >= 'a' && $ch <= 'z' ) || '_' === $ch;
	}

	/**
	 * Whether a character can continue a group name.
	 *
	 * @since 2.11.1
	 *
	 * @param string $ch Character.
	 *
	 * @return bool
	 */
	private static function is_name_char( $ch ) {
		return self::is_name_start( $ch ) || self::is_digit( $ch );
	}

	/**
	 * Whether a quantifier begins at an index.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $index Index to test.
	 *
	 * @return bool
	 */
	private static function quantifier_at( $expr, $index ) {
		$length = \strlen( $expr );
		if ( $index < 0 || $index >= $length ) {
			return false;
		}

		$ch = $expr[ $index ];
		return '?' === $ch || '*' === $ch || '+' === $ch || '{' === $ch;
	}

	/**
	 * Index of the parenthesis that closes the group opening at $open.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr Expression.
	 * @param int    $open Index of the opening parenthesis.
	 *
	 * @return int|null
	 */
	private static function group_close( $expr, $open ) {
		$length = \strlen( $expr );
		$depth  = 0;
		$class  = false;

		for ( $i = $open; $i < $length; $i++ ) {
			$ch = $expr[ $i ];
			if ( '\\' === $ch && $i + 1 < $length ) {
				++$i;
				continue;
			}

			if ( $class ) {
				if ( ']' === $ch ) {
					$class = false;
				}
				continue;
			}

			if ( '[' === $ch ) {
				$class = true;
				$next  = $i + 1;
				if ( $next < $length && '^' === $expr[ $next ] ) {
					++$next;
				}
				if ( $next < $length && ']' === $expr[ $next ] ) {
					$i = $next;
				}
				continue;
			}

			if ( '(' === $ch ) {
				++$depth;
				continue;
			}

			if ( ')' === $ch ) {
				--$depth;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		return null;
	}

	/**
	 * Index of the parenthesis that opens the group closing at $close.
	 *
	 * @since 2.11.1
	 *
	 * @param string $expr  Expression.
	 * @param int    $close Index of the closing parenthesis.
	 *
	 * @return int|null
	 */
	private static function group_open_for_close( $expr, $close ) {
		$length = \strlen( $expr );
		$class  = false;
		$stack  = array();

		for ( $i = 0; $i <= $close && $i < $length; $i++ ) {
			$ch = $expr[ $i ];
			if ( '\\' === $ch && $i + 1 < $length ) {
				++$i;
				continue;
			}

			if ( $class ) {
				if ( ']' === $ch ) {
					$class = false;
				}
				continue;
			}

			if ( '[' === $ch ) {
				$class = true;
				$next  = $i + 1;
				if ( $next < $length && '^' === $expr[ $next ] ) {
					++$next;
				}
				if ( $next < $length && ']' === $expr[ $next ] ) {
					$i = $next;
				}
				continue;
			}

			if ( '(' === $ch ) {
				$stack[] = $i;
				continue;
			}

			if ( ')' === $ch && array() !== $stack ) {
				$open = \array_pop( $stack );
				if ( $i === $close ) {
					return $open;
				}
			}
		}

		return null;
	}
}
