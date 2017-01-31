<?php
/**
 * DeepWiki
 *
 * @author Yuan Chong <ychongsaytc@gmail.com>
 */


// environment

date_default_timezone_set( 'UTC' );
setlocale( LC_ALL, 'en_US.UTF8' );
error_reporting( 0 );


class Deepwiki {

	private $config;
	private $query_string;
	private $authenticated;
	private $theme_root;
	private $theme_root_uri;
	private $docs_index;
	private $docs_items;
	private $queried_docs;
	private $theme_config;
	private $template_parts;
	private $response;

	public function __construct() {

		$current_path = trim( dirname( $_SERVER['PHP_SELF'] ), '/' );

		if ( array_key_exists( 'p', $_GET ) )
			$this->query_string = trim( $_GET['p'], '/' );
		else
			$this->query_string = '';

		// constants

		define( 'SITE_URI'       , '/' . $current_path );
		define( 'APP_ROOT'       , __DIR__ );
		define( 'CONFIG_ROOT'    , APP_ROOT . '/deepwiki-config' );
		define( 'VENDOR_ROOT'    , APP_ROOT . '/deepwiki-vendor' );
		define( 'THEMES_ROOT'    , APP_ROOT . '/deepwiki-themes' );
		define( 'THEMES_ROOT_URI', rtrim( SITE_URI, '/' ) . '/deepwiki-themes' );

		define( 'LOGGING_LOGGED_IN'     , 11 );
		define( 'LOGGING_NOT_LOGGED_IN' , 12 );
		define( 'LOGGING_WRONG_PASSWORD', 13 );

		require ( VENDOR_ROOT . '/erusev/parsedown/Parsedown.php' );
		require ( VENDOR_ROOT . '/erusev/parsedown-extra/ParsedownExtra.php' );

		$this->load_config();

		// constants based on configuration

		define( 'DOCS_ROOT'      , APP_ROOT . '/' . trim( $this->config['docs_path'], '/' ) );
		define( 'ASSETS_ROOT_URI', rtrim( SITE_URI, '/' ) . '/' . trim( $this->config['assets_path'], '/' ) );

		$this->load_theme();

	}

	public function handle() {
		$this->pre_load_template_parts();
		$this->authenticate();
		$this->pre_handle_request();
		$this->load_docs_index();
		$this->load_docs();
		$this->compile_docs();
		$this->handle_request();
		$this->load_template_parts();
		$this->response = $this->construct_output();
	}

	public function send() {
		echo $this->response;
	}

	public function terminate() {
		exit();
	}

	private function load_config() {

		// load configuration

		$config_filename = CONFIG_ROOT . '/config.json';
		if ( ! file_exists( $config_filename ) ) {
			$config_filename = CONFIG_ROOT . '/config-sample.json';
		}
		$config_json = file_get_contents( $config_filename );
		$this->config = json_decode( $config_json, true );

		if ( ! is_array( $this->config ) ) {
			$this->config = array();
		}

		// defaults

		$this->config = array_merge( array(
			'site_name'        => 'DeepWiki',
			'site_description' => 'Markdown Documents Showcase',
			'copyright'        => 'Powered by <a href="http://deepwiki.chon.io/" target="_blank">DeepWiki</a>.',
			'theme'            => 'default',
			'docs_path'        => 'deepwiki-docs',
			'assets_path'      => 'deepwiki-docs/assets',
			'home_route'       => null,
			'display_chapter'  => false,
			'display_index'    => false,
			'rewrite'          => false,
			'footer_code'      => null,
			'password'         => null,
			'cookie_salt'      => null,
			'docs'             => array(), // backward compatibility
		), $this->config );

	}

	private function load_theme() {

		$theme_name = $this->config['theme'];
		$this->theme_root = THEMES_ROOT . '/' . $theme_name;
		$this->theme_root_uri = THEMES_ROOT_URI . '/' . $theme_name;
		$theme_config_filepath = $this->theme_root . '/theme.json';
		if ( ! file_exists( $theme_config_filepath ) ) {
			exit( 'illegal theme' );
		} else {
			$this->theme_config = json_decode( file_get_contents( $theme_config_filepath ), true );
		}

	}

