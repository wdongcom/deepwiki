<?php
/**
 * DeepWiki
 *
 * @author Yuan Chong <ychongsaytc@gmail.com>
 */

// environment

date_default_timezone_set( 'UTC' );
error_reporting( 0 );

// load query

$current_path = trim( dirname( $_SERVER['PHP_SELF'] ), '/' );

if ( isset( $_GET['p'] ) )
	$query_string = trim( $_GET['p'], '/' );
else
	$query_string = '';

// constants

define( 'SITE_URI', '/' . $current_path );
define( 'APP_ROOT', __DIR__ );
define( 'CONFIG_ROOT', APP_ROOT . '/deepwiki-config' );
define( 'VENDOR_ROOT', APP_ROOT . '/deepwiki-vendor' );
define( 'THEMES_ROOT', APP_ROOT . '/deepwiki-themes' );
define( 'THEMES_ROOT_URI', rtrim( SITE_URI, '/' ) . '/deepwiki-themes' );

define( 'LOGGING_LOGGED_IN', 11 );
define( 'LOGGING_NOT_LOGGED_IN', 12 );
define( 'LOGGING_WRONG_PASSWORD', 13 );

// functions

function dw_uri( $path = null ) {
	global $config;
	if ( empty( $path ) )
		return rtrim( SITE_URI, '/' ) . '/';
	if ( $config['rewrite'] )
		return rtrim( SITE_URI, '/' ) . '/' . $path;
	else
		return rtrim( SITE_URI, '/' ) . '/index.php?p=' . $path;
}

function dw_doc_file_type( $extension_name ) {
	if ( in_array( $extension_name, array( 'markdown', 'md', 'mdml', 'mdown' ) ) )
		return 'markdown';
	if ( in_array( $extension_name, array( 'html', 'htm' ) ) )
		return 'html';
	if ( in_array( $extension_name, array( 'txt' ) ) )
		return 'plain';
	return false;
}

function dw_sanitize( $string ) {
	$output = strtolower( $string );
	$output = preg_replace( '#([^0-9a-z]+)#', '-', $output );
	return $output;
}

function dw_go_home() {
	global $config;
	header( 'Location: ' . dw_uri( $config['home_route'] ) );
	exit();
}

function dw_get_logged_in_hash() {
	global $config;
	return md5( md5( $config['password'] ) . ':' . $config['cookie_salt'] );
}

function dw_process_login() {
	setcookie( 'logging', dw_get_logged_in_hash(), time() + 86400, dw_uri() );
}

function dw_process_logout() {
	setcookie( 'logging', null, time() - 86400, dw_uri() );
}

// components

require_once ( VENDOR_ROOT . '/autoload.php' );

// load configuration

$config_filename = CONFIG_ROOT . '/config.json';
if ( ! file_exists( $config_filename ) )
	$config_filename = CONFIG_ROOT . '/config-sample.json';
$config_json = file_get_contents( $config_filename );
$config = json_decode( $config_json, true );

if ( empty( $config ) )
	$config = array();

// defaults

$config = array_merge( array(
	'site_name' => 'DeepWiki',
	'site_description' => '',
	'copyright' => 'Powered by <a href="https://github.com/ychongsaytc/deepwiki" target="_blank">DeepWiki</a>.',
	'theme' => 'default',
	'docs_path' => 'deepwiki-docs',
	'home_route' => null,
	'display_chapter' => false,
	'rewrite' => false,
	'footer_code' => null,
	'password' => null,
	'cookie_salt' => null,
), $config );

// constants based on configuration

define( 'DOCS_ROOT', APP_ROOT . '/' . trim( $config['docs_path'], '/' ) );

// load theme

$theme = $config['theme'];
$theme_root = THEMES_ROOT . '/' . $theme;
$theme_root_uri = THEMES_ROOT_URI . '/' . $theme;
$theme_config_filepath = $theme_root . '/theme.json';
if ( ! file_exists( $theme_config_filepath ) ) {
	exit( 'illegal theme' );
} else {
	$theme_config = json_decode( file_get_contents( $theme_config_filepath ), true );
}

// pre-construct template parts

