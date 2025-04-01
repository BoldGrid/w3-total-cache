<?php
/**
 * File: Minify_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Minify_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Minify_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_minify';

	/**
	 * Renders the minify settings page.
	 *
	 * @return void
	 */
	public function view() {
		$minify_enabled = $this->_config->get_boolean( 'minify.enabled' );
		$config_state   = Dispatcher::config_state();

		$minify_rewrite_disabled = ! Util_Rule::can_check_rules() || $this->_config->is_sealed( 'minify.rewrite' );
		$themes                  = Util_Theme::get_themes_by_key();
		$templates               = array();

		$current_theme     = Util_Theme::get_current_theme_name();
		$current_theme_key = '';

		foreach ( $themes as $theme_key => $theme_name ) {
			if ( $theme_name === $current_theme ) {
				$current_theme_key = $theme_key;
			}

			$templates[ $theme_key ] = Util_Theme::get_theme_templates( $theme_name );
		}

		$css_imports_values = array(
			''        => 'None',
			'bubble'  => 'Bubble',
			'process' => 'Process',
		);

		$auto = $this->_config->get_boolean( 'minify.auto' );

		$js_theme  = Util_Request::get_string( 'js_theme', $current_theme_key );
		$js_groups = $this->_config->get_array( 'minify.js.groups' );

		$css_theme  = Util_Request::get_string( 'css_theme', $current_theme_key );
		$css_groups = $this->_config->get_array( 'minify.css.groups' );

		$js_engine   = $this->_config->get_string( 'minify.js.engine' );
		$css_engine  = $this->_config->get_string( 'minify.css.engine' );
		$html_engine = $this->_config->get_string( 'minify.html.engine' );

		$css_imports = $this->_config->get_string( 'minify.css.imports' );

		// Required for Update Media Query String button.
		$browsercache_enabled         = $this->_config->get_boolean( 'browsercache.enabled' );
		$browsercache_update_media_qs = $this->_config->get_boolean( 'browsercache.cssjs.replace' );

		include W3TC_INC_DIR . '/options/minify.php';
	}

	/**
	 * Displays the minify recommendations for a theme.
	 *
	 * @return void
	 */
	public function recommendations() {
		$themes = Util_Theme::get_themes_by_key();

		$current_theme     = Util_Theme::get_current_theme_name();
		$current_theme_key = array_search( $current_theme, $themes, true );

		$theme_key  = Util_Request::get_string( 'theme_key', $current_theme_key );
		$theme_name = ( isset( $themes[ $theme_key ] ) ? $themes[ $theme_key ] : $current_theme );

		$templates       = Util_Theme::get_theme_templates( $theme_name );
		$recommendations = $this->get_theme_recommendations( $theme_name );

		list ( $js_groups, $css_groups ) = $recommendations;

		$minify_js_groups  = $this->_config->get_array( 'minify.js.groups' );
		$minify_css_groups = $this->_config->get_array( 'minify.css.groups' );

		$checked_js   = array();
		$checked_css  = array();
		$locations_js = array();

		if ( isset( $minify_js_groups[ $theme_key ] ) ) {
			foreach ( (array) $minify_js_groups[ $theme_key ] as $template => $locations ) {
				foreach ( (array) $locations as $location => $config ) {
					if ( isset( $config['files'] ) ) {
						foreach ( (array) $config['files'] as $file ) {
							if ( ! isset( $js_groups[ $template ] ) || ! in_array( $file, $js_groups[ $template ], true ) ) {
								$js_groups[ $template ][] = $file;
							}

							$checked_js[ $template ][ $file ]   = true;
							$locations_js[ $template ][ $file ] = $location;
						}
					}
				}
			}
		}

		if ( isset( $minify_css_groups[ $theme_key ] ) ) {
			foreach ( (array) $minify_css_groups [ $theme_key ] as $template => $locations ) {
				foreach ( (array) $locations as $location => $config ) {
					if ( isset( $config['files'] ) ) {
						foreach ( (array) $config['files'] as $file ) {
							if ( ! isset( $css_groups[ $template ] ) || ! in_array( $file, $css_groups[ $template ], true ) ) {
								$css_groups[ $template ][] = $file;
							}

							$checked_css[ $template ][ $file ] = true;
						}
					}
				}
			}
		}

		include W3TC_INC_DIR . '/lightbox/minify_recommendations.php';
	}

	/**
	 * Retrieves the theme URLs for a given theme name.
	 *
	 * @param string $theme_name The name of the theme to retrieve URLs for.
	 *
	 * @return array An associative array where the keys are template names and the values are their respective URLs.
	 */
	public function get_theme_urls( $theme_name ) {
		$urls  = array();
		$theme = Util_Theme::get( $theme_name );

		if ( $theme && isset( $theme['Template Files'] ) ) {
			$front_page_template = false;

			if ( 'page' === get_option( 'show_on_front' ) ) {
				$front_page_id = get_option( 'page_on_front' );

				if ( $front_page_id ) {
					$front_page_template_file = get_post_meta( $front_page_id, '_wp_page_template', true );

					if ( $front_page_template_file ) {
						$front_page_template = basename( $front_page_template_file, '.php' );
					}
				}
			}

			$home_url       = get_home_url();
			$template_files = (array) $theme['Template Files'];

			$mime_types        = get_allowed_mime_types();
			$custom_mime_types = array();

			foreach ( $mime_types as $mime_type ) {
				list ( $type1, $type2 ) = explode( '/', $mime_type );
				$custom_mime_types      = array_merge(
					$custom_mime_types,
					array(
						$type1,
						$type2,
						$type1 . '_' . $type2,
					)
				);
			}

			foreach ( $template_files as $template_file ) {
				$link     = false;
				$template = basename( $template_file, '.php' );

				// Check common templates.
				switch ( true ) {
					// Handle home.php or index.php or front-page.php or custom home page.
					case ( ! $front_page_template && 'home' === $template ):
					case ( ! $front_page_template && 'index' === $template ):
					case ( ! $front_page_template && 'front-page' === $template ):
					case ( $template === $front_page_template ):
						$link = $home_url . '/';
						break;

					// Handle 404.php.
					case ( '404' === $template ):
						$permalink = get_option( 'permalink_structure' );
						if ( $permalink ) {
							$link = sprintf( '%s/%s/', $home_url, '404_test' );
						} else {
							$link = sprintf( '%s/?p=%d', $home_url, 999999999 );
						}
						break;

					// Handle search.php.
					case ( 'search' === $template ):
						$link = sprintf( '%s/?s=%s', $home_url, 'search_test' );
						break;

					// Handle date.php or archive.php.
					case ( 'date' === $template ):
					case ( 'archive' === $template ):
						$posts = get_posts(
							array(
								'numberposts' => 1,
								'orderby'     => 'rand',
							)
						);

						if ( is_array( $posts ) && count( $posts ) ) {
							$time = strtotime( $posts[0]->post_date );
							$link = get_day_link( gmdate( 'Y', $time ), gmdate( 'm', $time ), gmdate( 'd', $time ) );
						}
						break;

					// Handle author.php.
					case ( 'author' === $template ):
						$author_id = false;
						if ( function_exists( 'get_users' ) ) {
							$users = get_users();
							if ( is_array( $users ) && count( $users ) ) {
								$user      = current( $users );
								$author_id = $user->ID;
							}
						} else {
							$author_ids = get_users(
								array(
									'role'   => 'author',
									'fields' => 'ID', // Only fetch user IDs.
								)
							);
							if ( is_array( $author_ids ) && count( $author_ids ) ) {
								$author_id = $author_ids[0];
							}
						}

						if ( $author_id ) {
							$link = get_author_posts_url( $author_id );
						}
						break;

					// Handle category.php.
					case ( 'category' === $template ):
						$category_ids = get_terms(
							array(
								'taxonomy' => 'category',
								'orderby'  => 'id',
								'number'   => 1,
								'fields'   => 'ids',
							)
						);

						if ( is_array( $category_ids ) && count( $category_ids ) ) {
							$link = get_category_link( $category_ids[0] );
						}
						break;

					// Handle tag.php.
					case ( 'tag' === $template ):
						$term_ids = get_terms(
							array(
								'taxonomy' => 'post_tag',
								'fields'   => 'ids',
							)
						);

						if ( is_array( $term_ids ) && count( $term_ids ) ) {
							$link = get_term_link( $term_ids[0], 'post_tag' );
						}

						break;

					// Handle taxonomy.php.
					case ( 'taxonomy' === $template ):
						$taxonomy = '';
						if ( isset( $GLOBALS['wp_taxonomies'] ) && is_array( $GLOBALS['wp_taxonomies'] ) ) {
							foreach ( $GLOBALS['wp_taxonomies'] as $wp_taxonomy ) {
								if (
									! in_array(
										$wp_taxonomy->name,
										array(
											'category',
											'post_tag',
											'link_category',
										),
										true
									)
								) {
									$taxonomy = $wp_taxonomy->name;
									break;
								}
							}
						}

						if ( $taxonomy ) {
							$terms = get_terms(
								array(
									'taxonomy' => $taxonomy,
									'number'   => 1,
								)
							);

							if ( is_array( $terms ) && count( $terms ) ) {
								$link = get_term_link( $terms[0], $taxonomy );
							}
						}
						break;

					// Handle attachment.php.
					case ( 'attachment' === $template ):
						$attachments = get_posts(
							array(
								'post_type'   => 'attachment',
								'numberposts' => 1,
								'orderby'     => 'rand',
							)
						);
						if ( is_array( $attachments ) && count( $attachments ) ) {
							$link = get_attachment_link( $attachments[0]->ID );
						}
						break;

					// Handle single.php.
					case ( 'single' === $template ):
						$posts = get_posts(
							array(
								'numberposts' => 1,
								'orderby'     => 'rand',
							)
						);
						if ( is_array( $posts ) && count( $posts ) ) {
							$link = get_permalink( $posts[0]->ID );
						}
						break;

					// Handle page.php.
					case ( 'page' === $template ):
						$pages_ids = get_all_page_ids();
						if ( is_array( $pages_ids ) && count( $pages_ids ) ) {
							$link = get_page_link( $pages_ids[0] );
						}
						break;

					// Handle comments-popup.php.
					case ( 'comments-popup' === $template ):
						$posts = get_posts(
							array(
								'numberposts' => 1,
								'orderby'     => 'rand',
							)
						);
						if ( is_array( $posts ) && count( $posts ) ) {
							$link = sprintf( '%s/?comments_popup=%d', $home_url, $posts[0]->ID );
						}
						break;

					// Handle paged.php.
					case ( 'paged' === $template ):
						global $wp_rewrite;
						if ( $wp_rewrite->using_permalinks() ) {
							$link = sprintf( '%s/page/%d/', $home_url, 1 );
						} else {
							$link = sprintf( '%s/?paged=%d', 1 );
						}
						break;

					// Handle author-id.php or author-nicename.php.
					case preg_match( '~^author-(.+)$~', $template, $matches ):
						if ( is_numeric( $matches[1] ) ) {
							$link = get_author_posts_url( $matches[1] );
						} else {
							$link = get_author_posts_url( null, $matches[1] );
						}
						break;

					// Handle category-id.php or category-slug.php.
					case preg_match( '~^category-(.+)$~', $template, $matches ):
						if ( is_numeric( $matches[1] ) ) {
							$link = get_category_link( $matches[1] );
						} else {
							$term = get_term_by( 'slug', $matches[1], 'category' );
							if ( is_object( $term ) ) {
								$link = get_category_link( $term->term_id );
							}
						}
						break;

					// Handle tag-id.php or tag-slug.php.
					case preg_match( '~^tag-(.+)$~', $template, $matches ):
						if ( is_numeric( $matches[1] ) ) {
							$link = get_tag_link( $matches[1] );
						} else {
							$term = get_term_by( 'slug', $matches[1], 'post_tag' );
							if ( is_object( $term ) ) {
								$link = get_tag_link( $term->term_id );
							}
						}
						break;

					// Handle taxonomy-taxonomy-term.php.
					case preg_match( '~^taxonomy-(.+)-(.+)$~', $template, $matches ):
						$link = get_term_link( $matches[2], $matches[1] );
						break;

					// Handle taxonomy-taxonomy.php.
					case preg_match( '~^taxonomy-(.+)$~', $template, $matches ):
						$terms = get_terms(
							array(
								'taxonomy' => $matches[1],
								'number'   => 1,
							)
						);

						if ( is_array( $terms ) && count( $terms ) ) {
							$link = get_term_link( $terms[0], $matches[1] );
						}

						break;

					// Handle MIME_type.php.
					case in_array( $template, $custom_mime_types, true ):
						$posts = get_posts(
							array(
								'post_mime_type' => '%' . $template . '%',
								'post_type'      => 'attachment',
								'numberposts'    => 1,
								'orderby'        => 'rand',
							)
						);
						if ( is_array( $posts ) && count( $posts ) ) {
							$link = get_permalink( $posts[0]->ID );
						}
						break;

					// Handle single-posttype.php.
					case preg_match( '~^single-(.+)$~', $template, $matches ):
						$posts = get_posts(
							array(
								'post_type'   => $matches[1],
								'numberposts' => 1,
								'orderby'     => 'rand',
							)
						);

						if ( is_array( $posts ) && count( $posts ) ) {
							$link = get_permalink( $posts[0]->ID );
						}
						break;

					// Handle page-id.php or page-slug.php.
					case preg_match( '~^page-(.+)$~', $template, $matches ):
						if ( is_numeric( $matches[1] ) ) {
							$link = get_permalink( $matches[1] );
						} else {
							$posts = get_posts(
								array(
									'pagename'    => $matches[1],
									'post_type'   => 'page',
									'numberposts' => 1,
								)
							);

							if ( is_array( $posts ) && count( $posts ) ) {
								$link = get_permalink( $posts[0]->ID );
							}
						}
						break;

					// Try to handle custom template.
					default:
						$posts = get_posts(
							array(
								'pagename'    => $template,
								'post_type'   => 'page',
								'numberposts' => 1,
							)
						);

						if ( is_array( $posts ) && count( $posts ) ) {
							$link = get_permalink( $posts[0]->ID );
						}
						break;
				}

				if ( $link && ! is_wp_error( $link ) ) {
					$urls[ $template ] = $link;
				}
			}
		}

		return $urls;
	}

	/**
	 * Retrieves the theme recommendations (JS and CSS files) based on the specified theme.
	 *
	 * @param string $theme_name The name of the theme for which to get recommendations.
	 *
	 * @return array An array containing the theme recommendations, categorized by JS and CSS files.
	 *
	 * @throws \Exception If an error occurs while processing the recommendations.
	 */
	public function get_theme_recommendations( $theme_name ) {
		$urls = $this->get_theme_urls( $theme_name );

		$js_groups  = array();
		$css_groups = array();

		@set_time_limit( $this->_config->get_integer( 'timelimit.minify_recommendations' ) );

		foreach ( $urls as $template => $url ) {
			// Append theme identifier.
			$url .= ( strstr( $url, '?' ) !== false ? '&' : '?' ) . 'w3tc_theme=' . rawurlencode( $theme_name );

			// If preview mode enabled append w3tc_preview.
			if ( $this->_config->is_preview() ) {
				$url .= '&w3tc_preview=1';
			}

			// Get page contents.
			$response = Util_Http::get( $url );

			if (
				! is_wp_error( $response ) &&
				(
					200 === $response['response']['code'] ||
					(
						404 === $response['response']['code'] &&
						'404' === $template
					)
				)
			) {
				$js_files  = $this->get_recommendations_js( $response['body'] );
				$css_files = $this->get_recommendations_css( $response['body'] );

				$js_groups[ $template ]  = $js_files;
				$css_groups[ $template ] = $css_files;
			}
		}

		$js_groups  = $this->get_theme_recommendations_by_groups( $js_groups );
		$css_groups = $this->get_theme_recommendations_by_groups( $css_groups );

		$recommendations = array(
			$js_groups,
			$css_groups,
		);

		return $recommendations;
	}

	/**
	 * Groups the theme recommendations by their usage across different templates.
	 *
	 * @param array $groups An array of theme recommendations categorized by template.
	 *
	 * @return array An array of grouped recommendations, with a 'default' group for common files.
	 */
	public function get_theme_recommendations_by_groups( $groups ) {
		// First calculate file usage count.
		$all_files = array();

		foreach ( $groups as $template => $files ) {
			foreach ( $files as $file ) {
				if ( ! isset( $all_files[ $file ] ) ) {
					$all_files[ $file ] = 0;
				}

				++$all_files[ $file ];
			}
		}

		// Determine default group files.
		$default_files = array();
		$count         = count( $groups );

		foreach ( $all_files as $all_file => $all_file_count ) {
			// If file usage count == groups count then file is common.
			if ( $count === $all_file_count ) {
				$default_files[] = $all_file;

				// If common file found unset it from all groups.
				foreach ( $groups as $template => $files ) {
					foreach ( $files as $index => $file ) {
						if ( $file === $all_file ) {
							array_splice( $groups[ $template ], $index, 1 );
							if ( ! count( $groups[ $template ] ) ) {
								unset( $groups[ $template ] );
							}
							break;
						}
					}
				}
			}
		}

		// If there are common files append add them into default group.
		if ( count( $default_files ) ) {
			$new_groups            = array();
			$new_groups['default'] = $default_files;

			foreach ( $groups as $template => $files ) {
				$new_groups[ $template ] = $files;
			}

			$groups = $new_groups;
		}

		// Unset empty templates.
		foreach ( $groups as $template => $files ) {
			if ( ! count( $files ) ) {
				unset( $groups[ $template ] );
			}
		}

		return $groups;
	}

	/**
	 * Extracts and normalizes the JavaScript files from the provided content.
	 *
	 * @param string $content The HTML content from which to extract JavaScript files.
	 *
	 * @return array A list of unique JavaScript file URLs after normalization.
	 */
	public function get_recommendations_js( $content ) {
		$files = Minify_Extract::extract_js( $content );

		$files        = array_map( array( '\W3TC\Util_Environment', 'normalize_file_minify' ), $files );
		$files        = array_unique( $files );
		$ignore_files = $this->_config->get_array( 'minify.reject.files.js' );
		$files        = array_diff( $files, $ignore_files );

		return $files;
	}

	/**
	 * Extracts and normalizes the CSS files from the provided content.
	 *
	 * @param string $content The HTML content from which to extract CSS files.
	 *
	 * @return array A list of unique CSS file URLs after normalization.
	 */
	public function get_recommendations_css( $content ) {
		$tag_files = Minify_Extract::extract_css( $content );
		$files     = array();
		foreach ( $tag_files as $tag_file ) {
			$files[] = $tag_file[1];
		}

		$files        = array_map( array( '\W3TC\Util_Environment', 'normalize_file_minify' ), $files );
		$files        = array_unique( $files );
		$ignore_files = $this->_config->get_array( 'minify.reject.files.css' );
		$files        = array_diff( $files, $ignore_files );

		return $files;
	}
}