	private function pre_load_template_parts() {

		// pre-construct template parts

		$this->template_parts = array(
			'{{site_name}}'        => htmlspecialchars( $this->config['site_name'] ),
			'{{site_description}}' => htmlspecialchars( $this->config['site_description'] ),
			'{{site_uri}}'         => $this->uri(),
			'{{html_head}}'        => '',
			'{{nav}}'              => '',
			'{{copyright}}'        => $this->config['copyright'],
			'{{body_footer}}'      => $this->config['footer_code'],
			'{{login_form}}'       => '',
			'{{logout_link}}'      => '',
			'{{doc_title}}'        => '',
			'{{doc_heading}}'      => '',
			'{{doc_content}}'      => '',
			'{{doc_index}}'        => '',
		);

		foreach ( $this->theme_config['assets']['css'] as $entry )
			$this->template_parts['{{html_head}}'] .= sprintf( '<link rel="stylesheet" type="text/css" href="%s" />' . PHP_EOL, $this->theme_root_uri . '/' . $entry );
		foreach ( $this->theme_config['assets']['js'] as $entry )
			$this->template_parts['{{body_footer}}'] .= sprintf( '<script type="text/javascript" src="%s"></script>' . PHP_EOL, $this->theme_root_uri . '/' . $entry );

		$this->template_parts['{{login_form}}'] = '<form method="post" role="form">' .
			'<div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" /></div>' .
			'<button type="submit" class="btn btn-default">Submit</button>' .
			'</form>';

	}

	private function authenticate() {

		$this->authenticated = LOGGING_NOT_LOGGED_IN;
		if ( ! empty( $this->config['password'] ) ) {
			if ( array_key_exists( 'logging', $_COOKIE ) ) {
				// has logging cookie
				$cookie_hash = $_COOKIE['logging'];
				if ( $cookie_hash === $this->get_logged_in_hash() ) {
					$this->authenticated = LOGGING_LOGGED_IN;
				}
			} elseif ( array_key_exists( 'password', $_POST ) && ! empty( $_POST['password'] ) ) {
				// post password
				if ( $this->config['password'] === $_POST['password'] ) {
					$this->process_login();
					$this->authenticated = LOGGING_LOGGED_IN;
				} else {
					$this->authenticated = LOGGING_WRONG_PASSWORD;
				}
			}
			// show logging form
			if ( LOGGING_LOGGED_IN !== $this->authenticated ) {
				// wrong password
				if ( LOGGING_WRONG_PASSWORD === $this->authenticated ) {
					$parts['{{login_form}}'] = '<div class="alert alert-danger" role="alert">Wrong password.</div>' . $parts['{{login_form}}'];
				}
				// load theme template
				$template = file_get_contents( $this->theme_root . '/login.html' );
				$output = str_replace( array_keys( $parts ), $parts, $template );
				// output html
				echo $output;
				exit();
			}
		}

	}

	private function pre_handle_request() {

		if ( empty( $this->query_string ) ) {
			$this->go_home();
		}

		// process to logout

		if ( '_logout' === $this->query_string ) {
			$this->process_logout();
			$this->go_home();
		}

	}

	private function load_docs_index() {

		$docs_index_filename = DOCS_ROOT . '/index.json';
		if ( file_exists( $docs_index_filename ) ) {
			$config_json = file_get_contents( $docs_index_filename );
			$this->docs_index = json_decode( $config_json, true );
		} else {
			$this->docs_index = $this->config['docs']; // backward compatibility
		}

	}