$parts = array(
	'{{site_name}}' => htmlspecialchars( $config['site_name'] ),
	'{{site_description}}' => htmlspecialchars( $config['site_description'] ),
	'{{site_uri}}' => dw_uri(),
	'{{html_head}}' => '',
	'{{nav}}' => '',
	'{{doc_title}}' => '',
	'{{doc_heading}}' => '',
	'{{doc_content}}' => '',
	'{{copyright}}' => $config['copyright'],
	'{{body_footer}}' => $config['footer_code'],
	'{{logout_link}}' => '',
	'{{login_form}}' => '',
);

foreach ( $theme_config['assets']['css'] as $entry )
	$parts['{{html_head}}'] .= sprintf( '<link rel="stylesheet" type="text/css" href="%s" />', $theme_root_uri . '/' . $entry );
foreach ( $theme_config['assets']['js'] as $entry )
	$parts['{{html_head}}'] .= sprintf( '<script type="text/javascript" src="%s"></script>', $theme_root_uri . '/' . $entry );

$parts['{{login_form}}'] = '<form method="post" role="form">' .
	'<div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" /></div>' .
	'<button type="submit" class="btn btn-default">Submit</button>' .
	'</form>';

// logging

$logged = LOGGING_NOT_LOGGED_IN;
if ( ! empty( $config['password'] ) ) {
	if ( isset( $_COOKIE['logging'] ) ) {
		// has logging cookie
		$cookie_hash = $_COOKIE['logging'];
		if ( $cookie_hash === dw_get_logged_in_hash() ) {
			$logged = LOGGING_LOGGED_IN;
		}
	} elseif ( isset( $_POST['password'] ) && ! empty( $_POST['password'] ) ) {
		// post password
		if ( $config['password'] === $_POST['password'] ) {
			dw_process_login();
			$logged = LOGGING_LOGGED_IN;
		} else {
			$logged = LOGGING_WRONG_PASSWORD;
		}
	}
	// show logging form
	if ( LOGGING_LOGGED_IN !== $logged ) {
		// wrong password
		if ( LOGGING_WRONG_PASSWORD === $logged ) {
			$parts['{{login_form}}'] = '<div class="alert alert-danger" role="alert">Wrong password.</div>' . $parts['{{login_form}}'];
		}
		// load theme template
		$template = file_get_contents( $theme_root . '/login.html' );
		$output = str_replace( array_keys( $parts ), $parts, $template );
		// output html
		echo $output;
		exit();
	}
}

// handle request

if ( empty( $query_string ) ) {
	dw_go_home();
}

// process to logout

if ( '_logout' === $query_string ) {
	dw_process_logout();
	dw_go_home();
}

// walk all document files

$filelist = scandir( DOCS_ROOT );
$items = array();
foreach ( $filelist as $filename ) {
	if ( in_array( $filename, array( '.', '..', '.gitignore' ) ) )
		continue;
	$item_file_extension = pathinfo( $filename, PATHINFO_EXTENSION );
	$type = dw_doc_file_type( $item_file_extension );
	if ( false === $type )
		continue;
	$filename_pure = substr( $filename, 0, strrpos( $filename, '.' . $item_file_extension ) );
	$matches = array();
	preg_match_all( '#^(([0-9a-z]+\.)+\ +)?(.+?)(\ +\[(\S+)\])?$#', $filename_pure, $matches );
	$title = $matches[3][0];
	$slug = dw_sanitize( $matches[5][0] );
	$chapter = rtrim( $matches[1][0], ' ' );
	if ( empty( $slug ) )
		$slug = dw_sanitize( $title );
	$chapter_tree = explode( '.', rtrim( $chapter, '.' ) );
	$depth = count( $chapter_tree );
	array_pop( $chapter_tree );
	if ( empty( $chapter_tree ) )
		$parent = '';
	else
		$parent = implode( '.', $chapter_tree ) . '.';
	$items[] = compact( 'title', 'slug', 'chapter', 'filename', 'type', 'depth', 'parent' );
}

// generate paths

