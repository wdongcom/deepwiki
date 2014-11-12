# DeepWiki

A pure documents showcase, bases on Markdown, coded in PHP.

## Installation

1. Download the latest release of DeepWiki.
2. Unarchive package, go to shell and run `composer update`.
3. Done.

## Quick Start

1. Make `deepwiki-config/config.json` from the sample.
2. Write down some Markdown files into `deepwiki-docs/`.
3. Run it in PHP. 

## Configuration

Main configuration file is `deepwiki-config/config.json`, can be made from the example file `deepwiki-config/config-sample.json`.

```json
{

	// title of website
	"site_name": "DeepWiki",

	// short description of website
	"site_description": "DeepWiki Showcase",

	// copyright text in footer, HTML format
	"copyright": "Powered by <a href=\"https://github.com/ychongsaytc/deepwiki\" target=\"_blank\">DeepWiki</a>.",

	// current theme, must be matched directory name in deepwiki-themes/
	"theme": "default",

	// the default route (of landing document slug name) for homepage visits
	"home_path": "quick-start",

	// display chapter number (like 1.1.a.) before document title
	"display_chapter": false,

	// enable global URL Rewrite (see URL Rewrite to enable rewrite feature for your server)
	"rewrite": false,

	// fill in password to enable site authentication
	"logging": {

		// a random string for encrypt cookies data, important
		"cookie_salt": "REPLACE_THIS_WITH_A_RANDOM_STRING",

		// main password to view website
		"password": ""

	}

}
```

## URL Rewrite

### For Apache HTTP Server

Place the code in the `/.htaccess` file

```
# prevent directory listing
Options -Indexes

# custom error documents
ErrorDocument 404 index.php\?p=_404
ErrorDocument 403 index.php\?p=_403

<IfModule mod_rewrite.c>
RewriteEngine on

# change / to your DeepWiki relative directory path, eg. /path/to/wiki/
RewriteBase /

# prevent illegal request
RewriteRule ^deepwiki-config/(.*)$ index.php\?p=_403 [L]
RewriteRule ^deepwiki-docs/(.*)$ index.php\?p=_403 [L]

# rewrite non-exist path to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php\?p=$1 [QSA,L]

</IfModule>
```

### For Nginx

TBD

## Directory Structure

```
deepwiki-docs/                   Document files in Markdown, plain text, HTML
deepwiki-config/config.json      Main configuration file in JSON
deepwiki-themes/xxx/             A theme named xxx
               /xxx/theme.json   Theme configuration in JSON
               /xxx/index.html   Document page template (see Theme Development)
               /xxx/404.html     Not Found (404 HTTP status) page template (see Theme Development)
               /xxx/login.html   User Login page template (see Theme Development)
deepwiki-vendor/                 The PHP Composer components
```

## Documents Structure

All documents must be placed in `deepwiki-docs/`.

### Full functional naming example

```
1. Parent Page One [parent-1].markdown
1.1. Child Page One [child-1].markdown
1.2. Child Page Two [child-2].markdown
1.2.1. Grandchild Page One [grandchild-1].markdown
1.2.2. Grandchild Page Two [grandchild-2].markdown
1.2.3. Grandchild Page Three [grandchild-3].markdown
1.3. Child Page Two [child-3].markdown
2. Parent Page Two [parent-2].markdown
3. Parent Page Three [parent-3].markdown
```

1. **Chapter Number** (optional). Using digits or letters ended with a point (`.`), if not set, the document will act in flat hierarchy.
1. **Document Title** (required).
1. **Document Slug Name** (optional). A string to be the ID (or short name) of document, enclosed by a pair of square brackets (`[` and `]`), leave this blank to use sanitized document title as slug name.
1. **Doucment File Extension Name** (required). Possible values are: `.markdonw`, `.md`, `.mdown`, `.txt`, `.html`.

### More examples

Pure titles, without hierarchy

```
Umentia.md
Ventis.md
Boreas.md
Bracchia.md
Congestaque.md
```

Using letters as chapter, with hierarchy

```
A. Crescendo.md
B. Mundum.md
C. Fulgura.md
D. Habendum.md
D.1. Discordia.md
D.2. Instabilis.md
D.3. Erectos.md
D.3.a. Utramque.md
D.3.b. Flamma.md
E. Scythiam.md
```

## Theme Development

TBD