	private function load_docs() {

		// walk all document files

		$this->docs_items = array();

		if ( empty( $this->docs_index ) ) :

			// scan docs directory if no configuration defined
			foreach ( scandir( DOCS_ROOT ) as $filename ) {
				if ( in_array( $filename, array( '.', '..', '.gitignore' ) ) )
					continue;
				$type = $this->doc_file_type( $filename );
				if ( false === $type )
					continue;
				$filename_pure = substr( $filename, 0, strrpos( $filename, '.' ) );
				$matches = array();
				preg_match_all( '#^(([0-9a-z]+\.)+\ +)?(.+?)(\ +\[(\S+)\])?$#', $filename_pure, $matches );
				$title = $matches[3][0];
				$chapter = rtrim( $matches[1][0], ' ' );
				if ( empty( $matches[5][0] ) )
					$slug = $this->sanitize( $title );
				else
					$slug = $this->sanitize( $matches[5][0] );
				$chapter_tree = explode( '.', rtrim( $chapter, '.' ) );
				$depth = count( $chapter_tree );
				array_pop( $chapter_tree );
				if ( empty( $chapter_tree ) )
					$parent = '';
				else
					$parent = implode( '.', $chapter_tree ) . '.';
				$this->docs_items[] = compact( 'title', 'slug', 'chapter', 'filename', 'type', 'depth', 'parent' );
			}

			// sort by chapter
			uasort( $this->docs_items, function( $a, $b ) {
				$chapter = array(
					$a['chapter'],
					$b['chapter'],
				);
				foreach ( array_keys( $chapter ) as $k ) {
					if ( empty( $chapter[ $k ] ) ) {
						continue;
					}
					$chapter[ $k ] = explode( '.', trim( $chapter[ $k ], '.' ) );
					$chapter[ $k ] = array_map( function( $v ) {
						return str_pad( $v, 10, '0', STR_PAD_LEFT );
					}, $chapter[ $k ] );
					$chapter[ $k ] = implode( '.', $chapter[ $k ] );
				}
				$sorted = $chapter;
				sort( $sorted );
				return ( $chapter === $sorted ? 0 : 1 );
			} );

		else :

			// read from docs configuration
			function _walk_config_docs_tree( $docs, &$items, $parent = '' ) {
				$i = 1;
				foreach ( $docs as $slug => $item ) {
					$item = array_merge( array(
						'title'    => null,
						'file'     => null,
						'children' => array(),
					), $item );
					$chapter = $parent . $i . '.';
					$items[] = array(
						'title'    => $item['title'],
						'slug'     => $slug,
						'chapter'  => $chapter,
						'filename' => $item['file'],
						'type'     => $this->doc_file_type( $item['file'] ),
						'depth'    => substr_count( $parent, '.' ) + 1,
						'parent'   => $parent,
					);
					if ( ! empty( $item['children'] ) )
						_walk_config_docs_tree( $item['children'], $items, $chapter );
					$i ++;
				}
			}
			_walk_config_docs_tree( $this->docs_index, $this->docs_items );

		endif;

		// generate paths

		foreach ( array_keys( $this->docs_items ) as $k ) {
			if ( 'url' === $this->docs_items[ $k ]['type'] ) {
				$this->docs_items[ $k ]['path'] = $this->docs_items[ $k ]['filename'];
				continue;
			}
			$path = '/' . $this->docs_items[ $k ]['slug'];
			$current_pos = $this->docs_items[ $k ]['parent'];
			for ( $i = $this->docs_items[ $k ]['depth'] - 1; $i >= 1; $i -- ) {
				foreach ( $this->docs_items as $entry ) {
					if ( $entry['depth'] === $i && $current_pos === $entry['chapter'] ) {
						$current_pos = $entry['parent'];
						$path = '/' . $entry['slug'] . $path;
						break;
					}
				}
			}
			$this->docs_items[ $k ]['path'] = trim( $path, '/' );
		}

	}