foreach ( array_keys( $items ) as $k ) {
	$path = '/' . $items[ $k ]['slug'];
	$current_pos = $items[ $k ]['parent'];
	for ( $i = $items[ $k ]['depth'] - 1; $i >= 1; $i -- ) {
		foreach ( $items as $entry ) {
			if ( $entry['depth'] === $i && $current_pos === $entry['chapter'] ) {
				$current_pos = $entry['parent'];
				$path = '/' . $entry['slug'] . $path;
				break;
			}
		}
	}
	$items[ $k ]['path'] = trim( $path, '/' );
}

// compile document content

foreach ( $items as $entry ) {
	if ( $entry['path'] === $query_string ) {
		$origin = file_get_contents( DOCS_ROOT . '/' . $entry['filename'] );
		switch ( $entry['type'] ) {
			case 'markdown':
				require_once( APP_ROOT . '/deepwiki-vendor/erusev/parsedown-extra/ParsedownExtra.php' );
				$Parsedown = new ParsedownExtra();
				$content = $Parsedown->text( $origin );
				// $content = \Michelf\MarkdownExtra::defaultTransform( $origin );
				break;
			case 'html':
				$content = $origin;
				break;
			default:
				$content = nl2br( htmlspecialchars( $origin ) );
				break;
		}
		$doc = array(
			'title' => $entry['title'],
			'slug' => $entry['slug'],
			'chapter' => $entry['chapter'],
			'filename' => $entry['filename'],
			'content' => $content,
		);
		break;
	}
}

// 404

if ( ! isset( $doc ) ) {
	// load theme template
	$template = file_get_contents( $theme_root . '/404.html' );
	$output = str_replace( array_keys( $parts ), $parts, $template );
	// output html
	echo $output;
	exit();
}

// construct the rest of template parts

$parts['{{doc_title}}'] = $doc['title'];
$parts['{{doc_heading}}'] = ( $config['display_chapter'] ? $doc['chapter'] . ' ' : null ) . $doc['title'];
$parts['{{doc_content}}'] = $doc['content'];

if ( LOGGING_LOGGED_IN == $logged )
	$parts['{{logout_link}}'] = sprintf( '<a href="%s">Logout</a>', dw_uri( '_logout' ) );

$parts['{{nav}}'] .= '<div class="list-group">';

$top_level_elements = array();
$children_elements  = array();
foreach ( $items as $entry ) {
	if ( empty( $entry['parent'] ) )
		$top_level_elements[] = $entry;
	else
		$children_elements[ $entry['parent'] ][] = $entry;
}

function _display_nav_item( $item, &$children_elements, &$output, &$submenu_number ) {
	global $query_string, $config;
	$item['has_children'] = isset( $children_elements[ $item['chapter'] ] );
	$item['is_current'] = 0 === strpos( $query_string, $item['path'] . '/' );
	$output .= sprintf( '<a class="%s" href="%s"%s%s>%s</a>',
		'list-group-item' . ( $query_string === $item['path'] ? ' active' : null ),
		( $item['has_children'] ? '#wiki-nav-' . $submenu_number : dw_uri( $item['path'] ) ),
		( $item['has_children'] ? ' data-toggle="collapse"' : null ),
		( $item['is_current'] ? ' aria-expanded="true"' : null ),
		( $config['display_chapter'] ? $item['chapter'] . ' ' : null ) .
			$item['title'] . ( $item['has_children'] ? ' <b class="caret"></b>' : null ) );
	if ( $item['has_children'] )
		foreach( $children_elements[ $item['chapter'] ] as $entry ) {
			if ( ! isset( $new_level ) ) {
				$new_level = true;
				$output .= '<div class="submenu panel-collapse collapse' . ( $item['is_current'] ? ' in' : null ) . '" id="wiki-nav-' . $submenu_number . '">';
				$submenu_number ++;
			}
			_display_nav_item( $entry, $children_elements, $output, $submenu_number );
		}
	if ( isset( $new_level ) ) {
		$output .= '</div>';
	}
	$output .= '';
}

$output_nav = '';
$submenu_number = 1;
foreach ( $top_level_elements as $entry )
	_display_nav_item( $entry, $children_elements, $output_nav, $submenu_number );

$parts['{{nav}}'] .= $output_nav;

$parts['{{nav}}'] .= '</div>';

// load theme template

$template = file_get_contents( $theme_root . '/index.html' );
$output = str_replace( array_keys( $parts ), $parts, $template );

// output html

echo $output;
exit();

