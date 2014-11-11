<?php
/**
 * HiWiki
 *
 * @author Yuan Chong <ychongsaytc@gmail.com>
 */

// environment

date_default_timezone_set( 'UTC' );
error_reporting( 0 );

// load query string

$current_path = dirname( $_SERVER['PATH_INFO'] );
if ( 0 === strpos( $_SERVER['REQUEST_URI'], $current_path ) )
	$query_string = substr( $_SERVER['REQUEST_URI'], strlen( $current_path ) );
else
	$query_string = $_SERVER['REQUEST_URI'];
$query_string = trim( $query_string, '/' );

// constants

define( 'SITE_URI', $current_path );
define( 'APP_ROOT', __DIR__ );
define( 'CONFIG_ROOT', APP_ROOT . '/hiwiki-config' );
define( 'VENDOR_ROOT', APP_ROOT . '/hiwiki-vendor' );
define( 'DOCS_ROOT', APP_ROOT . '/hiwiki-docs' );
define( 'THEMES_ROOT', APP_ROOT . '/hiwiki-themes' );
define( 'THEMES_ROOT_URI', SITE_URI . '/hiwiki-themes' );

define( 'LOGGING_LOGGED_IN', 11 );
define( 'LOGGING_NOT_LOGGED_IN', 12 );
define( 'LOGGING_WRONG_PASSWORD', 13 );

// components

require_once ( VENDOR_ROOT . '/autoload.php' );

// load configuration

$config_filename = CONFIG_ROOT . '/config.json';
if ( ! file_exists( $config_filename ) )
	$config_filename = CONFIG_ROOT . '/config.example.json';
$config_json = file_get_contents( $config_filename );
$config = json_decode( $config_json, true );

if ( empty( $config ) )
	$config = array();

$config = array_merge( array(
	'site_name' => 'HiWiki',
	'site_description' => '',
	'copyright' => '&copy; HiWiki.',
	'theme' => 'default',
	'logging' => array(
		'cookie_salt' => null,
		'credentials' => array(),
	),
), $config );

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
	'{{site_name}}' => $config['site_name'],
	'{{site_description}}' => $config['site_description'],
	'{{site_uri}}' => SITE_URI,
	'{{html_head}}' => '',
	'{{nav}}' => '',
	'{{doc_title}}' => '',
	'{{doc_heading}}' => '',
	'{{doc_content}}' => '',
	'{{copyright}}' => $config['copyright'],
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

if ( ! empty( $config['logging']['password'] ) ) {
	$logged = LOGGING_NOT_LOGGED_IN;
	if ( isset( $_COOKIE['logging'] ) ) {
		$cookie_hash = $_COOKIE['logging'];
		if ( $cookie_hash === md5( md5( $config['logging']['password'] ) . ':' . $config['logging']['cookie_salt'] ) ) {
			$logged = LOGGING_LOGGED_IN;
		}
	} elseif ( isset( $_POST['password'] ) && ! empty( $_POST['password'] ) ) {
		if ( $config['logging']['password'] === $_POST['password'] ) {
			setcookie( 'logging', md5( md5( $config['logging']['password'] ) . ':' . $config['logging']['cookie_salt'] ), time() + 86400, SITE_URI );
			$logged = LOGGING_LOGGED_IN;
		} else {
			$logged = LOGGING_WRONG_PASSWORD;
		}
	}
	if ( LOGGING_LOGGED_IN !== $logged ) {
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

// walk markdown files

$filelist = scandir( DOCS_ROOT );
$items = array();
foreach ( $filelist as $filename ) {
	if ( in_array( $filename, array( '.', '..', '.gitignore' ) ) )
		continue;
	$item_file_extension = pathinfo( $filename, PATHINFO_EXTENSION );
	if ( ! in_array( $item_file_extension, array( 'md', 'mdown', 'markdown', 'txt', 'html' ) ) )
		continue;
	$filename_pure = substr( $filename, 0, strrpos( $filename, '.' . $item_file_extension ) );
	$matches = array();
	preg_match_all( '#^(([0-9a-z]+\.)+\ +)?(\#?)(.+?)(\ +\[(\S+)\])?$#', $filename_pure, $matches );
	$title = $matches[4][0];
	$slug = $matches[6][0];
	$chapter = rtrim( $matches[1][0], ' ' );
	$is_anchor = ! empty( $matches[3][0] );
	if ( empty( $slug ) )
		$slug = $title;
	$chapter_tree = explode( '.', rtrim( $chapter, '.' ) );
	$depth = count( $chapter_tree );
	array_pop( $chapter_tree );
	if ( empty( $chapter_tree ) )
		$parent = '';
	else
		$parent = implode( '.', $chapter_tree ) . '.';
	$items[] = array(
		'title' => $title,
		'slug' => $slug,
		'chapter' => $chapter,
		'filename' => $filename,
		'is_anchor' => $is_anchor,
		'depth' => $depth,
		'parent' => $parent,
	);
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

// handle request

if ( empty( $query_string ) ) {
	foreach ( $items as $entry ) {
		if ( false === $entry['is_anchor'] ) {
			header( 'Location: ' . SITE_URI . '/' . $entry['path'] );
			exit();
		}
	}
}

// compile markdown

$doc = array();
foreach ( $items as $entry ) {
	if ( $entry['path'] === $query_string ) {
		$markdown = file_get_contents( DOCS_ROOT . '/' . $entry['filename'] );
		$html = \Michelf\MarkdownExtra::defaultTransform( $markdown );
		$doc = array(
			'title' => $entry['title'],
			'slug' => $entry['slug'],
			'chapter' => $entry['chapter'],
			'filename' => $entry['filename'],
			'markdown' => $markdown,
			'html' => $html,
		);
		break;
	}
}

// construct the rest of template parts

$parts['{{doc_title}}'] = $doc['title'];
$parts['{{doc_heading}}'] = $doc['chapter'] . ' ' . $doc['title'];
$parts['{{doc_content}}'] = $doc['html'];

$parts['{{nav}}'] .= '<div class="list-group">';
foreach ( $items as $k => $entry )
	$parts['{{nav}}'] .= sprintf( '<a class="%s" href="%s">%s %s</a>',
		'list-group-item' . ( $query_string === $entry['path'] ? ' active' : null ),
		$entry['is_anchor'] ? 'javascript:void(0);' : SITE_URI . '/' . $entry['path'],
		$entry['chapter'],
		$entry['title'] );
$parts['{{nav}}'] .= '</div>';

// load theme template

$template = file_get_contents( $theme_root . '/index.html' );
$output = str_replace( array_keys( $parts ), $parts, $template );

// output html

echo $output;