	private function compile_docs() {

		// compile document content

		foreach ( $this->docs_items as $entry ) {
			if ( $entry['path'] === $this->query_string ) {
				$origin = file_get_contents( DOCS_ROOT . '/' . $entry['filename'] );
				switch ( $entry['type'] ) {
					case 'markdown':
						$Parsedown = new Parsedown();
						$Parsedown->setUrlsLinked( false );
						$content = $Parsedown->text( $origin );
						break;
					case 'html':
						$content = $origin;
						break;
					default:
						$content = nl2br( htmlspecialchars( $origin ) );
						break;
				}
				/** intergrate dedicated translation */
				if ( in_array( $entry['type'], array( 'markdown', 'html' ) ) ) {
					/** replace inner page link */
					$matches = array();
					preg_match_all( '#\ (href|src)="\#\/([^\"]+)"#ui', $content, $matches );
					if ( $matches[0] ) {
						foreach ( array_keys( $matches[0] ) as $i ) {
							$content = str_replace( $matches[0][ $i ], ' ' . $matches[1][ $i ] . '="' . $this->uri( $matches[2][ $i ] ) . '"', $content );
						}
					}
					/** replace asset urls */
					$matches = array();
					preg_match_all( '#\ (href|src)="\!\/([^\"]+)"#ui', $content, $matches );
					if ( $matches[0] ) {
						foreach ( array_keys( $matches[0] ) as $i ) {
							$content = str_replace( $matches[0][ $i ], ' ' . $matches[1][ $i ] . '="' . $this->asset_uri( $matches[2][ $i ] ) . '"', $content );
						}
					}
					/** integrate tag properties */
					$matches = array();
					preg_match_all( '#\ \/>\{([^\}]+?)\}#', $content, $matches );
					if ( $matches[0] ) {
						foreach ( array_keys( $matches[0] ) as $i ) {
							$element = sprintf( ' %s />',
								$matches[1][ $i ]
							);
							$element = str_replace( '&quot;', '"', $element );
							$content = str_replace( $matches[0][ $i ], $element, $content );
						}
					}
					$matches = array();
					preg_match_all( '#>([^\>]*?)<\/([a-zA-Z]+)>\{([^\}]+?)\}#', $content, $matches );
					if ( $matches[0] ) {
						foreach ( array_keys( $matches[0] ) as $i ) {
							$element = sprintf( ' %s>%s</%s>',
								$matches[3][ $i ],
								$matches[1][ $i ], // plain text, no tags
								$matches[2][ $i ]
							);
							$element = str_replace( '&quot;', '"', $element );
							$content = str_replace( $matches[0][ $i ], $element, $content );
						}
					}
				}
				$this->queried_docs = array(
					'title'    => $entry['title'],
					'slug'     => $entry['slug'],
					'chapter'  => $entry['chapter'],
					'filename' => $entry['filename'],
					'content'  => $content,
				);
				break;
			}
		}

		// generate anchors for outline

		$matches = array();
		preg_match_all( '#\<h([1-6])\>([^\<]+)\<\/h([1-6])\>#ui', $this->queried_docs['content'], $matches );
		if ( count( $matches[0] ) ) {
			$slugs = array();
			foreach ( array_keys( $matches[2] ) as $k ) {
				$the_slug = $this->sanitize( $matches[2][ $k ] );
				if ( in_array( $the_slug, $slugs ) ) {
					$i = 2;
					while ( in_array( $the_slug . '-' . $i, $slugs ) ) {
						$i ++;
					}
					$the_slug = $the_slug . '-' . $i;
				}
				$slugs[ $k ] = $the_slug;
			}
			foreach ( array_keys( $matches[0] ) as $k ) {
				$this->queried_docs['content'] = substr_replace(
					$this->queried_docs['content'],
					sprintf( '<h%d id="%s">%s</h%d>',
						$matches[1][ $k ],
						$slugs[ $k ],
						$matches[2][ $k ],
						$matches[1][ $k ] ),
					strpos( $this->queried_docs['content'], $matches[0][ $k ] ),
					strlen( $matches[0][ $k ] )
				);
			}
		}

	}

	private function handle_request() {

		// 404

		if ( ! $this->queried_docs ) {
			header( 'HTTP/1.1 404 Not Found' );
			// load theme template
			$template = file_get_contents( $this->theme_root . '/404.html' );
			$output = str_replace( array_keys( $this->template_parts ), $this->template_parts, $template );
			// output html
			echo $output;
			exit();
		}

		// 403

		if ( ! $this->queried_docs && '_403' === $this->query_string ) {
			header( 'HTTP/1.1 403 Forbidden' );
			// load theme template
			$template = file_get_contents( $this->theme_root . '/403.html' );
			$output = str_replace( array_keys( $this->template_parts ), $this->template_parts, $template );
			// output html
			echo $output;
			exit();
		}

	}

