# DeepWiki

A pure documents showcase, bases on Markdown, coded in PHP.

See [Live Demo](http://deepwiki.deepdevelop.com/).

## Installation

1. [Download the latest release of DeepWiki](https://github.com/ychongsaytc/deepwiki/releases).
2. Unarchive package, go to shell and run `composer update`.
3. Done.

## Quick Start

1. Make `deepwiki-config/config.json` from the example configuration.
2. Write down some Markdown files into `deepwiki-docs/`.
3. Run it in PHP. 

## Configuration

Main configuration is placed in `deepwiki-config/config.json`, can be made from the sample file `deepwiki-config/config-sample.json`:

```json
{
	"site_name": "DeepWiki",
	"site_description": "DeepWiki Showcase",
	"copyright": "Powered by <a href=\"https://github.com/ychongsaytc/deepwiki\" target=\"_blank\">DeepWiki</a>.",
	"theme": "default",
	"docs_path": "deepwiki-docs",
	"home_route": "quick-start",
	"display_chapter": false,
	"rewrite": false,
	"cookie_salt": "REPLACE_THIS_WITH_A_RANDOM_STRING",
	"password": ""
}
```

Property | Description
--- | ---
`site_name` | Title of the website. Default: `DeepWiki`.
`site_description` | Short description of the website Default: `DeepWiki Showcase`.
`copyright` | Copyright text in footer HTML format. Default: `Powered by <a href="https://github.com/ychongsaytc/deepwiki" target="_blank">DeepWiki</a>.`.
`theme` | Slug name of current theme, must be matched a directory name in `deepwiki-themes/`. Default: `default`.
`docs_path` | Directory to find document files. Default: `deepwiki-docs`.
`home_route` | The default route (slug name of landing document) for homepage visits. Default: `quick-start`.
`display_chapter` | Display chapter number (like `1.1.a.`) before document title. Default: false.
`rewrite` | Enable global URL Rewrite (see URL Rewrite to enable rewrite feature for your server). Default: false.
`password` | Main password to view the website, fill in this to enable site authentication. Default is empty.
`cookie_salt` | A random string for encrypt cookies data, important. Default is empty.

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

1. **Chapter Number** (optional). Using digits or letters ended with a point (`.`). If not set, the document will act in flat hierarchy.
1. **Document Title** (required).
1. **Document Slug Name** (optional). A string to be the ID (or short name) of the document, enclosed by a pair of square brackets (`[` and `]`). Leave this blank to use sanitized document title as slug name.
1. **Doucment File Extension Name** (required). Possible values are: `.markdonw`, `.md`, `.mdown`, `.txt`, `.html`, etc.

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

## Auto Deployment for Documents

If you are using Git to manage your documents, you can setup an auto deployment tool to deploy files onto DeepWiki.

[**DeepDeploy**](https://deepdeploy.com/) is such an Auto Deployment system, deploying Git repository to any server (via FTP/SFTP). It's easy to use:

1. Go to [DeepDeploy](https://deepdeploy.com/), sign in and create a project.
1. Add your repository contains document file to the project.
1. Add your DeepWiki server FTP/SFTP information to Server section, with setting Path to Deploy to the DeepWiki document files path (eg. `/deepwiki-docs` or `/home/ubuntu/public_html/deepwiki-docs`).
1. Save the project and trigger your first auto deployment.

## URL Rewrite

### For Apache HTTP Server

Place the code in the `/.htaccess` file.

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

Place the code inside the `http { ... }` node.

```
server {

	# bind to http://example.com on port 80
	listen 80;
	server_name example.com;

	# change this to your DeepWiki root path
	root /var/www/deepwiki;

	# prevent illegal request
	location ~ /(deepwiki-config|deepwiki-docs) {
		deny all;
	}

	# rewrite non-exist path to index.php, or return HTTP 404 Not Found
	location / {
		try_files $uri $uri/ /index.php?p=$uri&$args;

		# custom error documents
		error_page 404 = /index.php\?p=_404;
		error_page 403 = /index.php\?p=_403;
	}

	# example of passing request to FastCGI
	fastcgi_param PHP_VALUE "open_basedir=$document_root:/tmp/";
	location ~ \.php$ {
		fastcgi_pass unix:/tmp/fastcgi-php.socket;
		fastcgi_index index.php;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		include fastcgi_params;
	}

	# prevent from requesting Apache HTTP Server configuration files
	location ~ /\.ht {
		deny all;
	}

}
```

## Theme Development

TBD