	private function load_template_parts() {

		// construct the rest of template parts

		$this->template_parts['{{doc_title}}'] = $this->queried_docs['title'];
		$this->template_parts['{{doc_heading}}'] = ( $this->config['display_chapter'] ? $this->queried_docs['chapter'] . ' ' : null ) . $this->queried_docs['title'];
		$this->template_parts['{{doc_content}}'] = $this->queried_docs['content'];

		if ( LOGGING_LOGGED_IN == $this->authenticated )
			$this->template_parts['{{logout_link}}'] = sprintf( '<a href="%s">Logout</a>', $this->uri( '_logout' ) );

		// generate outline

		if ( $this->config['display_index'] ) {

			$matches = array();
			preg_match_all( '#\<h([1-6]) id=\"([^\"]+)\"\>([^\<]+)\<\/h([1-6])\>#ui', $this->queried_docs['content'], $matches );

			if ( count( $matches[0] ) ) {
				$headings = array();
				foreach ( array_keys( $matches[0] ) as $k ) {
					$headings[] = array(
						'title' => $matches[3][ $k ],
						'anchor' => $matches[2][ $k ],
						'level' => intval( $matches[1][ $k ] ),
					);
				}
				$heading_index = array();
				$last_level = 0;
				$unclosed = 0;
				foreach ( $headings as $entry ) {
					if ( $entry['level'] > $last_level ) {
						$heading_index[] = '<ul>';
						$unclosed ++;
						$last_level = $entry['level'];
						$heading_index[] = '<li><a href="#' . $entry['anchor'] . '">' . $entry['title'] . '</a>';
					} elseif ( $entry['level'] < $last_level ) {
						if ( $unclosed > 0 ) {
							$heading_index[] = '</li>' . str_repeat( '</ul>', $last_level - $entry['level'] );
							$unclosed = $unclosed - ( $last_level - $entry['level'] );
						}
						$last_level = $entry['level'];
						$heading_index[] = '</li>' . '<li><a href="#' . $entry['anchor'] . '">' . $entry['title'] . '</a>';
					} else {
						$heading_index[] = '</li>' . '<li><a href="#' . $entry['anchor'] . '">' . $entry['title'] . '</a>';
					}
				}
				if ( $unclosed > 0 ) {
					$heading_index[] = '</li>' . str_repeat( '</ul>', $unclosed );
					$unclosed = 0;
				}
				$heading_index = implode( null, $heading_index );
				// only display index tree when contains more than two entrys
				if ( substr_count( $heading_index, '<a ' ) >= 2 ) {
					$this->template_parts['{{doc_index}}'] = '<div class="content-index"><p class="content-index-title">Contents</p>' . $heading_index . '</div>';
				}
			}

		}

		// generate navigation menu

		$this->template_parts['{{nav}}'] .= '<div class="list-group">';

		$top_level_elements = array();
		$children_elements  = array();
		foreach ( $this->docs_items as $entry ) {
			if ( empty( $entry['parent'] ) )
				$top_level_elements[] = $entry;
			else
				$children_elements[ $entry['parent'] ][] = $entry;
		}

		$output_nav = '';
		$submenu_number = 1;
		foreach ( $top_level_elements as $entry )
			$this->_display_nav_item( $entry, $children_elements, $output_nav, $submenu_number );

		$this->template_parts['{{nav}}'] .= $output_nav;

		$this->template_parts['{{nav}}'] .= '</div>';

	}

	private function _display_nav_item( $item, &$children_elements, &$output, &$submenu_number ) {
		$item['has_children'] = array_key_exists( $item['chapter'], $children_elements );
		$item['is_current'] = 0 === strpos( $this->query_string, $item['path'] . '/' );
		$output .= sprintf( '<a class="%s" href="%s"%s%s%s>%s</a>',
			'list-group-item' . ( $this->query_string === $item['path'] ? ' active' : null ),
			( $item['has_children'] ? '#wiki-nav-' . $submenu_number : $this->uri( $item['path'] ) ),
			( $item['has_children'] ? ' data-toggle="collapse"' : null ),
			( $item['is_current'] ? ' aria-expanded="true"' : null ),
			( 'url' === $item['type'] ? ' target="_blank"' : null ),
			( $this->config['display_chapter'] ? $item['chapter'] . ' ' : null ) .
				$item['title'] . ( $item['has_children'] ? ' <b class="caret"></b>' : null ) );
		if ( $item['has_children'] )
			foreach( $children_elements[ $item['chapter'] ] as $entry ) {
				if ( ! isset( $new_level ) ) {
					$new_level = true;
					$output .= '<div class="submenu panel-collapse collapse' . ( $item['is_current'] ? ' in' : null ) . '" id="wiki-nav-' . $submenu_number . '">';
					$submenu_number ++;
				}
				$this->_display_nav_item( $entry, $children_elements, $output, $submenu_number );
			}
		if ( isset( $new_level ) ) {
			$output .= '</div>';
		}
		$output .= '';
	}

	private function construct_output() {

		// load theme template

		$template = file_get_contents( $this->theme_root . '/index.html' );
		$output = str_replace( array_keys( $this->template_parts ), $this->template_parts, $template );

		return $output;

	}

	private function uri( $path = null, $absolute = false ) {
		if ( strpos( $path, '://' ) > 0 )
			return $path;
		if ( empty( $path ) ) {
			$uri = rtrim( SITE_URI, '/' ) . '/';
		} else {
			if ( $this->config['rewrite'] )
				$uri = rtrim( SITE_URI, '/' ) . '/' . $path . ( false === strpos( $path, '#' ) ? '/' : null );
			else
				$uri = rtrim( SITE_URI, '/' ) . '/index.php?p=' . trim( $path, '/' );
		}
		if ( $absolute )
			$uri = $this->absolute_uri( $uri );
		return $uri;
	}

	private function asset_uri( $path = null, $absolute = false ) {
		if ( empty( $path ) ) {
			return null;
		} else {
			$uri = ASSETS_ROOT_URI . '/' . ltrim( $path, '/' );
		}
		if ( $absolute )
			$uri = $this->absolute_uri( $uri );
		return $uri;
	}

	private function absolute_uri( $uri ) {
		$protocol = ( array_key_exists( 'HTTPS', $_SERVER ) && 'on' == $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
		$port = (
				array_key_exists( 'SERVER_PORT', $_SERVER ) &&
					( ( 'http' == $protocol && $_SERVER['SERVER_PORT'] != '80' ) ||
						( 'https' == $protocol && $_SERVER['SERVER_PORT'] != '443' ) )
			) ? ':' . $_SERVER['SERVER_PORT'] : null;
		return $protocol . $_SERVER['SERVER_NAME'] . $port . $uri;
	}

	private function doc_file_type( $filename ) {
		if ( strpos( $filename, '://' ) > 0 )
			return 'url';
		$extension_name = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $extension_name, array( 'markdown', 'md', 'mdml', 'mdown' ) ) )
			return 'markdown';
		if ( in_array( $extension_name, array( 'html', 'htm' ) ) )
			return 'html';
		if ( in_array( $extension_name, array( 'txt' ) ) )
			return 'plain';
		return false;
	}

	private function sanitize( $string ) {
		$output = iconv( 'UTF-8', 'ASCII//TRANSLIT', $string );
		$output = strtolower( $output );
		$output = preg_replace( '#([^0-9a-z]+)#', '-', $output );
		$output = trim( $output, '-' );
		if ( empty( $output ) )
			return 'title';
		return $output;
	}

	private function go_home() {
		header( 'Location: ' . $this->uri( $this->config['home_route'], true ) );
		exit();
	}

	private function get_logged_in_hash() {
		return md5( md5( $this->config['password'] ) . ':' . $this->config['cookie_salt'] );
	}

	private function process_login() {
		setcookie( 'logging', $this->get_logged_in_hash(), time() + 86400, $this->uri() );
	}

	private function process_logout() {
		setcookie( 'logging', null, time() - 86400, $this->uri() );
	}

}


$app = new Deepwiki;
$app->handle();
$app->send();
$app->terminate();

